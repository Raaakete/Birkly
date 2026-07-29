<?php
/**
 * Marketplace install consent helpers — local install lookup and manifest gating.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/marketplace.php';

$failures = 0;

function assert_true(bool $cond, string $label): void
{
    global $failures;
    if ($cond) {
        echo "OK  $label\n";
        return;
    }
    echo "FAIL $label\n";
    $failures++;
}

assert_true(
    !isset($GLOBALS['haru_plugins_loaded']),
    'loading marketplace client does not bootstrap plugins'
);

birkly_marketplace_consent_reset();

$emptyManifest = [
    'permissions' => [],
    'hooks' => [],
    'data_access' => [
        'entries' => 'none',
        'media' => 'none',
        'users' => 'none',
        'settings' => 'none',
    ],
];
assert_true(
    !birkly_marketplace_manifest_requires_consent($emptyManifest),
    'empty manifest does not require consent'
);

$privilegedManifest = [
    'permissions' => ['manage_content'],
    'hooks' => ['plugin_activated'],
    'data_access' => $emptyManifest['data_access'],
];
assert_true(
    birkly_marketplace_manifest_requires_consent($privilegedManifest),
    'manifest with permissions requires consent'
);

$settingsDir = sys_get_temp_dir() . '/birkly-mp-consent-' . bin2hex(random_bytes(4));
mkdir($settingsDir, 0755, true);
$GLOBALS['__birkly_marketplace_settings_dir'] = $settingsDir;
file_put_contents($settingsDir . '/plugins.json', json_encode([
    'automation' => [
        'active' => true,
        'marketplace_slug' => 'birkly-automation',
        'marketplace_version' => '1.0.0',
        'version' => '1.0.0',
    ],
], JSON_PRETTY_PRINT));

$local = birkly_marketplace_local_installed_offer('birkly-automation');
assert_true(
    is_array($local) && $local['plugin_id'] === 'automation' && $local['version'] === '1.0.0',
    'local installed offer resolves from plugin registry'
);
assert_true(
    birkly_marketplace_is_offer_version_installed('birkly-automation', '1.0.0'),
    'installed offer version matches locally'
);
assert_true(
    !birkly_marketplace_is_offer_version_installed('birkly-automation', '9.9.9'),
    'different offer version is not installed'
);

$marketplaceCalls = 0;
$GLOBALS['birkly_marketplace_mock_handler'] = static function () use (&$marketplaceCalls): array {
    $marketplaceCalls++;
    return ['ok' => false, 'status' => 500, 'data' => null, 'error' => 'should not be called'];
};

assert_true(
    !birkly_marketplace_has_install_consent('unknown-offer', '1.0.0'),
    'has_install_consent for unknown offer avoids marketplace calls'
);
assert_true($marketplaceCalls === 0, 'consent check for unknown offer made no marketplace requests');

unset($GLOBALS['birkly_marketplace_mock_handler']);

$require = birkly_marketplace_require_install_consent('unknown-offer', '1.0.0', false, $emptyManifest);
assert_true(!empty($require['ok']), 'install proceeds without consent when manifest is empty');

$requirePrivileged = birkly_marketplace_require_install_consent('unknown-offer', '1.0.0', false, $privilegedManifest);
assert_true(
    empty($requirePrivileged['ok']) && ($requirePrivileged['code'] ?? '') === 'consent_required',
    'install blocked when privileged manifest lacks consent'
);

$requireAccepted = birkly_marketplace_require_install_consent('unknown-offer', '1.0.0', true, $emptyManifest);
assert_true(!empty($requireAccepted['ok']), 'consent_accepted skips manifest reload');

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} test(s) failed.\n");
    exit(1);
}

echo "\nAll marketplace consent tests passed.\n";
