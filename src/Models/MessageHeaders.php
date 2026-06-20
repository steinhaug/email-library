<?php

namespace MailCleaner\Models;

class MessageHeaders
{
    public function __construct(
        public readonly string    $messageId,
        public readonly string    $from,
        public readonly string    $subject,
        public readonly \DateTime $date,
        public readonly bool      $isUnread,
        // Extra, optional fields — reflect real difference between Gmail
        // labels and IMAP folders. Additive to the interface contract.
        public readonly array     $labels = [],
        public readonly string    $folder = 'INBOX',
    ) {}
}
