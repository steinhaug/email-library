<?php

namespace MailCleaner\Models;

class MessageHeaders
{
    public function __construct(
        public readonly string    $id,
        public readonly string    $from,
        public readonly string    $subject,
        public readonly \DateTime $date,
        public readonly bool      $isRead,
        public readonly array     $labels = [],
        public readonly string    $folder = 'INBOX',
    ) {}
}
