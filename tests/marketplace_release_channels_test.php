<?php
/**
 * CMS marketplace release channels and pre-release acknowledgment (P84).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/api/storage.php';
require_once $root . '/core/marketplace.php';

$errors = [];
$settingsDir = sys_get_temp_dir() . '/birkly-mp-channels-' . bin2hex(random_bytes(4));
mkdir($settingsDir, 0777, true);
$GLOBALS['__birkly_marketplace_settings_dir'] = $settingsDir;

$releases = [
    ['version' => '1.0.0', 'stability' => 'stable'],
    ['version' => '1.1.0', 'stability' => 'beta'],
    ['version' => '1.2.0', 'stability' => 'alpha'],
    ['version' => '0.9.0'],
];

try {
    if (birkly_marketplace_update_channel() !== 'stable') {
        $errors[] = 'Default update channel should be stable';
    }

    birkly_marketplace_sync_update_channel('beta');
    birkly_marketplace_settings_reset();
    if (birkly_marketplace_update_channel() !== 'beta') {
        $errors[] = 'Saved update channel should persist';
    }

    if (birkly_marketplace_latest_release_version(birkly_marketplace_releases_for_channel($releases, 'stable')) !== '1.0.0') {
        $errors[] = 'Stable channel latest should be 1.0.0';
    }
    if (birkly_marketplace_latest_release_version(birkly_marketplace_releases_for_channel($releases, 'beta')) !== '1.1.0') {
        $errors[] = 'Beta channel latest should be 1.1.0';
    }
    if (birkly_marketplace_latest_release_version(birkly_marketplace_releases_for_channel($releases, 'alpha')) !== '1.2.0') {
        $errors[] = 'Alpha channel latest should be 1.2.0';
    }

    if (!birkly_marketplace_release_visible_for_channel(['version' => '0.9.0'], 'stable')) {
        $errors[] = 'Missing stability should default to stable';
    }

    $gate = birkly_marketplace_require_pre_release_ack('demo-plugin', '1.1.0', 'beta', false);
    if (!empty($gate['ok']) || ($gate['code'] ?? '') !== 'pre_release_ack_required') {
        $errors[] = 'Beta install should require pre_release_ack';
    }

    $gateStable = birkly_marketplace_require_pre_release_ack('demo-plugin', '1.0.0', 'stable', false);
    if (empty($gateStable['ok'])) {
        $errors[] = 'Stable release should not require pre_release_ack';
    }

    birkly_marketplace_record_pre_release_ack('demo-plugin', '1.1.0', 'beta', 'test@example.com');
    if (!birkly_marketplace_has_pre_release_ack('demo-plugin', '1.1.0')) {
        $errors[] = 'Pre-release ack should persist';
    }

    $gateAcked = birkly_marketplace_require_pre_release_ack('demo-plugin', '1.1.0', 'beta', false);
    if (empty($gateAcked['ok'])) {
        $errors[] = 'Stored pre_release_ack should satisfy gate';
    }

    if (birkly_marketplace_offer_is_installable(['slug' => 'soon', 'status' => 'coming_soon'])) {
        $errors[] = 'Coming soon offers must remain non-installable';
    }

    $betaOffer = ['distribution' => 'beta'];
    $stableRelease = ['version' => '2.0.0', 'stability' => 'stable'];
    if (birkly_marketplace_release_effective_stability($betaOffer, $stableRelease) !== 'beta') {
        $errors[] = 'Offer distribution beta should elevate stable release row';
    }
    if (birkly_marketplace_release_visible_for_channel($stableRelease, 'stable', $betaOffer)) {
        $errors[] = 'Beta-distributed offer should be hidden from stable site channel';
    }
    if (!birkly_marketplace_release_requires_pre_release_ack(
        birkly_marketplace_release_effective_stability($betaOffer, $stableRelease)
    )) {
        $errors[] = 'Effective beta stability should require pre_release_ack';
    }

    $alphaOffer = ['slug' => 'alpha-offer', 'distribution' => 'alpha'];
    $enriched = birkly_marketplace_enrich_offer_install_state($alphaOffer);
    if (($enriched['distribution'] ?? '') !== 'alpha') {
        $errors[] = 'Enriched offer should normalize distribution to alpha';
    }
} catch (Throwable $e) {
    $errors[] = 'Exception: ' . $e->getMessage();
} finally {
    unset(
        $GLOBALS['__birkly_marketplace_settings_dir'],
        $GLOBALS['__birkly_marketplace_settings'],
        $GLOBALS['__birkly_marketplace_pre_release_ack']
    );
    array_map('unlink', glob($settingsDir . '/*') ?: []);
    @rmdir($settingsDir);
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

echo "marketplace_release_channels_test: PASS\n";
exit(0);
