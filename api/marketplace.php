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

$contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
$isMultipart = stripos($contentType, 'multipart/form-data') !== false;

$rawInput = [];
if (!$isMultipart) {
    $decoded = json_decode((string) file_get_contents('php://input'), true);
    if (is_array($decoded)) {
        $rawInput = $decoded;
    }
} else {
    $rawInput = $_POST;
}

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
$action = $input['action'] ?? $_GET['action'] ?? $_POST['action'] ?? '';

requireAuth('manage_plugins');

if ($action === 'config') {
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'marketplace_url' => birkly_marketplace_url(),
            'installation_id' => birkly_installation_id(),
            'installation_owner_email' => birkly_marketplace_installation_owner_email(),
            'cloud_account_linked' => birkly_cloud_account_is_linked(),
            'display_currency' => birkly_marketplace_display_currency(),
            'supported_currencies' => array_keys(birkly_marketplace_supported_display_currencies()),
        ],
    ]);
    exit;
}

if ($action === 'save_display_currency') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_plugin_csrf($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }

    $currency = trim((string) ($input['currency'] ?? ''));
    try {
        birkly_marketplace_sync_display_currency($currency);
    } catch (InvalidArgumentException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }

    echo json_encode([
        'status' => 'ok',
        'data' => [
            'display_currency' => birkly_marketplace_display_currency(),
        ],
    ]);
    exit;
}

if ($action === 'browse') {
    $query = trim((string) ($input['q'] ?? $_GET['q'] ?? ''));
    $category = trim((string) ($input['category'] ?? $_GET['category'] ?? ''));
    $offerType = trim((string) ($input['offer_type'] ?? $_GET['offer_type'] ?? ''));
    $sort = trim((string) ($input['sort'] ?? $_GET['sort'] ?? 'name'));
    $price = trim((string) ($input['price'] ?? $_GET['price'] ?? ''));

    $result = birkly_marketplace_browse($query, $category, $offerType, $sort, $price);
    if (!$result['ok']) {
        echo json_encode([
            'status' => 'error',
            'message' => birkly_marketplace_user_error_message($result['error'] ?? 'Failed to load marketplace catalog'),
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
            'display_currency' => birkly_marketplace_display_currency(),
            'supported_currencies' => array_keys(birkly_marketplace_supported_display_currencies()),
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
            'reviews' => $detail['reviews'] ?? [],
            'rating_summary' => $detail['rating_summary'] ?? ($detail['offer']['rating_summary'] ?? null),
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

    $activation = birkly_marketplace_activate_license($licenseKey, [
        'offer_slug' => trim((string) ($input['offer_slug'] ?? '')),
    ]);
    if (!$activation['ok']) {
        echo json_encode([
            'status' => 'error',
            'message' => $activation['error'] ?? 'License activation failed',
            'code' => $activation['code'] ?? 'activation_failed',
        ]);
        exit;
    }

    $offerSlug = (string) ($activation['offer_slug'] ?? '');
    $attachedPluginId = null;
    if ($offerSlug !== '') {
        $attachedPluginId = birkly_marketplace_attach_entitlement_to_installed_offer(
            $offerSlug,
            $licenseKey,
            (string) ($activation['entitlement_token'] ?? ''),
            is_array($activation['entitlement'] ?? null) ? $activation['entitlement'] : []
        );
    }

    $offerTitle = $offerSlug;
    $offerVersion = '';
    if ($offerSlug !== '') {
        $detail = birkly_marketplace_offer_detail($offerSlug);
        if ($detail['ok'] && is_array($detail['offer'] ?? null)) {
            $offerTitle = (string) ($detail['offer']['title'] ?? $offerSlug);
            $offerVersion = (string) ($detail['offer']['latest_version'] ?? '');
        }
        if ($offerVersion === '') {
            foreach ($detail['releases'] ?? [] as $release) {
                if (!is_array($release)) {
                    continue;
                }
                $ver = (string) ($release['version'] ?? '');
                if ($ver !== '' && ($offerVersion === '' || version_compare($ver, $offerVersion, '>'))) {
                    $offerVersion = $ver;
                }
            }
        }
        birkly_marketplace_remember_hub_license(
            $offerSlug,
            $licenseKey,
            (string) ($activation['entitlement_token'] ?? ''),
            is_array($activation['entitlement'] ?? null) ? $activation['entitlement'] : [],
            $offerTitle,
            $offerVersion,
            $attachedPluginId
        );
    }

    echo json_encode([
        'status' => 'ok',
        'message' => 'License activated',
        'data' => [
            'code' => $activation['code'] ?? 'activated',
            'offer_slug' => $offerSlug,
            'license' => $activation['license'] ?? [],
            'entitlement' => $activation['entitlement'] ?? [],
            'entitlement_token_stored' => (string) ($activation['entitlement_token'] ?? '') !== '',
            'attached_plugin_id' => $attachedPluginId,
            'installation_id' => birkly_installation_id(),
            'pending_install' => $attachedPluginId === null && $offerSlug !== '',
            'title' => $offerTitle,
            'version' => $offerVersion,
        ],
    ]);
    exit;
}

if ($action === 'checkout') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_plugin_csrf($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }

    $slug = trim((string) ($input['slug'] ?? $input['offer_slug'] ?? ''));
    if ($slug === '') {
        echo json_encode(['status' => 'error', 'message' => 'Offer slug required', 'code' => 'missing_slug']);
        exit;
    }

    $checkout = birkly_marketplace_start_checkout($slug, [
        'plan_id' => trim((string) ($input['plan_id'] ?? '')),
        'promo_code' => trim((string) ($input['promo_code'] ?? '')),
        'quantity' => trim((string) ($input['quantity'] ?? '')),
        'email' => trim((string) ($input['email'] ?? '')),
        'return_url' => trim((string) ($input['return_url'] ?? '')),
    ]);
    if (!$checkout['ok']) {
        echo json_encode([
            'status' => 'error',
            'message' => $checkout['error'] ?? 'Checkout failed',
            'code' => $checkout['code'] ?? 'checkout_failed',
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'ok',
        'message' => 'Checkout ready',
        'data' => [
            'order' => $checkout['order'] ?? [],
            'entitlement' => $checkout['entitlement'] ?? [],
            'psp' => $checkout['psp'] ?? [],
            'checkout_url' => $checkout['checkout_url'] ?? '',
            'installation_id' => birkly_installation_id(),
            'return_url' => birkly_marketplace_return_url($slug, trim((string) ($input['plan_id'] ?? ''))),
        ],
    ]);
    exit;
}

if ($action === 'deactivate_license') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_plugin_csrf($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }

    $licenseKey = trim((string) ($input['license_key'] ?? ''));
    $installationId = trim((string) ($input['installation_id'] ?? ''));
    $pluginId = trim((string) ($input['plugin_id'] ?? ''));
    if ($licenseKey === '') {
        echo json_encode(['status' => 'error', 'message' => 'License key required', 'code' => 'missing_license_key']);
        exit;
    }

    $deactivation = birkly_marketplace_deactivate_license($licenseKey, $installationId !== '' ? $installationId : null);
    if (!$deactivation['ok']) {
        echo json_encode([
            'status' => 'error',
            'message' => $deactivation['error'] ?? 'License deactivation failed',
            'code' => $deactivation['code'] ?? 'deactivation_failed',
        ]);
        exit;
    }

    $licenseRef = birkly_marketplace_license_ref($licenseKey);
    if ($pluginId === '' && $licenseRef !== '' && function_exists('get_all_plugins')) {
        foreach (get_all_plugins() as $id => $plugin) {
            if (!is_array($plugin)) {
                continue;
            }
            if (trim((string) ($plugin['license_ref'] ?? '')) === $licenseRef) {
                $pluginId = (string) $id;
                break;
            }
        }
    }
    if ($pluginId !== '') {
        birkly_marketplace_clear_plugin_entitlement($pluginId);
    }
    birkly_marketplace_forget_hub_license('', $licenseRef);

    echo json_encode([
        'status' => 'ok',
        'message' => 'License deactivated on this site',
        'data' => [
            'code' => $deactivation['code'] ?? 'deactivated',
            'plugin_id' => $pluginId !== '' ? $pluginId : null,
            'license_ref' => $licenseRef !== '' ? $licenseRef : null,
        ],
    ]);
    exit;
}

if ($action === 'list_hub_licenses') {
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'pending' => birkly_marketplace_pending_hub_licenses(),
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
    $entitlementToken = '';
    $entitlement = [];
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
        $entitlementToken = (string) ($freeClaim['entitlement_token'] ?? '');
        $entitlement = is_array($freeClaim['entitlement'] ?? null) ? $freeClaim['entitlement'] : [];
        $freeInstall = true;
    } else {
        $activation = birkly_marketplace_activate_license($licenseKey, ['offer_slug' => $slug]);
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
            echo json_encode([
                'status' => 'error',
                'message' => birkly_marketplace_error_message('wrong_offer', 'License does not match this offer'),
                'code' => 'wrong_offer',
            ]);
            exit;
        }
        $entitlementToken = (string) ($activation['entitlement_token'] ?? '');
        $entitlement = is_array($activation['entitlement'] ?? null) ? $activation['entitlement'] : [];
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

    $packOptions = $offerType === 'template_pack' ? birkly_marketplace_api_pack_options($input) : [];
    $packOptions['entitlement_token'] = $entitlementToken;
    $packOptions['entitlement'] = $entitlement;
    $install = birkly_marketplace_install_offer(
        $slug,
        $version,
        $licenseKey,
        $offerType,
        false,
        $consentAccepted,
        $packOptions
    );
    if (!$install['ok']) {
        $code = (string) ($install['code'] ?? '');
        echo json_encode([
            'status' => 'error',
            'message' => 'Install failed: ' . implode('; ', $install['errors'] ?? []),
            'code' => $code !== '' ? $code : 'install_failed',
            'errors' => $install['errors'] ?? [],
            'preflight' => $install['preflight'] ?? null,
            'data' => [
                'missing_required_plugins' => $install['missing_required_plugins'] ?? ($install['preflight']['missing_required_plugins'] ?? []),
                'warnings' => $install['warnings'] ?? ($install['preflight']['warnings'] ?? []),
                'consent' => $install['consent'] ?? null,
            ],
        ]);
        exit;
    }

    if ($offerType === 'template_pack') {
        log_security_event('marketplace_template_pack_installed', [
            'slug' => $slug,
            'version' => $version,
            'offer_type' => $offerType,
            'installed_collections' => $install['installed_collections'] ?? [],
            'overwrite_mode' => $install['overwrite_mode'] ?? null,
            'include_demo_entries' => !empty($install['include_demo_entries']),
            'free_install' => $freeInstall,
            'user' => $_SESSION['user']['email'] ?? 'unknown',
        ]);

        echo json_encode([
            'status' => 'ok',
            'message' => 'Site pack installed successfully',
            'data' => [
                'offer_type' => 'template_pack',
                'version' => $install['version'] ?? $version,
                'source' => $install['source'] ?? 'marketplace',
                'installed_collections' => $install['installed_collections'] ?? [],
                'project_site' => !empty($install['project_site']),
                'path' => $install['path'] ?? '',
                'overwrite_mode' => $install['overwrite_mode'] ?? 'merge',
                'include_demo_entries' => !empty($install['include_demo_entries']),
                'backup_path' => $install['backup_path'] ?? null,
                'warnings' => $install['warnings'] ?? [],
                'safe_update' => !empty($install['safe_update']),
                'skipped_files' => $install['skipped_files'] ?? [],
                'updated_files' => $install['updated_files'] ?? [],
            ],
        ]);
        exit;
    }

    if ($offerType === 'theme') {
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
            'message' => $check['license_warning'] ?? 'Marketplace license is inactive. Renew to update.',
            'code' => 'license_lapsed',
        ]);
        exit;
    }
    if (empty($check['update_available'])) {
        echo json_encode(['status' => 'error', 'message' => 'No update available']);
        exit;
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
                'slug' => $slug,
                'plugin_id' => $pluginId,
                'permissions_manifest' => $permPreview['permissions_manifest'] ?? [],
                'version' => $permPreview['version'] ?? $targetVersion,
            ],
        ]);
        exit;
    }

    $wasActive = !empty($plugin['active']);
    if ($wasActive) {
        set_plugin_state($pluginId, false);
    }

    $install = birkly_marketplace_install_plugin($slug, $targetVersion, '', true, $consentAccepted);
    if (!$install['ok']) {
        if ($wasActive) {
            set_plugin_state($pluginId, true);
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'Update failed: ' . implode('; ', $install['errors'] ?? []),
            'code' => $install['code'] ?? null,
            'errors' => $install['errors'] ?? [],
        ]);
        exit;
    }

    load_plugins();
    $validationErrors = validate_plugin($plugins[$pluginId]['path'] ?? (__DIR__ . '/../plugins/' . $pluginId));
    if ($validationErrors !== []) {
        if ($wasActive) {
            set_plugin_state($pluginId, true);
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'Post-update validation failed: ' . implode(', ', $validationErrors),
        ]);
        exit;
    }

    if ($wasActive) {
        set_plugin_state($pluginId, true);
    }

    log_security_event('marketplace_plugin_updated', [
        'plugin' => $pluginId,
        'slug' => $slug,
        'version' => $targetVersion,
        'user' => $_SESSION['user']['email'] ?? 'unknown',
    ]);

    echo json_encode([
        'status' => 'ok',
        'message' => 'Plugin updated successfully',
        'data' => [
            'plugin_id' => $pluginId,
            'version' => $targetVersion,
        ],
    ]);
    exit;
}

// P53-M3 — Manual ZIP upload (same engine as Hub template_pack)
if ($action === 'install_template_pack_zip' || $action === 'preflight_template_pack_zip') {
    $csrf = $input['_csrf'] ?? $_POST['_csrf'] ?? '';
    if (!validate_plugin_csrf($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }

    if (!isset($_FILES['pack_file']) && !isset($_FILES['file'])) {
        echo json_encode(['status' => 'error', 'message' => 'No pack ZIP uploaded', 'code' => 'missing_file']);
        exit;
    }

    $file = $_FILES['pack_file'] ?? $_FILES['file'];
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'Pack ZIP upload failed', 'code' => 'upload_failed']);
        exit;
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $origName = (string) ($file['name'] ?? 'pack.zip');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid uploaded file', 'code' => 'upload_failed']);
        exit;
    }

    if (!str_ends_with(strtolower($origName), '.zip')) {
        echo json_encode(['status' => 'error', 'message' => 'File must be a .zip site pack', 'code' => 'invalid_pack']);
        exit;
    }

    $stored = sys_get_temp_dir() . '/birkly-pack-upload-' . getmypid() . '-' . bin2hex(random_bytes(4)) . '.zip';
    if (!@move_uploaded_file($tmpName, $stored)) {
        // Fallback for tests / non-HTTP contexts
        if (!@copy($tmpName, $stored)) {
            echo json_encode(['status' => 'error', 'message' => 'Could not store uploaded ZIP', 'code' => 'upload_failed']);
            exit;
        }
    }

    $packOptions = birkly_marketplace_api_pack_options($input);
    $packOptions['source'] = 'upload';

    if ($action === 'preflight_template_pack_zip') {
        $preflight = birkly_marketplace_preflight_template_pack($stored, $packOptions);
        @unlink($stored);
        echo json_encode([
            'status' => !empty($preflight['ok']) || !empty($preflight['requires_plugin_confirmation']) ? 'ok' : 'error',
            'message' => !empty($preflight['ok']) || !empty($preflight['requires_plugin_confirmation'])
                ? 'Pack preflight complete'
                : implode('; ', $preflight['errors'] ?? ['Preflight failed']),
            'code' => $preflight['code'] ?? null,
            'data' => $preflight,
        ]);
        exit;
    }

    $install = birkly_marketplace_install_template_pack_from_zip($stored, $packOptions);
    @unlink($stored);

    if (empty($install['ok'])) {
        echo json_encode([
            'status' => 'error',
            'message' => implode('; ', $install['errors'] ?? ['Site pack install failed']),
            'code' => $install['code'] ?? 'install_failed',
            'errors' => $install['errors'] ?? [],
            'preflight' => $install['preflight'] ?? null,
            'data' => [
                'missing_required_plugins' => $install['missing_required_plugins'] ?? ($install['preflight']['missing_required_plugins'] ?? []),
                'warnings' => $install['warnings'] ?? ($install['preflight']['warnings'] ?? []),
                'consent' => $install['consent'] ?? null,
            ],
        ]);
        exit;
    }

    log_security_event('marketplace_template_pack_uploaded', [
        'pack_id' => $install['pack_id'] ?? '',
        'version' => $install['version'] ?? '',
        'overwrite_mode' => $install['overwrite_mode'] ?? null,
        'include_demo_entries' => !empty($install['include_demo_entries']),
        'user' => $_SESSION['user']['email'] ?? 'unknown',
    ]);

    echo json_encode([
        'status' => 'ok',
        'message' => 'Site pack installed from ZIP',
        'data' => [
            'offer_type' => 'template_pack',
            'pack_id' => $install['pack_id'] ?? '',
            'version' => $install['version'] ?? '',
            'source' => 'upload',
            'installed_collections' => $install['installed_collections'] ?? [],
            'project_site' => !empty($install['project_site']),
            'path' => $install['path'] ?? '',
            'overwrite_mode' => $install['overwrite_mode'] ?? 'merge',
            'include_demo_entries' => !empty($install['include_demo_entries']),
            'backup_path' => $install['backup_path'] ?? null,
            'warnings' => $install['warnings'] ?? [],
            'safe_update' => !empty($install['safe_update']),
            'skipped_files' => $install['skipped_files'] ?? [],
            'updated_files' => $install['updated_files'] ?? [],
        ],
    ]);
    exit;
}

// P53-M7/M8 — Installed pack status, per-file reset, replace-all (Hub Installed hooks)
if ($action === 'list_installed_template_packs') {
    $packs = birkly_marketplace_get_installed_template_packs();
    $rows = [];
    foreach ($packs as $slug => $row) {
        if (!is_array($row)) {
            continue;
        }
        $rows[] = [
            'slug' => $slug,
            'pack_id' => $row['pack_id'] ?? $slug,
            'version' => $row['version'] ?? '',
            'source' => $row['source'] ?? '',
            'installed_collections' => $row['installed_collections'] ?? [],
            'project_site' => !empty($row['project_site']),
            'file_hashes_count' => is_array($row['file_hashes'] ?? null) ? count($row['file_hashes']) : 0,
            'installed_at' => $row['installed_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
    echo json_encode(['status' => 'ok', 'data' => ['packs' => $rows]]);
    exit;
}

if ($action === 'template_pack_status') {
    $slug = trim((string) ($input['slug'] ?? $_GET['slug'] ?? ''));
    if ($slug === '') {
        echo json_encode(['status' => 'error', 'message' => 'slug is required', 'code' => 'missing_slug']);
        exit;
    }
    $status = birkly_marketplace_template_pack_update_status($slug);
    echo json_encode([
        'status' => !empty($status['ok']) ? 'ok' : 'error',
        'message' => !empty($status['ok']) ? 'Pack status' : implode('; ', $status['errors'] ?? ['Status failed']),
        'code' => $status['code'] ?? null,
        'data' => $status,
    ]);
    exit;
}

if ($action === 'reset_template_pack_file') {
    $csrf = $input['_csrf'] ?? $_POST['_csrf'] ?? '';
    if (!validate_plugin_csrf($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    $slug = trim((string) ($input['slug'] ?? ''));
    $path = trim((string) ($input['path'] ?? $input['file'] ?? ''));
    if ($slug === '' || $path === '') {
        echo json_encode(['status' => 'error', 'message' => 'slug and path are required', 'code' => 'missing_params']);
        exit;
    }
    $result = birkly_marketplace_reset_template_pack_file($slug, $path, [
        'version' => (string) ($input['version'] ?? ''),
        'zip_path' => (string) ($input['zip_path'] ?? ''),
    ]);
    if (empty($result['ok'])) {
        echo json_encode([
            'status' => 'error',
            'message' => implode('; ', $result['errors'] ?? ['Reset failed']),
            'code' => $result['code'] ?? 'reset_failed',
            'errors' => $result['errors'] ?? [],
        ]);
        exit;
    }
    log_security_event('marketplace_template_pack_file_reset', [
        'slug' => $slug,
        'path' => $path,
        'user' => $_SESSION['user']['email'] ?? 'unknown',
    ]);
    echo json_encode([
        'status' => 'ok',
        'message' => 'File reset to pack version',
        'data' => $result,
    ]);
    exit;
}

if ($action === 'replace_all_template_pack') {
    $csrf = $input['_csrf'] ?? $_POST['_csrf'] ?? '';
    if (!validate_plugin_csrf($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    $slug = trim((string) ($input['slug'] ?? ''));
    if ($slug === '') {
        echo json_encode(['status' => 'error', 'message' => 'slug is required', 'code' => 'missing_slug']);
        exit;
    }
    $packOptions = birkly_marketplace_api_pack_options($input);
    $result = birkly_marketplace_replace_all_template_pack($slug, array_merge($packOptions, [
        'version' => (string) ($input['version'] ?? ''),
        'zip_path' => (string) ($input['zip_path'] ?? ''),
        'confirm_replace_all' => !empty($packOptions['confirm_replace_all']) || !empty($input['confirm_replace_all']),
        'source' => 'replace_all',
    ]));
    if (empty($result['ok'])) {
        echo json_encode([
            'status' => 'error',
            'message' => implode('; ', $result['errors'] ?? ['Replace all failed']),
            'code' => $result['code'] ?? 'replace_all_failed',
            'errors' => $result['errors'] ?? [],
            'data' => [
                'backup_path' => $result['backup_path'] ?? null,
            ],
        ]);
        exit;
    }
    log_security_event('marketplace_template_pack_replace_all', [
        'slug' => $slug,
        'version' => $result['version'] ?? '',
        'backup_path' => $result['backup_path'] ?? null,
        'user' => $_SESSION['user']['email'] ?? 'unknown',
    ]);
    echo json_encode([
        'status' => 'ok',
        'message' => 'Site pack replaced from vendor copy (backup created when site was dirty)',
        'data' => [
            'offer_type' => 'template_pack',
            'pack_id' => $result['pack_id'] ?? $slug,
            'version' => $result['version'] ?? '',
            'replace_all' => true,
            'backup_path' => $result['backup_path'] ?? null,
            'installed_collections' => $result['installed_collections'] ?? [],
            'skipped_files' => $result['skipped_files'] ?? [],
        ],
    ]);
    exit;
}

echo json_encode([
    'status' => 'error',
    'message' => 'Invalid action',
]);
