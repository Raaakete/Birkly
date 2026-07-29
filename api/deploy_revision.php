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

$action = trim((string) ($_GET['action'] ?? ''));

/**
 * @return array<string, mixed>
 */
function birkly_deploy_revision_plugin_diagnostics(string $projectRoot): array
{
    require_once $projectRoot . '/api/storage.php';
    $settingsDir = $projectRoot . '/settings';
    $pluginsDir = $projectRoot . '/plugins';
    $storage = new Storage($settingsDir);
    $states = $storage->read('plugins.json');
    if (!is_array($states)) {
        $states = [];
    }

    $rows = [];
    foreach ($states as $pluginId => $state) {
        if (!is_string($pluginId)) {
            continue;
        }
        $pluginFile = $pluginsDir . '/' . $pluginId . '/plugin.php';
        $syntaxOk = null;
        $syntaxDetail = null;
        if (is_file($pluginFile)) {
            $output = shell_exec('php -l ' . escapeshellarg($pluginFile) . ' 2>&1') ?? '';
            $syntaxOk = str_contains($output, 'No syntax errors detected');
            if (!$syntaxOk) {
                $syntaxDetail = trim($output);
            }
        }
        $rows[$pluginId] = [
            'active' => !empty($state['active']),
            'plugin_php_exists' => is_file($pluginFile),
            'syntax_ok' => $syntaxOk,
            'syntax_detail' => $syntaxDetail,
            'marketplace_slug' => is_array($state) ? ($state['marketplace_slug'] ?? null) : null,
        ];
    }

    if (is_dir($pluginsDir)) {
        foreach (scandir($pluginsDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || isset($rows[$entry])) {
                continue;
            }
            $path = $pluginsDir . '/' . $entry;
            if (!is_dir($path) || !is_file($path . '/plugin.php')) {
                continue;
            }
            $rows[$entry] = [
                'active' => false,
                'plugin_php_exists' => true,
                'syntax_ok' => null,
                'syntax_detail' => null,
                'marketplace_slug' => null,
                'untracked_directory' => true,
            ];
        }
    }

    return [
        'plugins_dir' => $pluginsDir,
        'settings_dir' => $settingsDir,
        'plugins' => $rows,
    ];
}

if ($action === 'diagnose_plugins') {
    echo json_encode([
        'status' => 'ok',
        'service' => 'birkly-cms',
        'build_revision' => $revision,
        'installation_id' => $installationId,
        'plugins' => birkly_deploy_revision_plugin_diagnostics($projectRoot),
        'time' => gmdate('c'),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'sync_bundled_plugin') {
    $requestedId = trim((string) ($_GET['installation_id'] ?? ''));
    if ($requestedId === '' || $installationId === null || !hash_equals((string) $installationId, $requestedId)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'installation_id mismatch'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pluginId = trim((string) ($_GET['plugin_id'] ?? 'automation'));
    if ($pluginId === '' || !preg_match('/^[a-z0-9_-]+$/i', $pluginId)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'invalid plugin_id'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $bundleDir = $projectRoot . '/bundled-plugins/' . $pluginId;
    $targetDir = $projectRoot . '/plugins/' . $pluginId;
    if (!is_dir($bundleDir)) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'bundled plugin not found', 'bundle_dir' => $bundleDir], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0775, true);
    }

    $copied = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($bundleDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $relative = $iterator->getSubPathName();
        $dest = $targetDir . '/' . $relative;
        if ($item->isDir()) {
            if (!is_dir($dest)) {
                mkdir($dest, 0775, true);
            }
            continue;
        }
        if (copy($item->getPathname(), $dest)) {
            $copied++;
        }
    }

    echo json_encode([
        'status' => 'ok',
        'message' => 'Bundled plugin synced',
        'plugin_id' => $pluginId,
        'files_copied' => $copied,
        'plugins' => birkly_deploy_revision_plugin_diagnostics($projectRoot),
        'time' => gmdate('c'),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'probe_plugin_load') {
    $pluginId = trim((string) ($_GET['plugin_id'] ?? 'automation'));
    if ($pluginId === '' || !preg_match('/^[a-z0-9_-]+$/i', $pluginId)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'invalid plugin_id'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $probeScript = $projectRoot . '/scripts/probe-plugin-load.php';
    $cmd = 'php ' . escapeshellarg($probeScript) . ' ' . escapeshellarg($pluginId) . ' 2>&1';
    $output = shell_exec($cmd) ?? '';
    $ok = str_contains($output, 'OK');

    echo json_encode([
        'status' => $ok ? 'ok' : 'error',
        'plugin_id' => $pluginId,
        'load_ok' => $ok,
        'detail' => trim($output) !== 'OK' ? trim($output) : null,
        'plugins' => birkly_deploy_revision_plugin_diagnostics($projectRoot),
        'time' => gmdate('c'),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'repair_plugins') {
    $requestedId = trim((string) ($_GET['installation_id'] ?? ''));
    if ($requestedId === '' || $installationId === null || !hash_equals((string) $installationId, $requestedId)) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'installation_id mismatch',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    require_once $projectRoot . '/api/storage.php';
    $storage = new Storage($projectRoot . '/settings');
    $states = $storage->read('plugins.json');
    if (!is_array($states)) {
        $states = [];
    }

    $mode = trim((string) ($_GET['mode'] ?? 'deactivate_broken'));
    $changed = [];

    if (is_dir($projectRoot . '/plugins')) {
        foreach (scandir($projectRoot . '/plugins') ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || !preg_match('/^[a-z0-9_-]+$/i', $entry)) {
                continue;
            }
            $pluginFile = $projectRoot . '/plugins/' . $entry . '/plugin.php';
            if (!is_file($pluginFile)) {
                continue;
            }
            if (!isset($states[$entry]) || !is_array($states[$entry])) {
                $states[$entry] = ['active' => false, 'settings' => []];
            }
        }
    }

    foreach ($states as $pluginId => $state) {
        if (!is_array($state) || !is_string($pluginId)) {
            continue;
        }
        $deactivate = false;
        if ($mode === 'deactivate_all') {
            $deactivate = !empty($state['active']);
        } else {
            if (empty($state['active'])) {
                continue;
            }
            $pluginFile = $projectRoot . '/plugins/' . $pluginId . '/plugin.php';
            if (!is_file($pluginFile)) {
                $deactivate = true;
            } else {
                $output = shell_exec('php -l ' . escapeshellarg($pluginFile) . ' 2>&1') ?? '';
                $deactivate = !str_contains($output, 'No syntax errors detected');
            }
        }
        if ($deactivate) {
            $states[$pluginId]['active'] = false;
            $changed[] = $pluginId;
        }
    }

    if ($changed !== []) {
        $storage->write('plugins.json', $states);
    }

    echo json_encode([
        'status' => 'ok',
        'message' => 'Plugin repair completed',
        'mode' => $mode,
        'deactivated' => $changed,
        'plugins' => birkly_deploy_revision_plugin_diagnostics($projectRoot),
        'time' => gmdate('c'),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

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
