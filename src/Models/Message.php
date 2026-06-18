<?php

namespace MailCleaner\Models;

class Message extends MessageHeaders
{
    public string $bodyText = '';
    public string $bodyHtml = '';
}
