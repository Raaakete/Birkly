<?php
/**
 * Hosting redirect bootstrap — SEO redirect must not fatal index.php paths.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$GLOBALS['__birkly_project_root'] = sys_get_temp_dir() . '/birkly_host_redir_' . bin2hex(random_bytes(4));

require_once $root . '/core/hosting_redirects.php';
require_once $root . '/core/discovery.php';
require_once $root . '/core/site_seo.php';

$failures = 0;

function assert_true(bool $cond, string $label): void
{
    global $failures;
    if ($cond) {
        echo "OK  $label\n";
        return;
    }
    echo "FAIL $label\n";
    $failures++;
}

$projectRoot = (string) $GLOBALS['__birkly_project_root'];
@mkdir($projectRoot . '/settings', 0755, true);

file_put_contents(
    $projectRoot . '/settings/hosting.json',
    json_encode([
        'cms_base_url' => 'https://cms.example.test',
        'public_site_mode' => 'project',
        'public_site_url' => 'https://www.example.test',
    ], JSON_PRETTY_PRINT) . "\n"
);

birkly_discovery_save_pack('seo', ['enabled' => true]);
birkly_site_seo_save([
    'enabled' => true,
    'published' => true,
    'url_map' => [
        'redirects' => [
            ['from' => '/legacy', 'to' => '/about.html', 'status' => 301],
        ],
    ],
]);

echo "==> apply_redirect bootstrap\n";
$_SERVER['HTTP_HOST'] = 'www.example.test';
$_SERVER['REQUEST_URI'] = '/favicon.ico';

$caught = false;
try {
    $handled = birkly_hosting_apply_redirect('/favicon.ico');
    assert_true($handled === false, 'non-matching uri is not redirected');
} catch (Throwable $e) {
    $caught = true;
    echo 'FAIL apply_redirect threw: ' . $e->getMessage() . "\n";
    $failures++;
}

if (!$caught) {
    assert_true(true, 'apply_redirect completes without fatal');
}

exit($failures > 0 ? 1 : 0);
