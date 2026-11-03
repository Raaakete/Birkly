<?php
/**
 * Raster media helpers — focal/crop presentation, web variants, replace master.
 */

declare(strict_types=1);

require_once __DIR__ . '/media_storage.php';

const BIRKLY_MEDIA_WEB_VARIANT_MAX_WIDTH = 1920;
const BIRKLY_MEDIA_WEB_VARIANT_QUALITY = 82;

/**
 * @return array{x:int,y:int}
 */
function birkly_media_normalize_focal_point(mixed $focalPoint): array
{
    $x = 50;
    $y = 50;

    if (is_array($focalPoint)) {
        if (isset($focalPoint['x']) && is_numeric($focalPoint['x'])) {
            $x = (int) round((float) $focalPoint['x']);
        }
        if (isset($focalPoint['y']) && is_numeric($focalPoint['y'])) {
            $y = (int) round((float) $focalPoint['y']);
        }
    }

    return [
        'x' => max(0, min(100, $x)),
        'y' => max(0, min(100, $y)),
    ];
}

/**
 * Normalized crop rectangle (percent of source). Null = full frame.
 *
 * @return array{x:int,y:int,w:int,h:int}|null
 */
function birkly_media_normalize_crop_rect(mixed $crop): ?array
{
    if (!is_array($crop)) {
        return null;
    }

    $x = isset($crop['x']) && is_numeric($crop['x']) ? (int) round((float) $crop['x']) : 0;
    $y = isset($crop['y']) && is_numeric($crop['y']) ? (int) round((float) $crop['y']) : 0;
    $w = isset($crop['w']) && is_numeric($crop['w']) ? (int) round((float) $crop['w']) : 100;
    $h = isset($crop['h']) && is_numeric($crop['h']) ? (int) round((float) $crop['h']) : 100;

    $x = max(0, min(100, $x));
    $y = max(0, min(100, $y));
    $w = max(1, min(100 - $x, $w));
    $h = max(1, min(100 - $y, $h));

    if ($x === 0 && $y === 0 && $w === 100 && $h === 100) {
        return null;
    }

    return ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
}

/**
 * Merge master metadata with an optional named variant (web, …).
 *
 * @param array<string, mixed> $item
 * @return array<string, mixed>
 */
function birkly_media_item_for_variant(array $item, string $variantKey = ''): array
{
    $variantKey = trim($variantKey);
    if ($variantKey === '') {
        return $item;
    }

    $variants = $item['variants'] ?? null;
    if (!is_array($variants) || !isset($variants[$variantKey]) || !is_array($variants[$variantKey])) {
        return $item;
    }

    $variant = $variants[$variantKey];

    return array_merge($item, [
        'url' => (string) ($variant['url'] ?? $item['url'] ?? ''),
        'ext' => (string) ($variant['ext'] ?? $item['ext'] ?? ''),
        'size' => $variant['size'] ?? ($item['size'] ?? null),
        'dimensions' => $variant['dimensions'] ?? ($item['dimensions'] ?? null),
        'type' => (string) ($variant['type'] ?? $item['type'] ?? ''),
        '_variant' => $variantKey,
    ]);
}

/**
 * @param array<string, mixed> $item
 */
function birkly_media_render_img(array $item, string $alt = ''): string
{
    $url = htmlspecialchars((string) ($item['url'] ?? ''), ENT_QUOTES, 'UTF-8');
    if ($url === '') {
        return '';
    }

    $altText = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
    if ($altText === '' && isset($item['alt_text'])) {
        $altText = htmlspecialchars((string) $item['alt_text'], ENT_QUOTES, 'UTF-8');
    }

    $focal = birkly_media_normalize_focal_point($item['focal_point'] ?? null);
    $crop = birkly_media_normalize_crop_rect($item['crop_rect'] ?? null);

    if ($crop === null) {
        $style = 'object-position:' . $focal['x'] . '% ' . $focal['y'] . '%;';

        return '<img src="' . $url . '" alt="' . $altText . '" loading="lazy" style="' . $style . '">';
    }

    $aspect = $crop['w'] . ' / ' . $crop['h'];
    $imgWidth = (100 / $crop['w']) * 100;
    $imgHeight = (100 / $crop['h']) * 100;
    $offsetX = ($crop['x'] / $crop['w']) * 100;
    $offsetY = ($crop['y'] / $crop['h']) * 100;

    $imgStyle = 'width:' . round($imgWidth, 4) . '%;height:' . round($imgHeight, 4) . '%;'
        . 'margin-left:-' . round($offsetX, 4) . '%;margin-top:-' . round($offsetY, 4) . '%;'
        . 'object-fit:cover;object-position:' . $focal['x'] . '% ' . $focal['y'] . '%;max-width:none;';

    return '<span class="birkly-media-crop" style="display:inline-block;max-width:100%;aspect-ratio:'
        . htmlspecialchars($aspect, ENT_QUOTES, 'UTF-8') . ';overflow:hidden;line-height:0;">'
        . '<img src="' . $url . '" alt="' . $altText . '" loading="lazy" style="' . $imgStyle . '">'
        . '</span>';
}

function birkly_media_is_raster_path(string $path, string $mime = ''): bool
{
    if ($mime === 'image/svg+xml') {
        return false;
    }
    if ($mime !== '' && str_starts_with($mime, 'image/')) {
        return $mime !== 'image/svg+xml';
    }

    $info = @getimagesize($path);

    return $info !== false && (int) ($info[2] ?? 0) !== IMAGETYPE_UNKNOWN;
}

/**
 * Scale a raster image to $destPath (WebP when supported, else JPEG).
 */
function birkly_media_raster_save_scaled(
    string $sourcePath,
    string $destPath,
    int $maxWidth = BIRKLY_MEDIA_WEB_VARIANT_MAX_WIDTH,
    int $quality = BIRKLY_MEDIA_WEB_VARIANT_QUALITY
): bool {
    if (!is_file($sourcePath)) {
        return false;
    }

    if (!function_exists('imagecreatetruecolor')) {
        return copy($sourcePath, $destPath);
    }

    $info = @getimagesize($sourcePath);
    if ($info === false) {
        return false;
    }

    $width = (int) ($info[0] ?? 0);
    $height = (int) ($info[1] ?? 0);
    $type = (int) ($info[2] ?? 0);
    if ($width < 1 || $height < 1) {
        return false;
    }

    $source = null;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = @imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $source = @imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_GIF:
            $source = @imagecreatefromgif($sourcePath);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagecreatefromwebp')) {
                $source = @imagecreatefromwebp($sourcePath);
            }
            break;
    }

    if (!$source) {
        return false;
    }

    $maxWidth = max(1, $maxWidth);
    $scale = min(1.0, $maxWidth / max($width, $height));
    $targetWidth = max(1, (int) round($width * $scale));
    $targetHeight = max(1, (int) round($height * $scale));

    $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
    if (!$canvas) {
        imagedestroy($source);

        return false;
    }

    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
        imagealphablending($canvas, true);
    }

    imagecopyresampled(
        $canvas,
        $source,
        0,
        0,
        0,
        0,
        $targetWidth,
        $targetHeight,
        $width,
        $height
    );

    $quality = max(1, min(100, $quality));
    $useWebp = function_exists('imagewebp') && str_ends_with(strtolower($destPath), '.webp');
    $saved = $useWebp
        ? @imagewebp($canvas, $destPath, $quality)
        : @imagejpeg($canvas, $destPath, $quality);

    imagedestroy($source);
    imagedestroy($canvas);

    return $saved && is_file($destPath);
}

/**
 * Crop a raster source to $destPath using a normalized crop rectangle (percent).
 */
function birkly_media_raster_crop_to_file(
    string $sourcePath,
    string $destPath,
    array $cropRect,
    int $maxWidth = 2560,
    int $quality = 85
): bool {
    $crop = birkly_media_normalize_crop_rect($cropRect);
    if ($crop === null || !is_file($sourcePath)) {
        return false;
    }

    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }

    $info = @getimagesize($sourcePath);
    if ($info === false) {
        return false;
    }

    $width = (int) ($info[0] ?? 0);
    $height = (int) ($info[1] ?? 0);
    $type = (int) ($info[2] ?? 0);
    if ($width < 1 || $height < 1) {
        return false;
    }

    $source = null;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = @imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $source = @imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_GIF:
            $source = @imagecreatefromgif($sourcePath);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagecreatefromwebp')) {
                $source = @imagecreatefromwebp($sourcePath);
            }
            break;
    }

    if (!$source) {
        return false;
    }

    $srcX = (int) floor($width * ($crop['x'] / 100));
    $srcY = (int) floor($height * ($crop['y'] / 100));
    $srcW = max(1, (int) floor($width * ($crop['w'] / 100)));
    $srcH = max(1, (int) floor($height * ($crop['h'] / 100)));
    $srcW = min($srcW, $width - $srcX);
    $srcH = min($srcH, $height - $srcY);

    $maxWidth = max(1, $maxWidth);
    $scale = min(1.0, $maxWidth / $srcW);
    $targetW = max(1, (int) round($srcW * $scale));
    $targetH = max(1, (int) round($srcH * $scale));

    $canvas = imagecreatetruecolor($targetW, $targetH);
    if (!$canvas) {
        imagedestroy($source);

        return false;
    }

    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $targetW, $targetH, $transparent);
        imagealphablending($canvas, true);
    }

    imagecopyresampled(
        $canvas,
        $source,
        0,
        0,
        $srcX,
        $srcY,
        $targetW,
        $targetH,
        $srcW,
        $srcH
    );

    $quality = max(1, min(100, $quality));
    $useWebp = function_exists('imagewebp') && str_ends_with(strtolower($destPath), '.webp');
    $saved = $useWebp
        ? @imagewebp($canvas, $destPath, $quality)
        : @imagejpeg($canvas, $destPath, $quality);

    imagedestroy($source);
    imagedestroy($canvas);

    return $saved && is_file($destPath);
}

/**
 * @param array<string, mixed> $variantMeta
 */
function birkly_media_store_variant(
    string $mediaId,
    string $variantKey,
    string $variantFileId,
    string $destPath,
    string $folder,
    array $extra = []
): array {
    $extension = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));
    $mime = $extension === 'webp' ? 'image/webp' : ($extension === 'png' ? 'image/png' : 'image/jpeg');
    $url = birkly_media_public_url($folder, $variantFileId, $extension);
    $size = filesize($destPath) ?: 0;
    $dimensions = birkly_media_image_dimensions($destPath, $mime);

    $metadata = birkly_load_media_metadata();
    if (!isset($metadata[$mediaId]) || !is_array($metadata[$mediaId])) {
        return ['ok' => false, 'message' => 'Media metadata not found.'];
    }

    if (!isset($metadata[$mediaId]['variants']) || !is_array($metadata[$mediaId]['variants'])) {
        $metadata[$mediaId]['variants'] = [];
    }

    $metadata[$mediaId]['variants'][$variantKey] = array_merge($extra, [
        'url' => $url,
        'ext' => $extension,
        'type' => $mime,
        'size' => $size,
        'dimensions' => $dimensions,
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    if (!birkly_save_media_metadata($metadata)) {
        return ['ok' => false, 'message' => 'Failed to save variant metadata.'];
    }

    return ['ok' => true, 'data' => $metadata[$mediaId]];
}

/**
 * @return array{ok:bool,message?:string,data?:array<string,mixed>}
 */
function birkly_media_create_web_variant(string $mediaId): array
{
    if (!birkly_validate_media_id($mediaId)) {
        return ['ok' => false, 'message' => 'Invalid media ID.'];
    }

    $resolved = birkly_media_resolve_download_file($mediaId);
    if ($resolved === null) {
        return ['ok' => false, 'message' => 'Media file not found.'];
    }

    if (!birkly_media_is_raster_path($resolved['path'], $resolved['mime'])) {
        return ['ok' => false, 'message' => 'Web variants are only available for raster images.'];
    }

    $metadata = birkly_load_media_metadata();
    if (!isset($metadata[$mediaId]) || !is_array($metadata[$mediaId])) {
        return ['ok' => false, 'message' => 'Media metadata not found.'];
    }

    $item = $metadata[$mediaId];
    $folder = (string) ($item['folder'] ?? '');
    $variantId = $mediaId . '-web';
    $extension = function_exists('imagewebp') ? 'webp' : 'jpg';
    $mime = $extension === 'webp' ? 'image/webp' : 'image/jpeg';

    $dirResult = birkly_ensure_media_folder($folder);
    if (!$dirResult['ok'] || empty($dirResult['path'])) {
        return ['ok' => false, 'message' => $dirResult['message'] ?? 'Media folder is not writable.'];
    }

    $destPath = $dirResult['path'] . '/' . $variantId . '.' . $extension;
    if (!birkly_media_raster_save_scaled($resolved['path'], $destPath)) {
        return ['ok' => false, 'message' => 'Could not create optimized variant.'];
    }

    $stored = birkly_media_store_variant($mediaId, 'web', $variantId, $destPath, $folder);
    if (!$stored['ok']) {
        @unlink($destPath);

        return ['ok' => false, 'message' => $stored['message'] ?? 'Failed to save variant metadata.'];
    }

    return [
        'ok' => true,
        'data' => $stored['data'],
    ];
}

/**
 * Bake the current crop_rect into a derivative file (variants.crop).
 *
 * @return array{ok:bool,message?:string,data?:array<string,mixed>}
 */
function birkly_media_bake_crop_variant(string $mediaId): array
{
    if (!birkly_validate_media_id($mediaId)) {
        return ['ok' => false, 'message' => 'Invalid media ID.'];
    }

    $resolved = birkly_media_resolve_download_file($mediaId);
    if ($resolved === null) {
        return ['ok' => false, 'message' => 'Media file not found.'];
    }

    if (!birkly_media_is_raster_path($resolved['path'], $resolved['mime'])) {
        return ['ok' => false, 'message' => 'Crop variants are only available for raster images.'];
    }

    $metadata = birkly_load_media_metadata();
    if (!isset($metadata[$mediaId]) || !is_array($metadata[$mediaId])) {
        return ['ok' => false, 'message' => 'Media metadata not found.'];
    }

    $crop = birkly_media_normalize_crop_rect($metadata[$mediaId]['crop_rect'] ?? null);
    if ($crop === null) {
        return ['ok' => false, 'message' => 'Set a crop region before baking (not full frame).'];
    }

    $folder = (string) ($metadata[$mediaId]['folder'] ?? '');
    $variantId = $mediaId . '-crop';
    $extension = function_exists('imagewebp') ? 'webp' : 'jpg';

    $dirResult = birkly_ensure_media_folder($folder);
    if (!$dirResult['ok'] || empty($dirResult['path'])) {
        return ['ok' => false, 'message' => $dirResult['message'] ?? 'Media folder is not writable.'];
    }

    $destPath = $dirResult['path'] . '/' . $variantId . '.' . $extension;
    if (!birkly_media_raster_crop_to_file($resolved['path'], $destPath, $crop)) {
        return ['ok' => false, 'message' => 'Could not bake crop variant.'];
    }

    $stored = birkly_media_store_variant($mediaId, 'crop', $variantId, $destPath, $folder, [
        'source_crop' => $crop,
    ]);
    if (!$stored['ok']) {
        @unlink($destPath);

        return ['ok' => false, 'message' => $stored['message'] ?? 'Failed to save variant metadata.'];
    }

    return [
        'ok' => true,
        'data' => $stored['data'],
    ];
}

/**
 * Replace the master binary for an existing media id (metadata id unchanged).
 *
 * @return array{ok:bool,message?:string,data?:array<string,mixed>}
 */
function birkly_media_replace_master_from_path(string $mediaId, string $sourcePath, string $displayName, string $mimeType): array
{
    if (!birkly_validate_media_id($mediaId)) {
        return ['ok' => false, 'message' => 'Invalid media ID.'];
    }

    if (!is_file($sourcePath)) {
        return ['ok' => false, 'message' => 'Uploaded file is missing.'];
    }

    $metadata = birkly_load_media_metadata();
    if (!isset($metadata[$mediaId]) || !is_array($metadata[$mediaId])) {
        return ['ok' => false, 'message' => 'Media item not found.'];
    }

    $item = $metadata[$mediaId];
    $folder = (string) ($item['folder'] ?? '');
    $oldExt = preg_replace('/[^a-zA-Z0-9]/', '', (string) ($item['ext'] ?? ''));
    $newExt = strtolower(pathinfo($displayName, PATHINFO_EXTENSION));
    if ($newExt === '') {
        $map = birkly_media_extension_mime_map();
        foreach ($map as $ext => $mime) {
            if ($mime === $mimeType) {
                $newExt = $ext;
                break;
            }
        }
    }
    if ($newExt === '') {
        return ['ok' => false, 'message' => 'Could not determine file extension.'];
    }

    $dirResult = birkly_ensure_media_folder($folder);
    if (!$dirResult['ok'] || empty($dirResult['path'])) {
        return ['ok' => false, 'message' => $dirResult['message'] ?? 'Media folder is not writable.'];
    }

    $destPath = $dirResult['path'] . '/' . $mediaId . '.' . $newExt;
    if (!@copy($sourcePath, $destPath)) {
        return ['ok' => false, 'message' => 'Failed to write replaced file.'];
    }

    if ($oldExt !== '' && $oldExt !== $newExt) {
        $oldPath = $dirResult['path'] . '/' . $mediaId . '.' . $oldExt;
        if (is_file($oldPath) && realpath($oldPath) !== realpath($destPath)) {
            @unlink($oldPath);
        }
    }

    $size = filesize($destPath) ?: 0;
    $dimensions = birkly_media_image_dimensions($destPath, $mimeType);
    $url = birkly_media_public_url($folder, $mediaId, $newExt);

    $metadata[$mediaId]['ext'] = $newExt;
    $metadata[$mediaId]['url'] = $url;
    $metadata[$mediaId]['type'] = $mimeType;
    $metadata[$mediaId]['size'] = $size;
    $metadata[$mediaId]['dimensions'] = $dimensions;
    if ($displayName !== '') {
        $metadata[$mediaId]['name'] = $displayName;
    }

    if (!birkly_save_media_metadata($metadata)) {
        return ['ok' => false, 'message' => 'Failed to update media metadata.'];
    }

    return ['ok' => true, 'data' => $metadata[$mediaId]];
}
