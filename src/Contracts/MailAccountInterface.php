<?php

namespace MailCleaner\Contracts;

use MailCleaner\Models\Message;
use MailCleaner\Models\MessageHeaders;
use MailCleaner\Models\SearchCriteria;

/**
 * Driver-agnostisk kontrakt for en e-postkonto.
 * Implementeres av GmailDriver og ImapDriver — samme kall,
 * uavhengig av hva som ligger bak (Gmail API vs IMAP).
 */
interface MailAccountInterface
{
    // --- Tilkobling ---
    public function connect(array $credentials): void;
    public function disconnect(): void;
    public function testConnection(): bool;

    // --- Søk/listing ---
    /** @return MessageHeaders[] */
    public function search(SearchCriteria $criteria): array;

    /** @return string[] Navn på mapper (IMAP) / labels (Gmail) */
    public function listContainers(): array;

    // --- Henting ---
    public function fetchHeaders(string $messageId): MessageHeaders;
    public function fetchBody(string $messageId): string;
    public function fetchMessage(string $messageId): Message;

    // --- Handling ---
    public function delete(string $messageId, bool $permanent = false): void;

    /** @param string[] $messageIds */
    public function deleteBatch(array $messageIds, bool $permanent = false): void;

    public function moveTo(string $messageId, string $container): void;
    public function addLabel(string $messageId, string $label): void;
    public function removeLabel(string $messageId, string $label): void;
    public function markAsRead(string $messageId): void;
}