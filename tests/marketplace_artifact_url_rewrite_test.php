<?php
/**
 * Marketplace artifact URLs rewrite to a configured custom marketplace base URL.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/marketplace.php';

$errors = [];

$GLOBALS['__birkly_marketplace_settings'] = [
    'marketplace_url' => 'https://custom.marketplace.example',
    'installation_owner_email' => '',
    'display_currency' => 'EUR',
    'update_channel' => 'alpha',
    'entitlement_secret' => '',
];

$cloudUrl = 'https://marketplace.birkly.cloud/api/v1/artifacts/birkly-automation/1.1.31/download';
$rewritten = birkly_marketplace_rewrite_service_url($cloudUrl);
if ($rewritten !== 'https://custom.marketplace.example/api/v1/artifacts/birkly-automation/1.1.31/download') {
    $errors[] = 'Expected cloud artifact URL to rewrite to configured marketplace base, got: ' . $rewritten;
}

unset($GLOBALS['__birkly_marketplace_settings']);

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "marketplace_artifact_url_rewrite_test: OK\n";
