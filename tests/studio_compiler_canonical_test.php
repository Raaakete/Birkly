<?php
/**
 * Studio compiler canonical syntax tests (P24-M1).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/plugins/studio/init.php';
require_once dirname(__DIR__) . '/plugins/studio/includes/StudioProjectRepository.php';
require_once dirname(__DIR__) . '/plugins/studio/includes/StudioCompiler.php';

$tmp = sys_get_temp_dir() . '/studio-canonical-' . getmypid();
@mkdir($tmp, 0755, true);

test_reset_failures();
echo "Studio compiler canonical syntax tests\n\n";

$repo = new StudioProjectRepository($tmp);
$compiler = new StudioCompiler($repo);
$project = $repo->createProject('Canonical', 'minimal', 'canonical-test');
$id = $project['id'];

$canvas = $repo->getPageCanvas($id, 'index');
$canvas['sections'][0]['rows'][0]['columns'][0]['elements'] = [[
    'id' => 'bind-1',
    'type' => 'binding',
    'bindingType' => 'filtered',
    'collection' => 'blog',
    'filterField' => 'status',
    'filterValue' => 'published',
    'itemTemplate' => '<article><h2>{title}</h2></article>',
]];
$repo->savePageCanvas($id, 'index', $canvas);

$html = $compiler->compilePage($id, 'index');
test_assert('uses endfor', str_contains($html, '{endfor}'));
test_assert('no legacy end tag', !preg_match('/\{end\}/', $html));
test_assert('canonical loop header', str_contains($html, "with status = published ordered by newest"));
test_assert('spaced title placeholder', str_contains($html, '{ title }'));

$canvas['sections'][0]['rows'][0]['columns'][0]['elements'][0]['bindingType'] = 'site_element';
$canvas['sections'][0]['rows'][0]['columns'][0]['elements'][0]['slug'] = 'hero';
$repo->savePageCanvas($id, 'index', $canvas);
$html2 = $compiler->compilePage($id, 'index');
test_assert('site element uses endget', str_contains($html2, '{endget}'));
test_assert('site element from syntax', str_contains($html2, "{from 'site_elements' get entry 'hero'}"));

$canvas['nav'] = [
    ['id' => 'n1', 'label' => 'Home', 'href' => '/', 'active' => true],
    ['id' => 'n2', 'label' => 'Blog', 'href' => '/blog/', 'active' => false],
];
$repo->savePageCanvas($id, 'index', $canvas);
$html3 = $compiler->compilePage($id, 'index');
test_assert('nav header compiled', str_contains($html3, 'studio-header'));
test_assert('nav links present', str_contains($html3, 'studio-nav-link') && str_contains($html3, 'Blog'));

$repo->deleteProject($id);
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

exit(test_finish('studio_compiler_canonical_test'));
