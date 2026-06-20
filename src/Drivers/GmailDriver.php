<?php

namespace MailCleaner\Drivers;

use MailCleaner\Contracts\MailAccountInterface;
use MailCleaner\Models\Message;
use MailCleaner\Models\MessageHeaders;
use MailCleaner\Models\SearchCriteria;

class GmailDriver implements MailAccountInterface
{
    private \Google\Service\Gmail $service;
    private string $userId = 'me';

    public function connect(array $credentials): void
    {
        $oauth = $credentials['oauth20'] ?? $credentials;

        $client = new \Google\Client();
        $client->setClientId($oauth['client_id']);
        $client->setClientSecret($oauth['client_secret']);
        $client->setScopes([
            \Google\Service\Gmail::GMAIL_MODIFY,
            \Google\Service\Gmail::GMAIL_SETTINGS_BASIC,
        ]);
        $client->setAccessType('offline');
        $client->refreshToken($oauth['refresh_token']);

        $this->service = new \Google\Service\Gmail($client);
    }

    public function disconnect(): void
    {
        // Gmail API is stateless — nothing to close
    }

    public function testConnection(): bool
    {
        try {
            $this->service->users->getProfile($this->userId);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function search(SearchCriteria $criteria): array
    {
        $q = [];
        if ($criteria->fromContains)    $q[] = 'from:' . $criteria->fromContains;
        if ($criteria->subjectContains) $q[] = 'subject:(' . $criteria->subjectContains . ')';
        if ($criteria->unreadOnly)      $q[] = 'is:unread';
        if ($criteria->container)       $q[] = 'label:' . $criteria->container;
        if ($criteria->newerThan)       $q[] = 'after:'  . $criteria->newerThan->format('Y/m/d');
        if ($criteria->olderThan)       $q[] = 'before:' . $criteria->olderThan->format('Y/m/d');

        $params = ['q' => implode(' ', $q)];
        if ($criteria->limit > 0) {
            $params['maxResults'] = min($criteria->limit, 500);
        }

        $headers = [];
        $pageToken = null;

        do {
            if ($pageToken) $params['pageToken'] = $pageToken;

            $response = $this->service->users_messages->listUsersMessages($this->userId, $params);
            $items = $response->getMessages() ?? [];

            foreach ($items as $stub) {
                $headers[] = $this->fetchHeaders($stub->getId());
                if ($criteria->limit > 0 && count($headers) >= $criteria->limit) {
                    break 2;
                }
            }

            $pageToken = $response->getNextPageToken();
        } while ($pageToken);

        return $headers;
    }

    public function listContainers(): array
    {
        $response = $this->service->users_labels->listUsersLabels($this->userId);
        return array_map(
            fn($l) => $l->getName(),
            $response->getLabels() ?? []
        );
    }

    /**
     * Gmail-specific (not in MailAccountInterface — IMAP has no equivalent).
     * Requires the GMAIL_SETTINGS_BASIC scope.
     *
     * @return array<int, array{id: string, criteria: array, action: array}>
     */
    public function listFilters(): array
    {
        $response = $this->service->users_settings_filters->listUsersSettingsFilters($this->userId);

        $filters = [];
        foreach ($response->getFilter() ?? [] as $f) {
            $filters[] = [
                'id'       => $f->getId(),
                'criteria' => $this->extractFilterCriteria($f->getCriteria()),
                'action'   => $this->extractFilterAction($f->getAction()),
            ];
        }
        return $filters;
    }

    public function fetchHeaders(string $messageId): MessageHeaders
    {
        $msg = $this->service->users_messages->get($this->userId, $messageId, [
            'format'          => 'metadata',
            'metadataHeaders' => ['From', 'Subject', 'Date'],
        ]);
        return $this->buildHeaders($msg);
    }

    public function fetchBody(string $messageId): string
    {
        $msg = $this->service->users_messages->get($this->userId, $messageId, ['format' => 'full']);
        $payload = $msg->getPayload();
        if (!$payload) return '';

        $text = $this->extractPart($payload, 'text/plain');
        return $text !== '' ? $text : $this->extractPart($payload, 'text/html');
    }

    public function fetchMessage(string $messageId): Message
    {
        $msg = $this->service->users_messages->get($this->userId, $messageId, ['format' => 'full']);
        $h = $this->buildHeaders($msg);

        $bodyPlain = '';
        $bodyHtml  = null;
        $payload = $msg->getPayload();
        if ($payload) {
            $bodyPlain = $this->extractPart($payload, 'text/plain');
            $html      = $this->extractPart($payload, 'text/html');
            $bodyHtml  = $html !== '' ? $html : null;
        }
        return new Message($h, $bodyPlain, $bodyHtml);
    }

    public function delete(string $messageId, bool $permanent = false): void
    {
        if ($permanent) {
            $this->service->users->messages->delete($this->userId, $messageId);
        } else {
            $this->service->users->messages->trash($this->userId, $messageId);
        }
    }

    public function deleteBatch(array $messageIds, bool $permanent = false): void
    {
        if ($permanent) {
            $req = new \Google\Service\Gmail\BatchDeleteMessagesRequest();
            $req->setIds($messageIds);
            $this->service->users_messages->batchDelete($this->userId, $req);
        } else {
            $req = new \Google\Service\Gmail\BatchModifyMessagesRequest();
            $req->setIds($messageIds);
            $req->setAddLabelIds(['TRASH']);
            $req->setRemoveLabelIds(['INBOX']);
            $this->service->users_messages->batchModify($this->userId, $req);
        }
    }

    public function moveTo(string $messageId, string $container): void
    {
        $req = new \Google\Service\Gmail\ModifyMessageRequest();
        $req->setAddLabelIds([$container]);
        $req->setRemoveLabelIds(['INBOX']);
        $this->service->users_messages->modify($this->userId, $messageId, $req);
    }

    public function addLabel(string $messageId, string $label): void
    {
        $req = new \Google\Service\Gmail\ModifyMessageRequest();
        $req->setAddLabelIds([$label]);
        $this->service->users_messages->modify($this->userId, $messageId, $req);
    }

    public function removeLabel(string $messageId, string $label): void
    {
        $req = new \Google\Service\Gmail\ModifyMessageRequest();
        $req->setRemoveLabelIds([$label]);
        $this->service->users_messages->modify($this->userId, $messageId, $req);
    }

    public function markAsRead(string $messageId): void
    {
        $this->removeLabel($messageId, 'UNREAD');
    }

    // --- Private helpers ---

    /**
     * (array)-cast on Google model objects yields mangled protected-prop keys,
     * so pull the fields we care about via explicit getters and drop nulls.
     */
    private function extractFilterCriteria(?\Google\Service\Gmail\FilterCriteria $c): array
    {
        if (!$c) return [];
        return array_filter([
            'from'           => $c->getFrom(),
            'to'             => $c->getTo(),
            'subject'        => $c->getSubject(),
            'query'          => $c->getQuery(),
            'negatedQuery'   => $c->getNegatedQuery(),
            'hasAttachment'  => $c->getHasAttachment(),
            'excludeChats'   => $c->getExcludeChats(),
            'size'           => $c->getSize(),
            'sizeComparison' => $c->getSizeComparison(),
        ], fn($v) => $v !== null);
    }

    private function extractFilterAction(?\Google\Service\Gmail\FilterAction $a): array
    {
        if (!$a) return [];
        return array_filter([
            'addLabelIds'    => $a->getAddLabelIds(),
            'removeLabelIds' => $a->getRemoveLabelIds(),
            'forward'        => $a->getForward(),
        ], fn($v) => $v !== null);
    }

    private function buildHeaders(\Google\Service\Gmail\Message $msg): MessageHeaders
    {
        $from = $subject = $dateStr = '';
        foreach ($msg->getPayload()?->getHeaders() ?? [] as $h) {
            match ($h->getName()) {
                'From'    => $from    = $h->getValue(),
                'Subject' => $subject = $h->getValue(),
                'Date'    => $dateStr = $h->getValue(),
                default   => null,
            };
        }

        try {
            $date = new \DateTime($dateStr);
        } catch (\Throwable) {
            $date = new \DateTime();
        }

        $labels   = $msg->getLabelIds() ?? [];
        $isUnread = in_array('UNREAD', $labels, true);
        $folder   = in_array('INBOX', $labels, true) ? 'INBOX' : ($labels[0] ?? 'INBOX');

        return new MessageHeaders($msg->getId(), $from, $subject, $date, $isUnread, $labels, $folder);
    }

    private function extractPart(\Google\Service\Gmail\MessagePart $part, string $mimeType): string
    {
        if ($part->getMimeType() === $mimeType) {
            $data = $part->getBody()?->getData();
            return $data ? base64_decode(str_replace(['-', '_'], ['+', '/'], $data)) : '';
        }
        foreach ($part->getParts() ?? [] as $sub) {
            $result = $this->extractPart($sub, $mimeType);
            if ($result !== '') return $result;
        }
        return '';
    }
}
