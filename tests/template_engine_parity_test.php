<?php
/**
 * PHP ⇄ JS template engine parity harness (P52-M1) — V-PHP-12.
 *
 * Renders every case in tests/template-engine/fixtures/*.json with BOTH
 * engines and requires byte-identical output:
 *   - PHP: core/birkly_template_engine.php (birkly_template_engine_render)
 *   - JS:  birkly-template-engine.js via tests/template-engine/render-fixtures.js
 *
 * Cases with an `expected` value are additionally asserted against it on the
 * PHP side (the JS side is implicitly covered by the byte comparison).
 *
 * The JS engine parses timezone-less date strings in local time, so the Node
 * child process is pinned to PHP's effective timezone via TZ.
 *
 * Run: php tests/template_engine_parity_test.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/birkly_template_engine.php';

$failures = 0;
$total = 0;

function assert_true(string $name, bool $ok, string $detail = ''): void
{
    global $failures, $total;
    $total++;
    echo '  ' . $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '    ' . $detail . "\n";
        }
    }
}

/** First point of difference between two strings, for failure diagnostics. */
function parity_diff_detail(string $php, string $js): string
{
    $len = min(strlen($php), strlen($js));
    $at = -1;
    for ($i = 0; $i < $len; $i++) {
        if ($php[$i] !== $js[$i]) {
            $at = $i;
            break;
        }
    }
    if ($at === -1) {
        $at = $len;
    }
    $from = max(0, $at - 40);
    return 'diverges at byte ' . $at
        . ' php=' . var_export(substr($php, $from, 80), true)
        . ' js=' . var_export(substr($js, $from, 80), true);
}

/** Locate a usable Node binary (BIRKLY_NODE_BIN, then PATH). */
function parity_node_binary(): ?string
{
    $candidates = [];
    $env = getenv('BIRKLY_NODE_BIN');
    if ($env !== false && trim((string) $env) !== '') {
        $candidates[] = trim((string) $env);
    }
    $candidates[] = 'node';
    foreach ($candidates as $candidate) {
        if (str_contains($candidate, '/')) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
            continue;
        }
        $probe = @shell_exec('command -v ' . escapeshellarg($candidate) . ' 2>/dev/null');
        if (is_string($probe)) {
            $probe = trim($probe);
            if ($probe !== '' && is_file($probe) && is_executable($probe)) {
                return $probe;
            }
        }
    }
    return null;
}

$root = dirname(__DIR__);
$fixtureDir = $root . '/tests/template-engine/fixtures';

$fixtureFiles = glob($fixtureDir . '/*.json') ?: [];
sort($fixtureFiles);
assert_true('fixture files present', count($fixtureFiles) > 0);

// ---------------------------------------------------------------------------
// PHP render pass (+ expected assertions)
// ---------------------------------------------------------------------------
echo "\n[V-PHP-12] PHP render pass over fixtures\n";
$phpResults = [];
foreach ($fixtureFiles as $file) {
    $doc = json_decode((string) file_get_contents($file), true);
    if (!is_array($doc) || !isset($doc['cases']) || !is_array($doc['cases'])) {
        assert_true(basename($file) . ' parses', false, 'invalid fixture JSON');
        continue;
    }
    foreach ($doc['cases'] as $case) {
        $name = (string) ($case['name'] ?? '');
        $label = basename($file) . ' :: ' . $name;
        $output = birkly_template_engine_render(
            (string) ($case['html'] ?? ''),
            $case['data'] ?? [],
            isset($case['options']) && is_array($case['options']) ? $case['options'] : []
        );
        $phpResults[] = ['file' => basename($file), 'name' => $name, 'output' => $output];
        if (array_key_exists('expected', $case)) {
            assert_true(
                $label . ' (expected)',
                $output === (string) $case['expected'],
                parity_diff_detail($output, (string) $case['expected'])
            );
        }
    }
}
assert_true('PHP rendered all cases', count($phpResults) > 0);

// ---------------------------------------------------------------------------
// JS render pass + byte-for-byte comparison
// ---------------------------------------------------------------------------
echo "\n[V-PHP-12] JS render pass + byte comparison\n";
$node = parity_node_binary();
if ($node === null) {
    echo "  JS parity: SKIP (node unavailable) — expected-value assertions above still apply\n";
} else {
    // Pin the Node child to PHP's effective timezone so `new Date()` local
    // parsing matches PHP DateTime local parsing.
    putenv('TZ=' . date_default_timezone_get());
    $script = $root . '/tests/template-engine/render-fixtures.js';
    $cmd = escapeshellarg($node) . ' ' . escapeshellarg($script) . ' 2>/dev/null';
    $raw = shell_exec($cmd);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    $jsOk = is_array($decoded) && !empty($decoded['ok']) && isset($decoded['results']) && is_array($decoded['results']);
    assert_true('JS fixture runner succeeded', $jsOk, is_array($decoded) ? (string) ($decoded['error'] ?? '') : 'no/invalid output');

    if ($jsOk) {
        $jsResults = $decoded['results'];
        assert_true(
            'same case count (php=' . count($phpResults) . ' js=' . count($jsResults) . ')',
            count($phpResults) === count($jsResults)
        );
        $count = min(count($phpResults), count($jsResults));
        for ($i = 0; $i < $count; $i++) {
            $php = $phpResults[$i];
            $js = $jsResults[$i];
            $label = $php['file'] . ' :: ' . $php['name'];
            if ($php['name'] !== ($js['name'] ?? '') || $php['file'] !== ($js['file'] ?? '')) {
                assert_true($label . ' (case alignment)', false, 'js case: ' . ($js['file'] ?? '?') . ' :: ' . ($js['name'] ?? '?'));
                continue;
            }
            $jsOut = (string) ($js['output'] ?? '');
            assert_true(
                $label,
                $php['output'] === $jsOut,
                parity_diff_detail($php['output'], $jsOut)
            );
        }
    }
}

// ---------------------------------------------------------------------------
echo "\ntemplate_engine_parity_test: " . ($failures === 0 ? 'PASS' : 'FAIL')
    . ' (' . ($total - $failures) . '/' . $total . ")\n";
exit($failures === 0 ? 0 : 1);
