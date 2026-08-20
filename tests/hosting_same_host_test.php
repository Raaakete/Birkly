<?php
/**
 * P66 — same-host public landing routing + clean path rewrites.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/hosting_urls.php';
require_once dirname(__DIR__) . '/core/project_site.php';
require_once dirname(__DIR__) . '/core/hosting_routing.php';

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

function same_host_test_write(array $config): void
{
    birkly_hosting_reset_cache();
    birkly_hosting_persist($config, false);
    birkly_hosting_reset_cache();
}

try {
    $_SERVER['HTTP_HOST'] = 'test.birkly.cloud';
    $_SERVER['HTTPS'] = 'on';
    unset($_SERVER['HTTP_X_FORWARDED_PROTO']);

    same_host_test_write([
        'schema_version' => 2,
        'cms_base_url' => 'https://test.birkly.cloud',
        'public_site_mode' => 'project',
        'public_site_url' => 'https://test.birkly.cloud',
        'same_host_landing' => 'public',
        'strict_admin_host' => true,
    ]);

    assert_true('same host detected', birkly_hosting_same_host() === true);
    assert_true('cms role on shared host', birkly_request_host_role() === 'cms');
    assert_true(
        'cms role can serve public root on same host',
        birkly_hosting_role_can_serve_public_project('cms', '/') === true
    );
    assert_true(
        'resolve index on same-host cms',
        birkly_hosting_resolve_public_file('/') !== null
    );
    assert_true(
        'resolve clean css path on same-host cms',
        birkly_hosting_resolve_public_file('/styles.css') !== null
            || birkly_hosting_resolve_public_file('/project/styles.css') !== null
    );

    $sample = '<link rel="stylesheet" href="/project/styles.css"><script src="/birkly-client.js"></script>';
    $prepared = birkly_project_prepare_html_for_serve($sample);
    assert_true(
        'rewrite strips /project/ prefix for clean public urls',
        !str_contains($prepared, 'href="/project/')
    );
    assert_true(
        'inject data-cms-url on client script',
        str_contains($prepared, 'data-cms-url="https://test.birkly.cloud"')
    );

    $repair = birkly_project_repair_legacy_paths(false);
    assert_true('path repair detects legacy html', $repair['changed'] === true);

    echo "\n[same-host admin at /]\n";
    same_host_test_write([
        'schema_version' => 2,
        'cms_base_url' => 'https://site.example.com',
        'public_site_mode' => 'project',
        'public_site_url' => 'https://site.example.com/project',
        'same_host_landing' => 'admin',
        'strict_admin_host' => true,
    ]);
    $loaded = birkly_hosting_load();
    assert_true(
        'normalize strips /project from public url',
        ($loaded['public_site_url'] ?? '') === 'https://site.example.com'
    );
    assert_true(
        'same-host admin uses project url prefix',
        birkly_hosting_same_host_uses_project_url_prefix() === true
    );
    assert_true(
        'visit url points at /project/',
        str_ends_with(birkly_public_site_visit_url(), '/project/')
            || str_ends_with(birkly_public_site_visit_url(), '/project/index.html')
    );
    $adminSample = '<link rel="stylesheet" href="/project/styles.css">';
    $adminPrepared = birkly_project_prepare_html_for_serve($adminSample);
    assert_true(
        'same-host admin keeps /project/ asset paths',
        str_contains($adminPrepared, 'href="/project/styles.css"')
    );
    assert_true(
        'cms host serves /project/styles.css',
        birkly_hosting_resolve_public_file('/project/styles.css') !== null
    );
} finally {
    birkly_hosting_reset_cache();
    if ($backup !== null) {
        file_put_contents($hostingFile, $backup);
    } elseif (is_file($hostingFile)) {
        unlink($hostingFile);
    }
}

echo "\nhosting_same_host_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
