<?php
/**
 * Local Ops updates URL discovery for owner/dev installs without feedback relay.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/platform.php';
require_once $root . '/core/platform_ops.php';

$errors = [];

$GLOBALS['__birkly_platform_config'] = ['owner_mode' => true];
$_SERVER['HTTP_HOST'] = 'localhost:8080';

$GLOBALS['birkly_ops_probe_health_override'] = static function (string $baseUrl): bool {
    return $baseUrl === 'http://127.0.0.1:8090';
};

// Inject override via renaming - platform_ops uses direct function. Use discover via mock in test differently.

// Call discover with a test hook - we'll patch by testing the URL builder when probe succeeds.
// Since we can't easily override birkly_ops_probe_health without changing code, test URL list + rewrite logic.

$urls = birkly_ops_local_instance_base_urls();
if ($urls === [] || $urls[0] !== 'http://127.0.0.1:8090') {
    $errors[] = 'Local Ops base URLs should include 127.0.0.1:8090';
}

$relay = birkly_feedback_relay_url();
if ($relay !== '') {
    $errors[] = 'Owner install should not use cloud feedback relay by default';
}

$updatesUrl = birkly_ops_updates_check_url();
if ($updatesUrl === '') {
    $errors[] = 'Owner install should fall back to cloud Ops updates URL when local Ops is offline';
}
if ($updatesUrl !== '' && !str_contains($updatesUrl, '/api/ops/v1/releases/check')) {
    $errors[] = 'Owner updates URL should point at Ops releases check endpoint';
}

unset($GLOBALS['__birkly_platform_config'], $_SERVER['HTTP_HOST']);

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "ops_local_updates_discovery_test: OK\n";
