<?php
/**
 * Ops starter client — catalog probe + offline zip extraction.
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/birkly_paths.php';
require_once __DIR__ . '/../core/ops_starters_client.php';
require_once __DIR__ . '/../core/demo_starter_templates.php';

$failed = 0;

function assert_true(string $label, bool $condition): void
{
    global $failed;
    if (!$condition) {
        echo "FAIL: $label\n";
        $failed++;
        return;
    }
    echo "OK: $label\n";
}

$errors = [];
$catalog = birkly_ops_fetch_starters_catalog($errors);
assert_true('ops catalog reachable', $catalog !== null);
if ($errors !== []) {
    echo 'catalog errors: ' . implode('; ', $errors) . "\n";
}

$availability = birkly_demo_starter_availability(birkly_project_root());
assert_true('marsk selectable from ops', !empty($availability['marsk']['selectable']));

$diagnostics = birkly_ops_starters_diagnostics('marsk');
assert_true('marsk diagnostics selectable', ($diagnostics['selectable'] ?? false) === true);

$tmp = sys_get_temp_dir() . '/birkly-ops-marsk-' . getmypid() . '.zip';
$downloadErrors = [];
$download = birkly_ops_download_starter_zip('marsk', $tmp, $downloadErrors);
assert_true('marsk zip downloads from ops', $download !== null && is_file($tmp));
if ($downloadErrors !== []) {
    echo 'download errors: ' . implode('; ', $downloadErrors) . "\n";
}

$root = sys_get_temp_dir() . '/birkly-ops-starter-' . getmypid();
@mkdir($root . '/content/collections', 0755, true);
@mkdir($root . '/project', 0755, true);

$zipPath = $root . '/blog.zip';
$zip = new ZipArchive();
$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFromString('manifest.json', json_encode([
    'id' => 'blog',
    'version' => '1.0.0',
    'collections' => ['blog'],
], JSON_THROW_ON_ERROR));
$zip->addFromString('collections/blog/collection.json', '{"name":"Blog"}');
$zip->addFromString('collections/blog/entries/post-1.json', '{"title":"Hello"}');
$zip->addFromString('project/index.html', '<!doctype html><title>Blog</title>');
$zip->close();

$result = birkly_ops_extract_starter_zip($zipPath, $root);
assert_true('offline extract succeeds', $result['success'] === true);
assert_true('blog collection installed', is_file($root . '/content/collections/blog/collection.json'));
assert_true('project site installed', is_file($root . '/project/index.html'));

$dockerRoot = sys_get_temp_dir() . '/birkly-ops-docker-' . getmypid();
@mkdir($dockerRoot . '/data/content', 0777, true);
@mkdir($dockerRoot . '/data/content/project', 0777, true);
symlink($dockerRoot . '/data/content', $dockerRoot . '/content');
symlink($dockerRoot . '/data/content/project', $dockerRoot . '/project');

$collectionsReady = birkly_ensure_content_collections_dir($dockerRoot);
assert_true('docker layout collections dir ready', ($collectionsReady['ok'] ?? false) === true);

if (is_file($tmp)) {
    $dockerExtract = birkly_ops_extract_starter_zip($tmp, $dockerRoot);
    assert_true('docker layout extract succeeds', ($dockerExtract['success'] ?? false) === true);
    assert_true(
        'docker layout blog collection installed',
        is_file($dockerRoot . '/content/collections/blog/collection.json')
    );
}

@unlink($tmp);
exec('rm -rf ' . escapeshellarg($root) . ' ' . escapeshellarg($dockerRoot));

exit($failed > 0 ? 1 : 0);
