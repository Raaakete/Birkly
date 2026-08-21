<?php
/**
 * Hosting URL resolver tests (P43 / P61-M1).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/hosting_urls.php';
require_once dirname(__DIR__) . '/core/project_site.php';
require_once dirname(__DIR__) . '/core/project_settings.php';

$failures = 0;
$hostingFile = birkly_hosting_settings_path();
$backup = is_readable($hostingFile) ? file_get_contents($hostingFile) : null;
$projectFile = birkly_project_root() . '/settings/project.json';
$projectBackup = is_readable($projectFile) ? file_get_contents($projectFile) : null;

function assert_true(string $name, bool $ok, string $detail = ''): void
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

function hosting_test_write(array $config): void
{
    birkly_hosting_reset_cache();
    birkly_hosting_persist($config, false);
    birkly_hosting_reset_cache();
}

try {
    hosting_test_write([
        'cms_base_url' => 'https://cms.birkly.cloud',
        'public_site_mode' => 'project',
        'public_site_url' => 'https://birkly.cloud',
        'same_host_landing' => 'admin',
        'strict_admin_host' => true,
    ]);

    $_SERVER['HTTP_HOST'] = 'cms.birkly.cloud';
    assert_true('cms host role', birkly_request_host_role() === 'cms');
    assert_true('cms host parsed', birkly_hosting_cms_host() === 'cms.birkly.cloud');
    assert_true('public host parsed', birkly_hosting_public_host() === 'birkly.cloud');

    $_SERVER['HTTP_HOST'] = 'birkly.cloud';
    assert_true('public host role', birkly_request_host_role() === 'public');
    assert_true('clean public url', birkly_public_site_url('/about.html') === 'https://birkly.cloud/about.html');
    assert_true(
        'auto visit home uses public root on clean URL host',
        birkly_public_site_visit_url(['visit_url_format' => 'auto']) === 'https://birkly.cloud/'
    );
    assert_true(
        'explicit index visit url keeps index.html',
        birkly_public_site_visit_url(['visit_url_format' => 'index']) === 'https://birkly.cloud/index.html'
    );
    assert_true('admin redirect on public host', birkly_hosting_should_redirect_admin() === true);
    assert_true(
        'admin canonical url',
        str_starts_with(birkly_hosting_admin_url('dashboard'), 'https://cms.birkly.cloud')
    );

    hosting_test_write([
        'cms_base_url' => 'https://cms.example.test',
        'public_site_mode' => 'none',
        'public_site_url' => '',
        'same_host_landing' => 'admin',
        'strict_admin_host' => true,
    ]);
    $_SERVER['HTTP_HOST'] = 'cms.example.test';
    assert_true('mode none root is admin', str_contains(birkly_hosting_root_redirect_url() ?? '', '/admin'));

    hosting_test_write([
        'cms_base_url' => 'https://mycompany.com',
        'public_site_mode' => 'project',
        'public_site_url' => 'https://mycompany.com',
        'same_host_landing' => 'public',
        'strict_admin_host' => true,
    ]);
    $_SERVER['HTTP_HOST'] = 'mycompany.com';
    assert_true('same host detected', birkly_hosting_same_host() === true);
    assert_true(
        'same host public landing',
        birkly_hosting_root_redirect_url() === 'https://mycompany.com/'
    );

    assert_true('normalize url strips slash', birkly_hosting_normalize_url('https://cms.test/') === 'https://cms.test');
    assert_true('clean project path', birkly_hosting_clean_public_url('/project/index.html') === '/index.html');

    $_SERVER['HTTP_HOST'] = 'localhost:8080';
    $_SERVER['HTTPS'] = 'off';
    unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
    assert_true(
        'request origin keeps non-default port',
        birkly_hosting_request_origin_url() === 'http://localhost:8080'
    );
    assert_true(
        'build url from request keeps port',
        birkly_hosting_build_url_from_request() === 'http://localhost:8080'
    );
    [$repaired, $changed] = birkly_hosting_repair_local_dev_urls([
        'cms_base_url' => 'http://localhost',
        'public_site_mode' => 'project',
        'public_site_url' => 'http://localhost',
        'same_host_landing' => 'admin',
        'strict_admin_host' => true,
    ]);
    assert_true('local port repair changes config', $changed === true);
    assert_true(
        'local port repair fixes public_site_url',
        ($repaired['public_site_url'] ?? '') === 'http://localhost:8080'
    );
    assert_true(
        'local port repair fixes cms_base_url',
        ($repaired['cms_base_url'] ?? '') === 'http://localhost:8080'
    );

    // P61-B10: external mode must not rewrite public_site_url to the local CMS port
    [$externalRepaired] = birkly_hosting_repair_local_dev_urls([
        'cms_base_url' => 'http://localhost',
        'public_site_mode' => 'external',
        'public_site_url' => 'https://www.example.com',
        'same_host_landing' => 'admin',
        'strict_admin_host' => true,
    ]);
    assert_true(
        'external mode still repairs cms_base_url',
        ($externalRepaired['cms_base_url'] ?? '') === 'http://localhost:8080'
    );
    assert_true(
        'external mode skips public_site_url port repair',
        ($externalRepaired['public_site_url'] ?? '') === 'https://www.example.com'
    );

    [$externalLocal] = birkly_hosting_repair_local_dev_urls([
        'cms_base_url' => 'http://localhost',
        'public_site_mode' => 'external',
        'public_site_url' => 'http://localhost:5173',
        'same_host_landing' => 'admin',
        'strict_admin_host' => true,
    ]);
    assert_true(
        'external repair leaves local public port alone',
        ($externalLocal['public_site_url'] ?? '') === 'http://localhost:5173'
    );

    // P61-B1: persist syncs project.json hosting_type from public_site_mode
    birkly_save_project_settings(['hosting_type' => 'birkly']);
    hosting_test_write([
        'cms_base_url' => 'https://cms.example.test',
        'public_site_mode' => 'external',
        'public_site_url' => 'https://www.example.test',
        'same_host_landing' => 'admin',
        'strict_admin_host' => true,
    ]);
    $afterExternal = birkly_load_project_settings();
    assert_true(
        'persist external syncs hosting_type=external',
        ($afterExternal['hosting_type'] ?? '') === 'external'
    );
    hosting_test_write([
        'cms_base_url' => 'https://cms.example.test',
        'public_site_mode' => 'project',
        'public_site_url' => 'https://www.example.test',
        'same_host_landing' => 'admin',
        'strict_admin_host' => true,
    ]);
    $afterProject = birkly_load_project_settings();
    assert_true(
        'persist project syncs hosting_type=birkly',
        ($afterProject['hosting_type'] ?? '') === 'birkly'
    );

    // P61-B3: client snippet includes data-cms-url + absolute CMS URL
    hosting_test_write([
        'cms_base_url' => 'https://cms.example.test',
        'public_site_mode' => 'external',
        'public_site_url' => 'https://www.example.test',
        'same_host_landing' => 'admin',
        'strict_admin_host' => true,
    ]);
    $snippet = birkly_hosting_client_snippet();
    assert_true('client snippet non-empty', $snippet !== '');
    assert_true('client snippet has data-cms-url', str_contains($snippet, 'data-cms-url="https://cms.example.test"'));
    assert_true(
        'client snippet absolute script src',
        str_contains($snippet, 'https://cms.example.test') && str_contains($snippet, 'birkly-client.js')
    );

    putenv('BIRKLY_TRUST_REQUEST_HOST=true');
    putenv('BIRKLY_CMS_BASE_URL=https://test.birkly.cloud');
    birkly_hosting_reset_cache();
    $_SERVER['HTTP_HOST'] = 'test.birkly.cloud';
    assert_true('trust request host treats staging as cms', birkly_request_host_role() === 'cms');
    assert_true(
        'env cms base url override',
        birkly_hosting_cms_host() === 'test.birkly.cloud'
    );
    assert_true(
        'unknown host does not redirect to prod cms',
        str_contains(birkly_hosting_admin_url('dashboard'), 'test.birkly.cloud')
    );
    putenv('BIRKLY_TRUST_REQUEST_HOST');
    putenv('BIRKLY_CMS_BASE_URL');
    birkly_hosting_reset_cache();

    $_SERVER['HTTP_HOST'] = 'cms.legacy.test';
    $_SERVER['HTTPS'] = 'on';
    birkly_hosting_reset_cache();
    if (is_file($hostingFile)) {
        unlink($hostingFile);
    }
    $adminFile = dirname(__DIR__) . '/settings/admin.json';
    $adminBackup = is_readable($adminFile) ? file_get_contents($adminFile) : null;
    if (is_readable($adminFile)) {
        $adminData = json_decode((string) file_get_contents($adminFile), true);
        if (is_array($adminData)) {
            unset($adminData['cms_url']);
            file_put_contents($adminFile, json_encode($adminData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        }
    }
    $migrated = birkly_hosting_migrate_legacy();
    assert_true('legacy cms.* migration infers cms url', str_contains((string) ($migrated['cms_base_url'] ?? ''), 'cms.legacy.test'));
    if ($adminBackup !== null) {
        file_put_contents($adminFile, $adminBackup);
    }

    echo "\n[P66] multi-domain aliases\n";
    hosting_test_write([
        'schema_version' => 2,
        'cms_base_url' => 'https://cms.example.com',
        'cms_host_aliases' => ['cms-alt.example.com'],
        'public_site_mode' => 'project',
        'public_site_url' => 'https://www.example.com',
        'public_host_aliases' => ['example.com'],
        'alias_redirect_to_primary' => true,
        'same_host_landing' => 'admin',
        'strict_admin_host' => true,
    ]);

    $_SERVER['HTTP_HOST'] = 'cms-alt.example.com';
    assert_true('cms alias host role', birkly_request_host_role() === 'cms');
    assert_true('cms alias match helper', birkly_hosting_host_matches_cms('cms-alt.example.com') === true);
    assert_true('cms alias detected', birkly_hosting_is_cms_alias_host('cms-alt.example.com') === true);

    $_SERVER['HTTP_HOST'] = 'example.com';
    assert_true('public alias host role', birkly_request_host_role() === 'public');
    assert_true('public alias match helper', birkly_hosting_host_matches_public('example.com') === true);
    require_once dirname(__DIR__) . '/core/project_site.php';
    $rewritten = birkly_project_rewrite_cross_host_urls(
        '<link rel="icon" href="https://www.example.com/media/logo.png">'
    );
    assert_true(
        'cross-host rewrite makes favicon relative',
        str_contains($rewritten, 'href="/media/logo.png"')
    );

    $_SERVER['HTTP_HOST'] = 'unknown.example.com';
    assert_true('unknown host role with aliases configured', birkly_request_host_role() === 'unknown');

    require_once dirname(__DIR__) . '/core/hosting_probe.php';
    $probe = birkly_delivery_probe(['deep' => false]);
    assert_true('delivery probe returns overall status', in_array($probe['overall'] ?? '', ['green', 'yellow', 'red'], true));
    assert_true('delivery probe includes checks', is_array($probe['checks'] ?? null) && count($probe['checks']) > 0);

    echo "\n[same-host alias overlap]\n";
    hosting_test_write([
        'schema_version' => 2,
        'cms_base_url' => 'https://site.example.com',
        'cms_host_aliases' => ['www.site.example.com'],
        'public_site_mode' => 'project',
        'public_site_url' => 'https://site.example.com',
        'public_host_aliases' => ['www.site.example.com'],
        'same_host_landing' => 'admin',
        'strict_admin_host' => true,
    ]);
    $overlap = birkly_hosting_overlapping_delivery_aliases();
    assert_true('detect overlapping aliases', $overlap === ['www.site.example.com']);
    $probeOverlap = birkly_delivery_probe(['deep' => false]);
    $overlapCodes = array_column($probeOverlap['checks'] ?? [], 'code');
    assert_true('probe warns on alias overlap', in_array('ALIAS_ON_BOTH_ROLES', $overlapCodes, true));
    [$deduped, $changed, $removed] = birkly_hosting_deduplicate_overlapping_aliases(birkly_hosting_load());
    assert_true('dedupe removes overlap from public aliases', $changed === true && $removed === ['www.site.example.com']);
    assert_true(
        'public aliases empty after dedupe',
        birkly_hosting_public_alias_hosts($deduped) === []
    );

    require_once dirname(__DIR__) . '/core/project_site.php';
    $demoIndex = birkly_public_site_path() . '/index.html';
    $demoIndexExisted = is_file($demoIndex);
    $demoIndexBackup = $demoIndexExisted ? (string) file_get_contents($demoIndex) : null;
    file_put_contents($demoIndex, '<!doctype html><title>Birkly - Demo Website</title>');
    assert_true(
        'bundled demo site detected',
        birkly_project_is_bundled_demo_site() === true
    );
    if ($demoIndexBackup !== null) {
        file_put_contents($demoIndex, $demoIndexBackup);
    } elseif (is_file($demoIndex)) {
        unlink($demoIndex);
    }

    echo "\n[repair bundled demo + redirects]\n";
    $demoIndex = birkly_public_site_path() . '/index.html';
    $demoStyles = birkly_public_site_path() . '/styles.css';
    @mkdir(dirname($demoIndex), 0755, true);
    file_put_contents($demoIndex, '<!doctype html><title>Birkly - Demo Website</title>');
    file_put_contents($demoStyles, '/* Modern Demo Website Styles */');
    $bundledRepair = birkly_project_repair_bundled_demo_site(true);
    assert_true('repair removes bundled demo html', !is_file($demoIndex));
    assert_true('repair removes bundled demo css', !is_file($demoStyles));
    assert_true('repair reports bundled demo files', ($bundledRepair['changed'] ?? false) === true);

    $redirectsFile = birkly_redirects_settings_path();
    $redirectsBackup = is_readable($redirectsFile) ? file_get_contents($redirectsFile) : null;
    hosting_test_write([
        'schema_version' => 2,
        'cms_base_url' => 'https://site.example.com',
        'cms_host_aliases' => ['www.site.example.com'],
        'public_site_mode' => 'project',
        'public_site_url' => 'https://site.example.com',
        'public_host_aliases' => [],
        'same_host_landing' => 'admin',
        'strict_admin_host' => true,
    ]);
    file_put_contents($redirectsFile, json_encode([[
        'id' => 'bad-project-redirect',
        'from_path' => '/project',
        'to_path' => 'https://www.site.example.com/project',
        'type' => 301,
        'enabled' => true,
    ]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    require_once dirname(__DIR__) . '/core/hosting_redirects.php';
    assert_true('detect harmful /project redirect', count(birkly_hosting_harmful_project_redirects()) === 1);
    $redirectRepair = birkly_hosting_repair_harmful_redirects(true);
    assert_true('repair disables harmful redirect', ($redirectRepair['changed'] ?? false) === true);
    $redirectRules = birkly_load_redirects();
    assert_true('redirect rule disabled', ($redirectRules[0]['enabled'] ?? true) === false);
    if ($redirectsBackup !== null) {
        file_put_contents($redirectsFile, $redirectsBackup);
    } elseif (is_file($redirectsFile)) {
        unlink($redirectsFile);
    }

    echo "\n[repair SSR cache]\n";
    require_once dirname(__DIR__) . '/core/project_ssr.php';
    $cacheDir = birkly_project_ssr_cache_dir();
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    $cacheMarker = $cacheDir . '/repair-test.html';
    file_put_contents($cacheMarker, '<html>stale</html>');
    $repair = birkly_hosting_repair_delivery(true, false);
    assert_true('repair clears SSR cache', !is_file($cacheMarker));
    assert_true('repair reports SSR invalidation', ($repair['ssr_invalidated'] ?? false) === true);
} finally {
    birkly_hosting_reset_cache();
    if ($backup !== null) {
        file_put_contents($hostingFile, $backup);
    } elseif (is_file($hostingFile)) {
        unlink($hostingFile);
    }
    if ($projectBackup !== null) {
        file_put_contents($projectFile, $projectBackup);
    }
}

echo "\nhosting_urls_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
