<?php
// Gmail API-test (google/apiclient, OAuth2). Skriver PASS eller FAIL.
// Forste kjoring uten refresh_token: gir auth-URL, lytter pa loopback, henter token.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
require __DIR__ . '/environment.php';

$oauth = $gmail_credentials['oauth20'];

$g = new Google\Client();
$g->setClientId($oauth['client_id']);
$g->setClientSecret($oauth['client_secret']);
$g->setScopes([Google\Service\Gmail::GMAIL_READONLY]);
$g->setRedirectUri('http://127.0.0.1:8080');   // loopback, trenger IKKE registreres
$g->setAccessType('offline');
$g->setPrompt('consent');                       // tvinger fram refresh_token

try {
    if (($oauth['refresh_token'] ?? '') === '') {
        echo "Apne i nettleser:\n" . $g->createAuthUrl() . "\n";

        $server = stream_socket_server('tcp://127.0.0.1:8080', $errno, $errstr);
        if (!$server) {
            echo "FAIL: kunne ikke lytte pa 127.0.0.1:8080 ($errstr)\n";
            exit;
        }
        $conn = stream_socket_accept($server, 180);
        $req  = fread($conn, 4096);
        preg_match('/GET \/\?code=([^&\s]+)/', $req, $m);
        fwrite($conn, "HTTP/1.1 200 OK\r\n\r\nFerdig, lukk fanen.");
        fclose($conn);
        fclose($server);

        $token = $g->fetchAccessTokenWithAuthCode(urldecode($m[1] ?? ''));
        if (isset($token['error'])) {
            echo "FAIL: " . ($token['error_description'] ?? $token['error']) . "\n";
            exit;
        }
        echo "refresh_token (legg i credentials.php): " . ($token['refresh_token'] ?? '(mangler)') . "\n";
        $g->setAccessToken($token);
    } else {
        $g->refreshToken($oauth['refresh_token']);
    }

    $service = new Google\Service\Gmail($g);
    $p = $service->users->getProfile('me');
    echo "PASS — " . $p->getEmailAddress() . " (" . $p->getMessagesTotal() . " meldinger)\n";
} catch (\Throwable $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}
