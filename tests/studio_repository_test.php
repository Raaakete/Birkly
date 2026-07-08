<?php
/**
 * Studio project repository tests (P22-M2).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/plugins/studio/init.php';
require_once dirname(__DIR__) . '/plugins/studio/includes/StudioProjectRepository.php';
require_once dirname(__DIR__) . '/plugins/studio/includes/StudioCompiler.php';

$tmp = sys_get_temp_dir() . '/studio-test-' . getmypid();
@mkdir($tmp, 0755, true);

test_reset_failures();
echo "Studio repository tests\n\n";

$repo = new StudioProjectRepository($tmp);

$project = $repo->createProject('Test Site', 'minimal', 'test-site');
test_assert('create returns project', is_array($project));
test_assert('project has id', !empty($project['id']));
test_assert('slug set', ($project['slug'] ?? '') === 'test-site');
test_assert('index page exists', $repo->getPageCanvas($project['id'], 'index') !== null);

$list = $repo->listProjects();
test_assert('list includes project', count($list) >= 1);

$dup = $repo->duplicateProject($project['id'], 'Copy Site');
test_assert('duplicate works', is_array($dup));
test_assert('duplicate new slug', ($dup['slug'] ?? '') !== ($project['slug'] ?? ''));

$canvas = $repo->getPageCanvas($project['id'], 'index');
$canvas['sections'][] = [
    'id' => 'sec-2',
    'classes' => ['studio-section'],
    'layout' => ['type' => 'grid', 'columns' => 1, 'gap' => '1rem'],
    'rows' => [[
        'id' => 'row-2',
        'columns' => [[
            'id' => 'col-2',
            'width' => '100%',
            'elements' => [[
                'id' => 'bind-1',
                'type' => 'binding',
                'bindingType' => 'loop',
                'collection' => 'blog',
                'itemTemplate' => '<h2>{title}</h2>',
            ]],
        ]],
    ]],
];
$repo->savePageCanvas($project['id'], 'index', $canvas);
$compiler = new StudioCompiler($repo);
$html = $compiler->compilePage($project['id'], 'index');
test_assert('compile produces html', str_contains($html, 'for each entry'));
test_assert('compile has doctype', str_contains($html, '<!DOCTYPE html>'));

$published = $repo->publishProject($project['id']);
test_assert('publish sets published', !empty($published['published']));
test_assert('revision bumped', (int) ($published['revision'] ?? 0) >= 2);

$found = $repo->findBySlug('test-site');
test_assert('find by slug', ($found['id'] ?? '') === $project['id']);

$repo->deleteProject($project['id']);
test_assert('delete removes project', $repo->getProject($project['id']) === null);

// cleanup
function studio_test_rm($dir) {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $dir . '/' . $f;
        is_dir($p) ? studio_test_rm($p) : unlink($p);
    }
    rmdir($dir);
}
studio_test_rm($tmp);

exit(test_finish('studio_repository_test'));
