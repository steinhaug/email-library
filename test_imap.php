<?php
// IMAP-test (Webklex\php-imap, ren PHP). Skriver PASS eller FAIL.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED); // illuminate/support v5.4 spam pa PHP 8.2
require __DIR__ . '/environment.php';

use Webklex\PHPIMAP\ClientManager;

$enc_map = ['SSL/TLS' => 'ssl', 'STARTTLS' => 'tls'];
$in = $imap_credentials['incoming'];

try {
    $cm = new ClientManager();
    $client = $cm->make([
        'host'          => $in['server'],
        'port'          => $in['port'],
        'encryption'    => $enc_map[$in['ssl']] ?? 'ssl',
        'validate_cert' => true,
        'username'      => $imap_credentials['username'],
        'password'      => $imap_credentials['password'],
        'protocol'      => 'imap',
    ]);
    $client->connect();
    $inbox = $client->getFolder('INBOX');
    echo "PASS — INBOX: " . $inbox->messages()->all()->count() . " meldinger\n";
    $client->disconnect();
} catch (\Throwable $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}
