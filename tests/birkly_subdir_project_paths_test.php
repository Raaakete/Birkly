<?php
/**
 * Subdirectory install: project/ URL prefix and HTML path rewriting.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/birkly_paths.php';
require_once dirname(__DIR__) . '/core/project_site.php';
require_once dirname(__DIR__) . '/core/hosting_urls.php';
require_once dirname(__DIR__) . '/core/project_site_filesystem.php';

$failures = 0;

function assert_true(string $name, bool $ok): void
{
    global $failures;
    echo $name . ': ' . ($ok ? 'PASS' : 'FAIL') . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

function with_server_env(array $env, callable $fn): mixed
{
    $backup = [];
    foreach ($env as $key => $value) {
        $backup[$key] = $_SERVER[$key] ?? null;
        if ($value === null) {
            unset($_SERVER[$key]);
        } else {
            $_SERVER[$key] = $value;
        }
    }
    birkly_paths_reset_cache();
    unset($GLOBALS['__birkly_hosting_config']);

    try {
        return $fn();
    } finally {
        foreach ($backup as $key => $value) {
            if ($value === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $value;
            }
        }
        birkly_paths_reset_cache();
        unset($GLOBALS['__birkly_hosting_config']);
    }
}

$sampleHtml = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
  <link rel="stylesheet" href="/project/css/marsk.css">
</head>
<body>
  <a href="/project/blog.html">Blog</a>
  <script src="/birkly-client.js"></script>
</body>
</html>
HTML;

with_server_env([
    'SCRIPT_NAME' => '/sites/demo/Birkly/admin/index.php',
    'REQUEST_URI' => '/sites/demo/Birkly/project/index.html',
    'HTTP_HOST' => 'example.test',
    'HTTPS' => 'off',
], static function () use ($sampleHtml): void {
    assert_true('base path detected', birkly_base_path() === '/sites/demo/Birkly');
    assert_true('site url prefix includes install base', birkly_public_site_url_prefix() === '/sites/demo/Birkly/project');
    assert_true('birkly_url project path', birkly_url('/project/css/x.css') === '/sites/demo/Birkly/project/css/x.css');

    $rewritten = birkly_project_rewrite_install_paths($sampleHtml);
    assert_true('rewrites css href', str_contains($rewritten, 'href="/sites/demo/Birkly/project/css/marsk.css"'));
    assert_true('rewrites page link', str_contains($rewritten, 'href="/sites/demo/Birkly/project/blog.html"'));
    assert_true('rewrites client script', str_contains($rewritten, 'src="/sites/demo/Birkly/birkly-client.js"'));

    $prepared = birkly_project_prepare_html_for_serve($sampleHtml);
    assert_true('prepare injects cms base meta', str_contains($prepared, 'name="birkly-cms-base"'));
    assert_true('prepare rewrites paths', str_contains($prepared, '/sites/demo/Birkly/project/css/marsk.css'));

    $info = birkly_project_site_info();
    assert_true('get_project_info prefix', ($info['site_url_prefix'] ?? '') === '/sites/demo/Birkly/project');
    assert_true('get_project_info cms_base_path', ($info['cms_base_path'] ?? '') === '/sites/demo/Birkly');
    assert_true(
        'get_project_info cms_web_base_url includes path',
        str_contains((string) ($info['cms_web_base_url'] ?? ''), '/sites/demo/Birkly')
    );

    $snippet = birkly_hosting_client_snippet();
    assert_true('client snippet uses web base', str_contains($snippet, 'data-cms-url="http://example.test/sites/demo/Birkly"'));
    assert_true('client snippet src prefixed', str_contains($snippet, '/sites/demo/Birkly/birkly-client.js'));
});

with_server_env([
    'SCRIPT_NAME' => '/index.php',
    'REQUEST_URI' => '/project/index.html',
    'HTTP_HOST' => 'example.test',
    'HTTPS' => 'off',
], static function () use ($sampleHtml): void {
    assert_true('root install: no rewrite', birkly_project_rewrite_install_paths($sampleHtml) === $sampleHtml);
    assert_true('root install: prefix stays /project', birkly_public_site_url_prefix() === '/project');
});

echo PHP_EOL . 'birkly_subdir_project_paths_test: ' . ($failures === 0 ? 'PASS' : "FAIL ($failures)") . PHP_EOL;
exit($failures === 0 ? 0 : 1);
