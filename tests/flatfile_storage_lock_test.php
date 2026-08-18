<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/storage/file_lock.php';
require_once dirname(__DIR__) . '/core/storage/FlatFileStorage.php';

$failures = 0;

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

$tmpRoot = sys_get_temp_dir() . '/birkly-flatfile-lock-' . bin2hex(random_bytes(4));
$contentRoot = $tmpRoot . '/content';
$storage = new FlatFileStorage($contentRoot);

$collectionDir = $contentRoot . '/collections/demo';
mkdir($collectionDir, 0755, true);
mkdir($collectionDir . '/entries', 0755, true);
file_put_contents($collectionDir . '/collection.json', '{"slug":"demo","name":"Demo"}');

$written = $storage->write('collections/demo/entries/entry1.json', [
    'id' => 'entry1',
    'title' => 'Lock test entry',
    'status' => 'published',
]);
assert_true('flatfile write succeeds', $written === true);
assert_true(
    'entry file exists on disk',
    is_file($collectionDir . '/entries/entry1.json')
);

$lockPath = $contentRoot . '/collections/demo/entries/contended.json.tmp';
$fp = fopen($lockPath, 'c');
assert_true('open contended tmp', is_resource($fp));
assert_true('hold exclusive lock', flock($fp, LOCK_EX));

$blocked = $storage->write('collections/demo/entries/contended.json', [
    'id' => 'contended',
    'title' => 'Should fail fast',
]);
assert_true('write fails when lock held', $blocked === false);

flock($fp, LOCK_UN);
fclose($fp);
@unlink($lockPath);

$afterRelease = $storage->write('collections/demo/entries/contended.json', [
    'id' => 'contended',
    'title' => 'After release',
]);
assert_true('write succeeds after lock released', $afterRelease === true);

// Cleanup
@unlink($collectionDir . '/entries/contended.json');
@unlink($collectionDir . '/entries/entry1.json');
@rmdir($collectionDir . '/entries');
@unlink($collectionDir . '/collection.json');
@rmdir($collectionDir);
@rmdir($contentRoot . '/collections');
@rmdir($contentRoot);
@rmdir($tmpRoot);

exit($failures > 0 ? 1 : 0);
