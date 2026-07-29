<?php
/**
 * CLI: smoke-load a plugin's plugin.php (used by deploy_revision diagnostics).
 */
declare(strict_types=1);

$pluginId = $argv[1] ?? '';
if ($pluginId === '' || !preg_match('/^[a-z0-9_-]+$/i', $pluginId)) {
    fwrite(STDERR, "Usage: php scripts/probe-plugin-load.php <plugin_id>\n");
    exit(2);
}

$root = dirname(__DIR__);
require_once $root . '/core/birkly_paths.php';
require_once $root . '/core/birkly_data_paths.php';
require_once $root . '/core/plugins.php';
require_once $root . '/core/plugins/health.php';

if (function_exists('load_plugins')) {
    load_plugins();
}

$smoke = birkly_plugin_smoke_load($pluginId);
if ($smoke['ok']) {
    echo "OK\n";
    exit(0);
}

fwrite(STDERR, ($smoke['error'] ?? 'unknown error') . "\n");
exit(1);
