<?php
// Integration test for MailCleaner driver layer. Read-only — no deletes/moves.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
require __DIR__ . '/environment.php';

use MailCleaner\Drivers\ImapDriver;
use MailCleaner\Drivers\GmailDriver;
use MailCleaner\Models\SearchCriteria;

$pass = 0;
$fail = 0;

function ok(string $label): void {
    global $pass;
    $pass++;
    echo "  PASS  {$label}\n";
}

function fail(string $label, string $reason): void {
    global $fail;
    $fail++;
    echo "  FAIL  {$label}: {$reason}\n";
}

function section(string $title): void {
    echo "\n=== {$title} ===\n";
}

// ---------------------------------------------------------------------------
section('ImapDriver');

try {
    $imap = new ImapDriver();
    $imap->connect($imap_credentials);

    // testConnection
    $imap->testConnection() ? ok('testConnection') : fail('testConnection', 'returned false');

    // listContainers
    $folders = $imap->listContainers();
    count($folders) > 0 ? ok('listContainers (' . count($folders) . ' folders)') : fail('listContainers', 'empty');

    // search — no criteria, limit 5
    $c = new SearchCriteria(limit: 5);
    $msgs = $imap->search($c);
    count($msgs) === 5 ? ok('search no-criteria limit=5') : fail('search no-criteria', 'expected 5 got ' . count($msgs));

    // search — with container
    $c2 = new SearchCriteria(container: 'INBOX', limit: 2);
    $msgs2 = $imap->search($c2);
    count($msgs2) > 0 ? ok('search container=INBOX limit=2') : fail('search container=INBOX', 'empty');

    // fetchHeaders
    if (!empty($msgs)) {
        $firstId = $msgs[0]->messageId;
        $h = $imap->fetchHeaders($firstId);
        ($h->messageId === $firstId) ? ok('fetchHeaders') : fail('fetchHeaders', 'messageId mismatch');

        // fetchBody
        $body = $imap->fetchBody($firstId);
        is_string($body) ? ok('fetchBody (len=' . strlen($body) . ')') : fail('fetchBody', 'not string');

        // fetchMessage
        $m = $imap->fetchMessage($firstId);
        ($m->headers->messageId === $firstId && is_string($m->bodyPlain)) ? ok('fetchMessage') : fail('fetchMessage', 'bad structure');
    }

    $imap->disconnect();
    ok('disconnect');

} catch (\Throwable $e) {
    fail('ImapDriver fatal', $e->getMessage());
}

// ---------------------------------------------------------------------------
section('GmailDriver');

try {
    $gmail = new GmailDriver();
    $gmail->connect($gmail_credentials);

    // testConnection
    $gmail->testConnection() ? ok('testConnection') : fail('testConnection', 'returned false');

    // listContainers (labels)
    $labels = $gmail->listContainers();
    count($labels) > 0 ? ok('listContainers (' . count($labels) . ' labels)') : fail('listContainers', 'empty');
    in_array('INBOX', $labels) ? ok('listContainers has INBOX') : fail('listContainers', 'INBOX label missing');

    // search — no criteria, limit 5
    $c = new SearchCriteria(limit: 5);
    $msgs = $gmail->search($c);
    count($msgs) === 5 ? ok('search no-criteria limit=5') : fail('search no-criteria', 'expected 5 got ' . count($msgs));

    // search — with from filter
    $c2 = new SearchCriteria(fromContains: 'notifications@github.com', limit: 2);
    $msgs2 = $gmail->search($c2);
    count($msgs2) > 0 ? ok('search fromContains=github limit=2') : fail('search fromContains=github', 'empty');

    // search — unread only
    $c3 = new SearchCriteria(unreadOnly: true, limit: 3);
    $msgs3 = $gmail->search($c3);
    is_array($msgs3) ? ok('search unreadOnly (got ' . count($msgs3) . ')') : fail('search unreadOnly', 'not array');

    // fetchHeaders
    if (!empty($msgs)) {
        $firstId = $msgs[0]->messageId;
        $h = $gmail->fetchHeaders($firstId);
        ($h->messageId === $firstId) ? ok('fetchHeaders') : fail('fetchHeaders', 'messageId mismatch');
        (in_array('UNREAD', $h->labels) === $h->isUnread) ? ok('fetchHeaders isUnread consistent') : fail('fetchHeaders', 'isUnread/labels mismatch');

        // fetchBody
        $body = $gmail->fetchBody($firstId);
        is_string($body) ? ok('fetchBody (len=' . strlen($body) . ')') : fail('fetchBody', 'not string');

        // fetchMessage
        $m = $gmail->fetchMessage($firstId);
        ($m->headers->messageId === $firstId && is_string($m->bodyPlain)) ? ok('fetchMessage') : fail('fetchMessage', 'bad structure');
    }

    $gmail->disconnect();
    ok('disconnect');

} catch (\Throwable $e) {
    fail('GmailDriver fatal', $e->getMessage());
}

// ---------------------------------------------------------------------------
echo "\n--- Result: {$pass} passed, {$fail} failed ---\n";
exit($fail > 0 ? 1 : 0);
