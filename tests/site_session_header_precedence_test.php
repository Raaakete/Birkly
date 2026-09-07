<?php
/**
 * Site session must honor X-Birkly-Site-Session even when a stale birkly_site_sid cookie exists.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/api/session.php';

$failures = 0;

function assert_true(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    echo $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '  ' . $detail . "\n";
        }
    }
}

$sessionSavePath = sys_get_temp_dir() . '/birkly_site_session_test_' . getmypid();
@mkdir($sessionSavePath, 0700, true);
session_save_path($sessionSavePath);

$domainsFile = dirname(__DIR__) . '/settings/domains.json';
$domainsBackup = is_readable($domainsFile) ? file_get_contents($domainsFile) : null;
file_put_contents($domainsFile, json_encode([
    'birkly.io' => [
        'domain' => 'birkly.io',
        'status' => 'active',
        'type' => 'site',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

$validSessionId = 'validheadersession12345';
$staleCookieId = 'stalecookiesession12345';
$sessionPayload = 'user|a:2:{s:2:"id";s:8:"user-123";s:4:"name";s:4:"Test";}';
file_put_contents($sessionSavePath . '/sess_' . $validSessionId, $sessionPayload);
file_put_contents($sessionSavePath . '/sess_' . $staleCookieId, 'user|a:0:{}');

$_SERVER['HTTP_HOST'] = 'cms.birkly.cloud';
$_SERVER['HTTP_ORIGIN'] = 'https://birkly.io';
$_SERVER['HTTP_X_BIRKLY_SITE_SESSION'] = $validSessionId;
$_COOKIE['birkly_site_sid'] = $staleCookieId;

if (session_status() !== PHP_SESSION_NONE) {
    session_write_close();
}

birkly_site_session_start();

assert_true(
    'header session loads user despite stale cookie',
    (($_SESSION['user']['id'] ?? '') === 'user-123')
);

session_write_close();

@unlink($sessionSavePath . '/sess_' . $validSessionId);
@unlink($sessionSavePath . '/sess_' . $staleCookieId);
@rmdir($sessionSavePath);

if ($domainsBackup !== null) {
    file_put_contents($domainsFile, $domainsBackup);
} elseif (is_file($domainsFile)) {
    unlink($domainsFile);
}

echo "\nsite_session_header_precedence_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
