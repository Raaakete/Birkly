<?php
/**
 * Minimal deploy fingerprint — no plugin bootstrap (safe health check for Dokploy).
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../core/birkly_paths.php';
require_once __DIR__ . '/../core/birkly_data_paths.php';
require_once __DIR__ . '/../core/hosting_urls.php';
require_once __DIR__ . '/../core/ops_starters_client.php';

$revision = birkly_deploy_revision();
$pluginsFile = birkly_project_root() . '/core/plugins.php';
$pluginsSnippet = is_readable($pluginsFile)
    ? (string) file_get_contents($pluginsFile, false, null, 0, 400)
    : '';

$projectRoot = birkly_project_root();
$hosting = birkly_hosting_load();
$installationId = null;
$installationFile = $projectRoot . '/settings/installation.json';
if (is_readable($installationFile)) {
    $installation = json_decode((string) file_get_contents($installationFile), true);
    if (is_array($installation)) {
        $installationId = $installation['installation_id'] ?? null;
    }
}
$dataRoot = getenv('BIRKLY_DATA') ?: '';
$dataRootResolved = $dataRoot !== '' ? (realpath($dataRoot) ?: $dataRoot) : null;
$opsStarters = birkly_ops_starters_diagnostics('marsk');
$opsStarters['install_probe'] = birkly_ops_starters_install_probe($projectRoot, 'marsk');

echo json_encode([
    'service' => 'birkly-cms',
    'build_revision' => $revision,
    'plugin_registry_globals' => str_contains($pluginsSnippet, 'GLOBALS[\'birkly_plugins\']'),
    'hosting' => [
        'cms_base_url' => $hosting['cms_base_url'] ?? '',
        'public_site_mode' => $hosting['public_site_mode'] ?? 'none',
        'public_site_url' => $hosting['public_site_url'] ?? '',
        'request_host' => birkly_cms_request_host(),
        'request_host_role' => birkly_request_host_role(),
        'env_overrides' => birkly_hosting_env_overrides(),
        'trust_request_host' => birkly_hosting_trust_request_host(),
    ],
    'volume' => [
        'installation_id' => $installationId,
        'data_root' => $dataRootResolved,
    ],
    'ops_starters' => $opsStarters,
    'time' => gmdate('c'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
