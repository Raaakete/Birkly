<?php
/**
 * Cross-origin storefront session cookie policy (club on birkly.io → cms.birkly.cloud).
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

$domainsFile = dirname(__DIR__) . '/settings/domains.json';
$domainsBackup = is_readable($domainsFile) ? file_get_contents($domainsFile) : null;
file_put_contents($domainsFile, json_encode([
    'birkly.io' => [
        'domain' => 'birkly.io',
        'status' => 'active',
        'type' => 'site',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

$_SERVER['HTTP_HOST'] = 'cms.birkly.cloud';

assert_true(
    'cross-origin registered site detected from Origin',
    birkly_is_cross_origin_registered_site_request('https://birkly.io', '')
);
assert_true(
    'same CMS host is not cross-origin storefront',
    birkly_is_cross_origin_registered_site_request('https://cms.birkly.cloud', '') === false
);
assert_true(
    'unregistered origin is not cross-origin storefront',
    birkly_is_cross_origin_registered_site_request('https://evil.example', '') === false
);
assert_true(
    'site session uses SameSite=None for cross-origin storefront',
    birkly_site_session_samesite('https://birkly.io', '') === 'None'
);
assert_true(
    'site session keeps Lax for same-host requests',
    birkly_site_session_samesite('https://cms.birkly.cloud', '') === 'Lax'
);
assert_true(
    'csrf scope is site for cross-origin club without cookies',
    birkly_resolve_csrf_session_scope(
        [],
        'https://birkly.io/club/login.html',
        '/api/csrf.php',
        'https://birkly.io'
    ) === 'site'
);
assert_true(
    'csrf scope stays cms for same-host api without site cookie',
    birkly_resolve_csrf_session_scope([], '', '/api/csrf.php', '') === 'cms'
);

if ($domainsBackup !== null) {
    file_put_contents($domainsFile, $domainsBackup);
} elseif (is_file($domainsFile)) {
    unlink($domainsFile);
}

echo "\nsession_cross_origin_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
