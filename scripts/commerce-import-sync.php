#!/usr/bin/env php
<?php
/**
 * Scheduled + manual live import sync for Shopify and WooCommerce (P12-M3).
 *
 * Usage:
 *   php scripts/commerce-import-sync.php
 *   php scripts/commerce-import-sync.php --platform=shopify
 *   php scripts/commerce-import-sync.php --token=YOUR_CRON_TOKEN
 *
 * HTTP (cron):
 *   GET /scripts/commerce-import-sync.php?token=YOUR_CRON_TOKEN
 */

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';

if (!commerce_plugin_active()) {
    fwrite(STDERR, "Commerce plugin is not active\n");
    exit(1);
}

commerce_bootstrap_cart_services();
require_once $root . '/plugins/commerce/includes/CommerceLiveImportService.php';
require_once $root . '/plugins/commerce/includes/CommerceImportCredentialStore.php';

$credentialStore = new CommerceImportCredentialStore();
$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
    header('Content-Type: application/json');
    $token = $_GET['token']
        ?? $_SERVER['HTTP_X_COMMERCE_IMPORT_CRON_TOKEN']
        ?? $_SERVER['HTTP_X_CRON_TOKEN']
        ?? '';
    if (!$credentialStore->cronTokenMatches(is_string($token) ? $token : '')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid cron token']);
        exit(1);
    }
}

$platform = null;
if ($isCli) {
    foreach ($argv ?? [] as $arg) {
        if (str_starts_with($arg, '--platform=')) {
            $platform = substr($arg, strlen('--platform='));
        }
        if (str_starts_with($arg, '--token=')) {
            $token = substr($arg, strlen('--token='));
            if (!$credentialStore->cronTokenMatches($token)) {
                fwrite(STDERR, "Invalid cron token\n");
                exit(1);
            }
        }
    }
} else {
    $platform = $_GET['platform'] ?? null;
    if (is_string($platform) && $platform === '') {
        $platform = null;
    }
}

$service = new CommerceLiveImportService($credentialStore);

if ($platform !== null) {
    $result = $service->syncPlatform((string) $platform);
    $output = ['results' => [array_merge(['platform' => $platform], $result)]];
} else {
    $output = ['results' => $service->syncScheduled()];
}

$ok = true;
foreach ($output['results'] as $row) {
    if (empty($row['ok'])) {
        $ok = false;
        break;
    }
}
$output['ok'] = $ok;

if ($isCli) {
    echo json_encode($output, JSON_PRETTY_PRINT) . "\n";
    exit($ok ? 0 : 1);
}

echo json_encode($output);
exit($ok ? 0 : 1);
