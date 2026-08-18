<?php

declare(strict_types=1);

/**
 * FlatFileStorage instances must be accepted by collection resolve helpers
 * (Storage extends FlatFileStorage — do not type-hint Storage alone).
 */

require_once dirname(__DIR__) . '/core/storage/StorageFactory.php';
require_once dirname(__DIR__) . '/core/content_resolve.php';

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

$root = sys_get_temp_dir() . '/birkly-storage-type-' . bin2hex(random_bytes(4));
$contentRoot = $root . '/content';
$slug = 'type-test-coll';
$collDir = $contentRoot . '/collections/' . $slug;
mkdir($collDir, 0755, true);
file_put_contents($collDir . '/collection.json', json_encode(['slug' => $slug, 'name' => 'Type test'], JSON_PRETTY_PRINT));

$storage = StorageFactory::createStorage($contentRoot);
assert_true('factory returns StorageInterface', $storage instanceof StorageInterface);

$resolved = birkly_content_resolve_collection_dir_name($storage, $slug);
assert_true(
    'resolve accepts factory storage instance',
    $resolved === $slug,
    'got ' . (string) $resolved
);

@unlink($collDir . '/collection.json');
@rmdir($collDir);
@rmdir($contentRoot . '/collections');
@rmdir($contentRoot);
@rmdir($root);

exit($failures > 0 ? 1 : 0);
