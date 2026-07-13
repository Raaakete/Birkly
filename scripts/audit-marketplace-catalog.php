<?php
/**
 * P28-M16 — Marketplace catalog schema + installable official offers audit.
 *
 * Invoked by scripts/audit-marketplace-catalog.sh
 */

declare(strict_types=1);

$marketplaceRoot = $argv[1] ?? '';
if ($marketplaceRoot === '' || !is_dir($marketplaceRoot)) {
    fwrite(STDERR, "Usage: php audit-marketplace-catalog.php /path/to/Birkly-Marketplace\n");
    exit(1);
}

require_once $marketplaceRoot . '/core/bootstrap.php';

$catalogDir = $marketplaceRoot . '/catalog';
$offerFiles = glob($catalogDir . '/offers/*.json') ?: [];
sort($offerFiles);

$failures = 0;
$installable = [];
$gaps = [];

echo "Catalog audit\n";
echo str_repeat('-', 40) . "\n";

foreach ($offerFiles as $offerFile) {
    $offer = marketplace_read_json_file($offerFile, []);
    if (!is_array($offer)) {
        echo "FAIL: invalid JSON {$offerFile}\n";
        $failures++;
        continue;
    }

    $slug = (string) ($offer['slug'] ?? basename($offerFile, '.json'));
    $manifest = $offer;
    unset($manifest['published_at'], $manifest['updated_at']);
    $errors = marketplace_validate_offer_manifest($manifest);
    if ($errors !== []) {
        echo "FAIL: {$slug} schema errors\n";
        foreach ($errors as $error) {
            echo "  - {$error}\n";
        }
        $failures++;
        continue;
    }

    echo "PASS: {$slug} validates against offer-manifest.schema.json\n";

    $providerId = (string) ($offer['provider_id'] ?? '');
    $isOfficial = $providerId === 'prov_official_birkly';
    $status = (string) ($offer['status'] ?? 'draft');
    $latest = (string) ($offer['releases'][0]['version'] ?? $offer['latest_version'] ?? '');
    $package = $catalogDir . '/packages/' . $slug . '-' . $latest . '.zip';
    $hasPackage = $latest !== '' && is_file($package);

    if ($isOfficial && $status === 'published') {
        if ($hasPackage) {
            $installable[] = $slug;
            echo "  installable official offer: {$slug} @ {$latest}\n";
        } else {
            $gaps[] = "{$slug}: missing package {$package}";
            echo "  GAP: official published offer missing artifact zip\n";
            $failures++;
        }
    } elseif ($isOfficial) {
        echo "  note: official offer status={$status}\n";
    }
}

$indexPath = $catalogDir . '/index.json';
if (is_file($indexPath)) {
    $index = marketplace_read_json_file($indexPath, []);
    $listed = array_map(static fn ($row) => (string) ($row['slug'] ?? ''), $index['offers'] ?? []);
    foreach ($offerFiles as $offerFile) {
        $offer = marketplace_read_json_file($offerFile, []);
        $slug = (string) ($offer['slug'] ?? '');
        if ($slug !== '' && !in_array($slug, $listed, true)) {
            echo "GAP: {$slug} missing from catalog/index.json\n";
            $gaps[] = "{$slug}: not listed in catalog index";
            $failures++;
        }
    }
}

echo str_repeat('-', 40) . "\n";
echo 'Official installable offers: ' . count($installable) . "\n";
if ($installable !== []) {
    echo '  - ' . implode("\n  - ", $installable) . "\n";
}
if ($gaps !== []) {
    echo "Gaps:\n";
    foreach ($gaps as $gap) {
        echo "  - {$gap}\n";
    }
}

if ($failures === 0) {
    echo "catalog-audit: PASS\n";
    exit(0);
}

echo "catalog-audit: FAIL ({$failures} issue(s))\n";
exit(1);
