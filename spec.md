# Claude Code-instruks: Test av PHP email-bibliotek

## Mål
Verifiser at to bibliotek faktisk kobler til og leser:
- **IMAP:** `webklex/php-imap` (ren PHP, krever ikke ext-imap)
- **Gmail:** `google/apiclient` (Gmail API over OAuth2)

Hver test skal skrive ut `PASS` eller `FAIL: <årsak>`. Ingen annen funksjonalitet.

## Miljø
- Windows, PowerShell/CMD
- PHP 8.x + Composer i PATH
- Ingen `ext-imap` kreves (Webklex er ren PHP)

## Steg

### 1. Init og installer
```
composer init --no-interaction --name=test/email-libs
composer require webklex/php-imap google/apiclient
```

### 2. config.php

Filen `credentials.php` har disse ferdig utfyllt.

```php
<?php
// Vanlig IMAP-host (Gmail med app-passord fungerer også her: host=imap.gmail.com)
$imap_credentials = [
    'email'    => '',
    'username' => '', // username
    'password' => '', // password
    'incoming' => [
        'server'   => 'imap.domeneshop.no',
        'port'     => 993,
        'ssl'      => 'SSL/TLS',
        'auth'     => 'password',
        'username' => '', // username
    ],
    'outgoing' => [
        'server'   => 'smtp.domeneshop.no',
        'port'     => 587,
        'ssl'      => 'STARTTLS',
        'auth'     => 'password',
        'username' => '', // username
    ],
];

// Gmail API = OAuth2, IKKE email/pass.
// Hentes én gang fra Google Cloud Console (se merknad nederst).
$gmail_credentials = [
    'oauth20' => [
        'client_id' => '',
        'client_secret' => '',
        'refresh_token' => ''
    ]
];
```

### 3. test_imap.php (Webklex)
```php
<?php
require 'vendor/autoload.php';
require 'config.php';

use Webklex\PHPIMAP\ClientManager;

try {
    $cm = new ClientManager();
    $client = $cm->make([
        'host'          => $imap_credentials['host'],
        'port'          => $imap_credentials['port'],
        'encryption'    => $imap_credentials['encryption'],
        'validate_cert' => true,
        'username'      => $imap_credentials['email'],
        'password'      => $imap_credentials['pass'],
        'protocol'      => 'imap',
    ]);
    $client->connect();
    $inbox = $client->getFolder('INBOX');
    echo "PASS — INBOX: " . $inbox->messages()->all()->count() . " meldinger\n";
    $client->disconnect();
} catch (\Throwable $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}
```

### 4. test_gmail.php (Gmail API)
```php
<?php
require 'vendor/autoload.php';
require 'config.php';

$g = new Google\Client();
$g->setClientId($gmail_credentials['client_id']);
$g->setClientSecret($gmail_credentials['client_secret']);
$g->setScopes([Google\Service\Gmail::GMAIL_READONLY]);
$g->setRedirectUri('http://127.0.0.1:8080');   // loopback, trenger IKKE registreres
$g->setAccessType('offline');
$g->setPrompt('consent');                        // tvinger fram refresh_token

try {
    if ($gmail_credentials['refresh_token'] === '') {
        echo "Åpne i nettleser:\n" . $g->createAuthUrl() . "\n";

        // Lytt på loopback og fang ?code=...
        $server = stream_socket_server('tcp://127.0.0.1:8080', $errno, $errstr);
        $conn   = stream_socket_accept($server, 120);
        $req    = fread($conn, 4096);
        preg_match('/GET \/\?code=([^&\s]+)/', $req, $m);
        fwrite($conn, "HTTP/1.1 200 OK\r\n\r\nFerdig, lukk fanen.");
        fclose($conn); fclose($server);

        $token = $g->fetchAccessTokenWithAuthCode(urldecode($m[1] ?? ''));
        echo "refresh_token (legg i config): " . ($token['refresh_token'] ?? '(mangler)') . "\n";
        $g->setAccessToken($token);
    } else {
        $g->refreshToken($gmail_credentials['refresh_token']);
    }

    $service = new Google\Service\Gmail($g);
    $p = $service->users->getProfile('me');
    echo "PASS — " . $p->getEmailAddress() . " (" . $p->getMessagesTotal() . " meldinger)\n";
} catch (\Throwable $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}
```

### 5. Kjør
```
php test_imap.php
php test_gmail.php
```

## Merknader (les før kjøring)
- **Gmail OAuth2-engangsoppsett:** Google Cloud Console → nytt prosjekt → aktiver *Gmail API* → OAuth-samtykkeskjerm → opprett OAuth-client (type «Desktop app») → kopier `client_id` + `client_secret` inn i config. Første kjøring av `test_gmail.php` gir auth-URL → godkjenn → lim koden tilbake → kopier `refresh_token` inn i config for videre kjøringer.
- **App-passord-snarvei:** Vil du bare se at Gmail svarer uten OAuth, sett `imap_credentials.host = imap.gmail.com` og bruk et app-passord (krever 2FA på kontoen). Da tester `test_imap.php` også Gmail.
- **Hvis `ext-imap` allerede finnes** og du vil sammenligne: `composer require ddeboer/imap` og lag en parallell `test_ddeboer.php`. Ellers ignorer — Webklex dekker behovet uten extension.