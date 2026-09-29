<?php
/**
 * Analytics config / domain resolution tests.
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/analytics_config.php';

$failures = 0;

function ac_assert(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    echo $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '  ' . $detail . "\n";
        }
    }
}

ac_assert('normalize strips port', birkly_analytics_normalize_domain('Example.COM:443') === 'example.com');
ac_assert('aliases include www', in_array('www.example.com', birkly_analytics_domain_aliases('example.com'), true));
ac_assert('aliases include apex from www', in_array('example.com', birkly_analytics_domain_aliases('www.example.com'), true));

birkly_analytics_set_domain_tracking('example.com', true);
ac_assert('resolve apex', birkly_analytics_resolve_tracking_id('example.com') === 'example.com');
ac_assert('resolve www to enabled apex', birkly_analytics_resolve_tracking_id('www.example.com') === 'example.com');

$ids = birkly_analytics_list_enabled_tracking_ids();
ac_assert('enabled list contains example.com', in_array('example.com', $ids, true));

birkly_analytics_set_domain_tracking('example.com', false);
@unlink(dirname(__DIR__) . '/data/analytics/tracking_ids.json');

// Public-site alias → canonical host (when hosting config is present).
require_once dirname(__DIR__) . '/core/hosting_urls.php';
$hostingConfigPath = dirname(__DIR__) . '/settings/hosting.json';
$hostingBackup = is_file($hostingConfigPath) ? (string) file_get_contents($hostingConfigPath) : null;
$hostingFixture = json_encode([
    'public_site_mode' => 'project',
    'public_site_url' => 'https://birkly.cloud',
    'public_host_aliases' => ['birkly.io'],
    'cms_base_url' => 'https://cms.birkly.cloud',
], JSON_PRETTY_PRINT);
if ($hostingFixture !== false) {
    file_put_contents($hostingConfigPath, $hostingFixture);
    if (function_exists('birkly_hosting_reset_cache')) {
        birkly_hosting_reset_cache();
    }
    birkly_analytics_set_domain_tracking('birkly.cloud', true);
    ac_assert(
        'resolve public alias to canonical host',
        birkly_analytics_resolve_tracking_id('birkly.io') === 'birkly.cloud'
    );
    birkly_analytics_set_domain_tracking('birkly.cloud', false);
    if ($hostingBackup !== null) {
        file_put_contents($hostingConfigPath, $hostingBackup);
    } else {
        @unlink($hostingConfigPath);
    }
    if (function_exists('birkly_hosting_reset_cache')) {
        birkly_hosting_reset_cache();
    }
}

exit($failures > 0 ? 1 : 0);
