<?php
/**
 * Durable Hub license bindings for this CMS installation.
 *
 * Stored under settings/ (DATA_ROOT) so CMS image/code deploys do not wipe them.
 * Full keys stay admin-local; marketplace remains the authority for validation.
 */

declare(strict_types=1);

/**
 * @return array<string, array<string, mixed>>
 */
function birkly_marketplace_load_hub_licenses(): array
{
    if (isset($GLOBALS['__birkly_hub_licenses']) && is_array($GLOBALS['__birkly_hub_licenses'])) {
        return $GLOBALS['__birkly_hub_licenses'];
    }

    require_once dirname(__DIR__, 2) . '/api/storage.php';
    $storage = new Storage(birkly_marketplace_settings_dir());
    $data = $storage->read('hub_licenses.json');
    if (!is_array($data)) {
        $data = [];
    }

    $GLOBALS['__birkly_hub_licenses'] = $data;
    return $data;
}

/**
 * @param array<string, array<string, mixed>> $data
 */
function birkly_marketplace_save_hub_licenses(array $data): void
{
    require_once dirname(__DIR__, 2) . '/api/storage.php';
    $storage = new Storage(birkly_marketplace_settings_dir());
    $storage->write('hub_licenses.json', $data);
    $GLOBALS['__birkly_hub_licenses'] = $data;
}

function birkly_marketplace_hub_licenses_reset(): void
{
    unset($GLOBALS['__birkly_hub_licenses']);
}

/**
 * Persist an activated license for this installation (pending or installed).
 *
 * @param array<string, mixed> $entitlement
 * @return array<string, mixed>
 */
function birkly_marketplace_remember_hub_license(
    string $offerSlug,
    string $licenseKey,
    string $entitlementToken = '',
    array $entitlement = [],
    string $title = '',
    string $version = '',
    ?string $pluginId = null
): array {
    $offerSlug = trim($offerSlug);
    $licenseKey = trim($licenseKey);
    if ($offerSlug === '' || $licenseKey === '') {
        return [];
    }

    $licenses = birkly_marketplace_load_hub_licenses();
    $row = [
        'offer_slug' => $offerSlug,
        'license_key' => $licenseKey,
        'license_ref' => birkly_marketplace_license_ref($licenseKey),
        'entitlement_token' => $entitlementToken,
        'entitlement' => $entitlement,
        'title' => $title !== '' ? $title : $offerSlug,
        'version' => $version,
        'plugin_id' => $pluginId,
        'updated_at' => gmdate('c'),
    ];
    if (!isset($licenses[$offerSlug]['activated_at'])) {
        $row['activated_at'] = gmdate('c');
    } else {
        $row['activated_at'] = (string) $licenses[$offerSlug]['activated_at'];
    }
    $licenses[$offerSlug] = $row;
    birkly_marketplace_save_hub_licenses($licenses);

    return $row;
}

function birkly_marketplace_forget_hub_license(string $offerSlug = '', string $licenseRef = ''): void
{
    $offerSlug = trim($offerSlug);
    $licenseRef = strtoupper(trim($licenseRef));
    $licenses = birkly_marketplace_load_hub_licenses();
    $changed = false;

    if ($offerSlug !== '' && isset($licenses[$offerSlug])) {
        unset($licenses[$offerSlug]);
        $changed = true;
    }

    if ($licenseRef !== '') {
        foreach ($licenses as $slug => $row) {
            if (!is_array($row)) {
                continue;
            }
            if (strtoupper((string) ($row['license_ref'] ?? '')) === $licenseRef) {
                unset($licenses[$slug]);
                $changed = true;
            }
        }
    }

    if ($changed) {
        birkly_marketplace_save_hub_licenses($licenses);
    }
}

/**
 * Bindings where the offer is not installed on this site yet.
 *
 * @return list<array<string, mixed>>
 */
function birkly_marketplace_pending_hub_licenses(): array
{
    $pending = [];
    foreach (birkly_marketplace_load_hub_licenses() as $slug => $row) {
        if (!is_array($row)) {
            continue;
        }
        $offerSlug = trim((string) ($row['offer_slug'] ?? $slug));
        if ($offerSlug === '') {
            continue;
        }
        if (function_exists('birkly_marketplace_local_installed_offer')
            && birkly_marketplace_local_installed_offer($offerSlug) !== null) {
            continue;
        }
        $pending[] = [
            'offer_slug' => $offerSlug,
            'license_key' => (string) ($row['license_key'] ?? ''),
            'license_ref' => (string) ($row['license_ref'] ?? ''),
            'title' => (string) ($row['title'] ?? $offerSlug),
            'version' => (string) ($row['version'] ?? ''),
            'entitlement' => is_array($row['entitlement'] ?? null) ? $row['entitlement'] : [],
            'activated_at' => (string) ($row['activated_at'] ?? ''),
        ];
    }

    return $pending;
}

/**
 * @return array<string, mixed>|null
 */
function birkly_marketplace_hub_license_for_offer(string $offerSlug): ?array
{
    $offerSlug = trim($offerSlug);
    if ($offerSlug === '') {
        return null;
    }
    $row = birkly_marketplace_load_hub_licenses()[$offerSlug] ?? null;
    return is_array($row) ? $row : null;
}
