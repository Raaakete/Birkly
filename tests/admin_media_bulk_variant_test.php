<?php
/**
 * Bulk web variant creation for media library.
 *
 * Run: php tests/admin_media_bulk_variant_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/media_storage.php';
require_once $root . '/core/media_image.php';

$failures = 0;
$assert = static function (bool $cond, string $msg) use (&$failures): void {
    if ($cond) {
        echo "ok - {$msg}\n";
        return;
    }
    echo "FAIL - {$msg}\n";
    $failures++;
};

$GLOBALS['__CMS_MCP_LOAD_FUNCTIONS_ONLY'] = true;
require_once $root . '/api/admin_media.php';
unset($GLOBALS['__CMS_MCP_LOAD_FUNCTIONS_ONLY']);

$assert(function_exists('bulkCreateMediaVariants'), 'bulkCreateMediaVariants is defined');

$parsed = admin_media_parse_id_list(['abc', 'bad id!', 'validid123']);
$assert(count($parsed) === 2 && in_array('abc', $parsed, true), 'admin_media_parse_id_list filters invalid ids');

$GLOBALS['__CMS_MCP_DISPATCH'] = true;
$_POST = ['ids' => '[]', 'variant' => 'web'];
$empty = bulkCreateMediaVariants();
unset($GLOBALS['__CMS_MCP_DISPATCH']);
$assert(($empty['status'] ?? '') === 'error', 'empty id list rejected');

$mediaDir = birkly_media_dir();
$metadataPath = birkly_media_metadata_path();
$metadataBackup = is_file($metadataPath) ? file_get_contents($metadataPath) : null;

$testId = 'birklybulkvar' . bin2hex(random_bytes(3));
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$testFile = $mediaDir . '/' . $testId . '.png';

try {
    if (!is_dir($mediaDir)) {
        mkdir($mediaDir, 0755, true);
    }
    file_put_contents($testFile, $png);
    $metadata = birkly_load_media_metadata();
    $metadata[$testId] = [
        'id' => $testId,
        'name' => 'bulk.png',
        'title' => 'bulk',
        'url' => '/media/' . $testId . '.png',
        'type' => 'image/png',
        'size' => strlen($png),
        'ext' => 'png',
        'folder' => '',
        'created_at' => date('c'),
    ];
    birkly_save_media_metadata($metadata);

    $GLOBALS['__CMS_MCP_DISPATCH'] = true;
    $_POST = ['ids' => json_encode([$testId]), 'variant' => 'web'];
    $bulk = bulkCreateMediaVariants();
    unset($GLOBALS['__CMS_MCP_DISPATCH']);

    if (extension_loaded('gd') && function_exists('imagejpeg')) {
        $assert(($bulk['status'] ?? '') === 'ok' && (int) ($bulk['data']['succeeded'] ?? 0) === 1, 'bulk create ok');
        $meta = birkly_load_media_metadata();
        $assert(isset($meta[$testId]['variants']['web']), 'web variant metadata stored');
    } else {
        $assert(($bulk['status'] ?? '') === 'ok', 'bulk returns ok envelope');
        $assert(count($bulk['data']['results'] ?? []) === 1, 'one result row');
    }
} finally {
    @unlink($testFile);
    @unlink($mediaDir . '/' . $testId . '-web.webp');
    @unlink($mediaDir . '/' . $testId . '-web.jpg');
    if ($metadataBackup !== null) {
        file_put_contents($metadataPath, $metadataBackup);
    }
}

require_once $root . '/core/cms_mcp_admin.php';
$meta = cms_mcp_admin_action_meta();
$assert(isset($meta['media.bulk_create_variants']), 'MCP catalog lists bulk_create_variants');

exit($failures > 0 ? 1 : 0);
