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
require_once '../core/cloud_account.php';

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
            'installation_owner_email' => birkly_marketplace_installation_owner_email(),
            'cloud_account_linked' => birkly_cloud_account_is_linked(),
        ],
    ]);
    exit;
}

if ($action === 'browse') {
    $query = trim((string) ($input['q'] ?? $_GET['q'] ?? ''));
    $category = trim((string) ($input['category'] ?? $_GET['category'] ?? ''));
    $offerType = trim((string) ($input['offer_type'] ?? $_GET['offer_type'] ?? ''));
    $sort = trim((string) ($input['sort'] ?? $_GET['sort'] ?? 'name'));

    $result = birkly_marketplace_browse($query, $category, $offerType, $sort);
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
            'installation_owner_email' => birkly_marketplace_installation_owner_email(),
            'fallback' => !empty($result['fallback']),
            'filter_options' => $result['filter_options'] ?? ['categories' => [], 'offer_types' => []],
            'total' => count($result['offers']),
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
            'provider' => $detail['provider'] ?? null,
            'fallback' => !empty($detail['fallback']),
            'install_state' => $detail['offer']['install_state'] ?? birkly_marketplace_offer_install_state($slug),
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

if ($action === 'permissions') {
    $slug = trim((string) ($input['slug'] ?? $_GET['slug'] ?? ''));
    $version = trim((string) ($input['version'] ?? $_GET['version'] ?? ''));
    if ($slug === '') {
        echo json_encode(['status' => 'error', 'message' => 'Offer slug required']);
        exit;
    }

    $result = birkly_marketplace_install_permissions($slug, $version);
    if (!$result['ok']) {
        echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Failed to load permissions']);
        exit;
    }

    echo json_encode([
        'status' => 'ok',
        'data' => [
            'slug' => $slug,
            'version' => $result['version'] ?? $version,
            'permissions_manifest' => $result['permissions_manifest'] ?? [],
            'consent_required' => !empty($result['consent_required']),
            'consent_record' => $result['consent_record'] ?? null,
        ],
    ]);
    exit;
}

if ($action === 'accept_consent') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_plugin_csrf($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }

    $slug = trim((string) ($input['slug'] ?? ''));
    $version = trim((string) ($input['version'] ?? ''));
    if ($slug === '' || $version === '') {
        echo json_encode(['status' => 'error', 'message' => 'Offer slug and version required']);
        exit;
    }

    $permInfo = birkly_marketplace_install_permissions($slug, $version);
    if (!$permInfo['ok']) {
        echo json_encode(['status' => 'error', 'message' => $permInfo['error'] ?? 'Failed to load permissions']);
        exit;
    }

    echo json_encode([
        'status' => 'ok',
        'message' => 'Permission consent acknowledged',
        'data' => [
            'slug' => $slug,
            'version' => $permInfo['version'] ?? $version,
            'permissions_manifest' => $permInfo['permissions_manifest'] ?? [],
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

    if ($slug === '') {
        echo json_encode(['status' => 'error', 'message' => 'Offer slug required']);
        exit;
    }

    $offerDetail = birkly_marketplace_offer_detail($slug);
    if ($offerDetail['ok']) {
        $offer = is_array($offerDetail['offer'] ?? null) ? $offerDetail['offer'] : [];
        if (!birkly_marketplace_offer_is_installable($offer)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'This plugin is coming soon and cannot be installed yet.',
                'code' => 'offer_coming_soon',
            ]);
            exit;
        }
    }

    $freeInstall = false;
    if ($licenseKey === '') {
        $freeClaim = birkly_marketplace_claim_free_license($slug);
        if (!$freeClaim['ok']) {
            echo json_encode([
                'status' => 'error',
                'message' => $freeClaim['error'] ?? 'License key required',
                'code' => $freeClaim['code'] ?? 'license_required',
            ]);
            exit;
        }
        $licenseKey = (string) ($freeClaim['license_key'] ?? '');
        $freeInstall = true;
    } else {
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

    $offerType = 'plugin';
    $detailForType = birkly_marketplace_offer_detail($slug);
    if ($detailForType['ok'] && is_array($detailForType['offer'] ?? null)) {
        $offerType = (string) ($detailForType['offer']['offer_type'] ?? 'plugin');
    }

    $consentAccepted = !empty($input['consent_accepted']) || !empty($input['consentAccepted']);
    if ($offerType === 'plugin') {
        $consentCheck = birkly_marketplace_require_install_consent($slug, $version, $consentAccepted);
        if (!$consentCheck['ok']) {
            $permPreview = birkly_marketplace_install_permissions($slug, $version);
            echo json_encode([
                'status' => 'error',
                'message' => $consentCheck['error'] ?? 'Permission consent required',
                'code' => $consentCheck['code'] ?? 'consent_required',
                'data' => [
                    'permissions_manifest' => $permPreview['permissions_manifest'] ?? [],
                    'version' => $permPreview['version'] ?? $version,
                ],
            ]);
            exit;
        }
    }

    $install = birkly_marketplace_install_offer($slug, $version, $licenseKey, $offerType, false, $consentAccepted);
    if (!$install['ok']) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Install failed: ' . implode('; ', $install['errors'] ?? []),
            'errors' => $install['errors'] ?? [],
            'preflight' => $install['preflight'] ?? null,
        ]);
        exit;
    }

    if (in_array($offerType, ['theme', 'template_pack'], true)) {
        log_security_event('marketplace_theme_installed', [
            'theme' => $install['theme_id'] ?? '',
            'slug' => $slug,
            'version' => $version,
            'offer_type' => $offerType,
            'free_install' => $freeInstall,
            'user' => $_SESSION['user']['email'] ?? 'unknown',
        ]);

        echo json_encode([
            'status' => 'ok',
            'message' => 'Theme installed successfully',
            'data' => [
                'theme_id' => $install['theme_id'] ?? '',
                'version' => $install['version'] ?? $version,
                'offer_type' => $offerType,
                'path' => $install['path'] ?? '',
            ],
        ]);
        exit;
    }

    log_security_event('marketplace_plugin_installed', [
        'plugin' => $install['plugin_id'] ?? '',
        'slug' => $slug,
        'version' => $version,
        'free_install' => $freeInstall,
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

if ($action === 'review_status') {
    $slug = trim((string) ($input['slug'] ?? $_GET['slug'] ?? ''));
    $pluginId = trim((string) ($input['plugin_id'] ?? $_GET['plugin_id'] ?? ''));
    if ($slug === '' && $pluginId !== '') {
        $plugins = get_all_plugins();
        $plugin = $plugins[$pluginId] ?? null;
        if (is_array($plugin)) {
            $slug = birkly_marketplace_plugin_offer_slug($pluginId, $plugin);
        }
    }
    if ($slug === '') {
        echo json_encode(['status' => 'error', 'message' => 'Offer slug or plugin_id required']);
        exit;
    }

    $licenseRef = null;
    if ($pluginId !== '') {
        $plugins = get_all_plugins();
        $licenseRef = trim((string) (($plugins[$pluginId] ?? [])['license_ref'] ?? ''));
    }

    $status = birkly_marketplace_review_status($slug, $licenseRef !== '' ? $licenseRef : null);
    if (!$status['ok']) {
        echo json_encode([
            'status' => 'error',
            'message' => $status['error'] ?? 'Review status unavailable',
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'ok',
        'data' => [
            'slug' => $slug,
            'can_review' => !empty($status['can_review']),
            'has_review' => !empty($status['has_review']),
            'installed' => !empty($status['installed']),
            'review' => $status['review'] ?? null,
        ],
    ]);
    exit;
}

if ($action === 'review_status_batch') {
    $plugins = get_all_plugins();
    $statuses = [];
    foreach ($plugins as $id => $plugin) {
        $slug = birkly_marketplace_plugin_offer_slug((string) $id, $plugin);
        if ($slug === '') {
            continue;
        }
        $licenseRef = trim((string) ($plugin['license_ref'] ?? ''));
        $status = birkly_marketplace_review_status($slug, $licenseRef !== '' ? $licenseRef : null);
        $statuses[$id] = [
            'slug' => $slug,
            'can_review' => !empty($status['can_review']),
            'has_review' => !empty($status['has_review']),
            'installed' => !empty($status['installed']),
            'review' => $status['review'] ?? null,
            'ok' => !empty($status['ok']),
        ];
    }

    echo json_encode(['status' => 'ok', 'data' => ['reviews' => $statuses]]);
    exit;
}

if ($action === 'submit_review') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_plugin_csrf($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }

    $slug = trim((string) ($input['slug'] ?? ''));
    $pluginId = trim((string) ($input['plugin_id'] ?? ''));
    $rating = (int) ($input['rating'] ?? 0);
    $body = trim((string) ($input['body'] ?? ''));

    if ($slug === '' && $pluginId !== '') {
        $plugins = get_all_plugins();
        $plugin = $plugins[$pluginId] ?? null;
        if (is_array($plugin)) {
            $slug = birkly_marketplace_plugin_offer_slug($pluginId, $plugin);
        }
    }

    if ($slug === '') {
        echo json_encode(['status' => 'error', 'message' => 'Offer slug or plugin_id required']);
        exit;
    }

    $licenseRef = null;
    if ($pluginId !== '') {
        $plugins = get_all_plugins();
        $licenseRef = trim((string) (($plugins[$pluginId] ?? [])['license_ref'] ?? ''));
    }

    $result = birkly_marketplace_submit_installation_review(
        $slug,
        $rating,
        $body,
        $licenseRef !== '' ? $licenseRef : null
    );
    if (!$result['ok']) {
        echo json_encode([
            'status' => 'error',
            'message' => $result['error'] ?? 'Review submission failed',
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'ok',
        'message' => 'Review submitted',
        'data' => ['review' => $result['review'] ?? []],
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
    $licenseRef = trim((string) ($plugin['license_ref'] ?? ''));
    $check = birkly_marketplace_check_plugin_update($slug, $current, $licenseRef !== '' ? $licenseRef : null);
    if (!empty($check['license_lapsed'])) {
        echo json_encode([
            'status' => 'error',
            'message' => $check['license_warning'] ?? 'Marketplace license is inactive. Renew to upgrade.',
            'code' => 'license_lapsed',
        ]);
        exit;
    }
    if (empty($check['update_available'])) {
        echo json_encode(['status' => 'error', 'message' => 'No update available']);
        exit;
    }

    $wasActive = !empty($plugin['active']);
    if ($wasActive) {
        set_plugin_state($pluginId, false);
    }

    $targetVersion = (string) ($check['latest_version'] ?? $current);
    $consentAccepted = !empty($input['consent_accepted']) || !empty($input['consentAccepted']);
    $consentCheck = birkly_marketplace_require_install_consent($slug, $targetVersion, $consentAccepted);
    if (!$consentCheck['ok']) {
        $permPreview = birkly_marketplace_install_permissions($slug, $targetVersion);
        echo json_encode([
            'status' => 'error',
            'message' => $consentCheck['error'] ?? 'Permission consent required',
            'code' => $consentCheck['code'] ?? 'consent_required',
            'data' => [
                'permissions_manifest' => $permPreview['permissions_manifest'] ?? [],
                'version' => $permPreview['version'] ?? $targetVersion,
            ],
        ]);
        exit;
    }

    $install = birkly_marketplace_install_plugin($slug, $targetVersion, '', true, $consentAccepted);
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
