<?php
/**
 * Studio filesystem sandbox tests (P23-M2/M16).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/plugins/studio/init.php';
require_once dirname(__DIR__) . '/plugins/studio/includes/StudioProjectRepository.php';
require_once dirname(__DIR__) . '/plugins/studio/includes/StudioFileSystem.php';
require_once dirname(__DIR__) . '/plugins/studio/includes/StudioCanvasMigrator.php';
require_once dirname(__DIR__) . '/plugins/studio/includes/StudioRevisionStore.php';

$tmp = sys_get_temp_dir() . '/studio-fs-test-' . getmypid();
@mkdir($tmp, 0755, true);

test_reset_failures();
echo "Studio filesystem tests\n\n";

$repo = new StudioProjectRepository($tmp);
$fs = new StudioFileSystem($repo);

$project = $repo->createProject('FS Test', 'minimal', 'fs-test');
$id = $project['id'];
test_assert('project created', !empty($id));

$tree = $fs->listTree($id);
test_assert('tree has pages dir', count($tree) >= 1);

$ok = $fs->createFile($id, 'pages/about.studio.json', '{"version":1,"sections":[]}');
test_assert('create file', $ok);

$read = $fs->readFile($id, 'pages/about.studio.json');
test_assert('read file', is_array($read) && str_contains($read['content'], 'sections'));

$write = $fs->writeFile($id, 'styles/notes.txt', 'hello');
test_assert('write txt', $write);

$blocked = $fs->readFile($id, '../../../etc/passwd');
test_assert('traversal read blocked', $blocked === null);

$blockedWrite = $fs->writeFile($id, '../escape.txt', 'nope');
test_assert('traversal write blocked', $blockedWrite === false);

$blockedResolve = $fs->resolvePath($id, 'pages/../../outside.txt', false);
test_assert('resolve traversal blocked', $blockedResolve === null);

$mkdir = $fs->createDirectory($id, 'assets');
test_assert('create directory', $mkdir);

$rename = $fs->renamePath($id, 'styles/notes.txt', 'assets/notes.txt');
test_assert('rename file', $rename);

$del = $fs->deletePath($id, 'assets/notes.txt');
test_assert('delete file', $del);

$protected = $fs->deletePath($id, 'project.json');
test_assert('project.json protected', $protected === false);

// v1 → v2 migration
$v1 = ['version' => 1, 'sections' => [[
    'id' => 'sec-1',
    'layout' => ['columns' => 3],
    'rows' => [],
]]];
$v2 = StudioCanvasMigrator::upgrade($v1);
test_assert('migrator sets version 2', (int) ($v2['version'] ?? 0) === 2);
test_assert('migrator adds responsive', isset($v2['sections'][0]['responsive']['desktop']));

// revisions
$revs = new StudioRevisionStore($repo);
$fs->writeFile($id, 'styles/custom.css', 'a { color: red; }');
$fs->writeFile($id, 'styles/custom.css', 'a { color: blue; }');
$list = $revs->listForPath($id, 'styles/custom.css');
test_assert('revision recorded', count($list) >= 1);

$repo->deleteProject($id);

function studio_fs_test_rm($dir) {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $dir . '/' . $f;
        is_dir($p) ? studio_fs_test_rm($p) : unlink($p);
    }
    rmdir($dir);
}
studio_fs_test_rm($tmp);

exit(test_finish('studio_filesystem_test'));
