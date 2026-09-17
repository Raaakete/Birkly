#!/usr/bin/env php
<?php
/**
 * Install Birkly MCP into Claude Desktop or Cursor on this computer (run in Terminal).
 *
 *   php scripts/birkly-mcp-desktop-install.php --target=claude --token-file=/path/to/credentials.json
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/mcp_desktop.php';

$target = 'claude';
$tokenFile = null;
$uninstall = false;
for ($i = 1; $i < count($argv); $i++) {
    $arg = $argv[$i];
    if ($arg === '--uninstall') {
        $uninstall = true;
        continue;
    }
    if (str_starts_with($arg, '--target=')) {
        $target = substr($arg, strlen('--target='));
        continue;
    }
    if ($arg === '--token-file' && isset($argv[$i + 1])) {
        $tokenFile = $argv[++$i];
        continue;
    }
    if (str_starts_with($arg, '--token-file=')) {
        $tokenFile = substr($arg, strlen('--token-file='));
    }
}

if ($target !== 'claude' && $target !== 'cursor') {
    fwrite(STDERR, "Unknown --target. Use claude or cursor.\n");
    exit(1);
}

if ($uninstall) {
    $result = birkly_mcp_desktop_remove_from_app_config($target);
    if (!($result['ok'] ?? false)) {
        fwrite(STDERR, (string) ($result['message'] ?? 'Uninstall failed') . "\n");
        exit(1);
    }
    $meta = birkly_mcp_desktop_install_target_meta($target);
    $path = (string) ($result['path'] ?? '');
    if ($result['removed'] ?? false) {
        echo "Birkly: removed “birkly” from {$path}\n";
    } else {
        echo "Birkly: no “birkly” entry in {$path}\n";
    }
    echo "Fully quit {$meta['app_label']}, then open it again.\n";
    exit(0);
}

if ($tokenFile !== null && $tokenFile !== '' && !str_starts_with($tokenFile, '/')
    && !preg_match('#^[A-Za-z]:[\\\\/]#', $tokenFile)) {
    $tokenFile = birkly_project_root() . '/' . ltrim($tokenFile, '/\\');
}

$credentials = birkly_mcp_desktop_load_credentials($tokenFile);
if ($credentials === null) {
    fwrite(STDERR, "Missing credentials. Pair in Settings → AI first, then pass --token-file=…\n");
    exit(1);
}

$credPath = $tokenFile ?? '';
if ($credPath === '') {
    $userId = (string) ($credentials['user_id'] ?? '');
    $credPath = $userId !== '' ? birkly_mcp_desktop_credentials_path($userId) : '';
}

$config = birkly_mcp_desktop_client_config_snippet($credPath);
$result = birkly_mcp_desktop_merge_into_app_config($target, $config);
if (!($result['ok'] ?? false)) {
    fwrite(STDERR, (string) ($result['message'] ?? 'Install failed') . "\n");
    exit(1);
}

$meta = birkly_mcp_desktop_install_target_meta($target);
echo "Birkly: saved to " . ($result['path'] ?? '') . "\n";
echo "Fully quit {$meta['app_label']}, then open it again.\n";
echo "In {$meta['app_label']}, look for the “birkly” MCP server in settings.\n";
exit(0);
