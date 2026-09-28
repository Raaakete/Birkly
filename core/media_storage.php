<?php
/**
 * Shared media library storage helpers (media/ + .metadata.json).
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';

/**
 * Upload allowlist: extension → MIME type. The server accepts exactly these types
 * (api/admin_media.php) and every file picker offers exactly these extensions.
 *
 * @return array<string, string>
 */
function birkly_media_extension_mime_map(): array
{
    return [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'mp4' => 'video/mp4',
        'm4v' => 'video/mp4',
        'webm' => 'video/webm',
        'ogv' => 'video/ogg',
        'mov' => 'video/quicktime',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
    ];
}

/**
 * @return list<string>
 */
function birkly_media_allowed_mime_types(): array
{
    return array_values(array_unique(birkly_media_extension_mime_map()));
}

/**
 * Value for <input type="file" accept="…"> matching the upload allowlist.
 */
function birkly_media_upload_accept(): string
{
    return implode(',', array_map(static fn (string $ext): string => '.' . $ext, array_keys(birkly_media_extension_mime_map())));
}

function birkly_media_dir(): string
{
    return birkly_project_root() . '/media';
}

function birkly_media_metadata_path(): string
{
    return birkly_media_dir() . '/.metadata.json';
}

function birkly_validate_media_folder(string $folder): bool
{
    if ($folder === '' || $folder === 'all') {
        return true;
    }

    if (strpos($folder, '..') !== false || strpos($folder, '\\') !== false) {
        return false;
    }

    return preg_match('/^[a-zA-Z0-9_-]+(?:\/[a-zA-Z0-9_-]+)*$/', $folder) === 1;
}

function birkly_validate_media_id(string $mediaId): bool
{
    if ($mediaId === '') {
        return false;
    }

    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $mediaId)) {
        return false;
    }

    return strpos($mediaId, '..') === false
        && strpos($mediaId, '/') === false
        && strpos($mediaId, '\\') === false;
}

/**
 * @return array{ok:bool,path?:string,message?:string}
 */
function birkly_ensure_media_folder(string $folder = ''): array
{
    $mediaDir = birkly_media_dir();

    if ($folder !== '' && !birkly_validate_media_folder($folder)) {
        return ['ok' => false, 'message' => 'Invalid media folder.'];
    }

    $targetDir = $folder !== '' ? $mediaDir . '/' . $folder : $mediaDir;

    if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true)) {
        return ['ok' => false, 'message' => 'Could not create media storage folder.'];
    }

    if (!is_dir($targetDir) || !is_writable($targetDir)) {
        return ['ok' => false, 'message' => 'Media storage folder is not writable.'];
    }

    return ['ok' => true, 'path' => $targetDir];
}

function birkly_load_media_metadata(): array
{
    $metadataPath = birkly_media_metadata_path();
    if (!is_file($metadataPath)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($metadataPath), true);
    return is_array($decoded) ? $decoded : [];
}

function birkly_save_media_metadata(array $metadata): bool
{
    $dirResult = birkly_ensure_media_folder();
    if (!$dirResult['ok']) {
        return false;
    }

    $metadataPath = birkly_media_metadata_path();
    return file_put_contents(
        $metadataPath,
        json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    ) !== false;
}

function birkly_generate_media_id(string $filename): string
{
    $baseName = pathinfo($filename, PATHINFO_FILENAME);

    if (is_string($baseName) && strlen($baseName) >= 12 && ctype_alnum($baseName)) {
        return $baseName;
    }

    return uniqid();
}

function birkly_media_public_url(string $folder, string $mediaId, string $extension): string
{
    $filename = $mediaId . '.' . ltrim($extension, '.');
    return '/media/' . ($folder !== '' ? $folder . '/' : '') . $filename;
}

function birkly_media_file_path(string $folder, string $mediaId, string $extension): string
{
    $dirResult = birkly_ensure_media_folder($folder);
    if (!$dirResult['ok'] || empty($dirResult['path'])) {
        return '';
    }

    return $dirResult['path'] . '/' . $mediaId . '.' . ltrim($extension, '.');
}

/**
 * @return array{width:int,height:int}|null
 */
function birkly_media_image_dimensions(string $filepath, string $mimeType): ?array
{
    if (strpos($mimeType, 'image/') !== 0) {
        return null;
    }

    $info = @getimagesize($filepath);
    if (!$info) {
        return null;
    }

    return [
        'width' => (int) $info[0],
        'height' => (int) $info[1],
    ];
}

/**
 * @param array<string, mixed> $item
 * @return array{ok:bool,data?:array<string,mixed>,message?:string}
 */
function birkly_register_media_item(array $item): array
{
    $mediaId = (string) ($item['id'] ?? '');
    if (!birkly_validate_media_id($mediaId)) {
        return ['ok' => false, 'message' => 'Invalid media ID.'];
    }

    $folder = (string) ($item['folder'] ?? '');
    if (!birkly_validate_media_folder($folder)) {
        return ['ok' => false, 'message' => 'Invalid media folder.'];
    }

    $extension = strtolower((string) ($item['ext'] ?? pathinfo((string) ($item['url'] ?? ''), PATHINFO_EXTENSION)));
    if ($extension === '') {
        return ['ok' => false, 'message' => 'Media extension is required.'];
    }

    $filepath = birkly_media_file_path($folder, $mediaId, $extension);
    if ($filepath === '' || !is_file($filepath)) {
        return ['ok' => false, 'message' => 'Media file not found.'];
    }

    $mimeType = (string) ($item['type'] ?? mime_content_type($filepath));
    $fileSize = (int) ($item['size'] ?? filesize($filepath));
    $url = (string) ($item['url'] ?? birkly_media_public_url($folder, $mediaId, $extension));

    $metadataItem = [
        'id' => $mediaId,
        'name' => (string) ($item['name'] ?? $mediaId . '.' . $extension),
        'title' => (string) ($item['title'] ?? $item['name'] ?? $mediaId),
        'url' => $url,
        'type' => $mimeType,
        'size' => $fileSize,
        'created_at' => (string) ($item['created_at'] ?? date('Y-m-d H:i:s')),
        'uploaded_by' => (string) ($item['uploaded_by'] ?? 'System'),
        'folder' => $folder,
        'alt_text' => (string) ($item['alt_text'] ?? ''),
        'focal_point' => ['x' => 50, 'y' => 50],
        'dimensions' => $item['dimensions'] ?? birkly_media_image_dimensions($filepath, $mimeType),
        'ext' => $extension,
    ];

    $metadata = birkly_load_media_metadata();
    $metadata[$mediaId] = $metadataItem;

    if (!birkly_save_media_metadata($metadata)) {
        return ['ok' => false, 'message' => 'Failed to save media metadata.'];
    }

    return ['ok' => true, 'data' => $metadataItem];
}

function birkly_delete_media_item(string $mediaId): bool
{
    if (!birkly_validate_media_id($mediaId)) {
        return false;
    }

    $mediaDir = realpath(birkly_media_dir());
    if ($mediaDir === false) {
        return false;
    }

    $metadata = birkly_load_media_metadata();
    if (!isset($metadata[$mediaId])) {
        return false;
    }

    $item = $metadata[$mediaId];
    $folder = (string) ($item['folder'] ?? '');
    $extension = preg_replace('/[^a-zA-Z0-9]/', '', (string) ($item['ext'] ?? ''));
    $filepath = $folder !== ''
        ? $mediaDir . '/' . $folder . '/' . $mediaId . ($extension !== '' ? '.' . $extension : '')
        : $mediaDir . '/' . $mediaId . ($extension !== '' ? '.' . $extension : '');

    $realPath = realpath($filepath);
    if ($realPath !== false && strpos($realPath, $mediaDir) === 0 && is_file($realPath)) {
        @unlink($realPath);
    }

    unset($metadata[$mediaId]);
    return birkly_save_media_metadata($metadata);
}

function birkly_delete_media_by_url(?string $url): void
{
    if ($url === null || $url === '') {
        return;
    }

    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $path = $url;
    }

    if (!preg_match('#/media/(?:avatars/)?([^/?]+)\.([a-zA-Z0-9]+)$#', $path, $matches)) {
        return;
    }

    $mediaId = $matches[1];
    birkly_delete_media_item($mediaId);
}
