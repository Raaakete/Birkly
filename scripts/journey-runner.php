#!/usr/bin/env php
<?php
/**
 * P28-M1 — User journey runner for beta launch automation.
 *
 * Usage:
 *   php scripts/journey-runner.php [--all|--journey J-ID] [--base URL] [--list]
 *
 * Environment:
 *   BIRKLY_JOURNEY_BASE_URL — overrides default http://127.0.0.1:8080 (staging mode, P28-M12)
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "journey-runner: CLI only\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/tests/journeys/_helpers.php';

function journey_runner_usage(): void
{
    $msg = <<<TXT
Birkly user journey runner (P28)

Usage:
  php scripts/journey-runner.php [--all|--journey J-ID] [--base URL] [--list]

Options:
  --all              Run every journey in tests/journeys/J-*.php
  --journey J-ID     Run a single journey (e.g. J-BLOG)
  --base URL         CMS base URL (default: http://127.0.0.1:8080)
  --list             List available journey IDs
  -h, --help         Show this help

Environment:
  BIRKLY_JOURNEY_BASE_URL  Staging CMS base URL (same as --base). Use for remote/staging
                           smoke without starting a local php -S server, e.g.:
                           BIRKLY_JOURNEY_BASE_URL=https://staging.example.com \\
                             php scripts/journey-runner.php --all

Output:
  logs/journey-report.json — run_at, base_url, journeys[{id, result, duration_ms, error?}]

Exit code 1 when any journey fails.

TXT;
    fwrite(STDOUT, $msg);
}

$opts = getopt('h', ['help', 'all', 'journey:', 'base:', 'list']);
if (isset($opts['h']) || isset($opts['help'])) {
    journey_runner_usage();
    exit(0);
}

if (!function_exists('curl_init')) {
    fwrite(STDERR, "journey-runner: curl extension required\n");
    exit(1);
}

$listOnly = isset($opts['list']);
$runAll = isset($opts['all']);
$single = $opts['journey'] ?? null;
$baseUrl = journey_parse_base_url($opts['base'] ?? null);

if (!$listOnly && !$runAll && $single === null) {
    journey_runner_usage();
    exit(1);
}

$journeyDir = $root . '/tests/journeys';
$files = glob($journeyDir . '/J-*.php') ?: [];
sort($files);

$registry = [];
foreach ($files as $file) {
    $def = require $file;
    if (!is_array($def) || empty($def['id']) || !is_callable($def['run'] ?? null)) {
        fwrite(STDERR, "journey-runner: invalid journey file $file\n");
        exit(1);
    }
    $registry[$def['id']] = $def;
}

if ($listOnly) {
    foreach (array_keys($registry) as $id) {
        $name = $registry[$id]['name'] ?? '';
        echo $id . ($name !== '' ? " — $name" : '') . "\n";
    }
    exit(0);
}

$selected = [];
if ($runAll) {
    $selected = array_keys($registry);
} else {
    $id = (string) $single;
    if (!isset($registry[$id])) {
        fwrite(STDERR, "journey-runner: unknown journey $id\n");
        exit(1);
    }
    $selected = [$id];
}

$sharedServerPid = 0;
$sharedServerStarted = false;
$needsSharedCms = false;
foreach ($selected as $id) {
    $mode = $registry[$id]['server'] ?? 'dev';
    if ($mode === 'dev' && !journey_is_external_base($baseUrl)) {
        $needsSharedCms = true;
        break;
    }
}

if ($needsSharedCms) {
    $server = journey_ensure_cms_server($root, $baseUrl);
    $sharedServerPid = $server['pid'];
    $sharedServerStarted = $server['started'];
    if (!$server['started'] && !journey_http_ready($baseUrl)) {
        fwrite(STDERR, "journey-runner: CMS not reachable at $baseUrl\n");
        exit(1);
    }
}

$report = [
    'run_at' => gmdate('c'),
    'base_url' => $baseUrl,
    'journeys' => [],
];

$anyFail = false;
echo "Birkly journey runner\n";
echo "Base URL: $baseUrl\n\n";

foreach ($selected as $id) {
    $def = $registry[$id];
    $name = (string) ($def['name'] ?? $id);
    echo "=== $id — $name ===\n";

    journey_reset_failures();
    $started = hrtime(true);
    $error = null;

    try {
        $ctx = [
            'id' => $id,
            'base_url' => $baseUrl,
            'repo_root' => $root,
            'external_base' => journey_is_external_base($baseUrl),
            'shared_server_pid' => $sharedServerPid,
        ];
        ($def['run'])($ctx);
        journey_finish();
        $result = 'pass';
    } catch (Throwable $e) {
        $result = 'fail';
        $error = $e->getMessage();
        $anyFail = true;
        echo "JOURNEY FAIL: $error\n";
    }

    $durationMs = (int) round((hrtime(true) - $started) / 1_000_000);
    $entry = [
        'id' => $id,
        'result' => $result,
        'duration_ms' => $durationMs,
    ];
    if ($error !== null) {
        $entry['error'] = $error;
    }
    $report['journeys'][] = $entry;
    echo "$id: " . strtoupper($result) . " ({$durationMs}ms)\n\n";
}

if ($sharedServerStarted && $sharedServerPid > 0) {
    journey_stop_cms_server($sharedServerPid);
}

$logsDir = $root . '/logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}
file_put_contents(
    $logsDir . '/journey-report.json',
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
);

echo "Report: logs/journey-report.json\n";
echo 'Summary: ' . count(array_filter($report['journeys'], static fn ($j) => $j['result'] === 'pass'))
    . '/' . count($report['journeys']) . " passed\n";

exit($anyFail ? 1 : 0);
