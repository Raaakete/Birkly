<?php
/**
 * Studio SSR renderer tests (P22-M9).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/plugins.php';
require_once dirname(__DIR__) . '/plugins/studio/init.php';
require_once dirname(__DIR__) . '/plugins/studio/includes/StudioProjectRepository.php';
require_once dirname(__DIR__) . '/plugins/studio/includes/StudioCompiler.php';
require_once dirname(__DIR__) . '/plugins/studio/includes/StudioRenderer.php';

ob_start();

$root = dirname(__DIR__);
$previousCwd = getcwd();
chdir($root);

$tmp = sys_get_temp_dir() . '/studio-ssr-' . getmypid();
@mkdir($tmp, 0755, true);

test_reset_failures();
echo "Studio renderer tests\n\n";

global $birkly_plugins;
$originalPlugins = $birkly_plugins;

try {
    $repo = new StudioProjectRepository($tmp);
    $project = $repo->createProject('SSR Test', 'minimal', 'ssr-test');
    $canvas = $repo->getPageCanvas($project['id'], 'index');
    $canvas['sections'][0]['rows'][0]['columns'][0]['elements'] = [[
        'id' => 't1',
        'type' => 'text',
        'tag' => 'h1',
        'content' => 'Hello SSR',
        'classes' => [],
        'styles' => [],
    ]];
    $repo->savePageCanvas($project['id'], 'index', $canvas);
    $repo->publishProject($project['id']);

    $renderer = new StudioRenderer($repo);
    $html = $repo->getPageHtml($project['id'], 'index');
    $out = $renderer->renderHtml($html ?: '');
    test_assert('render html contains title', str_contains($out, 'Hello SSR'));

    $birkly_plugins['studio'] = [
        'id' => 'studio',
        'active' => true,
        'path' => $root . '/plugins/studio',
    ];

    // Published home URL with trailing slash
    $repo->publishProject($project['id']);
    ob_start();
    $handledHome = $renderer->handlePublicRequest('/site/ssr-test/');
    $homeBody = ob_get_clean();
    test_assert('trailing slash home handled', $handledHome === true);
    test_assert('home page renders', str_contains($homeBody, 'Hello SSR'));

    // Inactive plugin returns 503
    $birkly_plugins['studio']['active'] = false;
    ob_start();
    $handled = $renderer->handlePublicRequest('/site/ssr-test/');
    $body = ob_get_clean();
    test_assert('inactive plugin handled', $handled === true);
    test_assert('inactive returns 503 body', str_contains($body, 'unavailable') || http_response_code() === 503);
} finally {
    $birkly_plugins = $originalPlugins;
    foreach (scandir($tmp) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $tmp . '/' . $entry;
        if (is_dir($path)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $file) {
                $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
            }
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($tmp);
    chdir($previousCwd ?: $root);
}

$status = test_finish('studio_renderer_test');
ob_end_flush();
exit($status);
