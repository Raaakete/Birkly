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
function birkly_analytics_set_domain_tracking(string $domain, bool $enabled): bool
{
    $domain = strtolower(trim($domain));
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
