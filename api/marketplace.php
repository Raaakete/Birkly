<?php
/**
 * Marketplace client API (P9-M6) — browse registry, license activate, install, update.
 */

if (!defined('HARU_API_REQUEST')) {
    define('HARU_API_REQUEST', true);
}

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    header('Content-Type: application/json');
}

require_once 'auth.php';
require_once 'validate.php';
require_once '../core/csrf.php';
require_once 'log.php';
require_once '../core/plugins.php';
require_once '../core/plugins/security.php';
require_once '../core/plugins/validation.php';
require_once '../core/marketplace.php';

$rawInput = json_decode(file_get_contents('php://input'), true);
$input = [];
if (is_array($rawInput)) {
    foreach ($rawInput as $key => $value) {
        if (is_string($value)) {
            $input[$key] = validate_plugin_input($value, 'string', ['max_length' => 10000]);
        } elseif (is_array($value)) {
            $input[$key] = validate_plugin_input($value, 'array');
        } else {
            $input[$key] = $value;
        }
    }
}
$input = array_merge($input, sanitize_input($input));
$action = $input['action'] ?? $_GET['action'] ?? '';

requireAuth('manage_plugins');

if ($action === 'config') {
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'marketplace_url' => birkly_marketplace_url(),
            'installation_id' => birkly_installation_id(),
        ],
    ]);
    exit;
}

if ($action === 'browse') {
    $query = trim((string) ($input['q'] ?? $_GET['q'] ?? ''));
    $category = trim((string) ($input['category'] ?? $_GET['category'] ?? ''));
    $offerType = trim((string) ($input['offer_type'] ?? $_GET['offer_type'] ?? 'plugin'));
    if ($offerType === '') {
        $offerType = 'plugin';
    }

    $result = birkly_marketplace_browse($query, $category, $offerType);
    if (!$result['ok']) {
        echo json_encode([
            'status' => 'error',
            'message' => $result['error'] ?? 'Failed to load marketplace catalog',
            'data' => ['offers' => []],
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'ok',
        'data' => [
            'offers' => $result['offers'],
            'marketplace_url' => birkly_marketplace_url(),
            'fallback' => !empty($result['fallback']),
        ],
    ]);
    exit;
}

if ($action === 'offer') {
    $slug = trim((string) ($input['slug'] ?? $_GET['slug'] ?? ''));
    if ($slug === '') {
        echo json_encode(['status' => 'error', 'message' => 'Offer slug required']);
        exit;
    }

    $detail = birkly_marketplace_offer_detail($slug);
    if (!$detail['ok']) {
        echo json_encode(['status' => 'error', 'message' => $detail['error'] ?? 'Offer not found']);
        exit;
    }

    echo json_encode([
        'status' => 'ok',
        'data' => [
            'offer' => $detail['offer'],
            'releases' => $detail['releases'],
        ],
    ]);
    exit;
}

if ($action === 'activate_license') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_plugin_csrf($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }

    $licenseKey = trim((string) ($input['license_key'] ?? ''));
    if ($licenseKey === '') {
        echo json_encode(['status' => 'error', 'message' => 'License key required']);
        exit;
    }

    $activation = birkly_marketplace_activate_license($licenseKey);
    if (!$activation['ok']) {
        echo json_encode([
            'status' => 'error',
            'message' => $activation['error'] ?? 'License activation failed',
            'code' => $activation['code'] ?? 'activation_failed',
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'ok',
        'message' => 'License activated',
        'data' => [
            'code' => $activation['code'] ?? 'activated',
            'offer_slug' => $activation['offer_slug'] ?? '',
            'license' => $activation['license'] ?? [],
            'installation_id' => birkly_installation_id(),
        ],
    ]);
    exit;
}

if ($action === 'install') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_plugin_csrf($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }

    $licenseKey = trim((string) ($input['license_key'] ?? ''));
    $slug = trim((string) ($input['slug'] ?? ''));
    $version = trim((string) ($input['version'] ?? ''));

    if ($licenseKey === '' || $slug === '') {
        echo json_encode(['status' => 'error', 'message' => 'License key and offer slug required']);
        exit;
    }

    $activation = birkly_marketplace_activate_license($licenseKey);
    if (!$activation['ok']) {
        echo json_encode([
            'status' => 'error',
            'message' => $activation['error'] ?? 'License activation failed',
            'code' => $activation['code'] ?? 'activation_failed',
        ]);
        exit;
    }

    $offerSlug = (string) ($activation['offer_slug'] ?? $slug);
    if ($offerSlug !== $slug) {
        echo json_encode(['status' => 'error', 'message' => 'License does not match this offer']);
        exit;
    }

    if ($version === '') {
        $detail = birkly_marketplace_offer_detail($slug);
        if (!$detail['ok']) {
            echo json_encode(['status' => 'error', 'message' => $detail['error'] ?? 'Offer not found']);
            exit;
        }
        $releases = $detail['releases'] ?? [];
        $latest = '0.0.0';
        foreach ($releases as $release) {
            if (!is_array($release)) {
                continue;
            }
            $ver = (string) ($release['version'] ?? '');
            if ($ver !== '' && version_compare($ver, $latest, '>')) {
                $latest = $ver;
            }
        }
        if ($latest === '0.0.0') {
            echo json_encode(['status' => 'error', 'message' => 'No releases available for this offer']);
            exit;
        }
        $version = $latest;
    }

    $install = birkly_marketplace_install_plugin($slug, $version, $licenseKey, false);
    if (!$install['ok']) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Install failed: ' . implode('; ', $install['errors'] ?? []),
            'errors' => $install['errors'] ?? [],
            'preflight' => $install['preflight'] ?? null,
        ]);
        exit;
    }

    log_security_event('marketplace_plugin_installed', [
        'plugin' => $install['plugin_id'] ?? '',
        'slug' => $slug,
        'version' => $version,
        'user' => $_SESSION['user']['email'] ?? 'unknown',
    ]);

    echo json_encode([
        'status' => 'ok',
        'message' => 'Plugin installed successfully',
        'data' => [
            'plugin_id' => $install['plugin_id'] ?? '',
            'version' => $install['version'] ?? $version,
            'capabilities' => $install['preflight']['capabilities'] ?? [],
        ],
    ]);
    exit;
}

if ($action === 'check_updates') {
    $updates = birkly_marketplace_installed_plugin_updates();
    $anyAvailable = false;
    foreach ($updates as $row) {
        if (!empty($row['update_available'])) {
            $anyAvailable = true;
            break;
        }
    }

    echo json_encode([
        'status' => 'ok',
        'data' => [
            'updates' => $updates,
            'update_count' => count(array_filter($updates, fn ($u) => !empty($u['update_available']))),
            'any_available' => $anyAvailable,
        ],
    ]);
    exit;
}

if ($action === 'upgrade') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_plugin_csrf($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }

    $pluginId = trim((string) ($input['plugin_id'] ?? ''));
    if ($pluginId === '') {
        echo json_encode(['status' => 'error', 'message' => 'Plugin ID required']);
        exit;
    }

    $plugins = get_all_plugins();
    if (!isset($plugins[$pluginId])) {
        echo json_encode(['status' => 'error', 'message' => 'Plugin not found']);
        exit;
    }

    $plugin = $plugins[$pluginId];
    $slug = (string) ($plugin['marketplace_slug'] ?? '');
    if ($slug === '') {
        echo json_encode(['status' => 'error', 'message' => 'Plugin was not installed from marketplace']);
        exit;
    }

    $current = (string) ($plugin['version'] ?? '0.0.0');
    $check = birkly_marketplace_check_plugin_update($slug, $current);
    if (empty($check['update_available'])) {
        echo json_encode(['status' => 'error', 'message' => 'No update available']);
        exit;
    }

    $wasActive = !empty($plugin['active']);
    if ($wasActive) {
        set_plugin_state($pluginId, false);
    }

    $targetVersion = (string) ($check['latest_version'] ?? $current);
    $install = birkly_marketplace_install_plugin($slug, $targetVersion, '', true);
    if (!$install['ok']) {
        if ($wasActive) {
            set_plugin_state($pluginId, true);
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'Upgrade failed: ' . implode('; ', $install['errors'] ?? []),
            'errors' => $install['errors'] ?? [],
        ]);
        exit;
    }

    load_plugins();
    $validationErrors = validate_plugin($plugins[$pluginId]['path'] ?? (__DIR__ . '/../plugins/' . $pluginId));
    if ($validationErrors !== []) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Post-upgrade validation failed: ' . implode(', ', $validationErrors),
        ]);
        exit;
    }

    if ($wasActive) {
        set_plugin_state($pluginId, true);
    }

    log_security_event('marketplace_plugin_upgraded', [
        'plugin' => $pluginId,
        'slug' => $slug,
        'version' => $targetVersion,
        'user' => $_SESSION['user']['email'] ?? 'unknown',
    ]);

    echo json_encode([
        'status' => 'ok',
        'message' => 'Plugin upgraded successfully',
        'data' => [
            'plugin_id' => $pluginId,
            'version' => $targetVersion,
        ],
    ]);
    exit;
}

echo json_encode([
    'status' => 'error',
    'message' => 'Invalid action',
]);
