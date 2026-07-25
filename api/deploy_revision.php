<?php
/**
 * Minimal deploy fingerprint — no plugin bootstrap (safe health check for Dokploy).
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../core/birkly_paths.php';
require_once __DIR__ . '/../core/birkly_data_paths.php';

$revision = birkly_deploy_revision();
$pluginsFile = birkly_project_root() . '/core/plugins.php';
$pluginsSnippet = is_readable($pluginsFile)
    ? (string) file_get_contents($pluginsFile, false, null, 0, 400)
    : '';

echo json_encode([
    'service' => 'birkly-cms',
    'build_revision' => $revision,
    'plugin_registry_globals' => str_contains($pluginsSnippet, 'GLOBALS[\'birkly_plugins\']'),
    'time' => gmdate('c'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
