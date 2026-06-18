<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
require __DIR__ . '/environment.php';

$oauth = $gmail_credentials['oauth20'];
$code  = '4/0AdkVLPxHjYtKh8M9fvST3XTcTp7V-S4SttC57g7s0ZZqbUdzu5TTcrDn88FCHxX3crWwOw';

$g = new Google\Client();
$g->setClientId($oauth['client_id']);
$g->setClientSecret($oauth['client_secret']);
$g->setRedirectUri('http://127.0.0.1:8080');
$g->setAccessType('offline');

$token = $g->fetchAccessTokenWithAuthCode($code);

if (isset($token['error'])) {
    echo "FAIL: " . ($token['error_description'] ?? $token['error']) . "\n";
    exit;
}

echo "refresh_token: " . ($token['refresh_token'] ?? '(mangler — prøv med prompt=consent)') . "\n";
echo "access_token:  " . substr($token['access_token'] ?? '', 0, 40) . "...\n";
