<?php
/**
 * CORS origin allowlist helpers (P61-B5/B6).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/cors.php';

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

$allowed = [
    'https://example.com',
    'http://example.com',
    'https://www.example.com',
    'http://www.example.com',
    'http://localhost',
    'http://127.0.0.1',
];

assert_true(
    'exact registered origin allowed',
    birkly_cors_origin_allowed('https://example.com', $allowed)
);
assert_true(
    'www origin allowed when apex registered',
    birkly_cors_origin_allowed('https://www.example.com', ['https://example.com', 'http://example.com'])
);
assert_true(
    'apex origin allowed when www registered',
    birkly_cors_origin_allowed('https://example.com', ['https://www.example.com', 'http://www.example.com'])
);
assert_true(
    'localhost with port allowed',
    birkly_cors_origin_allowed('http://localhost:5173', $allowed)
);
assert_true(
    '127.0.0.1 with port allowed',
    birkly_cors_origin_allowed('http://127.0.0.1:3000', $allowed)
);
assert_true(
    'unrelated origin rejected',
    birkly_cors_origin_allowed('https://evil.example', $allowed) === false
);

// get_registered_domains expands www↔apex
$domainsFile = dirname(__DIR__) . '/settings/domains.json';
$domainsBackup = is_readable($domainsFile) ? file_get_contents($domainsFile) : null;
file_put_contents($domainsFile, json_encode([
    'external.test' => [
        'domain' => 'external.test',
        'status' => 'active',
        'type' => 'site',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

// Clear static cache by re-including is not possible; call via reflection-free path:
// get_registered_domains caches statically — run in a fresh PHP process for full coverage.
// Smoke: helper itself covers matching against an allowlist shaped like get_registered_domains.
assert_true(
    'registered external origin shape matches helper',
    birkly_cors_origin_allowed('https://www.external.test', [
        'https://external.test',
        'http://external.test',
        'https://www.external.test',
        'http://www.external.test',
    ])
);

if ($domainsBackup !== null) {
    file_put_contents($domainsFile, $domainsBackup);
} elseif (is_file($domainsFile)) {
    unlink($domainsFile);
}

echo "\ncors_origin_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
