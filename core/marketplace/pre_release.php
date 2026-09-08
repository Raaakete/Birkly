<?php
/**
 * Marketplace pre-release install acknowledgment (P84).
 */

declare(strict_types=1);

/**
 * @return array<string, array<string, array<string, mixed>>>
 */
function birkly_marketplace_load_pre_release_ack(): array
{
    if (isset($GLOBALS['__birkly_marketplace_pre_release_ack'])) {
        return $GLOBALS['__birkly_marketplace_pre_release_ack'];
    }

    require_once dirname(__DIR__, 2) . '/api/storage.php';
    $storage = new Storage(birkly_marketplace_settings_dir());
    $data = $storage->read('marketplace_pre_release_ack.json');
    if (!is_array($data)) {
        $data = [];
    }

    $GLOBALS['__birkly_marketplace_pre_release_ack'] = $data;
    return $data;
}

/**
 * @param array<string, array<string, array<string, mixed>>> $data
 */
function birkly_marketplace_save_pre_release_ack(array $data): void
{
    require_once dirname(__DIR__, 2) . '/api/storage.php';
    $storage = new Storage(birkly_marketplace_settings_dir());
    $storage->write('marketplace_pre_release_ack.json', $data);
    $GLOBALS['__birkly_marketplace_pre_release_ack'] = $data;
}

function birkly_marketplace_pre_release_ack_reset(): void
{
    unset($GLOBALS['__birkly_marketplace_pre_release_ack']);
}

function birkly_marketplace_release_requires_pre_release_ack(string $stability): bool
{
    $stability = birkly_marketplace_normalize_release_stability($stability);

    return in_array($stability, ['beta', 'alpha'], true);
}

function birkly_marketplace_has_pre_release_ack(string $slug, string $version): bool
{
    $slug = trim($slug);
    $version = trim($version);
    if ($slug === '' || $version === '') {
        return false;
    }

    $records = birkly_marketplace_load_pre_release_ack();
    $entry = $records[$slug][$version] ?? null;

    return is_array($entry) && !empty($entry['accepted_at']);
}

function birkly_marketplace_record_pre_release_ack(
    string $slug,
    string $version,
    string $stability,
    ?string $acceptedBy = null
): void {
    $slug = trim($slug);
    $version = trim($version);
    if ($slug === '' || $version === '') {
        return;
    }

    $records = birkly_marketplace_load_pre_release_ack();
    if (!isset($records[$slug]) || !is_array($records[$slug])) {
        $records[$slug] = [];
    }

    $records[$slug][$version] = [
        'accepted_at' => gmdate('c'),
        'accepted_by' => $acceptedBy ?? ($_SESSION['user']['email'] ?? 'unknown'),
        'stability' => birkly_marketplace_normalize_release_stability($stability),
    ];

    birkly_marketplace_save_pre_release_ack($records);
}

/**
 * @return array{ok:bool,error?:string,code?:string,stability?:string}
 */
function birkly_marketplace_require_pre_release_ack(
    string $slug,
    string $version,
    string $stability,
    bool $ackAccepted = false
): array {
    $stability = birkly_marketplace_normalize_release_stability($stability);
    if (!birkly_marketplace_release_requires_pre_release_ack($stability)) {
        return ['ok' => true, 'stability' => $stability];
    }

    if ($ackAccepted || birkly_marketplace_has_pre_release_ack($slug, $version)) {
        return ['ok' => true, 'stability' => $stability];
    }

    return [
        'ok' => false,
        'error' => 'Pre-release acknowledgment is required before installing or updating this version',
        'code' => 'pre_release_ack_required',
        'stability' => $stability,
    ];
}

/**
 * @return array{ok:bool,error?:string,code?:string,stability?:string,version?:string}
 */
function birkly_marketplace_gate_pre_release_ack(string $slug, string $version, bool $ackAccepted = false): array
{
    $detail = birkly_marketplace_offer_detail($slug);
    if (!$detail['ok']) {
        return [
            'ok' => false,
            'error' => $detail['error'] ?? 'Offer not found',
            'code' => 'offer_not_found',
        ];
    }

    $releases = is_array($detail['releases'] ?? null) ? $detail['releases'] : [];
    $offer = is_array($detail['offer'] ?? null) ? $detail['offer'] : [];
    $stability = birkly_marketplace_release_stability_from_releases($releases, $version, $offer);
    $check = birkly_marketplace_require_pre_release_ack($slug, $version, $stability, $ackAccepted);
    $check['version'] = $version;
    $check['stability'] = $stability;

    return $check;
}

function birkly_marketplace_finalize_pre_release_ack(
    string $slug,
    string $version,
    string $stability,
    bool $ackAccepted
): void {
    if (!$ackAccepted || !birkly_marketplace_release_requires_pre_release_ack($stability)) {
        return;
    }

    if (birkly_marketplace_has_pre_release_ack($slug, $version)) {
        return;
    }

    birkly_marketplace_record_pre_release_ack($slug, $version, $stability);
    if (function_exists('log_security_event')) {
        log_security_event('marketplace_pre_release_ack', [
            'slug' => $slug,
            'version' => $version,
            'stability' => birkly_marketplace_normalize_release_stability($stability),
            'user' => $_SESSION['user']['email'] ?? 'unknown',
        ]);
    }
}

/**
 * Resolve default install version for the site update channel.
 *
 * @return array{ok:bool,version?:string,stability?:string,channel?:string,error?:string}
 */
function birkly_marketplace_resolve_channel_install_version(string $slug, string $version = ''): array
{
    $detail = birkly_marketplace_offer_detail($slug);
    if (!$detail['ok']) {
        return ['ok' => false, 'error' => $detail['error'] ?? 'Offer not found'];
    }

    $channel = birkly_marketplace_update_channel();
    $releases = is_array($detail['releases'] ?? null) ? $detail['releases'] : [];
    $offer = is_array($detail['offer'] ?? null) ? $detail['offer'] : [];
    $targetVersion = trim($version);
    if ($targetVersion === '') {
        $targetVersion = birkly_marketplace_latest_release_version(
            birkly_marketplace_releases_for_channel($releases, $channel, $offer)
        ) ?? '';
        if ($targetVersion === '') {
            return ['ok' => false, 'error' => 'No releases available for this offer'];
        }
    }

    return [
        'ok' => true,
        'version' => $targetVersion,
        'stability' => birkly_marketplace_release_stability_from_releases($releases, $targetVersion, $offer),
        'channel' => $channel,
    ];
}
