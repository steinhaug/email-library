<?php

namespace MailCleaner\Models;

class SearchCriteria
{
    public function __construct(
        public readonly ?string $fromContains = null,
        public readonly ?string $subjectContains = null,
        public readonly ?\DateTimeImmutable $olderThan = null,
        public readonly ?\DateTimeImmutable $newerThan = null,
        public readonly ?bool $unreadOnly = null,
        public readonly ?string $container = null,
        public readonly int $limit = 100,
    ) {}
}
