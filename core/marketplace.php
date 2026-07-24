<?php
/**
 * Birkly marketplace client (P9-M6) — registry API, installation identity, license activate.
 */

const BIRKLY_MARKETPLACE_DEFAULT_URL = 'https://marketplace.birkly.cloud';

function birkly_marketplace_settings_dir(): string
{
    if (isset($GLOBALS['__birkly_marketplace_settings_dir'])) {
        return (string) $GLOBALS['__birkly_marketplace_settings_dir'];
    }

    return __DIR__ . '/../settings';
}

/**
 * @return array<string, mixed>
 */
function birkly_marketplace_settings(): array
{
    if (array_key_exists('__birkly_marketplace_settings', $GLOBALS)) {
        return $GLOBALS['__birkly_marketplace_settings'];
    }

    $path = birkly_marketplace_settings_dir() . '/marketplace.json';
    $stored = [];
    if (is_file($path)) {
        $decoded = json_decode((string) file_get_contents($path), true);
        if (is_array($decoded)) {
            $stored = $decoded;
        }
    }

    $envUrl = trim((string) (getenv('BIRKLY_MARKETPLACE_URL') ?: ''));
    $url = $envUrl !== '' ? $envUrl : trim((string) ($stored['marketplace_url'] ?? BIRKLY_MARKETPLACE_DEFAULT_URL));
    if ($url === '') {
        $url = BIRKLY_MARKETPLACE_DEFAULT_URL;
    }

    $GLOBALS['__birkly_marketplace_settings'] = [
        'marketplace_url' => rtrim($url, '/'),
        'installation_owner_email' => trim((string) ($stored['installation_owner_email'] ?? '')),
    ];
    return $GLOBALS['__birkly_marketplace_settings'];
}

function birkly_marketplace_installation_owner_email(): ?string
{
    $email = trim((string) (birkly_marketplace_settings()['installation_owner_email'] ?? ''));
    return $email !== '' ? $email : null;
}

function birkly_marketplace_sync_owner_email(?string $email): void
{
    require_once __DIR__ . '/../api/storage.php';
    $storage = new Storage(birkly_marketplace_settings_dir());
    $data = $storage->read('marketplace.json') ?? [];
    if (!is_array($data)) {
        $data = [];
    }

    if ($email === null || trim($email) === '') {
        unset($data['installation_owner_email']);
    } else {
        $data['installation_owner_email'] = trim($email);
    }

    $storage->write('marketplace.json', $data);
    birkly_marketplace_settings_reset();
}

function birkly_marketplace_settings_reset(): void
{
    unset($GLOBALS['__birkly_marketplace_settings']);
}

function birkly_marketplace_url(): string
{
    return (string) (birkly_marketplace_settings()['marketplace_url'] ?? BIRKLY_MARKETPLACE_DEFAULT_URL);
}

/**
 * Stable CMS installation identity for license activation (stored locally).
 */
function birkly_installation_id(): string
{
    if (array_key_exists('__birkly_installation_id', $GLOBALS)) {
        return (string) $GLOBALS['__birkly_installation_id'];
    }

    require_once __DIR__ . '/../api/storage.php';
    $storage = new Storage(birkly_marketplace_settings_dir());
    $data = $storage->read('installation.json') ?? [];
    $id = trim((string) ($data['installation_id'] ?? ''));

    if ($id === '') {
        $id = 'inst_' . bin2hex(random_bytes(16));
        $data['installation_id'] = $id;
        $data['created_at'] = gmdate('Y-m-d\TH:i:s\Z');
        $storage->write('installation.json', $data);
    }

    $GLOBALS['__birkly_installation_id'] = $id;
    return $id;
}

function birkly_installation_id_reset(): void
{
    unset($GLOBALS['__birkly_installation_id']);
}

function birkly_marketplace_license_ref(string $licenseKey): string
{
    $key = strtoupper(trim($licenseKey));
    if ($key === '') {
        return '';
    }
    $parts = explode('-', $key);
    return (string) end($parts);
}

/**
 * @return array{ok:bool,status:int,data:?array,error?:string}
 */
function birkly_marketplace_request(string $method, string $path, ?array $body = null, array $query = []): array
{
    if (isset($GLOBALS['birkly_marketplace_mock_handler']) && is_callable($GLOBALS['birkly_marketplace_mock_handler'])) {
        return ($GLOBALS['birkly_marketplace_mock_handler'])($method, $path, $body, $query);
    }

    $base = birkly_marketplace_url();
    $url = $base . '/' . ltrim($path, '/');
    if ($query !== []) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    $headers = [
        'Accept: application/json',
        'User-Agent: Birkly-CMS/' . (function_exists('get_birkly_version') ? get_birkly_version() : '1.0.0'),
    ];

    $opts = [
        'http' => [
            'method' => strtoupper($method),
            'timeout' => 20,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers),
        ],
    ];

    if ($body !== null) {
        $opts['http']['header'] .= "\r\nContent-Type: application/json";
        $opts['http']['content'] = json_encode($body);
    }

    $context = stream_context_create($opts);
    $raw = @file_get_contents($url, false, $context);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\d{3}/', (string) $http_response_header[0], $m)) {
        $status = (int) $m[0];
    }

    if ($raw === false) {
        return ['ok' => false, 'status' => $status ?: 0, 'data' => null, 'error' => 'Marketplace request failed'];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['ok' => false, 'status' => $status, 'data' => null, 'error' => 'Invalid JSON from marketplace'];
    }

    $ok = $status >= 200 && $status < 300;
    return ['ok' => $ok, 'status' => $status, 'data' => $data, 'error' => $ok ? null : (string) ($data['message'] ?? $data['error'] ?? 'Request failed')];
}

/**
 * @return array{ok:bool,offers:array,error?:string}
 */
function birkly_marketplace_browse(string $query = '', string $category = '', string $offerType = '', string $sort = 'name'): array
{
    $params = [];
    if ($offerType !== '' && $offerType !== 'all') {
        $params['offer_type'] = $offerType;
    }
    if ($query !== '') {
        $params['q'] = $query;
    }
    if ($category !== '') {
        $params['category'] = $category;
    }

    $response = birkly_marketplace_request('GET', '/api/v1/registry', null, $params);
    if (!$response['ok'] || !is_array($response['data'])) {
        $fallback = birkly_marketplace_fallback_catalog();
        $offers = is_array($fallback['offers'] ?? null) ? $fallback['offers'] : [];
        if ($offerType !== '' && $offerType !== 'all') {
            $offers = birkly_marketplace_filter_offers($offers, '', '', $offerType);
        }
        if ($query !== '' || $category !== '') {
            $offers = birkly_marketplace_filter_offers($offers, $query, $category, $offerType);
        }
        if ($offers !== []) {
            $offers = birkly_marketplace_sort_offers($offers, $sort);
            return [
                'ok' => true,
                'offers' => birkly_marketplace_enrich_offers_install_state($offers),
                'fallback' => true,
                'filter_options' => birkly_marketplace_catalog_filter_options($fallback['offers'] ?? []),
            ];
        }
        return ['ok' => false, 'offers' => [], 'error' => $response['error'] ?? 'Registry unavailable'];
    }

    $offers = is_array($response['data']['offers'] ?? null) ? $response['data']['offers'] : [];
    if ($query !== '' || $category !== '' || ($offerType !== '' && $offerType !== 'all')) {
        $offers = birkly_marketplace_filter_offers($offers, $query, $category, $offerType);
    }
    $offers = birkly_marketplace_sort_offers($offers, $sort);
    $filterOptions = is_array($response['data']['filter_options'] ?? null)
        ? $response['data']['filter_options']
        : birkly_marketplace_catalog_filter_options(is_array($response['data']['offers'] ?? null) ? $response['data']['offers'] : []);

    return ['ok' => true, 'offers' => birkly_marketplace_enrich_offers_install_state($offers), 'filter_options' => $filterOptions];
}

/**
 * Bundled catalog used when the remote registry is unreachable (alpha / offline).
 *
 * @return array{offers: array}
 */
function birkly_marketplace_fallback_catalog(): array
{
    static $catalog = null;
    if ($catalog !== null) {
        return $catalog;
    }

    $path = __DIR__ . '/../catalog/marketplace-registry-fallback.json';
    if (!is_file($path)) {
        $catalog = ['offers' => []];
        return $catalog;
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    $catalog = is_array($decoded) ? $decoded : ['offers' => []];
    return $catalog;
}

function birkly_marketplace_demo_catalog_slugs(): array
{
    return [
        'birkly-hello-starter',
        'birkly-seo-basics',
        'birkly-analytics-pro',
        'birkly-minimal-theme',
    ];
}

function birkly_marketplace_is_demo_catalog_slug(string $slug): bool
{
    return in_array($slug, birkly_marketplace_demo_catalog_slugs(), true);
}

function birkly_marketplace_filter_offers(array $offers, string $query, string $category, string $offerType): array
{
    $q = strtolower(trim($query));
    $filtered = [];
    foreach ($offers as $offer) {
        if (!is_array($offer)) {
            continue;
        }
        if (birkly_marketplace_is_demo_catalog_slug((string) ($offer['slug'] ?? ''))) {
            continue;
        }
        if ($offerType !== '' && $offerType !== 'all' && ($offer['offer_type'] ?? '') !== $offerType) {
            continue;
        }
        if ($category !== '' && ($offer['category'] ?? '') !== $category) {
            continue;
        }
        if ($q !== '') {
            $tags = is_array($offer['tags'] ?? null) ? $offer['tags'] : [];
            $haystack = strtolower(
                (string) ($offer['title'] ?? '') . ' '
                . (string) ($offer['summary'] ?? '') . ' '
                . implode(' ', array_map('strval', $tags))
            );
            if (!str_contains($haystack, $q)) {
                continue;
            }
        }
        $filtered[] = $offer;
    }
    return $filtered;
}

function birkly_marketplace_catalog_filter_options(array $offers): array
{
    $categories = [];
    $offerTypes = [];
    foreach ($offers as $offer) {
        if (!is_array($offer)) {
            continue;
        }
        $cat = (string) ($offer['category'] ?? '');
        if ($cat !== '') {
            $categories[$cat] = true;
        }
        $type = (string) ($offer['offer_type'] ?? '');
        if ($type !== '') {
            $offerTypes[$type] = true;
        }
    }
    $categoryKeys = array_keys($categories);
    $typeKeys = array_keys($offerTypes);
    sort($categoryKeys);
    sort($typeKeys);

    return [
        'categories' => $categoryKeys,
        'offer_types' => $typeKeys,
    ];
}

function birkly_marketplace_sort_offers(array $offers, string $sort): array
{
    $sort = strtolower(trim($sort));
    if ($sort === '' || $sort === 'featured') {
        $sort = 'name';
    }

    usort($offers, static function (array $a, array $b) use ($sort): int {
        switch ($sort) {
            case 'price_asc':
                return ((int) ($a['pricing']['amount_cents'] ?? 0)) <=> ((int) ($b['pricing']['amount_cents'] ?? 0));
            case 'price_desc':
                return ((int) ($b['pricing']['amount_cents'] ?? 0)) <=> ((int) ($a['pricing']['amount_cents'] ?? 0));
            case 'rating':
                $ra = (float) ($a['rating_summary']['average'] ?? 0);
                $rb = (float) ($b['rating_summary']['average'] ?? 0);
                return $rb <=> $ra;
            case 'downloads':
                return ((int) ($b['download_count'] ?? 0)) <=> ((int) ($a['download_count'] ?? 0));
            case 'name':
            default:
                return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        }
    });

    return $offers;
}

function birkly_marketplace_offer_type_label(string $offerType): string
{
    return match ($offerType) {
        'theme' => 'Theme',
        'template_pack' => 'Template pack',
        default => 'Plugin',
    };
}

function birkly_marketplace_offer_is_free(array $offer): bool
{
    $pricing = is_array($offer['pricing'] ?? null) ? $offer['pricing'] : [];
    $model = (string) ($pricing['model'] ?? '');
    if ($model === 'free') {
        return true;
    }

    return (int) ($pricing['amount_cents'] ?? -1) === 0;
}

function birkly_marketplace_offer_requires_license(array $offer): bool
{
    return !birkly_marketplace_offer_is_free($offer);
}

function birkly_marketplace_purchase_url(string $slug): string
{
    return birkly_marketplace_url() . '/buy/' . rawurlencode($slug);
}

/**
 * @return array{ok:bool,active:bool,status?:string,pricing_model?:string,code?:string,error?:string,license_lapsed?:bool}
 */
function birkly_marketplace_verify_license_status(string $slug, string $licenseRef, ?string $installationId = null): array
{
    $licenseRef = strtoupper(trim($licenseRef));
    if ($licenseRef === '' || $slug === '') {
        return ['ok' => false, 'active' => false, 'error' => 'License reference required', 'code' => 'missing_license_ref'];
    }

    $installId = $installationId ?? birkly_installation_id();
    $response = birkly_marketplace_request('GET', '/api/v1/licenses/status', null, [
        'license_ref' => $licenseRef,
        'offer_slug' => $slug,
        'installation_id' => $installId,
    ]);

    if (!$response['ok'] || !is_array($response['data'])) {
        return [
            'ok' => false,
            'active' => false,
            'error' => $response['error'] ?? 'License status check failed',
            'code' => is_array($response['data']) ? (string) ($response['data']['error'] ?? 'status_failed') : 'status_failed',
            'license_lapsed' => true,
        ];
    }

    $data = $response['data'];
    if (empty($data['ok'])) {
        return [
            'ok' => false,
            'active' => false,
            'error' => (string) ($data['message'] ?? 'License inactive'),
            'code' => (string) ($data['error'] ?? 'license_inactive'),
            'license_lapsed' => true,
        ];
    }

    $status = (string) ($data['status'] ?? 'active');
    return [
        'ok' => true,
        'active' => $status === 'active',
        'status' => $status,
        'pricing_model' => (string) ($data['pricing_model'] ?? 'one_time'),
        'license_lapsed' => $status !== 'active',
    ];
}

/**
 * Official plugin id → marketplace offer slug (from cms-release-manifest.json).
 *
 * @return array<string, string>
 */
function birkly_official_marketplace_slugs(): array
{
    static $map = null;
    if (is_array($map)) {
        return $map;
    }

    $map = [];
    $path = __DIR__ . '/../scripts/cms-release-manifest.json';
    if (is_file($path)) {
        $decoded = json_decode((string) file_get_contents($path), true);
        if (is_array($decoded['official_marketplace_slugs'] ?? null)) {
            foreach ($decoded['official_marketplace_slugs'] as $pluginId => $slug) {
                if (is_string($pluginId) && is_string($slug) && $slug !== '') {
                    $map[$pluginId] = $slug;
                }
            }
        }
    }

    return $map;
}

/**
 * Resolve marketplace offer slug for an installed plugin (stored slug or official map).
 */
function birkly_marketplace_plugin_offer_slug(string $pluginId, array $plugin): string
{
    $slug = trim((string) ($plugin['marketplace_slug'] ?? ''));
    if ($slug !== '') {
        return $slug;
    }

    return (string) (birkly_official_marketplace_slugs()[$pluginId] ?? '');
}

/**
 * Map marketplace offer slug → installed plugin state.
 *
 * @return array<string, array{installed:bool,plugin_id:?string,installed_version:?string,latest_version:string,update_available:bool}>
 */
function birkly_marketplace_installed_by_slug(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $cache = [];
    foreach (birkly_marketplace_installed_plugin_updates() as $id => $row) {
        $slug = (string) ($row['marketplace_slug'] ?? '');
        if ($slug === '') {
            continue;
        }
        $cache[$slug] = [
            'installed' => true,
            'plugin_id' => (string) ($row['plugin_id'] ?? $id),
            'installed_version' => (string) ($row['current_version'] ?? ''),
            'latest_version' => (string) ($row['latest_version'] ?? ''),
            'update_available' => !empty($row['update_available']),
        ];
    }

    return $cache;
}

/**
 * @return array{installed:bool,plugin_id:?string,installed_version:?string,latest_version:string,update_available:bool}
 */
function birkly_marketplace_offer_install_state(string $slug, ?string $catalogLatestVersion = null): array
{
    $bySlug = birkly_marketplace_installed_by_slug();
    if ($slug !== '' && isset($bySlug[$slug])) {
        return $bySlug[$slug];
    }

    return [
        'installed' => false,
        'plugin_id' => null,
        'installed_version' => null,
        'latest_version' => (string) ($catalogLatestVersion ?? ''),
        'update_available' => false,
    ];
}

function birkly_marketplace_enrich_offer_install_state(array $offer): array
{
    $slug = (string) ($offer['slug'] ?? '');
    $offer['install_state'] = birkly_marketplace_offer_install_state(
        $slug,
        (string) ($offer['latest_version'] ?? '')
    );

    return $offer;
}

/**
 * @param array<int, array<string, mixed>> $offers
 * @return array<int, array<string, mixed>>
 */
function birkly_marketplace_enrich_offers_install_state(array $offers): array
{
    $enriched = [];
    foreach ($offers as $offer) {
        if (!is_array($offer)) {
            continue;
        }
        $enriched[] = birkly_marketplace_enrich_offer_install_state($offer);
    }

    return $enriched;
}

/**
 * @return array<string, mixed>|null
 */
function birkly_marketplace_find_fallback_offer(string $slug): ?array
{
    $catalog = birkly_marketplace_fallback_catalog();
    foreach ($catalog['offers'] ?? [] as $offer) {
        if (is_array($offer) && ($offer['slug'] ?? '') === $slug) {
            return $offer;
        }
    }

    return null;
}

/**
 * @return array{ok:bool,offer?:array,releases?:array,fallback?:bool,error?:string}
 */
function birkly_marketplace_offer_detail(string $slug): array
{
    $response = birkly_marketplace_request('GET', '/api/v1/offers/' . rawurlencode($slug));
    if ($response['ok'] && is_array($response['data'])) {
        $offer = is_array($response['data']['offer'] ?? null) ? $response['data']['offer'] : [];
        return [
            'ok' => true,
            'offer' => birkly_marketplace_enrich_offer_install_state($offer),
            'releases' => is_array($response['data']['releases'] ?? null) ? $response['data']['releases'] : [],
            'fallback' => false,
        ];
    }

    $offer = birkly_marketplace_find_fallback_offer($slug);
    if ($offer !== null) {
        $releases = [];
        $latest = (string) ($offer['latest_version'] ?? '');
        if ($latest !== '') {
            $releases[] = ['version' => $latest];
        }

        return [
            'ok' => true,
            'offer' => birkly_marketplace_enrich_offer_install_state($offer),
            'releases' => $releases,
            'fallback' => true,
        ];
    }

    return ['ok' => false, 'error' => $response['error'] ?? 'Offer not found'];
}

function birkly_marketplace_local_free_license_key(string $slug): string
{
    return 'BIRKLY-LOCAL-FREE-' . strtoupper(substr(hash('sha256', $slug), 0, 16));
}

/**
 * Auto-issue or skip license activation for free marketplace offers.
 *
 * @return array{ok:bool,license_key?:string,offer_slug?:string,source?:string,code?:string,error?:string}
 */
function birkly_marketplace_claim_free_license(string $slug): array
{
    $slug = trim($slug);
    if ($slug === '') {
        return ['ok' => false, 'error' => 'Offer slug required', 'code' => 'missing_slug'];
    }

    $detail = birkly_marketplace_offer_detail($slug);
    if (!$detail['ok']) {
        return ['ok' => false, 'error' => $detail['error'] ?? 'Offer not found', 'code' => 'offer_not_found'];
    }

    $offer = is_array($detail['offer'] ?? null) ? $detail['offer'] : [];
    if (!birkly_marketplace_offer_is_free($offer)) {
        return ['ok' => false, 'error' => 'This offer requires a license key', 'code' => 'license_required'];
    }

    if (empty($detail['fallback'])) {
        $installId = preg_replace('/[^a-zA-Z0-9._-]/', '', birkly_installation_id()) ?: 'install';
        $email = $installId . '@install.birkly.local';
        $checkout = birkly_marketplace_request('POST', '/api/v1/checkout', [
            'offer_slug' => $slug,
            'email' => $email,
        ]);

        if ($checkout['ok'] && is_array($checkout['data'])) {
            $licenseKey = (string) (
                $checkout['data']['license']['license_key']
                ?? $checkout['data']['order']['license_key']
                ?? ''
            );
            if ($licenseKey !== '') {
                $activation = birkly_marketplace_activate_license($licenseKey);
                if ($activation['ok']) {
                    return [
                        'ok' => true,
                        'license_key' => $licenseKey,
                        'offer_slug' => (string) ($activation['offer_slug'] ?? $slug),
                        'source' => 'marketplace',
                    ];
                }
            }
        }
    }

    return [
        'ok' => true,
        'license_key' => birkly_marketplace_local_free_license_key($slug),
        'offer_slug' => $slug,
        'source' => 'local',
    ];
}

/**
 * @return array{ok:bool,code?:string,offer_slug?:string,license?:array,error?:string}
 */
function birkly_marketplace_activate_license(string $licenseKey): array
{
    $licenseKey = trim($licenseKey);
    if ($licenseKey === '') {
        return ['ok' => false, 'error' => 'License key is required', 'code' => 'missing_license_key'];
    }

    $response = birkly_marketplace_request('POST', '/api/v1/licenses/activate', [
        'license_key' => $licenseKey,
        'installation_id' => birkly_installation_id(),
    ]);

    if (!$response['ok'] || !is_array($response['data'])) {
        $code = is_array($response['data']) ? (string) ($response['data']['error'] ?? '') : '';
        return [
            'ok' => false,
            'error' => $response['error'] ?? 'License activation failed',
            'code' => $code !== '' ? $code : 'activation_failed',
        ];
    }

    $data = $response['data'];
    if (empty($data['ok'])) {
        return [
            'ok' => false,
            'error' => (string) ($data['message'] ?? 'License activation failed'),
            'code' => (string) ($data['error'] ?? 'activation_failed'),
        ];
    }

    return [
        'ok' => true,
        'code' => (string) ($data['code'] ?? 'activated'),
        'offer_slug' => (string) ($data['offer_slug'] ?? ''),
        'license' => is_array($data['license'] ?? null) ? $data['license'] : [],
    ];
}

/**
 * @return array{ok:bool,version?:string,download_url?:string,checksum_sha256?:string,error?:string}
 */
function birkly_marketplace_release_download(string $slug, string $version): array
{
    $path = '/api/v1/offers/' . rawurlencode($slug) . '/releases/' . rawurlencode($version);
    $response = birkly_marketplace_request('GET', $path);
    if (!$response['ok'] || !is_array($response['data'])) {
        return ['ok' => false, 'error' => $response['error'] ?? 'Release not found'];
    }

    return [
        'ok' => true,
        'version' => (string) ($response['data']['version'] ?? $version),
        'download_url' => (string) ($response['data']['download_url'] ?? ''),
        'checksum_sha256' => (string) ($response['data']['checksum_sha256'] ?? ''),
    ];
}

/**
 * @return array{ok:bool,latest_version?:string,update_available:bool,current_version:string,error?:string}
 */
function birkly_marketplace_check_plugin_update(string $slug, string $currentVersion, ?string $licenseRef = null): array
{
    $detail = birkly_marketplace_offer_detail($slug);
    if (!$detail['ok']) {
        return [
            'ok' => false,
            'update_available' => false,
            'current_version' => $currentVersion,
            'error' => $detail['error'] ?? 'Update check failed',
        ];
    }

    $releases = $detail['releases'] ?? [];
    $latest = $currentVersion;
    foreach ($releases as $release) {
        if (!is_array($release)) {
            continue;
        }
        $ver = (string) ($release['version'] ?? '');
        if ($ver !== '' && version_compare($ver, $latest, '>')) {
            $latest = $ver;
        }
    }

    $result = [
        'ok' => true,
        'latest_version' => $latest,
        'update_available' => version_compare($latest, $currentVersion, '>'),
        'current_version' => $currentVersion,
    ];

    $licenseRef = trim((string) ($licenseRef ?? ''));
    if ($licenseRef !== '') {
        $licenseStatus = birkly_marketplace_verify_license_status($slug, $licenseRef);
        if (!$licenseStatus['active']) {
            $result['license_lapsed'] = true;
            $result['license_warning'] = 'Marketplace license is inactive. Renew your subscription to receive updates.';
            $result['update_available'] = false;
        }
    }

    return $result;
}

/**
 * Capability preview from plugin.json inside a zip (before install).
 *
 * @return array{ok:bool,capabilities:array,plugin_json?:array,errors:array}
 */
function birkly_marketplace_preview_zip_capabilities(string $zipPath): array
{
    $errors = [];
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'capabilities' => [], 'errors' => ['ZipArchive extension not available']];
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return ['ok' => false, 'capabilities' => [], 'errors' => ['Cannot open zip archive']];
    }

    $pluginJson = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (is_string($name) && basename($name) === 'plugin.json') {
            $content = $zip->getFromIndex($i);
            if (is_string($content) && $content !== '') {
                $pluginJson = json_decode($content, true);
            }
            break;
        }
    }
    $zip->close();

    if (!is_array($pluginJson)) {
        return ['ok' => false, 'capabilities' => [], 'errors' => ['plugin.json missing or invalid']];
    }

    $capabilities = is_array($pluginJson['capabilities'] ?? null) ? $pluginJson['capabilities'] : [];
    return ['ok' => true, 'capabilities' => $capabilities, 'plugin_json' => $pluginJson, 'errors' => $errors];
}

function birkly_marketplace_installation_offer_api_path(string $slug, string $suffix): string
{
    return '/api/v1/installations/' . rawurlencode(birkly_installation_id())
        . '/offers/' . rawurlencode(trim($slug)) . '/' . $suffix;
}

/**
 * Tell the marketplace this CMS installation has installed an offer (enables reviews).
 *
 * @return array{ok:bool,data?:array,error?:string}
 */
function birkly_marketplace_attest_installation_offer(string $slug, ?string $licenseRef = null): array
{
    $slug = trim($slug);
    if ($slug === '') {
        return ['ok' => false, 'error' => 'Offer slug required'];
    }

    $body = null;
    $licenseRef = $licenseRef !== null ? trim($licenseRef) : '';
    if ($licenseRef !== '') {
        $body = ['license_ref' => $licenseRef];
    }

    $response = birkly_marketplace_request(
        'POST',
        birkly_marketplace_installation_offer_api_path($slug, 'install'),
        $body
    );
    if (!$response['ok']) {
        return ['ok' => false, 'error' => $response['error'] ?? 'Install attestation failed'];
    }

    return ['ok' => true, 'data' => is_array($response['data']) ? $response['data'] : []];
}

/**
 * @return array{ok:bool,can_review?:bool,has_review?:bool,installed?:bool,review?:array,error?:string}
 */
function birkly_marketplace_review_status(string $slug, ?string $licenseRef = null): array
{
    $slug = trim($slug);
    if ($slug === '') {
        return ['ok' => false, 'error' => 'Offer slug required'];
    }

    $query = [];
    $licenseRef = $licenseRef !== null ? trim($licenseRef) : '';
    if ($licenseRef !== '') {
        $query['license_ref'] = $licenseRef;
    }

    $response = birkly_marketplace_request(
        'GET',
        birkly_marketplace_installation_offer_api_path($slug, 'review'),
        null,
        $query
    );
    if (!$response['ok'] || !is_array($response['data'])) {
        return ['ok' => false, 'error' => $response['error'] ?? 'Review status unavailable'];
    }

    $status = is_array($response['data']['status'] ?? null) ? $response['data']['status'] : [];

    return [
        'ok' => true,
        'can_review' => !empty($status['can_review']),
        'has_review' => !empty($status['has_review']),
        'installed' => !empty($status['installed']),
        'review' => is_array($status['review'] ?? null) ? $status['review'] : null,
    ];
}

/**
 * @return array{ok:bool,review?:array,error?:string,message?:string}
 */
function birkly_marketplace_submit_installation_review(string $slug, int $rating, string $body, ?string $licenseRef = null): array
{
    $slug = trim($slug);
    $body = trim($body);
    if ($slug === '') {
        return ['ok' => false, 'error' => 'Offer slug required'];
    }
    if ($body === '') {
        return ['ok' => false, 'error' => 'Please write a review'];
    }
    if ($rating < 1 || $rating > 5) {
        return ['ok' => false, 'error' => 'Rating must be between 1 and 5 stars'];
    }

    $payload = [
        'rating' => $rating,
        'body' => $body,
    ];
    $licenseRef = $licenseRef !== null ? trim($licenseRef) : '';
    if ($licenseRef !== '') {
        $payload['license_ref'] = $licenseRef;
    }

    $response = birkly_marketplace_request(
        'POST',
        birkly_marketplace_installation_offer_api_path($slug, 'review'),
        $payload
    );
    if (!$response['ok'] || !is_array($response['data'])) {
        $message = is_array($response['data']) ? (string) ($response['data']['message'] ?? '') : '';
        return [
            'ok' => false,
            'error' => $message !== '' ? $message : ($response['error'] ?? 'Review submission failed'),
            'message' => $message,
        ];
    }

    return [
        'ok' => true,
        'review' => is_array($response['data']['review'] ?? null) ? $response['data']['review'] : [],
    ];
}

require_once __DIR__ . '/marketplace/install.php';
require_once __DIR__ . '/marketplace/consent.php';
