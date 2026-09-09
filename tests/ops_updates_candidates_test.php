<?php
/**
 * Ops update checks try local 8090 before cloud for local dev installs.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/platform.php';
require_once $root . '/core/platform_ops.php';

$errors = [];

$_SERVER['HTTP_HOST'] = 'localhost:8080';
$GLOBALS['__birkly_platform_config'] = ['owner_mode' => true];

$candidates = birkly_ops_updates_check_candidate_urls();
if ($candidates === []) {
    $errors[] = 'Owner local dev should have Ops update candidate URLs';
}
if (($candidates[0] ?? '') !== 'http://localhost:8090/api/ops/v1/releases/check') {
    $errors[] = 'Local Ops on localhost:8090 should be first candidate for localhost dev, got: ' . ($candidates[0] ?? '(none)');
}
if (!in_array('https://ops.birkly.cloud/api/ops/v1/releases/check', $candidates, true)) {
    $errors[] = 'Cloud Ops should remain a fallback candidate';
}

unset($GLOBALS['__birkly_platform_config'], $_SERVER['HTTP_HOST']);

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "ops_updates_candidates_test: OK\n";
