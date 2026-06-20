<?php

namespace MailCleaner\Models;

class Message
{
    public function __construct(
        public readonly MessageHeaders $headers,
        public readonly string $bodyPlain,
        public readonly ?string $bodyHtml = null,
    ) {}
}
