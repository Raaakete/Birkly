<?php
/**
 * Studio SSR integration — body must not contain raw template directives (P24-M3).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/plugins/studio/init.php';
require_once dirname(__DIR__) . '/plugins/studio/includes/StudioProjectRepository.php';
require_once dirname(__DIR__) . '/plugins/studio/includes/StudioCompiler.php';
require_once dirname(__DIR__) . '/plugins/studio/includes/StudioRenderer.php';
require_once dirname(__DIR__) . '/plugins/studio/includes/StudioTemplateEngine.php';

$tmp = sys_get_temp_dir() . '/studio-ssr-curl-' . getmypid();
@mkdir($tmp, 0755, true);

test_reset_failures();
echo "Studio SSR curl-style tests\n\n";

$repo = new StudioProjectRepository($tmp);
$compiler = new StudioCompiler($repo);
$project = $repo->createProject('SSR Curl', 'minimal', 'ssr-curl');
$id = $project['id'];

$canvas = $repo->getPageCanvas($id, 'index');
$canvas['sections'][0]['rows'][0]['columns'][0]['elements'] = [[
    'id' => 'b1',
    'type' => 'binding',
    'bindingType' => 'filtered',
    'collection' => 'blog',
    'filterField' => 'status',
    'filterValue' => 'published',
    'limit' => 3,
    'itemTemplate' => '<article><h2>{ title }</h2><p>{ excerpt }</p></article>',
]];
$repo->savePageCanvas($id, 'index', $canvas);
$repo->publishProject($id);

$html = $repo->getPageHtml($id, 'index');
test_assert('compiled html has endfor', str_contains($html ?? '', '{endfor}'));

$mockData = [
    'blog' => [
        ['title' => 'Live Post', 'slug' => 'live', 'status' => 'published', 'excerpt' => 'Rendered excerpt'],
    ],
];

$engine = new StudioTemplateEngine();
$body = $engine->render($html ?? '', $mockData);

test_assert('body contains rendered title', str_contains($body, 'Live Post'));
test_assert('body contains rendered excerpt', str_contains($body, 'Rendered excerpt'));
test_assert('body must not contain for each directive', !str_contains($body, '{for each'));
test_assert('body must not contain endfor directive', !str_contains($body, '{endfor}'));
test_assert('body must not contain raw title brace', !str_contains($body, '{ title }'));

$renderer = new StudioRenderer($repo, $engine);
global $birkly_plugins;
$birkly_plugins['studio'] = ['active' => true];
ob_start();
$handled = $renderer->handlePublicRequest('/site/ssr-curl/');
$httpBody = ob_get_clean();
test_assert('public route handled', $handled === true);
test_assert('public body has no for each', !str_contains($httpBody, '{for each'));

$repo->deleteProject($id);
@rmdir($tmp);

exit(test_finish('studio_ssr_curl_test'));
