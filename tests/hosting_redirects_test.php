<?php
/**
 * Hosting redirect enforcement tests (P66-M3).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/hosting_redirects.php';

$failures = 0;
$redirectsFile = birkly_redirects_settings_path();
$backup = is_readable($redirectsFile) ? file_get_contents($redirectsFile) : null;

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

try {
    $rules = [[
        'id' => 'test-redirect',
        'from_path' => '/old-page',
        'to_path' => '/new-page',
        'type' => 301,
        'enabled' => true,
        'created_at' => date('c'),
        'updated_at' => date('c'),
    ]];
    file_put_contents($redirectsFile, json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

    $match = birkly_hosting_match_redirect('/old-page');
    assert_true('redirect rule matches from_path', $match !== null && ($match['to_path'] ?? '') === '/new-page');
    assert_true('redirect rule type 301', $match !== null && (int) ($match['type'] ?? 0) === 301);
    assert_true('no match for unrelated path', birkly_hosting_match_redirect('/other') === null);
} finally {
    if ($backup !== null) {
        file_put_contents($redirectsFile, $backup);
    } elseif (is_file($redirectsFile)) {
        unlink($redirectsFile);
    }
}

echo "\nhosting_redirects_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
