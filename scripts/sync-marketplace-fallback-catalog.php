<?php
/**
 * Refresh catalog/marketplace-registry-fallback.json from the live registry API.
 *
 * Usage: php scripts/sync-marketplace-fallback-catalog.php [--url=https://marketplace.birkly.cloud]
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/marketplace/http.php';
require_once $root . '/core/marketplace.php';

$manifestPath = $root . '/scripts/cms-release-manifest.json';
$manifest = is_file($manifestPath)
    ? json_decode((string) file_get_contents($manifestPath), true)
    : [];
$officialSlugs = [];
if (is_array($manifest['official_marketplace_slugs'] ?? null)) {
    foreach ($manifest['official_marketplace_slugs'] as $slug) {
        if (is_string($slug) && $slug !== '') {
            $officialSlugs[] = $slug;
        }
    }
}
if ($officialSlugs === []) {
    $officialSlugs = [
        'birkly-commerce',
        'birkly-events',
        'birkly-automation',
        'birkly-user-management',
        'birkly-studio',
    ];
}

$baseUrl = rtrim((string) ($argv[1] ?? getenv('BIRKLY_MARKETPLACE_URL') ?: 'https://marketplace.birkly.cloud'), '/');
if (str_starts_with($baseUrl, '--url=')) {
    $baseUrl = rtrim(substr($baseUrl, 6), '/');
}

$fetch = birkly_marketplace_http_fetch($baseUrl . '/api/v1/registry', 'GET', ['Accept: application/json'], null, 30);
if (!$fetch['ok']) {
    fwrite(STDERR, 'Registry fetch failed: ' . ($fetch['error'] ?? 'unknown error') . PHP_EOL);
    exit(1);
}

$data = json_decode((string) ($fetch['body'] ?? ''), true);
if (!is_array($data)) {
    fwrite(STDERR, 'Registry response was not valid JSON' . PHP_EOL);
    exit(1);
}

$offers = [];
foreach ($data['offers'] ?? [] as $offer) {
    if (!is_array($offer)) {
        continue;
    }
    $slug = (string) ($offer['slug'] ?? '');
    if ($slug === '' || !in_array($slug, $officialSlugs, true)) {
        continue;
    }

    $detailFetch = birkly_marketplace_http_fetch(
        $baseUrl . '/api/v1/offers/' . rawurlencode($slug),
        'GET',
        ['Accept: application/json'],
        null,
        30
    );
    if ($detailFetch['ok']) {
        $detail = json_decode((string) ($detailFetch['body'] ?? ''), true);
        if (is_array($detail)) {
            $releases = is_array($detail['releases'] ?? null) ? $detail['releases'] : [];
            $offer['releases'] = array_slice($releases, 0, 8);
            $latest = birkly_marketplace_latest_release_version($releases);
            if ($latest !== null) {
                $offer['latest_version'] = $latest;
            }
        }
    }

    $offers[] = $offer;
}

usort($offers, static fn (array $a, array $b): int => strcmp((string) ($a['slug'] ?? ''), (string) ($b['slug'] ?? '')));

$payload = [
    'version' => 1,
    'updated_at' => gmdate('Y-m-d'),
    'source' => $baseUrl,
    'offers' => $offers,
];

$outPath = $root . '/catalog/marketplace-registry-fallback.json';
$encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($encoded === false || file_put_contents($outPath, $encoded . PHP_EOL) === false) {
    fwrite(STDERR, 'Failed to write ' . $outPath . PHP_EOL);
    exit(1);
}

echo 'Wrote ' . count($offers) . ' official offers to ' . $outPath . PHP_EOL;

$packagesDir = $root . '/catalog/packages';
if (!is_dir($packagesDir) && !mkdir($packagesDir, 0755, true)) {
    fwrite(STDERR, 'Failed to create ' . $packagesDir . PHP_EOL);
    exit(1);
}

$marketplaceRoot = dirname($root) . '/Birkly-Marketplace';
$sourcePackages = is_dir($marketplaceRoot . '/catalog/packages')
    ? $marketplaceRoot . '/catalog/packages'
    : '';

$copied = 0;
foreach ($offers as $offer) {
    if (!is_array($offer)) {
        continue;
    }
    $slug = trim((string) ($offer['slug'] ?? ''));
    $version = trim((string) ($offer['latest_version'] ?? ''));
    if ($slug === '' || $version === '') {
        continue;
    }

    $filename = $slug . '-' . $version . '.zip';
    $dest = $packagesDir . '/' . $filename;
    $source = $sourcePackages !== '' ? $sourcePackages . '/' . $filename : '';
    if ($source !== '' && is_file($source)) {
        if (@copy($source, $dest)) {
            $copied++;
            continue;
        }
    }

    $downloadUrl = $baseUrl . '/api/v1/artifacts/' . rawurlencode($slug) . '/' . rawurlencode($version) . '/download';
    $artifactFetch = birkly_marketplace_http_fetch($downloadUrl, 'GET', ['User-Agent: Birkly-CMS'], null, 120);
    if ($artifactFetch['ok'] && is_string($artifactFetch['body'] ?? null) && $artifactFetch['body'] !== '') {
        if (file_put_contents($dest, $artifactFetch['body']) !== false) {
            $copied++;
        }
    }
}

echo 'Synced ' . $copied . ' bundled plugin packages to ' . $packagesDir . PHP_EOL;
