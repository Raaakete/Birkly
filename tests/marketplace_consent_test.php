<?php
/**
 * P35-M17 — Marketplace install permission consent storage and gating.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/marketplace.php';

test_reset_failures();
echo "Marketplace install consent tests\n\n";

$settingsDir = sys_get_temp_dir() . '/birkly-consent-test-' . bin2hex(random_bytes(4));
mkdir($settingsDir, 0755, true);
$GLOBALS['__birkly_marketplace_settings_dir'] = $settingsDir;
birkly_marketplace_consent_reset();

$manifest = [
    'permissions' => ['manage_commerce'],
    'hooks' => ['after_entry_save'],
    'data_access' => [
        'entries' => 'read_write',
        'media' => 'read',
        'users' => 'none',
        'settings' => 'none',
    ],
];

test_assert('no consent initially', !birkly_marketplace_has_install_consent('birkly-commerce', '1.0.0'));

$blocked = birkly_marketplace_require_install_consent('birkly-commerce', '1.0.0', false);
test_assert('install blocked without consent', empty($blocked['ok']) && ($blocked['code'] ?? '') === 'consent_required');

$allowed = birkly_marketplace_require_install_consent('birkly-commerce', '1.0.0', true);
test_assert('install allowed when consent flag set', !empty($allowed['ok']));

birkly_marketplace_record_install_consent('birkly-commerce', '1.0.0', $manifest, 'admin@test.local');
test_assert('consent recorded', birkly_marketplace_has_install_consent('birkly-commerce', '1.0.0'));

$stored = birkly_marketplace_load_consent();
test_assert(
    'stored manifest matches',
    ($stored['birkly-commerce']['1.0.0']['permissions_manifest']['permissions'][0] ?? '') === 'manage_commerce'
);

$normalized = birkly_marketplace_normalize_permissions_manifest([
    'permissions' => ['z', 'a'],
    'hooks' => ['plugin_activated'],
    'data_access' => ['entries' => 'read'],
]);
test_assert('permissions sorted', ($normalized['permissions'] ?? []) === ['a', 'z']);
test_assert('data_access defaults filled', ($normalized['data_access']['users'] ?? '') === 'none');

$GLOBALS['birkly_marketplace_mock_handler'] = function (string $method, string $path, ?array $body, array $query): array {
    if ($method === 'GET' && str_contains($path, '/api/v1/offers/birkly-hello-starter')) {
        return [
            'ok' => true,
            'status' => 200,
            'data' => [
                'offer' => ['slug' => 'birkly-hello-starter', 'offer_type' => 'plugin'],
                'releases' => [[
                    'version' => '1.0.0',
                    'permissions_manifest' => [
                        'permissions' => ['view_stats'],
                        'hooks' => ['plugin_activated'],
                        'data_access' => [
                            'entries' => 'read',
                            'media' => 'none',
                            'users' => 'none',
                            'settings' => 'none',
                        ],
                    ],
                ]],
            ],
        ];
    }
    return ['ok' => false, 'status' => 404, 'data' => null, 'error' => 'not_found'];
};

$permInfo = birkly_marketplace_install_permissions('birkly-hello-starter');
test_assert('permissions loaded from API release', !empty($permInfo['ok']));
test_assert(
    'release manifest returned',
    ($permInfo['permissions_manifest']['permissions'][0] ?? '') === 'view_stats'
);

unset($GLOBALS['birkly_marketplace_mock_handler'], $GLOBALS['__birkly_marketplace_settings_dir']);
birkly_marketplace_consent_reset();
@unlink($settingsDir . '/marketplace_consent.json');
@rmdir($settingsDir);

exit(test_finish('marketplace_consent_test'));
