<?php

namespace MailCleaner\Drivers;

use MailCleaner\Contracts\MailAccountInterface;
use MailCleaner\Models\Message;
use MailCleaner\Models\MessageHeaders;
use MailCleaner\Models\SearchCriteria;

class ImapDriver implements MailAccountInterface
{
    private \Webklex\PHPIMAP\ClientManager $manager;
    private \Webklex\PHPIMAP\Client $client;

    public function connect(array $credentials): void
    {
        // TODO: $this->manager = new ClientManager();
        // $this->client = $this->manager->make([
        //     'host' => $credentials['host'],       // imap.domeneshop.no e.l.
        //     'port' => $credentials['port'] ?? 993,
        //     'encryption' => 'ssl',
        //     'username' => $credentials['username'],
        //     'password' => $credentials['password'],
        // ]);
        // $this->client->connect();
        throw new \RuntimeException('Not implemented');
    }

    public function disconnect(): void
    {
        // TODO: $this->client->disconnect();
        throw new \RuntimeException('Not implemented');
    }

    public function testConnection(): bool
    {
        // TODO: $this->client->isConnected()
        throw new \RuntimeException('Not implemented');
    }

    public function search(SearchCriteria $criteria): array
    {
        // TODO: oversett SearchCriteria til IMAP SEARCH-kriterier
        // ($folder->messages()->where(...)), map til MessageHeaders[]
        // NB: IMAP henter ofte hele meldingen selv ved "header-søk" —
        // sjekk om Webklex støtter "peek"/metadata-only fetch for ytelse
        throw new \RuntimeException('Not implemented');
    }

    public function listContainers(): array
    {
        // TODO: $this->client->getFolders() — IMAP-mapper er hierarkiske,
        // flat ut til string[] med f.eks. "/" som separator
        throw new \RuntimeException('Not implemented');
    }

    public function fetchHeaders(string $messageId): MessageHeaders
    {
        // TODO: hent melding, les kun header-delen (unngå body-transfer)
        throw new \RuntimeException('Not implemented');
    }

    public function fetchBody(string $messageId): string
    {
        // TODO: hent full melding, returner body (plain eller html->text)
        throw new \RuntimeException('Not implemented');
    }

    public function fetchMessage(string $messageId): Message
    {
        // TODO: kombiner fetchHeaders + fetchBody
        throw new \RuntimeException('Not implemented');
    }

    public function delete(string $messageId, bool $permanent = false): void
    {
        // TODO: permanent=false -> flytt til "Trash"-mappe
        //       permanent=true  -> $message->delete() + expunge
        throw new \RuntimeException('Not implemented');
    }

    public function deleteBatch(array $messageIds, bool $permanent = false): void
    {
        // TODO: IMAP har ikke ekte batch-delete — loop, men gjør én
        // connect/expunge i stedet for expunge per melding
        throw new \RuntimeException('Not implemented');
    }

    public function moveTo(string $messageId, string $container): void
    {
        // TODO: $message->move($container)
        throw new \RuntimeException('Not implemented');
    }

    public function addLabel(string $messageId, string $label): void
    {
        // TODO: IMAP har ikke ekte labels (Gmail-spesifikt konsept).
        // Emulering: bruk IMAP keywords/flags der serveren støtter det,
        // ellers no-op eller kast UnsupportedOperationException
        throw new \RuntimeException('Not implemented');
    }

    public function removeLabel(string $messageId, string $label): void
    {
        // TODO: se addLabel — samme begrensning
        throw new \RuntimeException('Not implemented');
    }

    public function markAsRead(string $messageId): void
    {
        // TODO: $message->setFlag('Seen')
        throw new \RuntimeException('Not implemented');
    }
}