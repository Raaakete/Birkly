<?php
/**
 * Optional browser smoke (Playwright). Skips when Playwright is unavailable.
 * Run: php tests/browser_reference_smoke_test.php
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Browser reference smoke (optional)\n\n";

$root = dirname(__DIR__);
$script = $root . '/scripts/reference-browser-smoke.sh';

if (!is_file($script)) {
    echo "SKIP: script missing\n";
    exit(test_finish('browser_reference_smoke_test'));
}

$base = getenv('BIRKLY_BASE_URL') ?: 'http://127.0.0.1:8080';
$probe = @file_get_contents($base . '/api/health.php');
if ($probe === false) {
    echo "SKIP: dev server not running at $base\n";
    exit(test_finish('browser_reference_smoke_test'));
}

chmod($script, 0755);
$out = [];
$code = 0;
exec('bash ' . escapeshellarg($script) . ' 2>&1', $out, $code);
$text = implode("\n", $out);

if (strpos($text, 'SKIP:') !== false) {
    echo $text . "\n";
    echo "SKIP: playwright not available\n";
    exit(test_finish('browser_reference_smoke_test'));
}

test_assert('browser smoke exit 0', $code === 0, $text);
echo $text . "\n";

exit(test_finish('browser_reference_smoke_test'));
