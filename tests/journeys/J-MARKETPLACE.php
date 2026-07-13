<?php
/**
 * J-MARKETPLACE — Install commerce plugin from registry fixture ZIP.
 */

require_once __DIR__ . '/_helpers.php';

return [
    'id' => 'J-MARKETPLACE',
    'name' => 'Marketplace plugin install',
    'server' => 'isolated',
    'run' => function (array $ctx): void {
        $extractInfo = journey_build_release_extract();
        $extractDir = $extractInfo['dir'];
        $port = journey_allocate_port(90);
        $base = "http://127.0.0.1:$port";
        $pid = 0;
        $zipPath = null;
        $runnerScript = null;

        try {
            $pluginEntries = array_diff(scandir($extractDir . '/plugins') ?: [], ['.', '..', 'README.md', '.gitkeep']);
            journey_assert('plugins dir empty except readme', count($pluginEntries) === 0, implode(',', $pluginEntries));

            $zipPath = journey_make_plugin_zip('commerce');
            $checksum = hash_file('sha256', $zipPath);

            $runnerScript = sys_get_temp_dir() . '/birkly-journey-mp-install-' . getmypid() . '.php';
            $runnerCode = <<<'PHP'
<?php
$extractDir = $argv[1];
$zipPath = $argv[2];
$checksum = $argv[3];
chdir($extractDir);
$GLOBALS['birkly_marketplace_mock_handler'] = function (string $method, string $path, ?array $body, array $query) use ($zipPath, $checksum): array {
    if ($method === 'GET' && str_contains($path, '/api/v1/offers/birkly-commerce/releases/1.0.0')) {
        return [
            'ok' => true,
            'status' => 200,
            'data' => [
                'version' => '1.0.0',
                'download_url' => 'file://' . $zipPath,
                'checksum_sha256' => $checksum,
            ],
        ];
    }
    if ($method === 'GET' && str_contains($path, '/api/v1/offers/birkly-commerce')) {
        return [
            'ok' => true,
            'status' => 200,
            'data' => [
                'offer' => [
                    'slug' => 'birkly-commerce',
                    'title' => 'Commerce',
                    'pricing' => ['model' => 'free', 'amount_cents' => 0],
                ],
                'releases' => [['version' => '1.0.0']],
            ],
        ];
    }
    if ($method === 'POST' && $path === '/api/v1/checkout') {
        return [
            'ok' => true,
            'status' => 201,
            'data' => ['license' => ['license_key' => 'BIRKLY-JOURNEY-TEST-KEY']],
        ];
    }
    if ($method === 'POST' && $path === '/api/v1/licenses/activate') {
        return [
            'ok' => true,
            'status' => 200,
            'data' => ['ok' => true, 'offer_slug' => 'birkly-commerce'],
        ];
    }
    return ['ok' => false, 'status' => 404, 'data' => null, 'error' => 'not_found'];
};
require $extractDir . '/core/marketplace.php';
$claim = birkly_marketplace_claim_free_license('birkly-commerce');
$install = birkly_marketplace_install_offer('birkly-commerce', '1.0.0', (string) ($claim['license_key'] ?? ''), 'plugin', false);
echo json_encode(['claim' => $claim, 'install' => $install]);
PHP;
            file_put_contents($runnerScript, $runnerCode);

            $cmd = sprintf(
                'php %s %s %s %s',
                escapeshellarg($runnerScript),
                escapeshellarg($extractDir),
                escapeshellarg($zipPath),
                escapeshellarg($checksum)
            );
            $output = shell_exec($cmd);
            $result = json_decode((string) $output, true);
            if (!is_array($result)) {
                journey_fail('marketplace install subprocess failed: ' . (string) $output);
            }

            journey_assert('free license claim ok', !empty($result['claim']['ok']), json_encode($result['claim'] ?? []));
            journey_assert('marketplace install ok', !empty($result['install']['ok']), json_encode($result['install'] ?? []));
            $pluginId = (string) ($result['install']['plugin_id'] ?? 'commerce');
            journey_assert('commerce plugin files on disk', is_file($extractDir . '/plugins/' . $pluginId . '/plugin.php'));

            $pid = journey_start_cms_server($extractDir, $port);
            $cookieFile = journey_temp_cookie_file('marketplace-admin');
            journey_create_first_admin($base, $cookieFile, 'mp-journey-' . getmypid() . '@birkly.test', 'SecurePass123!');
            journey_onboarding_skip($base, $cookieFile);
            $pluginsPath = $extractDir . '/settings/plugins.json';
            if (!is_dir(dirname($pluginsPath))) {
                mkdir(dirname($pluginsPath), 0755, true);
            }
            file_put_contents($pluginsPath, json_encode([
                'commerce' => ['active' => true, 'settings' => []],
            ], JSON_PRETTY_PRINT) . "\n");

            $commerceAdmin = journey_http($base, '/admin/index.php?page=commerce', ['cookie_file' => $cookieFile]);
            journey_assert('commerce admin route HTTP 200', $commerceAdmin['code'] === 200, 'code ' . $commerceAdmin['code']);
            @unlink($cookieFile);
        } finally {
            if ($pid > 0) {
                journey_stop_cms_server($pid);
            }
            if ($zipPath && is_file($zipPath)) {
                @unlink($zipPath);
            }
            if ($runnerScript && is_file($runnerScript)) {
                @unlink($runnerScript);
            }
            if (isset($extractInfo['zip']) && is_file($extractInfo['zip'])) {
                @unlink($extractInfo['zip']);
            }
            if (isset($extractDir)) {
                journey_remove_tree($extractDir);
            }
        }
    },
];
