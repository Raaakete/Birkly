#!/usr/bin/env php
<?php
/**
 * Rebind hosting settings to a CMS hostname (e.g. test.birkly.cloud).
 *
 * Usage:
 *   php scripts/repair-hosting-domain.php https://test.birkly.cloud
 *   php scripts/repair-hosting-domain.php --cms-url=https://test.birkly.cloud --public-mode=none
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/hosting_urls.php';

function repair_usage(): void
{
    fwrite(STDERR, "Usage: php scripts/repair-hosting-domain.php <cms-url>\n");
    fwrite(STDERR, "   or: php scripts/repair-hosting-domain.php --cms-url=<url> [--public-url=<url>] [--public-mode=none|project|external]\n");
}

$cmsUrl = trim((string) (getenv('BIRKLY_REPAIR_CMS_URL') ?: ''));
$publicUrl = trim((string) (getenv('BIRKLY_REPAIR_PUBLIC_URL') ?: ''));
$publicMode = trim((string) (getenv('BIRKLY_REPAIR_PUBLIC_MODE') ?: ''));

$args = array_slice($argv, 1);
foreach ($args as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        repair_usage();
        exit(0);
    }
    if (str_starts_with($arg, '--cms-url=')) {
        $cmsUrl = substr($arg, strlen('--cms-url='));
        continue;
    }
    if (str_starts_with($arg, '--public-url=')) {
        $publicUrl = substr($arg, strlen('--public-url='));
        continue;
    }
    if (str_starts_with($arg, '--public-mode=')) {
        $publicMode = substr($arg, strlen('--public-mode='));
        continue;
    }
    if ($cmsUrl === '' && $arg !== '') {
        $cmsUrl = $arg;
    }
}

$cmsUrl = birkly_hosting_normalize_url($cmsUrl, false);
if ($cmsUrl === '') {
    repair_usage();
    fwrite(STDERR, "Error: a valid CMS URL is required.\n");
    exit(1);
}

$config = array_merge(birkly_hosting_load(), [
    'cms_base_url' => $cmsUrl,
]);

if ($publicMode !== '') {
    $config['public_site_mode'] = birkly_hosting_normalize_mode($publicMode);
}

if ($publicUrl !== '') {
    $config['public_site_url'] = birkly_hosting_normalize_url($publicUrl, true);
    if ($config['public_site_mode'] === 'none') {
        $config['public_site_mode'] = 'project';
    }
} elseif ($config['public_site_mode'] !== 'none') {
    $cmsHost = birkly_hosting_url_host($cmsUrl);
    $publicHost = birkly_hosting_url_host((string) ($config['public_site_url'] ?? ''));
    if ($publicHost !== '' && $publicHost !== $cmsHost) {
        $config['public_site_mode'] = 'none';
        $config['public_site_url'] = '';
    } else {
        $config['public_site_url'] = $cmsUrl;
        $config['same_host_landing'] = 'admin';
    }
}

if (!birkly_hosting_persist($config, true)) {
    fwrite(STDERR, "Error: failed to write hosting settings.\n");
    exit(1);
}

echo "Hosting repaired.\n";
echo '  cms_base_url: ' . $config['cms_base_url'] . PHP_EOL;
echo '  public_site_mode: ' . $config['public_site_mode'] . PHP_EOL;
echo '  public_site_url: ' . ($config['public_site_url'] ?? '') . PHP_EOL;
