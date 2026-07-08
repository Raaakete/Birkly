<?php
/**
 * Studio SSR renderer tests (P22-M9).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/plugins/studio/init.php';
require_once dirname(__DIR__) . '/plugins/studio/includes/StudioProjectRepository.php';
require_once dirname(__DIR__) . '/plugins/studio/includes/StudioCompiler.php';
require_once dirname(__DIR__) . '/plugins/studio/includes/StudioRenderer.php';

$tmp = sys_get_temp_dir() . '/studio-ssr-' . getmypid();
@mkdir($tmp, 0755, true);

test_reset_failures();
echo "Studio renderer tests\n\n";

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

// Published home URL with trailing slash
$birkly_plugins['studio'] = ['active' => true];
$repo->publishProject($project['id']);
ob_start();
$handledHome = $renderer->handlePublicRequest('/site/ssr-test/');
$homeBody = ob_get_clean();
test_assert('trailing slash home handled', $handledHome === true);
test_assert('home page renders', str_contains($homeBody, 'Hello SSR'));

// Inactive plugin returns 503
global $birkly_plugins;
$birkly_plugins['studio'] = ['active' => false];
ob_start();
$handled = $renderer->handlePublicRequest('/site/ssr-test/');
$body = ob_get_clean();
test_assert('inactive plugin handled', $handled === true);
test_assert('inactive returns 503 body', str_contains($body, 'unavailable') || http_response_code() === 503);

// cleanup
foreach (scandir($tmp) ?: [] as $f) {
    if ($f === '.' || $f === '..') continue;
    $p = $tmp . '/' . $f;
    if (is_dir($p)) {
        array_map('unlink', glob($p . '/*/*') ?: []);
        array_map('unlink', glob($p . '/*') ?: []);
        array_map('rmdir', glob($p . '/*') ?: []);
        @rmdir($p);
    }
}
@rmdir($tmp);

exit(test_finish('studio_renderer_test'));
