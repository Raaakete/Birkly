<?php
/**
 * Hosting URL resolver tests (P43).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/hosting_urls.php';
require_once dirname(__DIR__) . '/core/project_site.php';

$failures = 0;
$hostingFile = birkly_hosting_settings_path();
$backup = is_readable($hostingFile) ? file_get_contents($hostingFile) : null;

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
} finally {
    birkly_hosting_reset_cache();
    if ($backup !== null) {
        file_put_contents($hostingFile, $backup);
    } elseif (is_file($hostingFile)) {
        unlink($hostingFile);
    }
}

echo "\nhosting_urls_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
