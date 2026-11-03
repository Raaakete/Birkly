<?php
/**
 * Media replace, crop metadata, and web variant helpers.
 *
 * Run: php tests/admin_media_replace_variant_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
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

$assert(
    birkly_media_normalize_crop_rect(['x' => 0, 'y' => 0, 'w' => 100, 'h' => 100]) === null,
    'full-frame crop normalizes to null'
);
$crop = birkly_media_normalize_crop_rect(['x' => 10, 'y' => 5, 'w' => 50, 'h' => 40]);
$assert(is_array($crop) && $crop['w'] === 50, 'partial crop retained');

$html = birkly_media_render_img([
    'url' => '/media/test.jpg',
    'alt_text' => 'Hero',
    'focal_point' => ['x' => 30, 'y' => 70],
]);
$assert(str_contains($html, 'object-position:30% 70%'), 'focal point applied in media_img html');

$cropHtml = birkly_media_render_img([
    'url' => '/media/test.jpg',
    'alt_text' => 'Hero',
    'crop_rect' => ['x' => 10, 'y' => 10, 'w' => 50, 'h' => 50],
]);
$assert(str_contains($cropHtml, 'birkly-media-crop'), 'crop wrapper emitted');

$metadataPath = birkly_media_metadata_path();
$metadataBackup = is_file($metadataPath) ? file_get_contents($metadataPath) : null;
$mediaDir = birkly_media_dir();
$testId = 'birklyrepl' . bin2hex(random_bytes(3));
$masterPath = $mediaDir . '/' . $testId . '.png';
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

try {
    if (!is_dir($mediaDir)) {
        mkdir($mediaDir, 0755, true);
    }
    file_put_contents($masterPath, $png);

    $metadata = birkly_load_media_metadata();
    $metadata[$testId] = [
        'id' => $testId,
        'name' => $testId . '.png',
        'title' => 'Replace test',
        'url' => '/media/' . $testId . '.png',
        'type' => 'image/png',
        'size' => strlen($png),
        'created_at' => date('Y-m-d H:i:s'),
        'uploaded_by' => 'test',
        'folder' => '',
        'alt_text' => 'keep',
        'focal_point' => ['x' => 40, 'y' => 60],
        'ext' => 'png',
    ];
    birkly_save_media_metadata($metadata);

    $tmp = tempnam(sys_get_temp_dir(), 'birkly-repl-');
    file_put_contents($tmp, $png);
    $replace = birkly_media_replace_master_from_path($testId, $tmp, 'replaced.jpg', 'image/jpeg');
    @unlink($tmp);
    $assert($replace['ok'] ?? false, 'replace master succeeds');
    $assert(!is_file($masterPath), 'old png removed after ext change');
    $assert(is_file($mediaDir . '/' . $testId . '.jpg'), 'new jpg on disk');

    $metaAfter = birkly_load_media_metadata()[$testId] ?? [];
    $assert(($metaAfter['alt_text'] ?? '') === 'keep', 'replace preserves alt metadata');
    $assert(str_ends_with((string) ($metaAfter['url'] ?? ''), '.jpg'), 'metadata url updated');

    $metadata[$testId]['crop_rect'] = ['x' => 5, 'y' => 5, 'w' => 50, 'h' => 50];
    birkly_save_media_metadata($metadata);

    if (function_exists('imagecreatetruecolor')) {
        $variant = birkly_media_create_web_variant($testId);
        $assert($variant['ok'] ?? false, 'web variant created when GD available');
        $webPath = $mediaDir . '/' . $testId . '-web.' . (function_exists('imagewebp') ? 'webp' : 'jpg');
        $assert(is_file($webPath), 'variant file exists on disk');

        $cropVariant = birkly_media_bake_crop_variant($testId);
        $assert($cropVariant['ok'] ?? false, 'crop variant baked when GD available');
        $cropPath = $mediaDir . '/' . $testId . '-crop.' . (function_exists('imagewebp') ? 'webp' : 'jpg');
        $assert(is_file($cropPath), 'baked crop file exists on disk');
    } else {
        echo "skip - GD not available for variant test\n";
    }
} finally {
    @unlink($masterPath);
    @unlink($mediaDir . '/' . $testId . '.jpg');
    @unlink($mediaDir . '/' . $testId . '-web.webp');
    @unlink($mediaDir . '/' . $testId . '-web.jpg');
    @unlink($mediaDir . '/' . $testId . '-crop.webp');
    @unlink($mediaDir . '/' . $testId . '-crop.jpg');
    if ($metadataBackup !== null) {
        file_put_contents($metadataPath, $metadataBackup);
    } else {
        @unlink($metadataPath);
    }
}

require_once $root . '/api/admin_media.php';
$assert(function_exists('replaceMedia') && function_exists('createMediaVariant'), 'admin media actions registered');

exit($failures > 0 ? 1 : 0);
