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

function birkly_avatars_dir(): string
{
    return birkly_project_root() . '/data/avatars';
}

function birkly_legacy_avatars_dir(): string
{
    return birkly_project_root() . '/admin/assets/avatars';
}

function birkly_avatar_filename_is_safe(string $filename): bool
{
    return (bool) preg_match('/^avatar-[a-f0-9]{32}-[a-zA-Z0-9.]+\.jpg$/', $filename);
}

function birkly_avatar_public_url(string $filename): string
{
    return birkly_url(birkly_avatar_storage_path($filename));
}

function birkly_avatar_storage_path(string $filename): string
{
    return '/api/avatar.php?f=' . rawurlencode($filename);
}

function birkly_path_is_writable(string $path): bool
{
    if ($path === '') {
        return false;
    }

    if (is_writable($path)) {
        return true;
    }

    if (!is_dir($path) && !is_file($path)) {
        return false;
    }

    $probe = rtrim($path, '/\\') . '/.birkly-write-probe-' . getmypid() . '-' . bin2hex(random_bytes(4));
    if (@file_put_contents($probe, 'ok') === false) {
        return false;
    }

    @unlink($probe);
    return true;
}

/**
 * @return array{ok:bool,path?:string,message?:string}
 */
function birkly_ensure_avatars_dir(): array
{
    $dir = birkly_avatars_dir();

    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        @mkdir($dir, 0777, true);
    }

    if (!is_dir($dir)) {
        return ['ok' => false, 'message' => 'Could not create avatar storage folder.'];
    }

    if (!birkly_path_is_writable($dir)) {
        @chmod($dir, 0775);
    }
    if (!birkly_path_is_writable($dir)) {
        @chmod($dir, 0777);
    }
    if (!birkly_path_is_writable($dir)) {
        return ['ok' => false, 'message' => 'Avatar storage folder is not writable.'];
    }

    return ['ok' => true, 'path' => $dir];
}

function birkly_avatar_file_path(string $filename): ?string
{
    if (!birkly_avatar_filename_is_safe($filename)) {
        return null;
    }

    $primary = birkly_avatars_dir() . '/' . $filename;
    if (is_file($primary)) {
        return $primary;
    }

    $legacy = birkly_legacy_avatars_dir() . '/' . $filename;
    if (is_file($legacy)) {
        return $legacy;
    }

    return null;
}

function birkly_delete_local_avatar_file(?string $avatarUrl): void
{
    if ($avatarUrl === null || $avatarUrl === '') {
        return;
    }

    $path = parse_url($avatarUrl, PHP_URL_PATH) ?: $avatarUrl;
    $query = [];
    $queryString = parse_url($avatarUrl, PHP_URL_QUERY);
    if (is_string($queryString) && $queryString !== '') {
        parse_str($queryString, $query);
    }

    $filename = '';
    if (isset($query['f'])) {
        $filename = basename((string) $query['f']);
    } elseif (preg_match('#/avatars/([^/?]+)$#', $path, $matches)) {
        $filename = basename($matches[1]);
    }

    if ($filename === '' || !birkly_avatar_filename_is_safe($filename)) {
        return;
    }

    foreach ([birkly_avatars_dir(), birkly_legacy_avatars_dir()] as $dir) {
        $filepath = $dir . '/' . $filename;
        if (is_file($filepath)) {
            @unlink($filepath);
        }
    }
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

    $dirResult = birkly_ensure_avatars_dir();
    if (!$dirResult['ok']) {
        return ['ok' => false, 'message' => $dirResult['message'] ?? 'Avatar storage folder is not writable.'];
    }
    $avatarsDir = $dirResult['path'];

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
        'path' => birkly_avatar_storage_path($filename),
    ];
}
