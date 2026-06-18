<?php

namespace MailCleaner\Models;

class SearchCriteria
{
    public ?string $from      = null;
    public ?string $subject   = null;
    public ?string $folder    = null;
    public bool    $unreadOnly = false;
    public int     $limit     = 50;
    public ?\DateTime $after  = null;
    public ?\DateTime $before = null;
}
