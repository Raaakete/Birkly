<?php
/**
 * Avatar upload helpers — validation, optimization, and storage.
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';

const BIRKLY_AVATAR_MAX_UPLOAD_BYTES = 8388608; // 8MB raw upload
const BIRKLY_AVATAR_MAX_DIMENSION = 512;
const BIRKLY_AVATAR_JPEG_QUALITY = 88;

function birkly_avatar_max_upload_bytes(): int
{
    return BIRKLY_AVATAR_MAX_UPLOAD_BYTES;
}

function birkly_avatar_max_upload_label(): string
{
    $mb = (int) round(BIRKLY_AVATAR_MAX_UPLOAD_BYTES / (1024 * 1024));
    return $mb . 'MB';
}

function birkly_avatar_upload_error_message(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
            $limit = trim((string) ini_get('upload_max_filesize'));
            return $limit !== ''
                ? 'File exceeds the server upload limit (' . $limit . '). Choose a smaller image or contact your host.'
                : 'File exceeds the server upload limit. Choose a smaller image.';
        case UPLOAD_ERR_FORM_SIZE:
            return 'File is too large for this form. Choose a smaller image.';
        case UPLOAD_ERR_PARTIAL:
            return 'The uploaded file was only partially uploaded. Please try again.';
        case UPLOAD_ERR_NO_FILE:
            return 'No file uploaded.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Server is missing a temporary folder for uploads.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write uploaded file to disk.';
        case UPLOAD_ERR_EXTENSION:
            return 'A server extension blocked the file upload.';
        default:
            return 'Upload error (code ' . $code . ').';
    }
}

/**
 * Resize and re-encode an uploaded avatar to a square-bounded JPEG.
 */
function birkly_optimize_avatar_file(string $sourcePath, string $destPath): bool
{
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
        return copy($sourcePath, $destPath);
    }

    $maxDimension = BIRKLY_AVATAR_MAX_DIMENSION;
    $scale = min(1, $maxDimension / max($width, $height));
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
    } else {
        $background = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $background);
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

    $saved = imagejpeg($canvas, $destPath, BIRKLY_AVATAR_JPEG_QUALITY);
    imagedestroy($source);
    imagedestroy($canvas);

    return $saved && is_file($destPath);
}

/**
 * Process an uploaded avatar field and store an optimized JPEG.
 *
 * @param array<string, mixed> $sessionUser
 * @return array{ok:bool,path?:string,message?:string}
 */
function birkly_process_avatar_upload_from_request(array $sessionUser): array
{
    $file = null;
    foreach (['avatar', 'file'] as $field) {
        if (!isset($_FILES[$field])) {
            continue;
        }
        if ((int) $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ((int) $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            return [
                'ok' => false,
                'message' => birkly_avatar_upload_error_message((int) $_FILES[$field]['error']),
            ];
        }
        $file = $_FILES[$field];
        break;
    }

    if (!$file || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'message' => 'No file uploaded or upload error'];
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    if (!in_array($mimeType, $allowedTypes, true)) {
        return ['ok' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WebP images are allowed.'];
    }

    if (($file['size'] ?? 0) > birkly_avatar_max_upload_bytes()) {
        return [
            'ok' => false,
            'message' => 'File too large. Maximum size is ' . birkly_avatar_max_upload_label() . '.',
        ];
    }

    $avatarsDir = dirname(__DIR__) . '/admin/assets/avatars';
    if (!is_dir($avatarsDir) && !mkdir($avatarsDir, 0755, true)) {
        return ['ok' => false, 'message' => 'Avatar storage folder is not writable.'];
    }
    if (!is_writable($avatarsDir)) {
        return ['ok' => false, 'message' => 'Avatar storage folder is not writable.'];
    }

    $avatarUserId = trim((string) ($sessionUser['id'] ?? ''));
    if ($avatarUserId === '' && !empty($sessionUser['email'])) {
        require_once __DIR__ . '/birkly_users.php';
        $avatarUserId = birkly_user_id_from_email((string) $sessionUser['email']);
    }

    $filename = 'avatar-' . $avatarUserId . '-' . uniqid('', true) . '.jpg';
    $filepath = $avatarsDir . '/' . $filename;
    $tempPath = $filepath . '.upload';

    if (!move_uploaded_file($file['tmp_name'], $tempPath)) {
        return ['ok' => false, 'message' => 'Failed to save uploaded file.'];
    }

    if (!birkly_optimize_avatar_file($tempPath, $filepath)) {
        @unlink($tempPath);
        return ['ok' => false, 'message' => 'Failed to process image. Try a JPG or PNG file.'];
    }

    @unlink($tempPath);

    return [
        'ok' => true,
        'path' => birkly_url('/admin/assets/avatars/' . $filename),
    ];
}
