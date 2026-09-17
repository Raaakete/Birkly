<?php
/**
 * Deploy / install diagnostics for authenticated admins (P88.1).
 *
 * Logic lives here; public health stays on api/deploy_revision.php only.
 */
declare(strict_types=1);

require_once __DIR__ . '/birkly_data_paths.php';
require_once __DIR__ . '/hosting_urls.php';
require_once __DIR__ . '/ops_starters_client.php';
require_once __DIR__ . '/deploy_health.php';

function birkly_deploy_installation_id(): ?string
{
    $projectRoot = birkly_project_root();
    $installationFile = $projectRoot . '/settings/installation.json';
    if (!is_readable($installationFile)) {
        return null;
    }
    $installation = json_decode((string) file_get_contents($installationFile), true);
    if (!is_array($installation)) {
        return null;
    }
    $id = $installation['installation_id'] ?? null;

    return is_string($id) && $id !== '' ? $id : null;
}

/**
 * @return array{plugins: array<string, mixed>}
 */
function birkly_deploy_plugin_diagnostics(): array
{
    $projectRoot = birkly_project_root();
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
            $lint = birkly_php_lint_file($pluginFile);
            $syntaxOk = $lint['ok'];
            if (!$syntaxOk) {
                $syntaxDetail = $lint['detail'];
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

    return ['plugins' => $rows];
}

/**
 * @return array<string, mixed>
 */
function birkly_deploy_verbose_payload(): array
{
    $projectRoot = birkly_project_root();
    $hosting = birkly_hosting_load();
    $dataRoot = getenv('BIRKLY_DATA') ?: '';
    $dataRootResolved = $dataRoot !== '' ? (realpath($dataRoot) ?: $dataRoot) : null;
    $opsStarters = birkly_ops_starters_diagnostics('marsk');
    $opsStarters['install_probe'] = birkly_ops_starters_install_probe($projectRoot, 'marsk');

    $pluginsFile = $projectRoot . '/core/plugins.php';
    $pluginsSnippet = is_readable($pluginsFile)
        ? (string) file_get_contents($pluginsFile, false, null, 0, 400)
        : '';

    return [
        'status' => 'ok',
        'service' => 'birkly-cms',
        'build_revision' => birkly_deploy_revision(),
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
            'installation_id' => birkly_deploy_installation_id(),
            'data_root' => $dataRootResolved,
        ],
        'ops_starters' => $opsStarters,
        'time' => gmdate('c'),
    ];
}

/**
 * @return array{status: string, message?: string, plugin_id?: string, files_copied?: int, plugins?: array<string, mixed>}
 */
function birkly_deploy_sync_bundled_plugin(string $pluginId): array
{
    if ($pluginId === '' || !preg_match('/^[a-z0-9_-]+$/i', $pluginId)) {
        return ['status' => 'error', 'message' => 'invalid plugin_id'];
    }

    $projectRoot = birkly_project_root();
    $bundleDir = $projectRoot . '/bundled-plugins/' . $pluginId;
    $targetDir = $projectRoot . '/plugins/' . $pluginId;
    if (!is_dir($bundleDir)) {
        return ['status' => 'error', 'message' => 'bundled plugin not found'];
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

    return [
        'status' => 'ok',
        'message' => 'Bundled plugin synced',
        'plugin_id' => $pluginId,
        'files_copied' => $copied,
        'plugins' => birkly_deploy_plugin_diagnostics(),
        'time' => gmdate('c'),
    ];
}

/**
 * @return array<string, mixed>
 */
function birkly_deploy_repair_plugins(string $mode = 'deactivate_broken'): array
{
    $projectRoot = birkly_project_root();
    require_once $projectRoot . '/api/storage.php';
    $storage = new Storage($projectRoot . '/settings');
    $states = $storage->read('plugins.json');
    if (!is_array($states)) {
        $states = [];
    }

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
        } elseif (!empty($state['active'])) {
            $pluginFile = $projectRoot . '/plugins/' . $pluginId . '/plugin.php';
            if (!is_file($pluginFile)) {
                $deactivate = true;
            } else {
                $lint = birkly_php_lint_file($pluginFile);
                $deactivate = !$lint['ok'];
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

    return [
        'status' => 'ok',
        'message' => 'Plugin repair completed',
        'mode' => $mode,
        'deactivated' => $changed,
        'plugins' => birkly_deploy_plugin_diagnostics(),
        'time' => gmdate('c'),
    ];
}

/**
 * @return array<string, mixed>
 */
function birkly_deploy_plugin_dev_sync(): array
{
    $projectRoot = birkly_project_root();
    $script = $projectRoot . '/scripts/plugin-dev-git-sync.sh';
    if (!is_file($script)) {
        return ['status' => 'error', 'message' => 'plugin-dev-git-sync.sh missing'];
    }

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = @proc_open(['sh', $script], $descriptors, $pipes, $projectRoot);
    $output = '';
    if (is_resource($process)) {
        fclose($pipes[0]);
        $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    }

    $automationJson = $projectRoot . '/plugins/automation/plugin.json';
    $version = null;
    if (is_readable($automationJson)) {
        $decoded = json_decode((string) file_get_contents($automationJson), true);
        if (is_array($decoded)) {
            $version = $decoded['version'] ?? null;
        }
    }

    return [
        'status' => 'ok',
        'message' => 'plugin-dev-git-sync ran',
        'automation_version' => $version,
        'output_tail' => substr(trim($output), -1200),
        'plugins' => birkly_deploy_plugin_diagnostics(),
        'time' => gmdate('c'),
    ];
}

/**
 * @return array<string, mixed>
 */
function birkly_deploy_probe_plugin(string $pluginId): array
{
    $probe = birkly_probe_plugin_load($pluginId);
    if (!$probe['ok'] && ($probe['error'] ?? '') === 'invalid plugin_id') {
        return ['status' => 'error', 'message' => 'invalid plugin_id'];
    }

    return [
        'status' => $probe['ok'] ? 'ok' : 'error',
        'plugin_id' => $pluginId,
        'load_ok' => $probe['ok'],
        'detail' => $probe['error'],
        'plugins' => birkly_deploy_plugin_diagnostics(),
        'time' => gmdate('c'),
    ];
}
