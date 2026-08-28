<?php
/**
 * Analytics tracking config helpers (no HTTP headers — safe to include from other APIs).
 */

function birkly_analytics_dir(): string
{
    return dirname(__DIR__) . '/data/analytics';
}

function birkly_analytics_tracking_config_path(): string
{
    return birkly_analytics_dir() . '/tracking_ids.json';
}

function birkly_analytics_load_tracking_ids(): array
{
    $path = birkly_analytics_tracking_config_path();
    if (!is_file($path)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function birkly_analytics_save_tracking_ids(array $trackingIds): bool
{
    $dir = birkly_analytics_dir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return false;
    }

    $path = birkly_analytics_tracking_config_path();
    $json = json_encode($trackingIds, JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }

    return file_put_contents($path, $json, LOCK_EX) !== false;
}

/**
 * Enable or disable self-hosted analytics for a registered domain (tracking id = domain slug).
 */
function birkly_analytics_normalize_domain(string $domain): string
{
    $domain = strtolower(trim($domain));
    if ($domain === '') {
        return '';
    }

    return (string) (preg_replace('/:\d+$/', '', $domain) ?: $domain);
}

/**
 * Hostname variants that should share one analytics bucket (www ↔ apex).
 *
 * @return list<string>
 */
function birkly_analytics_domain_aliases(string $domain): array
{
    $domain = birkly_analytics_normalize_domain($domain);
    if ($domain === '') {
        return [];
    }

    $aliases = [$domain];
    if (str_starts_with($domain, 'www.')) {
        $base = substr($domain, 4);
        if ($base !== '') {
            $aliases[] = $base;
        }
    } elseif (!in_array($domain, ['localhost', '127.0.0.1', '::1'], true) && str_contains($domain, '.')) {
        $aliases[] = 'www.' . $domain;
    }

    return array_values(array_unique($aliases));
}

/**
 * Resolve a client tracking id to a licensed/enabled canonical id.
 */
function birkly_analytics_resolve_tracking_id(string $requestedId): ?string
{
    $requestedId = birkly_analytics_normalize_domain($requestedId);
    if ($requestedId === '') {
        return null;
    }

    $candidates = birkly_analytics_domain_aliases($requestedId);

    // Unify configured public-site hostnames (e.g. birkly.io alias → birkly.cloud primary).
    $hostingFile = __DIR__ . '/hosting_urls.php';
    if (is_file($hostingFile)) {
        require_once $hostingFile;
        if (function_exists('birkly_hosting_host_matches_public')
            && birkly_hosting_host_matches_public($requestedId)) {
            $canonical = birkly_hosting_public_host();
            if ($canonical !== '' && !in_array($canonical, $candidates, true)) {
                array_unshift($candidates, $canonical);
            }
        }
    }

    $trackingIds = birkly_analytics_load_tracking_ids();

    $licenseEnabled = [];
    if (class_exists('DomainAuth', false) || is_file(__DIR__ . '/../api/domain_auth.php')) {
        if (!class_exists('DomainAuth', false)) {
            require_once __DIR__ . '/../api/domain_auth.php';
        }
        try {
            $domainAuth = new DomainAuth();
            foreach ($domainAuth->getAllLicenses() as $domain => $license) {
                if (!empty($license['analytics_enabled'])) {
                    $licenseEnabled[birkly_analytics_normalize_domain((string) $domain)] = true;
                }
            }
        } catch (Throwable $e) {
            error_log('birkly_analytics_resolve_tracking_id licenses: ' . $e->getMessage());
        }
    }

    foreach ($candidates as $candidate) {
        if (!empty($licenseEnabled[$candidate])) {
            return $candidate;
        }
        if (isset($trackingIds[$candidate]) && !empty($trackingIds[$candidate]['enabled'])) {
            return $candidate;
        }
    }

    return null;
}

/**
 * Keep www and apex hostnames enabled together when one is turned on.
 */
function birkly_analytics_sync_domain_aliases(string $domain): void
{
    $domain = birkly_analytics_normalize_domain($domain);
    if ($domain === '') {
        return;
    }

    foreach (birkly_analytics_domain_aliases($domain) as $alias) {
        birkly_analytics_set_domain_tracking($alias, true);
    }
}

/**
 * @return list<string>
 */
function birkly_analytics_list_enabled_tracking_ids(): array
{
    $enabled = [];

    if (class_exists('DomainAuth', false) || is_file(__DIR__ . '/../api/domain_auth.php')) {
        if (!class_exists('DomainAuth', false)) {
            require_once __DIR__ . '/../api/domain_auth.php';
        }
        try {
            $domainAuth = new DomainAuth();
            foreach ($domainAuth->getAllLicenses() as $domain => $license) {
                if (!empty($license['analytics_enabled'])) {
                    $id = birkly_analytics_normalize_domain((string) $domain);
                    if ($id !== '') {
                        $enabled[$id] = true;
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('birkly_analytics_list_enabled_tracking_ids licenses: ' . $e->getMessage());
        }
    }

    foreach (birkly_analytics_load_tracking_ids() as $id => $cfg) {
        if (is_array($cfg) && !empty($cfg['enabled'])) {
            $enabled[birkly_analytics_normalize_domain((string) $id)] = true;
        }
    }

    $ids = array_keys($enabled);
    sort($ids, SORT_STRING);

    return $ids;
}

function birkly_analytics_set_domain_tracking(string $domain, bool $enabled): bool
{
    $domain = birkly_analytics_normalize_domain($domain);
    if ($domain === '') {
        return false;
    }

    $trackingIds = birkly_analytics_load_tracking_ids();

    if ($enabled) {
        $trackingIds[$domain] = [
            'id' => $domain,
            'name' => $domain,
            'domain' => $domain,
            'enabled' => true,
            'created_at' => $trackingIds[$domain]['created_at'] ?? date('c'),
            'updated_at' => date('c'),
        ];
    } elseif (isset($trackingIds[$domain])) {
        $trackingIds[$domain]['enabled'] = false;
        $trackingIds[$domain]['updated_at'] = date('c');
    }

    return birkly_analytics_save_tracking_ids($trackingIds);
}

/**
 * When a collecting preset is saved, ensure at least localhost + public/CMS hosts
 * can receive events (and flip analytics_enabled on registered domain licenses).
 *
 * @return list<string> Tracking ids that were enabled
 */
function birkly_analytics_ensure_site_tracking(): array
{
    $targets = ['localhost'];

    if (function_exists('birkly_cms_base_url')) {
        $cms = (string) birkly_cms_base_url();
        $host = $cms !== '' ? (string) (parse_url($cms, PHP_URL_HOST) ?? '') : '';
        if ($host !== '') {
            $targets[] = strtolower($host);
        }
    }
    if (function_exists('birkly_hosting_public_host')) {
        $host = strtolower(trim((string) birkly_hosting_public_host()));
        if ($host !== '') {
            $targets[] = $host;
        }
    } elseif (function_exists('birkly_hosting_public_base_url')) {
        $public = (string) birkly_hosting_public_base_url();
        $host = $public !== '' ? (string) (parse_url($public, PHP_URL_HOST) ?? '') : '';
        if ($host !== '') {
            $targets[] = strtolower($host);
        }
    }
    if (function_exists('birkly_hosting_cms_host')) {
        $host = strtolower(trim((string) birkly_hosting_cms_host()));
        if ($host !== '') {
            $targets[] = $host;
        }
    }
    $reqHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($reqHost !== '') {
        $targets[] = preg_replace('/:\d+$/', '', $reqHost) ?: $reqHost;
    }

    if (class_exists('DomainAuth', false) || is_file(__DIR__ . '/../api/domain_auth.php')) {
        if (!class_exists('DomainAuth', false)) {
            require_once __DIR__ . '/../api/domain_auth.php';
        }
        try {
            $domainAuth = new DomainAuth();
            foreach (array_keys($domainAuth->getAllLicenses()) as $domain) {
                $domain = strtolower(trim((string) $domain));
                if ($domain !== '') {
                    $targets[] = $domain;
                }
            }
            $targets = array_values(array_unique($targets));
            foreach ($targets as $domain) {
                if ($domain === 'localhost' || $domain === '127.0.0.1') {
                    continue;
                }
                $domainAuth->updateLicense($domain, [
                    'analytics_enabled' => true,
                    'updated_at' => date('c'),
                    'updated_by' => 'analytics_preset',
                ]);
            }
        } catch (Throwable $e) {
            error_log('birkly_analytics_ensure_site_tracking licenses: ' . $e->getMessage());
        }
    }

    $targets = array_values(array_unique(array_filter($targets)));
    $enabled = [];
    foreach ($targets as $id) {
        if (birkly_analytics_set_domain_tracking($id, true)) {
            $enabled[] = $id;
        }
    }
    return $enabled;
}
