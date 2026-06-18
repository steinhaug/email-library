<?php

namespace MailCleaner\Drivers;

use MailCleaner\Contracts\MailAccountInterface;
use MailCleaner\Models\Message;
use MailCleaner\Models\MessageHeaders;
use MailCleaner\Models\SearchCriteria;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Client;

class ImapDriver implements MailAccountInterface
{
    private ClientManager $manager;
    private Client $client;

    // messageId encoding: "{folder}||{uid}" — double-pipe is the separator
    private const SEP = '||';

    public function connect(array $credentials): void
    {
        $enc_map = ['SSL/TLS' => 'ssl', 'STARTTLS' => 'tls'];
        $in = $credentials['incoming'] ?? $credentials;

        $this->manager = new ClientManager();
        $this->client  = $this->manager->make([
            'host'          => $in['server'] ?? $in['host'],
            'port'          => $in['port'] ?? 993,
            'encryption'    => $enc_map[$in['ssl'] ?? ''] ?? 'ssl',
            'validate_cert' => true,
            'username'      => $credentials['username'],
            'password'      => $credentials['password'],
            'protocol'      => 'imap',
        ]);
        $this->client->connect();
    }

    public function disconnect(): void
    {
        $this->client->disconnect();
    }

    public function testConnection(): bool
    {
        return $this->client->isConnected();
    }

    public function search(SearchCriteria $criteria): array
    {
        $folderName = $criteria->folder ?? 'INBOX';
        $folder = $this->client->getFolder($folderName);
        $query  = $folder->messages();

        if ($criteria->from)       $query->where('FROM',    $criteria->from);
        if ($criteria->subject)    $query->where('SUBJECT', $criteria->subject);
        if ($criteria->unreadOnly) $query->where('UNSEEN');
        if ($criteria->after)      $query->where('SINCE',  $criteria->after->format('d-M-Y'));
        if ($criteria->before)     $query->where('BEFORE', $criteria->before->format('d-M-Y'));

        // IMAP SEARCH requires at least one criterion
        if ($query->getQuery()->isEmpty()) $query->where('ALL');

        if ($criteria->limit > 0) $query->limit($criteria->limit);

        $messages = $query->get();

        $result = [];
        foreach ($messages as $msg) {
            $result[] = $this->buildHeaders($msg, $folderName);
        }
        return $result;
    }

    public function listContainers(): array
    {
        $folders = $this->client->getFolders();
        return array_map(fn($f) => $f->full_name, $folders->toArray());
    }

    public function fetchHeaders(string $messageId): MessageHeaders
    {
        [$folder, $uid] = $this->decodeId($messageId);
        $msg = $this->getByUid($folder, $uid);
        return $this->buildHeaders($msg, $folder);
    }

    public function fetchBody(string $messageId): string
    {
        [$folder, $uid] = $this->decodeId($messageId);
        $msg = $this->getByUid($folder, $uid);

        $text = $msg->getTextBody();
        return $text ?: (string) $msg->getHTMLBody();
    }

    public function fetchMessage(string $messageId): Message
    {
        [$folder, $uid] = $this->decodeId($messageId);
        $msg = $this->getByUid($folder, $uid);

        $h = $this->buildHeaders($msg, $folder);
        $m = new Message($h->id, $h->from, $h->subject, $h->date, $h->isRead, $h->labels, $h->folder);
        $m->bodyText = (string) ($msg->getTextBody() ?: '');
        $m->bodyHtml = (string) ($msg->getHTMLBody() ?: '');
        return $m;
    }

    public function delete(string $messageId, bool $permanent = false): void
    {
        [$folder, $uid] = $this->decodeId($messageId);
        $msg = $this->getByUid($folder, $uid);

        if ($permanent) {
            $msg->delete();
            $this->client->getFolder($folder)->expunge();
        } else {
            $msg->move('Trash');
        }
    }

    public function deleteBatch(array $messageIds, bool $permanent = false): void
    {
        // Group by folder to minimise expunge calls
        $byFolder = [];
        foreach ($messageIds as $id) {
            [$folder, $uid] = $this->decodeId($id);
            $byFolder[$folder][] = $uid;
        }

        foreach ($byFolder as $folderName => $uids) {
            $folder = $this->client->getFolder($folderName);
            foreach ($uids as $uid) {
                $msgs = $folder->messages()->where('UID', $uid)->get();
                $msg  = $msgs->first();
                if (!$msg) continue;
                $permanent ? $msg->delete() : $msg->move('Trash');
            }
            if ($permanent) $folder->expunge();
        }
    }

    public function moveTo(string $messageId, string $container): void
    {
        [$folder, $uid] = $this->decodeId($messageId);
        $this->getByUid($folder, $uid)->move($container);
    }

    public function addLabel(string $messageId, string $label): void
    {
        // IMAP uses keywords/flags, not labels — map to custom keyword
        [$folder, $uid] = $this->decodeId($messageId);
        $this->getByUid($folder, $uid)->setFlag($label);
    }

    public function removeLabel(string $messageId, string $label): void
    {
        [$folder, $uid] = $this->decodeId($messageId);
        $this->getByUid($folder, $uid)->unsetFlag($label);
    }

    public function markAsRead(string $messageId): void
    {
        [$folder, $uid] = $this->decodeId($messageId);
        $this->getByUid($folder, $uid)->setFlag('Seen');
    }

    // --- Private helpers ---

    private function encodeId(string $folder, string|int $uid): string
    {
        return $folder . self::SEP . $uid;
    }

    private function decodeId(string $messageId): array
    {
        $pos = strrpos($messageId, self::SEP);
        return [
            substr($messageId, 0, $pos),
            substr($messageId, $pos + strlen(self::SEP)),
        ];
    }

    private function getByUid(string $folderName, string|int $uid): \Webklex\PHPIMAP\Message
    {
        $msgs = $this->client->getFolder($folderName)->messages()->where('UID', (string) $uid)->get();
        $msg  = $msgs->first();
        if (!$msg) throw new \RuntimeException("Message UID {$uid} not found in {$folderName}");
        return $msg;
    }

    private function buildHeaders(\Webklex\PHPIMAP\Message $msg, string $folderName): MessageHeaders
    {
        $uid = (string) $msg->getUid();

        try {
            $fromAddr = $msg->getFrom()->first();
            $from = $fromAddr ? (string) $fromAddr : '';
        } catch (\Throwable) {
            $from = '';
        }

        try {
            $subject = (string) $msg->getSubject();
        } catch (\Throwable) {
            $subject = '';
        }

        try {
            $date = $msg->getDate()->first()->toDateTime();
        } catch (\Throwable) {
            $date = new \DateTime();
        }

        $flags  = $msg->getFlags()->toArray();
        $isRead = in_array('Seen', $flags, true);

        return new MessageHeaders(
            $this->encodeId($folderName, $uid),
            $from,
            $subject,
            $date,
            $isRead,
            $flags,
            $folderName,
        );
    }
}
