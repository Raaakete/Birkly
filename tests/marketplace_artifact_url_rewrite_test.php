<?php
/**
 * Marketplace artifact URLs rewrite to the effective local base when cloud is unreachable.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/marketplace.php';

$errors = [];

$GLOBALS['__birkly_marketplace_settings'] = [
    'marketplace_url' => 'http://127.0.0.1:8090',
    'installation_owner_email' => '',
    'display_currency' => 'EUR',
    'update_channel' => 'alpha',
    'entitlement_secret' => '',
];

$cloudUrl = 'https://marketplace.birkly.cloud/api/v1/artifacts/birkly-automation/1.1.31/download';
$rewritten = birkly_marketplace_rewrite_service_url($cloudUrl);
if ($rewritten !== 'http://127.0.0.1:8090/api/v1/artifacts/birkly-automation/1.1.31/download') {
    $errors[] = 'Expected cloud artifact URL to rewrite to local marketplace base, got: ' . $rewritten;
}

$_SERVER['HTTP_HOST'] = 'localhost:8080';
$candidates = birkly_marketplace_artifact_download_candidates($cloudUrl);
unset($_SERVER['HTTP_HOST']);
if (!in_array($cloudUrl, $candidates, true)) {
    $errors[] = 'Candidates should include the original cloud URL';
}
if (!in_array('http://127.0.0.1:8090/api/v1/artifacts/birkly-automation/1.1.31/download', $candidates, true)) {
    $errors[] = 'Candidates should include rewritten local marketplace URL';
}
if (($candidates[0] ?? '') !== 'http://127.0.0.1:8090/api/v1/artifacts/birkly-automation/1.1.31/download') {
    $errors[] = 'Local dev should try local marketplace artifact URL before cloud';
}

unset($GLOBALS['__birkly_marketplace_settings']);

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "marketplace_artifact_url_rewrite_test: OK\n";
