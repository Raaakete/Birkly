<?php
/**
 * Birkly marketplace client (P9-M6) — registry API, installation identity, license activate.
 */

const BIRKLY_MARKETPLACE_DEFAULT_URL = 'https://marketplace.birkly.cloud';
const BIRKLY_MARKETPLACE_DEFAULT_DISPLAY_CURRENCY = 'EUR';
const BIRKLY_MARKETPLACE_DEFAULT_UPDATE_CHANNEL = 'alpha';

require_once __DIR__ . '/marketplace/http.php';

/** Display-only FX rates (units per 1 EUR) for CMS marketplace price conversion. */
const BIRKLY_MARKETPLACE_CURRENCY_RATES = [
    'EUR' => 1.0,
    'USD' => 1.08,
    'GBP' => 0.85,
    'CHF' => 0.95,
    'DKK' => 7.46,
    'SEK' => 11.50,
    'NOK' => 11.80,
    'CAD' => 1.47,
    'AUD' => 1.65,
];

function birkly_marketplace_supported_display_currencies(): array
{
    return BIRKLY_MARKETPLACE_CURRENCY_RATES;
}

function birkly_marketplace_normalize_display_currency(string $currency): string
{
    $currency = strtoupper(trim($currency));
    if (!array_key_exists($currency, BIRKLY_MARKETPLACE_CURRENCY_RATES)) {
        throw new InvalidArgumentException('Unsupported display currency');
    }

    return $currency;
}

function birkly_marketplace_display_currency(): string
{
    $currency = strtoupper(trim((string) (birkly_marketplace_settings()['display_currency'] ?? BIRKLY_MARKETPLACE_DEFAULT_DISPLAY_CURRENCY)));
    if (!array_key_exists($currency, BIRKLY_MARKETPLACE_CURRENCY_RATES)) {
        return BIRKLY_MARKETPLACE_DEFAULT_DISPLAY_CURRENCY;
    }

    return $currency;
}

function birkly_marketplace_sync_display_currency(string $currency): void
{
    require_once __DIR__ . '/../api/storage.php';
    $currency = birkly_marketplace_normalize_display_currency($currency);
    $storage = new Storage(birkly_marketplace_settings_dir());
    $data = $storage->read('marketplace.json') ?? [];
    if (!is_array($data)) {
        $data = [];
    }

    $data['display_currency'] = $currency;
    $storage->write('marketplace.json', $data);
    birkly_marketplace_settings_reset();
}

function birkly_marketplace_update_channels(): array
{
    return ['stable', 'beta', 'alpha'];
}

function birkly_marketplace_normalize_update_channel(string $channel): string
{
    $channel = strtolower(trim($channel));
    if (!in_array($channel, birkly_marketplace_update_channels(), true)) {
        return BIRKLY_MARKETPLACE_DEFAULT_UPDATE_CHANNEL;
    }

    return $channel;
}

function birkly_marketplace_update_channel(): string
{
    $channel = strtolower(trim((string) (birkly_marketplace_settings()['update_channel'] ?? BIRKLY_MARKETPLACE_DEFAULT_UPDATE_CHANNEL)));

    return birkly_marketplace_normalize_update_channel($channel);
}

function birkly_marketplace_sync_update_channel(string $channel): void
{
    require_once __DIR__ . '/../api/storage.php';
    $normalized = strtolower(trim($channel));
    if (!in_array($normalized, birkly_marketplace_update_channels(), true)) {
        throw new InvalidArgumentException('Unsupported update channel');
    }

    $storage = new Storage(birkly_marketplace_settings_dir());
    $data = $storage->read('marketplace.json') ?? [];
    if (!is_array($data)) {
        $data = [];
    }

    $data['update_channel'] = $normalized;
    $storage->write('marketplace.json', $data);
    birkly_marketplace_settings_reset();
}

function birkly_marketplace_normalize_release_stability(string $stability): string
{
    $stability = strtolower(trim($stability));
    if (!in_array($stability, birkly_marketplace_update_channels(), true)) {
        return 'stable';
    }

    return $stability;
}

function birkly_marketplace_offer_distribution(array $offer): string
{
    return birkly_marketplace_normalize_release_stability((string) ($offer['distribution'] ?? 'stable'));
}

function birkly_marketplace_default_distribution_notice(string $distribution): string
{
    return match (birkly_marketplace_normalize_release_stability($distribution)) {
        'alpha' => 'This is an alpha pre-release. Builds may be unstable or incomplete. Only install if you accept that risk.',
        'beta' => 'This is a beta pre-release. Builds may still have issues before general release.',
        default => '',
    };
}

function birkly_marketplace_offer_distribution_notices(array $offer): array
{
    $raw = is_array($offer['distribution_notices'] ?? null) ? $offer['distribution_notices'] : [];
    $notices = [];
    foreach (['alpha', 'beta'] as $channel) {
        $custom = trim((string) ($raw[$channel] ?? ''));
        if ($custom !== '') {
            if (strlen($custom) > 2000) {
                $custom = substr($custom, 0, 2000);
            }
            $notices[$channel] = $custom;
        }
    }

    return $notices;
}

function birkly_marketplace_offer_distribution_notice(array $offer, ?string $distribution = null): string
{
    $distribution = birkly_marketplace_normalize_release_stability($distribution ?? birkly_marketplace_offer_distribution($offer));
    if ($distribution === 'stable') {
        return '';
    }

    $notices = birkly_marketplace_offer_distribution_notices($offer);
    if (isset($notices[$distribution])) {
        return $notices[$distribution];
    }

    return birkly_marketplace_default_distribution_notice($distribution);
}

function birkly_marketplace_pre_release_stability_rank(string $stability): int
{
    return match (birkly_marketplace_normalize_release_stability($stability)) {
        'alpha' => 2,
        'beta' => 1,
        default => 0,
    };
}

function birkly_marketplace_merge_release_stability(string $left, string $right): string
{
    $left = birkly_marketplace_normalize_release_stability($left);
    $right = birkly_marketplace_normalize_release_stability($right);

    return birkly_marketplace_pre_release_stability_rank($left) >= birkly_marketplace_pre_release_stability_rank($right)
        ? $left
        : $right;
}

function birkly_marketplace_release_effective_stability(array $offer, array $release): string
{
    $releaseStability = birkly_marketplace_normalize_release_stability((string) ($release['stability'] ?? 'stable'));

    return birkly_marketplace_merge_release_stability(birkly_marketplace_offer_distribution($offer), $releaseStability);
}

function birkly_marketplace_release_visible_for_channel(array $release, string $channel, ?array $offer = null): bool
{
    $channel = birkly_marketplace_normalize_update_channel($channel);
    $stability = $offer !== null
        ? birkly_marketplace_release_effective_stability($offer, $release)
        : birkly_marketplace_normalize_release_stability((string) ($release['stability'] ?? 'stable'));
    if ($channel === 'alpha') {
        return true;
    }
    if ($channel === 'beta') {
        return in_array($stability, ['stable', 'beta'], true);
    }

    return $stability === 'stable';
}

/**
 * @param list<array<string, mixed>> $releases
 * @return list<array<string, mixed>>
 */
function birkly_marketplace_releases_for_channel(array $releases, string $channel, ?array $offer = null): array
{
    $filtered = [];
    foreach ($releases as $release) {
        if (!is_array($release)) {
            continue;
        }
        if (birkly_marketplace_release_visible_for_channel($release, $channel, $offer)) {
            $filtered[] = $release;
        }
    }

    return $filtered;
}

/**
 * @param list<array<string, mixed>> $releases
 */
/**
 * Releases this site can install: each release's min_birkly must accept the running CMS version.
 *
 * @param list<array<string, mixed>> $releases
 * @return list<array<string, mixed>>
 */
function birkly_marketplace_releases_compatible_with_site(array $releases): array
{
    require_once __DIR__ . '/plugins/version_constraints.php';
    $cmsVersion = birkly_cms_version_for_plugin_compat();

    return array_values(array_filter($releases, static function ($release) use ($cmsVersion): bool {
        if (!is_array($release)) {
            return false;
        }
        $constraint = trim((string) ($release['min_birkly'] ?? ''));
        return $constraint === '' || birkly_version_satisfies_constraint($cmsVersion, birkly_minimum_version_constraint($constraint));
    }));
}

/**
 * @param list<array<string, mixed>> $releases
 */
function birkly_marketplace_release_min_birkly(array $releases, string $version): string
{
    foreach ($releases as $release) {
        if (is_array($release) && (string) ($release['version'] ?? '') === $version) {
            return (string) ($release['min_birkly'] ?? '');
        }
    }

    return '';
}

function birkly_marketplace_latest_release_version(array $releases, ?string $channel = null): ?string
{
    if ($channel !== null) {
        $releases = birkly_marketplace_releases_for_channel($releases, $channel);
    }

    $latest = null;
    foreach ($releases as $release) {
        if (!is_array($release)) {
            continue;
        }
        $ver = trim((string) ($release['version'] ?? ''));
        if ($ver !== '' && ($latest === null || version_compare($ver, $latest, '>'))) {
            $latest = $ver;
        }
    }

    return $latest;
}

/**
 * @param list<array<string, mixed>> $releases
 */
function birkly_marketplace_release_stability_from_releases(array $releases, string $version, ?array $offer = null): string
{
    foreach ($releases as $release) {
        if (!is_array($release) || (string) ($release['version'] ?? '') !== $version) {
            continue;
        }

        if ($offer !== null) {
            return birkly_marketplace_release_effective_stability($offer, $release);
        }

        return birkly_marketplace_normalize_release_stability((string) ($release['stability'] ?? 'stable'));
    }

    return 'stable';
}

/**
 * Minimum site update channel required to receive a specific release.
 */
function birkly_marketplace_required_channel_for_release(array $offer, array $release): string
{
    $stability = birkly_marketplace_release_effective_stability($offer, $release);

    return match ($stability) {
        'alpha' => 'alpha',
        'beta' => 'beta',
        default => 'stable',
    };
}

/**
 * @param list<array<string, mixed>> $releases
 */
function birkly_marketplace_required_channel_for_version(array $releases, string $version, array $offer): string
{
    $version = trim($version);
    if ($version === '') {
        return 'stable';
    }

    foreach ($releases as $release) {
        if (!is_array($release) || (string) ($release['version'] ?? '') !== $version) {
            continue;
        }

        return birkly_marketplace_required_channel_for_release($offer, $release);
    }

    return birkly_marketplace_normalize_update_channel(birkly_marketplace_offer_distribution($offer));
}

function birkly_marketplace_stability_label(string $stability): string
{
    return match (birkly_marketplace_normalize_release_stability($stability)) {
        'alpha' => 'Alpha',
        'beta' => 'Beta',
        default => 'Stable',
    };
}

function birkly_marketplace_convert_cents(int $cents, string $fromCurrency, string $toCurrency): int
{
    $fromCurrency = strtoupper(trim($fromCurrency));
    $toCurrency = strtoupper(trim($toCurrency));
    if ($cents <= 0 || $fromCurrency === $toCurrency) {
        return max(0, $cents);
    }

    $rates = BIRKLY_MARKETPLACE_CURRENCY_RATES;
    if (!isset($rates[$fromCurrency], $rates[$toCurrency])) {
        return max(0, $cents);
    }

    $inEur = $cents / $rates[$fromCurrency];

    return (int) round($inEur * $rates[$toCurrency]);
}

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
    $configuredUrl = $envUrl !== '' ? $envUrl : trim((string) ($stored['marketplace_url'] ?? BIRKLY_MARKETPLACE_DEFAULT_URL));
    if ($configuredUrl === '') {
        $configuredUrl = BIRKLY_MARKETPLACE_DEFAULT_URL;
    }
    $url = rtrim($configuredUrl, '/');

    $displayCurrency = strtoupper(trim((string) ($stored['display_currency'] ?? BIRKLY_MARKETPLACE_DEFAULT_DISPLAY_CURRENCY)));
    if (!array_key_exists($displayCurrency, BIRKLY_MARKETPLACE_CURRENCY_RATES)) {
        $displayCurrency = BIRKLY_MARKETPLACE_DEFAULT_DISPLAY_CURRENCY;
    }

    $updateChannel = birkly_marketplace_normalize_update_channel(
        (string) ($stored['update_channel'] ?? BIRKLY_MARKETPLACE_DEFAULT_UPDATE_CHANNEL)
    );

    $GLOBALS['__birkly_marketplace_settings'] = [
        'marketplace_url' => rtrim($url, '/'),
        'installation_owner_email' => trim((string) ($stored['installation_owner_email'] ?? '')),
        'display_currency' => $displayCurrency,
        'update_channel' => $updateChannel,
        'entitlement_secret' => trim((string) ($stored['entitlement_secret'] ?? (getenv('BIRKLY_MARKETPLACE_ENTITLEMENT_SECRET') ?: ''))),
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

function birkly_marketplace_user_error_message(string $error): string
{
    $error = trim($error);
    if ($error === '') {
        $error = 'Marketplace request failed';
    }

    if (
        stripos($error, 'ssl certificate') !== false
        || stripos($error, 'certificate problem') !== false
        || stripos($error, 'unable to get local issuer') !== false
    ) {
        return $error . ' On Windows with Birkly portable PHP, run install-php.ps1 again (it installs a CA bundle for HTTPS), restart the server, then retry Hub.';
    }

    if (
        stripos($error, 'timed out') !== false
        || stripos($error, 'could not resolve') !== false
        || stripos($error, 'failed to connect') !== false
    ) {
        $url = birkly_marketplace_url();
        return $error . ' Verify PHP can reach ' . $url
            . ' (terminal: curl -4 ' . $url . '/health). '
            . 'Broken HTTP_PROXY env vars, routing, or ISP issues can cause PHP timeouts while a browser still works.';
    }

    return $error;
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
function birkly_marketplace_request(
    string $method,
    string $path,
    ?array $body = null,
    array $query = [],
    bool $forceLive = false
): array {
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

    $payload = $body !== null ? json_encode($body) : null;
    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    $fetch = birkly_marketplace_http_fetch($url, $method, $headers, $payload, 20);
    $status = (int) ($fetch['status'] ?? 0);
    if (!$fetch['ok']) {
        return [
            'ok' => false,
            'status' => $status,
            'data' => null,
            'error' => birkly_marketplace_transport_error_message('Marketplace request failed', $fetch['error'] ?? null),
        ];
    }

    $raw = (string) ($fetch['body'] ?? '');
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
function birkly_marketplace_browse(string $query = '', string $category = '', string $offerType = '', string $sort = 'name', string $price = ''): array
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
    if ($sort !== '' && $sort !== 'name') {
        $params['sort'] = $sort;
    }
    if ($price !== '') {
        $params['price'] = $price;
    }

    $priceSelection = birkly_marketplace_price_selection($price);
    $effectiveSort = $priceSelection['sort'] !== '' ? $priceSelection['sort'] : $sort;

    $response = birkly_marketplace_request('GET', '/api/v1/registry', null, $params);
    if (!$response['ok'] || !is_array($response['data'])) {
        $fallback = birkly_marketplace_fallback_catalog();
        $offers = is_array($fallback['offers'] ?? null) ? $fallback['offers'] : [];
        if ($offerType !== '' && $offerType !== 'all') {
            $offers = birkly_marketplace_filter_offers($offers, '', '', $offerType, $priceSelection['price_range']);
        }
        if ($query !== '' || $category !== '' || $priceSelection['price_range'] !== '') {
            $offers = birkly_marketplace_filter_offers($offers, $query, $category, $offerType, $priceSelection['price_range']);
        }
        if ($offers !== []) {
            $offers = birkly_marketplace_sort_offers($offers, $effectiveSort);
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
    $offers = birkly_marketplace_filter_offers($offers, $query, $category, $offerType, $priceSelection['price_range']);
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

function birkly_marketplace_filter_offers(array $offers, string $query, string $category, string $offerType, string $priceRange = ''): array
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
        if (!birkly_marketplace_offer_matches_price_range($offer, $priceRange)) {
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

/**
 * @return array{price_range: string, sort: string}
 */
function birkly_marketplace_price_selection(string $price): array
{
    $price = trim($price);
    if ($price === '' || $price === 'all') {
        return ['price_range' => '', 'sort' => ''];
    }
    if (in_array($price, ['price_asc', 'price_desc'], true)) {
        return ['price_range' => '', 'sort' => $price];
    }
    if (in_array($price, ['free', 'under_10', '10_50', '50_100', 'over_100'], true)) {
        return ['price_range' => $price, 'sort' => ''];
    }

    return ['price_range' => '', 'sort' => ''];
}

function birkly_marketplace_offer_amount_cents(array $offer): int
{
    $pricing = is_array($offer['pricing'] ?? null) ? $offer['pricing'] : [];
    if ((string) ($pricing['model'] ?? '') === 'free') {
        return 0;
    }

    return max(0, (int) ($pricing['amount_cents'] ?? 0));
}

function birkly_marketplace_offer_matches_price_range(array $offer, string $range): bool
{
    if ($range === '') {
        return true;
    }

    $cents = birkly_marketplace_offer_amount_cents($offer);

    return match ($range) {
        'free' => $cents === 0,
        'under_10' => $cents > 0 && $cents <= 1000,
        '10_50' => $cents > 1000 && $cents <= 5000,
        '50_100' => $cents > 5000 && $cents <= 10000,
        'over_100' => $cents > 10000,
        default => true,
    };
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

function birkly_marketplace_site_url(): string
{
    $envUrl = trim((string) (getenv('BIRKLY_CMS_BASE_URL') ?: ''));
    if ($envUrl !== '') {
        return rtrim($envUrl, '/');
    }

    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '';
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return ($https ? 'https://' : 'http://') . $host;
}

function birkly_marketplace_environment(?string $siteUrl = null): string
{
    $host = '';
    $siteUrl = trim((string) ($siteUrl ?? birkly_marketplace_site_url()));
    if ($siteUrl !== '') {
        $host = strtolower((string) (parse_url($siteUrl, PHP_URL_HOST) ?: ''));
    }
    if ($host === '') {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    }
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    $host = trim($host, '[]');

    if (
        in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)
        || str_ends_with($host, '.local')
        || str_ends_with($host, '.test')
        || str_ends_with($host, '.dev')
        || str_starts_with($host, 'staging.')
        || str_contains($host, '.staging.')
        || str_contains($host, '-staging.')
        || str_contains($host, '.dev.')
        || str_contains($host, '-dev.')
    ) {
        return 'staging';
    }

    return 'production';
}

/**
 * @return array<string, string>
 */
function birkly_marketplace_activation_context(): array
{
    $siteUrl = birkly_marketplace_site_url();
    return [
        'environment' => birkly_marketplace_environment($siteUrl),
        'site_url' => $siteUrl,
    ];
}

function birkly_marketplace_return_url(string $slug = '', string $planId = ''): string
{
    $base = birkly_marketplace_site_url();
    if ($base === '') {
        return '';
    }

    $url = rtrim($base, '/') . '/admin/index.php?page=plugins&tab=discover';
    if ($slug !== '') {
        $url .= '&offer=' . rawurlencode($slug) . '&hub_install=1';
    }
    if ($planId !== '') {
        $url .= '&plan_id=' . rawurlencode($planId);
    }

    return $url;
}

function birkly_marketplace_error_message(string $code, string $fallback = ''): string
{
    return match ($code) {
        'inactive', 'license_inactive', 'entitlement_inactive' => 'This entitlement is inactive. Activate a valid license or complete checkout before installing.',
        'held', 'entitlement_held' => 'This entitlement is on hold. Update payment or contact the marketplace provider.',
        'expired', 'entitlement_expired', 'license_expired' => 'This entitlement has expired. Renew it before installing or updating.',
        'revoked', 'entitlement_revoked' => 'This entitlement was revoked and cannot be used on this installation.',
        'seat_limit', 'seat_limit_reached', 'activation_limit_reached' => 'This license has reached its activation or seat limit. Deactivate another installation or buy more seats.',
        'wrong_offer', 'offer_mismatch', 'license_offer_mismatch' => 'This license belongs to a different Hub offer.',
        'license_required', 'checkout_required' => 'This paid offer needs checkout or a valid license key before install.',
        default => $fallback !== '' ? $fallback : 'Marketplace entitlement check failed.',
    };
}

/**
 * @return array{ok:bool,order?:array,license?:array,entitlement?:array,entitlement_token?:string,psp?:array,checkout_url?:string,code?:string,error?:string}
 */
function birkly_marketplace_start_checkout(string $slug, array $options = []): array
{
    $slug = trim($slug);
    if ($slug === '') {
        return ['ok' => false, 'error' => 'Offer slug required', 'code' => 'missing_slug'];
    }

    $context = birkly_marketplace_activation_context();
    $body = [
        'offer_slug' => $slug,
        'installation_id' => birkly_installation_id(),
        'environment' => $context['environment'],
        'site_url' => $context['site_url'],
        'return_url' => (string) ($options['return_url'] ?? birkly_marketplace_return_url($slug, (string) ($options['plan_id'] ?? ''))),
    ];

    foreach (['email', 'plan_id', 'promo_code', 'quantity'] as $key) {
        if (isset($options[$key]) && trim((string) $options[$key]) !== '') {
            $body[$key] = $options[$key];
        }
    }

    $response = birkly_marketplace_request('POST', '/api/v1/checkout', $body, [], true);
    if (!$response['ok'] || !is_array($response['data'])) {
        $code = is_array($response['data']) ? (string) ($response['data']['error'] ?? '') : '';
        return [
            'ok' => false,
            'error' => birkly_marketplace_error_message($code, $response['error'] ?? 'Checkout failed'),
            'code' => $code !== '' ? $code : 'checkout_failed',
        ];
    }

    $data = $response['data'];
    if (empty($data['ok'])) {
        $code = (string) ($data['error'] ?? 'checkout_failed');
        return [
            'ok' => false,
            'error' => birkly_marketplace_error_message($code, (string) ($data['message'] ?? 'Checkout failed')),
            'code' => $code,
        ];
    }

    $psp = is_array($data['psp'] ?? null) ? $data['psp'] : [];
    $checkoutUrl = (string) (
        $data['checkout_url']
        ?? $psp['checkout_url']
        ?? $psp['url']
        ?? ''
    );

    return [
        'ok' => true,
        'order' => is_array($data['order'] ?? null) ? $data['order'] : [],
        'license' => is_array($data['license'] ?? null) ? $data['license'] : [],
        'entitlement' => is_array($data['entitlement'] ?? null) ? $data['entitlement'] : [],
        'entitlement_token' => (string) ($data['entitlement_token'] ?? ''),
        'psp' => $psp,
        'checkout_url' => $checkoutUrl,
    ];
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
        $code = (string) ($data['error'] ?? 'license_inactive');
        return [
            'ok' => false,
            'active' => false,
            'error' => birkly_marketplace_error_message($code, (string) ($data['message'] ?? 'License inactive')),
            'code' => $code,
            'license_lapsed' => true,
        ];
    }

    $status = (string) ($data['status'] ?? 'active');
    $activeStatuses = ['active', 'trialing', 'grace'];
    return [
        'ok' => true,
        'active' => in_array($status, $activeStatuses, true),
        'status' => $status,
        'pricing_model' => (string) ($data['pricing_model'] ?? 'one_time'),
        'license_lapsed' => !in_array($status, $activeStatuses, true),
        'error' => in_array($status, $activeStatuses, true) ? null : birkly_marketplace_error_message($status),
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
 * Attach an activated entitlement to an installed plugin with the matching offer slug.
 */
function birkly_marketplace_attach_entitlement_to_installed_offer(
    string $offerSlug,
    string $licenseKey = '',
    string $entitlementToken = '',
    array $entitlement = []
): ?string {
    $offerSlug = trim($offerSlug);
    if ($offerSlug === '' || (!function_exists('get_all_plugins') && !function_exists('load_plugins'))) {
        return null;
    }
    if (!function_exists('get_all_plugins')) {
        return null;
    }

    global $birkly_plugins;
    $plugins = get_all_plugins();
    foreach ($plugins as $id => $plugin) {
        if (birkly_marketplace_plugin_offer_slug((string) $id, is_array($plugin) ? $plugin : []) !== $offerSlug) {
            continue;
        }
        if (!isset($birkly_plugins[$id])) {
            continue;
        }
        if ($licenseKey !== '') {
            $birkly_plugins[$id]['license_ref'] = birkly_marketplace_license_ref($licenseKey);
        }
        if ($entitlementToken !== '') {
            $birkly_plugins[$id]['entitlement_token'] = $entitlementToken;
        }
        if ($entitlement !== []) {
            $birkly_plugins[$id]['entitlement'] = $entitlement;
            unset($birkly_plugins[$id]['entitlement_grace_started_at']);
        }
        if (function_exists('save_plugin_states')) {
            save_plugin_states();
        }
        return (string) $id;
    }

    return null;
}

/**
 * Remove stored license binding and entitlement payload from an installed plugin.
 */
function birkly_marketplace_clear_plugin_entitlement(string $pluginId): bool
{
    $pluginId = trim($pluginId);
    if ($pluginId === '' || !function_exists('get_all_plugins')) {
        return false;
    }

    global $birkly_plugins;
    if (!isset($birkly_plugins[$pluginId]) && function_exists('load_plugins')) {
        load_plugins();
    }
    if (!isset($birkly_plugins[$pluginId])) {
        return false;
    }

    unset(
        $birkly_plugins[$pluginId]['license_ref'],
        $birkly_plugins[$pluginId]['entitlement_token'],
        $birkly_plugins[$pluginId]['entitlement'],
        $birkly_plugins[$pluginId]['entitlement_grace_started_at']
    );

    if (function_exists('save_plugin_states')) {
        save_plugin_states();
    }

    return true;
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

function birkly_marketplace_offer_is_installable(array $offer): bool
{
    $status = (string) ($offer['status'] ?? '');
    if ($status === '') {
        return true;
    }

    return $status === 'published';
}

function birkly_marketplace_enrich_offer_install_state(array $offer): array
{
    $slug = (string) ($offer['slug'] ?? '');
    $offer['distribution'] = birkly_marketplace_offer_distribution($offer);
    $offer['distribution_notices'] = birkly_marketplace_offer_distribution_notices($offer);
    $offer['distribution_notice'] = birkly_marketplace_offer_distribution_notice($offer);
    $offer['install_state'] = birkly_marketplace_offer_install_state(
        $slug,
        (string) ($offer['latest_version'] ?? '')
    );
    $offer['installable'] = birkly_marketplace_offer_is_installable($offer);

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
 * @param array<string, mixed> $offer
 * @return array{ok:bool,offer:array,releases:array,fallback:bool}
 */
function birkly_marketplace_detail_from_fallback_offer(array $offer): array
{
    $releases = is_array($offer['releases'] ?? null) ? $offer['releases'] : [];
    if ($releases === []) {
        $latest = (string) ($offer['latest_version'] ?? '');
        if ($latest !== '') {
            $releases[] = ['version' => $latest, 'stability' => 'stable'];
        }
    }

    return [
        'ok' => true,
        'offer' => birkly_marketplace_enrich_offer_install_state($offer),
        'releases' => $releases,
        'fallback' => true,
    ];
}

function birkly_marketplace_offer_detail_disk_cache_path(): string
{
    return birkly_marketplace_settings_dir() . '/.marketplace_offer_detail_cache.json';
}

/**
 * @return array<string, mixed>|null
 */
function birkly_marketplace_read_offer_detail_disk_cache(string $slug): ?array
{
    $path = birkly_marketplace_offer_detail_disk_cache_path();
    if (!is_file($path)) {
        return null;
    }

    $raw = file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return null;
    }

    $entry = $data[$slug] ?? null;
    if (!is_array($entry) || !is_array($entry['detail'] ?? null)) {
        return null;
    }

    $fetchedAt = (int) ($entry['fetched_at'] ?? 0);
    if ($fetchedAt <= 0 || (time() - $fetchedAt) > 180) {
        return null;
    }

    return $entry['detail'];
}

/**
 * @param array<string, mixed> $detail
 */
function birkly_marketplace_write_offer_detail_disk_cache(string $slug, array $detail): void
{
    $path = birkly_marketplace_offer_detail_disk_cache_path();
    $data = [];
    if (is_file($path)) {
        $existing = json_decode((string) file_get_contents($path), true);
        if (is_array($existing)) {
            $data = $existing;
        }
    }

    $data[$slug] = [
        'fetched_at' => time(),
        'detail' => $detail,
    ];

    $encoded = json_encode($data, JSON_UNESCAPED_SLASHES);
    if ($encoded !== false) {
        @file_put_contents($path, $encoded);
    }
}

function birkly_marketplace_clear_offer_detail_disk_cache(?string $slug = null): void
{
    $path = birkly_marketplace_offer_detail_disk_cache_path();
    if (!is_file($path)) {
        return;
    }

    if ($slug === null || trim($slug) === '') {
        @unlink($path);
        return;
    }

    $raw = file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return;
    }

    unset($data[trim($slug)]);
    if ($data === []) {
        @unlink($path);
        return;
    }

    $encoded = json_encode($data, JSON_UNESCAPED_SLASHES);
    if ($encoded !== false) {
        @file_put_contents($path, $encoded);
    }
}

/**
 * @return array{ok:bool,version?:string,download_url?:string,checksum_sha256?:string,error?:string}
 */
function birkly_marketplace_release_download_from_registry_api(string $slug, string $version): array
{
    $path = '/api/v1/offers/' . rawurlencode($slug) . '/releases/' . rawurlencode($version);
    $response = birkly_marketplace_request('GET', $path, null, [], true);
    if (!$response['ok'] || !is_array($response['data'])) {
        return ['ok' => false, 'error' => $response['error'] ?? 'Release not found'];
    }

    $downloadUrl = birkly_marketplace_rewrite_service_url((string) ($response['data']['download_url'] ?? ''));

    return [
        'ok' => true,
        'version' => (string) ($response['data']['version'] ?? $version),
        'download_url' => $downloadUrl,
        'checksum_sha256' => (string) ($response['data']['checksum_sha256'] ?? ''),
    ];
}

/**
 * @param list<array<string, mixed>> $releases
 * @return array{permissions?:array,hooks?:array,data_access?:array}|null
 */
function birkly_marketplace_permissions_manifest_for_release(array $releases, string $targetVersion): ?array
{
    foreach ($releases as $release) {
        if (!is_array($release) || (string) ($release['version'] ?? '') !== $targetVersion) {
            continue;
        }
        if (is_array($release['permissions_manifest'] ?? null)) {
            return $release['permissions_manifest'];
        }
        $pluginJson = is_array($release['plugin_json'] ?? null) ? $release['plugin_json'] : null;
        if ($pluginJson !== null) {
            return birkly_marketplace_permissions_manifest_from_plugin_json($pluginJson);
        }
    }

    return null;
}

/**
 * Rewrite cloud marketplace/Ops artifact URLs to the configured marketplace base when needed.
 */
function birkly_marketplace_rewrite_service_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return $url;
    }

    $isArtifact = str_contains($url, '/api/v1/artifacts/');
    $effectiveBase = rtrim(birkly_marketplace_url(), '/');
    $publicMarketplace = rtrim(BIRKLY_MARKETPLACE_DEFAULT_URL, '/');
    $knownBases = [
        rtrim(BIRKLY_MARKETPLACE_DEFAULT_URL, '/'),
        'https://marketplace.birkly.cloud',
        'https://ops.birkly.cloud',
    ];

    // Ops does not serve public artifact downloads (wrong_surface). Keep marketplace URLs as-is.
    if ($isArtifact) {
        foreach ($knownBases as $cloudBase) {
            $cloudBase = rtrim($cloudBase, '/');
            if ($cloudBase === '' || !str_starts_with($url, $cloudBase . '/')) {
                continue;
            }
            if ($effectiveBase === 'https://ops.birkly.cloud' || $effectiveBase === 'http://ops.birkly.cloud') {
                if ($cloudBase === 'https://ops.birkly.cloud' || $cloudBase === 'http://ops.birkly.cloud') {
                    return $url;
                }
                return $publicMarketplace . substr($url, strlen($cloudBase));
            }
            break;
        }
    }

    foreach ($knownBases as $cloudBase) {
        $cloudBase = rtrim($cloudBase, '/');
        if ($cloudBase === '' || $effectiveBase === '' || $effectiveBase === $cloudBase) {
            continue;
        }
        if (str_starts_with($url, $cloudBase . '/')) {
            $rewritten = $effectiveBase . substr($url, strlen($cloudBase));
            if ($isArtifact && ($effectiveBase === 'https://ops.birkly.cloud' || $effectiveBase === 'http://ops.birkly.cloud')) {
                return $url;
            }
            return $rewritten;
        }
    }

    return $url;
}

/**
 * @param list<array<string, mixed>> $releases
 * @return array{ok:bool,version:string,download_url:string,checksum_sha256:string}|null
 */
function birkly_marketplace_release_download_from_releases(array $releases, string $version): ?array
{
    $version = trim($version);
    if ($version === '') {
        return null;
    }

    foreach ($releases as $release) {
        if (!is_array($release) || (string) ($release['version'] ?? '') !== $version) {
            continue;
        }

        $artifact = is_array($release['artifact'] ?? null) ? $release['artifact'] : [];
        $downloadUrl = trim((string) ($artifact['url'] ?? $release['download_url'] ?? ''));
        if ($downloadUrl === '') {
            return null;
        }

        return [
            'ok' => true,
            'version' => $version,
            'download_url' => birkly_marketplace_rewrite_service_url($downloadUrl),
            'checksum_sha256' => (string) ($artifact['checksum_sha256'] ?? $release['checksum_sha256'] ?? ''),
        ];
    }

    return null;
}

/**
 * @return array{ok:bool,offer:array,releases:array,fallback:bool}|null
 */
function birkly_marketplace_offer_detail_from_fallback(string $slug): ?array
{
    $offer = birkly_marketplace_find_fallback_offer($slug);
    if ($offer === null) {
        return null;
    }

    return birkly_marketplace_detail_from_fallback_offer($offer);
}

/**
 * @return array{ok:bool,offer?:array,releases?:array,fallback?:bool,error?:string}
 */
function birkly_marketplace_offer_detail(string $slug): array
{
    static $requestCache = [];
    if (isset($requestCache[$slug])) {
        return $requestCache[$slug];
    }

    $diskCached = birkly_marketplace_read_offer_detail_disk_cache($slug);
    if ($diskCached !== null) {
        $requestCache[$slug] = $diskCached;
        return $diskCached;
    }

    $response = birkly_marketplace_request('GET', '/api/v1/offers/' . rawurlencode($slug), null, [], true);
    if ($response['ok'] && is_array($response['data'])) {
        $offer = is_array($response['data']['offer'] ?? null) ? $response['data']['offer'] : [];
        $provider = is_array($response['data']['provider'] ?? null) ? $response['data']['provider'] : null;

        $detail = [
            'ok' => true,
            'offer' => birkly_marketplace_enrich_offer_install_state($offer),
            'releases' => is_array($response['data']['releases'] ?? null) ? $response['data']['releases'] : [],
            'reviews' => is_array($response['data']['reviews'] ?? null) ? $response['data']['reviews'] : [],
            'rating_summary' => is_array($response['data']['rating_summary'] ?? null) ? $response['data']['rating_summary'] : null,
            'provider' => $provider,
            'fallback' => false,
        ];
        if ($detail['releases'] === [] && is_array($offer['releases'] ?? null)) {
            $detail['releases'] = $offer['releases'];
        }
        birkly_marketplace_write_offer_detail_disk_cache($slug, $detail);
        $requestCache[$slug] = $detail;
        return $detail;
    }

    $offer = birkly_marketplace_find_fallback_offer($slug);
    if ($offer !== null) {
        $detail = birkly_marketplace_detail_from_fallback_offer($offer);
        $requestCache[$slug] = $detail;
        return $detail;
    }

    $detail = ['ok' => false, 'error' => $response['error'] ?? 'Offer not found'];
    $requestCache[$slug] = $detail;
    return $detail;
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
function birkly_marketplace_local_free_entitlement(string $slug): array
{
    return [
        'ok' => true,
        'offer_slug' => $slug,
        'entitlement' => [
            'offer_slug' => $slug,
            'status' => 'active',
            'pricing_model' => 'free',
            'source' => 'local',
        ],
        'source' => 'local',
    ];
}

function birkly_marketplace_claim_free_license(string $slug): array
{
    $slug = trim($slug);
    if ($slug === '') {
        return ['ok' => false, 'error' => 'Offer slug required', 'code' => 'missing_slug'];
    }

    $fallbackOffer = birkly_marketplace_find_fallback_offer($slug);

    $detail = birkly_marketplace_offer_detail($slug);
    if (!$detail['ok']) {
        if ($fallbackOffer !== null && birkly_marketplace_offer_is_free($fallbackOffer)) {
            return birkly_marketplace_local_free_entitlement($slug);
        }

        return ['ok' => false, 'error' => $detail['error'] ?? 'Offer not found', 'code' => 'offer_not_found'];
    }

    $offer = is_array($detail['offer'] ?? null) ? $detail['offer'] : [];
    if (!birkly_marketplace_offer_is_free($offer)) {
        return ['ok' => false, 'error' => 'This offer requires a license key', 'code' => 'license_required'];
    }

    if (empty($detail['fallback'])) {
        $installId = preg_replace('/[^a-zA-Z0-9._-]/', '', birkly_installation_id()) ?: 'install';
        $email = $installId . '@install.birkly.local';
        $checkout = birkly_marketplace_start_checkout($slug, ['email' => $email]);

        if ($checkout['ok']) {
            $checkoutLicense = is_array($checkout['license'] ?? null) ? $checkout['license'] : [];
            $checkoutOrder = is_array($checkout['order'] ?? null) ? $checkout['order'] : [];
            $licenseKey = (string) ($checkoutLicense['license_key'] ?? $checkoutOrder['license_key'] ?? '');
            if ($licenseKey !== '') {
                $activation = birkly_marketplace_activate_license($licenseKey);
                if ($activation['ok']) {
                    return [
                        'ok' => true,
                        'offer_slug' => (string) ($activation['offer_slug'] ?? $slug),
                        'entitlement_token' => (string) ($activation['entitlement_token'] ?? ''),
                        'entitlement' => is_array($activation['entitlement'] ?? null) ? $activation['entitlement'] : [],
                        'source' => 'marketplace',
                    ];
                }
            }
            if (!empty($checkout['entitlement']) || !empty($checkout['entitlement_token'])) {
                return [
                    'ok' => true,
                    'offer_slug' => (string) (($checkout['entitlement']['offer_slug'] ?? '') ?: $slug),
                    'entitlement_token' => (string) ($checkout['entitlement_token'] ?? ''),
                    'entitlement' => is_array($checkout['entitlement'] ?? null) ? $checkout['entitlement'] : [],
                    'source' => 'marketplace',
                ];
            }
        }
    }

    return birkly_marketplace_local_free_entitlement($slug);
}

/**
 * @return array{ok:bool,code?:string,offer_slug?:string,license?:array,error?:string}
 */
function birkly_marketplace_activate_license(string $licenseKey, array $options = []): array
{
    $licenseKey = trim($licenseKey);
    if ($licenseKey === '') {
        return ['ok' => false, 'error' => 'License key is required', 'code' => 'missing_license_key'];
    }

    $context = birkly_marketplace_activation_context();
    $payload = [
        'license_key' => $licenseKey,
        'installation_id' => birkly_installation_id(),
        'environment' => (string) ($options['environment'] ?? $context['environment']),
        'site_url' => (string) ($options['site_url'] ?? $context['site_url']),
    ];
    if (isset($options['offer_slug']) && trim((string) $options['offer_slug']) !== '') {
        $payload['offer_slug'] = trim((string) $options['offer_slug']);
    }

    $response = birkly_marketplace_request('POST', '/api/v1/licenses/activate', $payload, [], true);

    if (!$response['ok'] || !is_array($response['data'])) {
        $code = is_array($response['data']) ? (string) ($response['data']['error'] ?? '') : '';
        return [
            'ok' => false,
            'error' => birkly_marketplace_error_message($code, $response['error'] ?? 'License activation failed'),
            'code' => $code !== '' ? $code : 'activation_failed',
        ];
    }

    $data = $response['data'];
    if (empty($data['ok'])) {
        $code = (string) ($data['error'] ?? 'activation_failed');
        return [
            'ok' => false,
            'error' => birkly_marketplace_error_message($code, (string) ($data['message'] ?? 'License activation failed')),
            'code' => $code,
        ];
    }

    $entitlement = is_array($data['entitlement'] ?? null) ? $data['entitlement'] : [];
    return [
        'ok' => true,
        'code' => (string) ($data['code'] ?? 'activated'),
        'offer_slug' => (string) (($data['offer_slug'] ?? '') ?: ($entitlement['offer_slug'] ?? '')),
        'license' => is_array($data['license'] ?? null) ? $data['license'] : [],
        'entitlement_token' => (string) ($data['entitlement_token'] ?? ''),
        'entitlement' => $entitlement,
        'activation' => is_array($data['activation'] ?? null) ? $data['activation'] : [],
    ];
}

/**
 * @return array{ok:bool,code?:string,error?:string}
 */
function birkly_marketplace_deactivate_license(string $licenseKey, ?string $installationId = null): array
{
    $licenseKey = trim($licenseKey);
    if ($licenseKey === '') {
        return ['ok' => false, 'error' => 'License key is required', 'code' => 'missing_license_key'];
    }

    $response = birkly_marketplace_request('POST', '/api/v1/licenses/deactivate', [
        'license_key' => $licenseKey,
        'installation_id' => $installationId ?? birkly_installation_id(),
    ]);
    if (!$response['ok'] || !is_array($response['data'])) {
        $code = is_array($response['data']) ? (string) ($response['data']['error'] ?? '') : '';
        return [
            'ok' => false,
            'error' => birkly_marketplace_error_message($code, $response['error'] ?? 'License deactivation failed'),
            'code' => $code !== '' ? $code : 'deactivation_failed',
        ];
    }

    if (empty($response['data']['ok'])) {
        $code = (string) ($response['data']['error'] ?? 'deactivation_failed');
        return [
            'ok' => false,
            'error' => birkly_marketplace_error_message($code, (string) ($response['data']['message'] ?? 'License deactivation failed')),
            'code' => $code,
        ];
    }

    return ['ok' => true, 'code' => (string) ($response['data']['code'] ?? 'deactivated')];
}

/**
 * @return array{ok:bool,version?:string,download_url?:string,checksum_sha256?:string,error?:string}
 */
function birkly_marketplace_release_download(string $slug, string $version): array
{
    $fromApi = birkly_marketplace_release_download_from_registry_api($slug, $version);
    if (!empty($fromApi['ok'])) {
        return $fromApi;
    }

    $fallbackOffer = birkly_marketplace_find_fallback_offer($slug);
    if ($fallbackOffer !== null) {
        $fromFallback = birkly_marketplace_release_download_from_releases(
            is_array($fallbackOffer['releases'] ?? null) ? $fallbackOffer['releases'] : [],
            $version
        );
        if ($fromFallback !== null) {
            return $fromFallback;
        }
    }

    $detail = birkly_marketplace_offer_detail($slug);
    if ($detail['ok']) {
        $fromDetail = birkly_marketplace_release_download_from_releases(
            is_array($detail['releases'] ?? null) ? $detail['releases'] : [],
            $version
        );
        if ($fromDetail !== null) {
            return $fromDetail;
        }
    }

    return $fromApi;
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

    $listedReleases = is_array($detail['releases'] ?? null) ? $detail['releases'] : [];
    $allReleases = birkly_marketplace_releases_compatible_with_site($listedReleases);
    $offerMeta = is_array($detail['offer'] ?? null) ? $detail['offer'] : [];
    $latestOverall = birkly_marketplace_latest_release_version($allReleases);
    $latestListed = birkly_marketplace_latest_release_version($listedReleases);
    $latestOverallStr = $latestOverall ?? $currentVersion;
    $updateAvailable = $latestOverall !== null && version_compare($latestOverall, $currentVersion, '>');
    $latestStability = birkly_marketplace_release_stability_from_releases($allReleases, $latestOverallStr, $offerMeta);
    $offerDistribution = birkly_marketplace_offer_distribution($offerMeta);

    $result = [
        'ok' => true,
        'latest_version' => $latestOverallStr,
        'latest_stability' => $latestStability,
        'offer_distribution' => $offerDistribution,
        'offer_distribution_notice' => birkly_marketplace_offer_distribution_notice($offerMeta),
        'update_available' => $updateAvailable,
        'current_version' => $currentVersion,
    ];
    if ($latestListed !== null && ($latestOverall === null || version_compare($latestListed, $latestOverall, '>'))) {
        $result['requires_newer_birkly'] = [
            'version' => $latestListed,
            'min_birkly' => birkly_marketplace_release_min_birkly($listedReleases, $latestListed),
        ];
    }

    $licenseRef = trim((string) ($licenseRef ?? ''));
    if ($licenseRef !== '') {
        $licenseStatus = birkly_marketplace_verify_license_status($slug, $licenseRef);
        if (!$licenseStatus['active']) {
            $result['license_lapsed'] = true;
            $result['license_warning'] = $licenseStatus['error'] ?? 'Marketplace license is inactive. Renew your subscription to receive updates.';
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
        $body,
        [],
        true
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
require_once __DIR__ . '/marketplace/template_pack.php';
require_once __DIR__ . '/marketplace/consent.php';
require_once __DIR__ . '/marketplace/pre_release.php';
require_once __DIR__ . '/marketplace/hub_licenses.php';
