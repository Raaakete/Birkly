<?php
/**
 * Optional custom 404 page for Birkly-hosted project sites.
 *
 * Run: php tests/hosting_custom_404_test.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/hosting_urls.php';
require_once dirname(__DIR__) . '/core/project_custom_404.php';
require_once dirname(__DIR__) . '/core/hosting_routing.php';
require_once dirname(__DIR__) . '/core/ai_presence_discovery.php';

$failures = 0;
$root = dirname(__DIR__);
$hostingFile = birkly_hosting_settings_path();
$projectSettingsFile = $root . '/settings/project.json';
$hostingBackup = is_readable($hostingFile) ? file_get_contents($hostingFile) : null;
$projectBackup = is_readable($projectSettingsFile) ? file_get_contents($projectSettingsFile) : null;

$test404Rel = '_birkly_custom_404_test.html';
$test404Abs = $root . '/project/' . $test404Rel;
$test404Backup = is_readable($test404Abs) ? file_get_contents($test404Abs) : null;

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

function custom404_write_hosting_project(): void
{
    birkly_hosting_reset_cache();
    birkly_hosting_persist([
        'cms_base_url' => 'https://cms.custom404.test',
        'public_site_mode' => 'project',
        'public_site_url' => 'https://custom404.test',
        'same_host_landing' => 'admin',
        'strict_admin_host' => true,
    ], false);
    birkly_hosting_reset_cache();
    $_SERVER['HTTP_HOST'] = 'custom404.test';
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml';
}

function custom404_write_settings(array $patch): void
{
    global $projectSettingsFile;
    $existing = is_readable($projectSettingsFile)
        ? json_decode((string) file_get_contents($projectSettingsFile), true)
        : [];
    if (!is_array($existing)) {
        $existing = [];
    }
    file_put_contents($projectSettingsFile, json_encode(array_merge($existing, $patch), JSON_PRETTY_PRINT));
}

function custom404_capture_not_found(string $uri): array
{
    ob_start();
    $prior = http_response_code();
    birkly_hosting_try_respond_not_found($uri);
    $body = ob_get_clean();
    $code = http_response_code();
    if ($prior !== false) {
        http_response_code($prior);
    }

    return ['code' => $code, 'body' => $body, 'headers' => headers_list()];
}

try {
    custom404_write_hosting_project();
    file_put_contents($test404Abs, '<!DOCTYPE html><html><head><title>Missing</title></head><body><p>CUSTOM404MARKER</p></body></html>');

    custom404_write_settings([
        'custom_404_enabled' => false,
        'custom_404_path' => $test404Rel,
        'render_mode' => 'client',
    ]);

    $plain = custom404_capture_not_found('/nope');
    assert_true(
        'toggle off returns plain body',
        $plain['code'] === 404 && $plain['body'] === 'Not found'
    );

    custom404_write_settings(['custom_404_enabled' => true]);

    foreach (['/nope', '/nope.html', '/a/b/c.html'] as $missing) {
        $resp = custom404_capture_not_found($missing);
        assert_true(
            'toggle on missing path returns custom page: ' . $missing,
            $resp['code'] === 404 && str_contains($resp['body'], 'CUSTOM404MARKER'),
            'code=' . var_export($resp['code'], true)
        );
    }

    $_GET = ['lang' => 'de'];
    $respLang = custom404_capture_not_found('/nope?lang=de');
    unset($_GET['lang']);
    assert_true(
        'custom 404 preserves query handling',
        $respLang['code'] === 404 && str_contains($respLang['body'], 'CUSTOM404MARKER')
    );

    foreach (['/nope.css', '/media/nope.jpg', '/api/nope'] as $asset) {
        $assetResp = custom404_capture_not_found($asset);
        assert_true(
            'non-page paths stay plain: ' . $asset,
            $assetResp['code'] === 404 && $assetResp['body'] === 'Not found'
        );
    }

    custom404_write_settings(['custom_404_path' => 'missing-page.html']);
    $missingFile = custom404_capture_not_found('/nope');
    assert_true(
        'missing configured file falls back to plain 404',
        $missingFile['code'] === 404 && $missingFile['body'] === 'Not found'
    );

    custom404_write_settings(['custom_404_path' => $test404Rel]);
    $direct = custom404_capture_not_found('/' . $test404Rel);
    assert_true(
        'direct request to configured file uses HTTP 404 when enabled',
        $direct['code'] === 404 && str_contains($direct['body'], 'CUSTOM404MARKER')
    );

    $resp = custom404_capture_not_found('/nope');
    assert_true(
        'custom response injects birkly-not-found meta',
        str_contains($resp['body'], 'name="birkly-not-found"')
    );

    $serveSource = (string) file_get_contents($root . '/core/project_custom_404.php');
    assert_true(
        'custom 404 serve sets X-Robots-Tag noindex',
        str_contains($serveSource, "header('X-Robots-Tag: noindex')")
    );

    assert_true(
        'sitemap helper skips configured 404 file',
        birkly_project_custom_404_sitemap_should_skip_rel('/' . $test404Rel)
    );

    assert_true(
        'asset path not eligible for custom page',
        !birkly_hosting_request_prefers_custom_404_page('/style.css')
    );
} finally {
    if ($hostingBackup !== null) {
        file_put_contents($hostingFile, $hostingBackup);
    }
    if ($projectBackup !== null) {
        file_put_contents($projectSettingsFile, $projectBackup);
    } else {
        @unlink($projectSettingsFile);
    }
    if ($test404Backup !== null) {
        file_put_contents($test404Abs, $test404Backup);
    } else {
        @unlink($test404Abs);
    }
    birkly_hosting_reset_cache();
}

exit($failures > 0 ? 1 : 0);
