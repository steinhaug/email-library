<?php

namespace MailCleaner\Drivers;

use MailCleaner\Contracts\MailAccountInterface;
use MailCleaner\Models\Message;
use MailCleaner\Models\MessageHeaders;
use MailCleaner\Models\SearchCriteria;

class GmailDriver implements MailAccountInterface
{
    private \Google_Service_Gmail $service;
    private string $userId = 'me';

    public function connect(array $credentials): void
    {
        // TODO: bygg Google_Client med OAuth-token fra $credentials,
        // instansier $this->service = new Google_Service_Gmail($client)
        throw new \RuntimeException('Not implemented');
    }

    public function disconnect(): void
    {
        // Gmail API er stateless per request — ingenting å lukke
    }

    public function testConnection(): bool
    {
        // TODO: kall users.getProfile som lett health-check
        throw new \RuntimeException('Not implemented');
    }

    public function search(SearchCriteria $criteria): array
    {
        // TODO: oversett SearchCriteria til Gmail search-syntax
        // (from:, subject:, after:, before:, is:unread, label:)
        // kall users.messages.list, deretter map til MessageHeaders[]
        throw new \RuntimeException('Not implemented');
    }

    public function listContainers(): array
    {
        // TODO: users.labels.list
        throw new \RuntimeException('Not implemented');
    }

    public function fetchHeaders(string $messageId): MessageHeaders
    {
        // TODO: users.messages.get med format=metadata
        throw new \RuntimeException('Not implemented');
    }

    public function fetchBody(string $messageId): string
    {
        // TODO: users.messages.get med format=full, parse payload
        throw new \RuntimeException('Not implemented');
    }

    public function fetchMessage(string $messageId): Message
    {
        // TODO: kombiner fetchHeaders + fetchBody (eller ett kall med format=full)
        throw new \RuntimeException('Not implemented');
    }

    public function delete(string $messageId, bool $permanent = false): void
    {
        // TODO: permanent=false -> users.messages.trash
        //       permanent=true  -> users.messages.delete
        throw new \RuntimeException('Not implemented');
    }

    public function deleteBatch(array $messageIds, bool $permanent = false): void
    {
        // TODO: bruk users.messages.batchModify (legg til TRASH-label)
        // eller batchDelete for permanent — viktig pga. 197k meldinger,
        // én og én vil være uakseptabelt tregt
        throw new \RuntimeException('Not implemented');
    }

    public function moveTo(string $messageId, string $container): void
    {
        // TODO: Gmail har ikke "flytt" — fjern INBOX-label, legg til mål-label
        throw new \RuntimeException('Not implemented');
    }

    public function addLabel(string $messageId, string $label): void
    {
        // TODO: users.messages.modify, addLabelIds
        throw new \RuntimeException('Not implemented');
    }

    public function removeLabel(string $messageId, string $label): void
    {
        // TODO: users.messages.modify, removeLabelIds
        throw new \RuntimeException('Not implemented');
    }

    public function markAsRead(string $messageId): void
    {
        // TODO: removeLabel('UNREAD')
        throw new \RuntimeException('Not implemented');
    }
}