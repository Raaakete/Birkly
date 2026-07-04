<?php
/**
 * P5.7-M2 — General settings media field key/display tests (GEN-01–05).
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Settings general tests\n\n";

$root = dirname(__DIR__);
$mediaFile = $root . '/settings/media.json';
$backup = sys_get_temp_dir() . '/birkly-media-test-' . getmypid() . '.json';
$hadFile = is_file($mediaFile);
if ($hadFile) {
    copy($mediaFile, $backup);
}

$sample = [
    'max_upload_size' => 10485760,
    'image_quality' => 85,
    'create_thumbnails' => false,
    'thumbnail_size' => [200, 200],
];
file_put_contents($mediaFile, json_encode($sample, JSON_PRETTY_PRINT));

$loaded = json_decode(file_get_contents($mediaFile), true);
$maxUploadMb = (int) round($loaded['max_upload_size'] / 1024 / 1024);
test_assert('GEN-01 max upload bytes to MB display', $maxUploadMb === 10);
test_assert('GEN-03 image_quality snake_case readable', ($loaded['image_quality'] ?? null) === 85);
test_assert('GEN-04 thumbnail_size width', ($loaded['thumbnail_size'][0] ?? null) === 200);
test_assert('GEN-04 thumbnail_size height', ($loaded['thumbnail_size'][1] ?? null) === 200);
test_assert('GEN-05 create_thumbnails boolean false', ($loaded['create_thumbnails'] ?? true) === false);

// Simulate save_all mapping (MB → bytes, checkbox boolean)
$formInput = [
    'mediaMaxUploadSize' => '10',
    'mediaImageQuality' => '90',
    'mediaThumbnailWidth' => '300',
    'mediaThumbnailHeight' => '250',
    'mediaCreateThumbnails' => true,
];
$mapped = [
    'max_upload_size' => (int) $formInput['mediaMaxUploadSize'] * 1024 * 1024,
    'image_quality' => (int) $formInput['mediaImageQuality'],
    'thumbnail_size' => [
        (int) $formInput['mediaThumbnailWidth'],
        (int) $formInput['mediaThumbnailHeight'],
    ],
    'create_thumbnails' => filter_var($formInput['mediaCreateThumbnails'], FILTER_VALIDATE_BOOLEAN),
];
$merged = array_merge($loaded, $mapped);
file_put_contents($mediaFile, json_encode($merged, JSON_PRETTY_PRINT));
$saved = json_decode(file_get_contents($mediaFile), true);

test_assert('save maps MB to bytes', ($saved['max_upload_size'] ?? 0) === 10485760);
test_assert('save maps image_quality', ($saved['image_quality'] ?? 0) === 90);
test_assert('save maps thumbnail width', ($saved['thumbnail_size'][0] ?? 0) === 300);
test_assert('save maps thumbnail height', ($saved['thumbnail_size'][1] ?? 0) === 250);
test_assert('save maps create_thumbnails boolean', ($saved['create_thumbnails'] ?? false) === true);

if ($hadFile) {
    copy($backup, $mediaFile);
} else {
    @unlink($mediaFile);
}
@unlink($backup);

exit(test_finish('settings_general_test'));
