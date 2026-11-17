<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$GLOBALS['__birkly_project_root'] = sys_get_temp_dir() . '/birkly_slug_' . bin2hex(random_bytes(4));

require_once $root . '/core/entry_storage.php';

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
$coll = $projectRoot . '/content/collections/blog';
@mkdir($coll . '/entries', 0755, true);
file_put_contents($coll . '/collection.json', json_encode(['name' => 'Blog', 'slug' => 'blog']) . "\n");
file_put_contents($coll . '/entries/abc123.json', json_encode([
    'id' => 'abc123',
    'slug' => 'hello-world',
    'title' => 'Hello',
    'status' => 'published',
]) . "\n");

assert_true(birkly_entry_resolve_record_id('blog', 'abc123') === 'abc123', 'resolve by id');
assert_true(birkly_entry_resolve_record_id('blog', 'hello-world') === 'abc123', 'resolve by slug');
assert_true(birkly_entry_resolve_record_id('blog', 'missing') === null, 'missing slug returns null');

@unlink($coll . '/entries/abc123.json');
@rmdir($coll . '/entries');
@rmdir($coll);
@rmdir(dirname(dirname($coll)));
@rmdir(dirname(dirname(dirname($coll))));
unset($GLOBALS['__birkly_project_root']);

exit($failures > 0 ? 1 : 0);