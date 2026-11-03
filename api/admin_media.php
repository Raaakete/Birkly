<?php

$__cmsMcpFunctionsOnly = !empty($GLOBALS['__CMS_MCP_LOAD_FUNCTIONS_ONLY']);

require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../core/media_storage.php';

if ($__cmsMcpFunctionsOnly || !empty($GLOBALS['__HARU_INTERNAL_CALL'])) {
    require_once __DIR__ . '/../core/cms_mcp_request.php';
}

function admin_media_csrf_valid(): bool
{
    if (!empty($GLOBALS['__CMS_MCP_DISPATCH'])) {
        return true;
    }

    return validate_csrf_token((string) ($_POST['_csrf'] ?? ''));
}

function listMedia() {
    $mediaDir = __DIR__ . '/../media';
    $media = [];
    
    // Create media directory if it doesn't exist
    if (!is_dir($mediaDir)) {
        mkdir($mediaDir, 0755, true);
    }
    
    // Get folder filter
    $folder = $_GET['folder'] ?? '';
    if (!birkly_validate_media_folder($folder)) {
        return ['error' => 'Invalid folder name'];
    }
    $search = $_GET['search'] ?? '';
    $type = $_GET['type'] ?? '';
    $sort = $_GET['sort'] ?? 'date';
    $order = $_GET['order'] ?? 'desc';
    
    // Load only the root metadata file (consolidated approach)
    $metadata = [];
    $mainMetadataPath = $mediaDir . '/.metadata.json';
    if (file_exists($mainMetadataPath)) {
        $metadata = json_decode(file_get_contents($mainMetadataPath), true) ?: [];
    }
    
    // Process metadata and filter by folder
    if ($metadata) {
        foreach ($metadata as $id => $item) {
            // Filter by folder first (unless showing all)
            if ($folder !== 'all') {
                $itemFolder = $item['folder'] ?? '';
                if ($folder !== $itemFolder) {
                    continue;
                }
            }
            
            // Check if file still exists
            $itemFolder = $item['folder'] ?? '';
            $filePath = $itemFolder ? $mediaDir . '/' . $itemFolder . '/' . $id . '.' . $item['ext'] : $mediaDir . '/' . $id . '.' . $item['ext'];
            
            if (file_exists($filePath)) {
                // Apply other filters
                if ($search && stripos($item['name'], $search) === false) {
                    continue;
                }
                if ($type && $item['type'] !== $type) {
                    continue;
                }
                
                $media[] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'title' => $item['title'] ?? $item['name'],
                    'url' => $item['url'],
                    'type' => $item['type'],
                    'category' => getMediaCategory($item['type']),
                    'size' => $item['size'],
                    'size_formatted' => formatFileSize($item['size']),
                    'uploaded_at' => $item['created_at'],
                    'uploaded_by' => $item['uploaded_by'] ?? 'Unknown',
                    'folder' => $item['folder'] ?? $folder,
                    'alt_text' => $item['alt_text'] ?? '',
                    'focal_point' => normalizeFocalPoint($item['focal_point'] ?? null),
                    'crop_rect' => $item['crop_rect'] ?? null,
                    'variants' => $item['variants'] ?? null,
                    'dimensions' => isset($item['dimensions']) ? $item['dimensions'] : null,
                    'duration' => isset($item['duration']) ? $item['duration'] : null
                ];
            }
        }
    }
    
    // If no metadata or no media found, fallback to file system scan
    if (empty($media)) {
        $scanPath = $folder ? $mediaDir . '/' . $folder : $mediaDir;
        $files = glob($scanPath . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                $filename = basename($file);
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                // Get file info
                $fileSize = filesize($file);
                $fileType = mime_content_type($file);
                $uploadTime = date('Y-m-d H:i:s', filemtime($file));
                
                // Extract ID from filename (assuming format: ID.ext)
                $mediaId = pathinfo($filename, PATHINFO_FILENAME);
                
                $media[] = [
                    'id' => $mediaId,
                    'name' => $filename,
                    'title' => $filename,
                    'url' => '/media/' . ($folder ? $folder . '/' : '') . $filename,
                    'type' => $fileType,
                    'category' => getMediaCategory($fileType),
                    'size' => $fileSize,
                    'size_formatted' => formatFileSize($fileSize),
                    'uploaded_at' => $uploadTime,
                    'uploaded_by' => 'System',
                    'folder' => $folder,
                    'alt_text' => '',
                    'focal_point' => normalizeFocalPoint(null),
                    'dimensions' => getImageDimensions($file, $fileType),
                    'duration' => null
                ];
            }
        }
    }
    
    // Sort media
    usort($media, function($a, $b) use ($sort, $order) {
        $result = 0;
        switch ($sort) {
            case 'name':
                $result = strcasecmp($a['name'], $b['name']);
                break;
            case 'size':
                $result = $a['size'] - $b['size'];
                break;
            case 'type':
                $result = strcasecmp($a['type'], $b['type']);
                break;
            case 'date':
            default:
                $result = strtotime($a['uploaded_at']) - strtotime($b['uploaded_at']);
                break;
        }
        return $order === 'desc' ? -$result : $result;
    });
    
    return [
        'status' => 'ok',
        'data' => $media
    ];
}

function listFolders() {
    $mediaDir = __DIR__ . '/../media';
    $folders = [];
    
    if (is_dir($mediaDir)) {
        $folders = scanFoldersRecursively($mediaDir, '');
    }
    
    return ['status' => 'ok', 'data' => $folders];
}

function admin_media_metadata_folder_counts(): array
{
    static $counts = null;
    if ($counts !== null) {
        return $counts;
    }
    $counts = [];
    $metadataPath = __DIR__ . '/../media/.metadata.json';
    if (!is_file($metadataPath)) {
        return $counts;
    }
    $metadata = json_decode((string) file_get_contents($metadataPath), true);
    if (!is_array($metadata)) {
        return $counts;
    }
    foreach ($metadata as $item) {
        if (!is_array($item)) {
            continue;
        }
        $folder = (string) ($item['folder'] ?? '');
        $counts[$folder] = ($counts[$folder] ?? 0) + 1;
    }
    return $counts;
}

function scanFoldersRecursively($baseDir, $relativePath) {
    $folders = [];
    $fullPath = $relativePath ? $baseDir . '/' . $relativePath : $baseDir;
    $metadataCounts = admin_media_metadata_folder_counts();
    
    if (is_dir($fullPath)) {
        $items = scandir($fullPath);
        foreach ($items as $item) {
            if ($item !== '.' && $item !== '..' && is_dir($fullPath . '/' . $item)) {
                $folderPath = $relativePath ? $relativePath . '/' . $item : $item;
                $folderFullPath = $fullPath . '/' . $item;
                
                $count = $metadataCounts[$folderPath] ?? null;
                if ($count === null) {
                    $count = 0;
                    $files = glob($folderFullPath . '/*');
                    foreach ($files as $file) {
                        if (is_file($file) && !str_ends_with($file, '.metadata.json')) {
                            $count++;
                        }
                    }
                }
                
                $folders[] = [
                    'name' => $item,
                    'path' => $folderPath,
                    'count' => $count,
                    'level' => substr_count($folderPath, '/')
                ];
                
                // Recursively scan subfolders
                $subfolders = scanFoldersRecursively($baseDir, $folderPath);
                $folders = array_merge($folders, $subfolders);
            }
        }
    }
    
    return $folders;
}

function createFolder() {
    // SECURITY FIX: Require CSRF token
    if (!admin_media_csrf_valid()) {
        return ['error' => 'Invalid CSRF token'];
    }
    
    $folderName = $_POST['name'] ?? '';
    if (empty($folderName)) {
        return ['error' => 'Folder name is required'];
    }
    
    $mediaDir = __DIR__ . '/../media';
    $folderPath = $mediaDir . '/' . $folderName;
    
    if (is_dir($folderPath)) {
        return ['error' => 'Folder already exists'];
    }
    
    if (mkdir($folderPath, 0755, true)) {
        return [
            'status' => 'ok',
            'message' => 'Folder created successfully',
            'data' => [
                'name' => $folderName,
                'path' => $folderName
            ]
        ];
    } else {
        return ['error' => 'Failed to create folder'];
    }
}

/**
 * php.ini size shorthand (8M, 64M) to bytes. 0 means unlimited or unparsable.
 */
function admin_media_ini_to_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '' || $value === '-1') {
        return 0;
    }
    $unit = strtolower(substr($value, -1));
    $number = $value;
    $multiplier = 1;
    if ($unit === 'g' || $unit === 'm' || $unit === 'k') {
        $number = substr($value, 0, -1);
        $multiplier = $unit === 'g' ? 1024 * 1024 * 1024 : ($unit === 'm' ? 1024 * 1024 : 1024);
    }
    if (!is_numeric($number)) {
        return 0;
    }
    return (int) round((float) $number * $multiplier);
}

function admin_media_content_length_exceeds_post_max(int $contentLength, string $postMax): bool
{
    if ($contentLength <= 0) {
        return false;
    }
    $limit = admin_media_ini_to_bytes($postMax);
    if ($limit <= 0) {
        return false;
    }
    return $contentLength > $limit;
}

/**
 * PHP drops $_POST and $_FILES when the body exceeds post_max_size, which
 * otherwise looks like a missing CSRF token.
 */
function admin_media_request_exceeds_post_limit(): bool
{
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if (admin_media_content_length_exceeds_post_max($length, (string) ini_get('post_max_size'))) {
        return true;
    }
    return $length > 0 && $_POST === [] && $_FILES === [];
}

/**
 * upload_max_filesize rejects the file but still leaves $_POST intact, so the
 * empty-body check above does not see it.
 */
function admin_media_upload_rejected_by_ini(): bool
{
    $file = $_FILES['file'] ?? null;
    if (!is_array($file)) {
        return false;
    }
    $error = (int) ($file['error'] ?? UPLOAD_ERR_OK);
    return $error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE;
}

/**
 * Media setting from Settings → Media. Falls back to 50MB when unset.
 * The settings form allows 1–100MB.
 */
function admin_media_configured_max_bytes(): int
{
    $fallback = 50 * 1024 * 1024;
    $path = dirname(__DIR__) . '/settings/media.json';
    if (!is_file($path)) {
        return $fallback;
    }
    $raw = json_decode((string) file_get_contents($path), true);
    $bytes = is_array($raw) ? (int) ($raw['max_upload_size'] ?? 0) : 0;
    if ($bytes < 1024 * 1024) {
        return $fallback;
    }
    return min($bytes, 100 * 1024 * 1024);
}

function admin_media_server_upload_limit_message(): string
{
    $upload = trim((string) ini_get('upload_max_filesize'));
    $post = trim((string) ini_get('post_max_size'));
    $mb = max(1, (int) round(admin_media_configured_max_bytes() / (1024 * 1024)));
    $uploadLabel = $upload !== '' ? $upload : 'unknown';
    $postLabel = $post !== '' ? $post : 'unknown';

    return 'File too large for this server. PHP upload_max_filesize is ' . $uploadLabel
        . ' and post_max_size is ' . $postLabel
        . '. The media setting allows ' . $mb
        . 'MB. Increase both PHP limits to at least that size, then try again.';
}

/**
 * Extension fallback when Windows finfo reports application/octet-stream.
 * Executable extensions are intentionally absent.
 *
 * @return array<string, string>
 */
function admin_media_extension_mime_map(): array
{
    return birkly_media_extension_mime_map();
}

function admin_media_allowed_mime_types(): array
{
    return birkly_media_allowed_mime_types();
}

/**
 * Detect MIME type for uploads, normalizing SVG which finfo may report as text/xml.
 */
function detectUploadMimeType(string $tmpPath, string $originalName): string
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $mappedEarly = admin_media_extension_mime_map()[$ext] ?? '';
    // Windows finfo can be slow or unhelpful on large video/audio files; trust the allowlisted extension map.
    if ($mappedEarly !== '' && (str_starts_with($mappedEarly, 'video/') || str_starts_with($mappedEarly, 'audio/'))) {
        return $mappedEarly;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detected = $finfo ? (finfo_file($finfo, $tmpPath) ?: '') : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    $detected = strtolower(trim((string) $detected));
    if ($ext === 'svg') {
        $head = @file_get_contents($tmpPath, false, null, 0, 8192);
        if ($head !== false && stripos($head, '<svg') !== false) {
            return 'image/svg+xml';
        }
    }

    $mapped = admin_media_extension_mime_map()[$ext] ?? '';
    $weak = $detected === ''
        || $detected === 'application/octet-stream'
        || $detected === 'application/x-empty'
        || ($detected === 'text/plain' && $mapped !== '' && !str_starts_with($mapped, 'text/'));
    if ($weak && $mapped !== '' && $ext !== 'svg') {
        return $mapped;
    }

    if ($mapped !== '' && !in_array($detected, admin_media_allowed_mime_types(), true)
        && in_array($mapped, admin_media_allowed_mime_types(), true)) {
        return $mapped;
    }

    return $detected;
}

function isRasterImageMime(string $mimeType): bool
{
    return str_starts_with($mimeType, 'image/') && $mimeType !== 'image/svg+xml';
}

function uploadMedia() {
    @set_time_limit(0);
    @ini_set('max_input_time', '600');

    if (admin_media_request_exceeds_post_limit() || admin_media_upload_rejected_by_ini()) {
        return [
            'status'  => 'error',
            'message' => admin_media_server_upload_limit_message(),
        ];
    }

    if (!admin_media_csrf_valid()) {
        return [
            'status'  => 'error',
            'message' => 'Invalid CSRF token'
        ];
    }
    
    // SECURITY FIX: Add rate limiting for file uploads
    require_once __DIR__ . '/rate_limit.php';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userId = $_SESSION['user']['id'] ?? $ip;
    
    // Rate limit: 30 uploads per 5 minutes per user/IP
    $rateLimiter = new RateLimiter('admin_media_upload_' . $userId, 30, 300);
    if (!$rateLimiter->check()) {
        $retryAfter = $rateLimiter->getRetryAfter();
        return [
            'status'      => 'error',
            'message'     => 'Too many upload attempts. Please try again later.',
            'retry_after' => $retryAfter
        ];
    }
    
    if (!isset($_FILES['file'])) {
        return [
            'status'  => 'error',
            'message' => 'No file uploaded'
        ];
    }
    
    $file = $_FILES['file'];
    $mediaDir = __DIR__ . '/../media';
    $folder = $_POST['folder'] ?? '';
    
    // Create media directory if it doesn't exist
    if (!is_dir($mediaDir)) {
        mkdir($mediaDir, 0755, true);
    }
    
    // Create folder if specified
    if ($folder) {
        $folderPath = $mediaDir . '/' . $folder;
        if (!is_dir($folderPath)) {
            mkdir($folderPath, 0755, true);
        }
        $mediaDir = $folderPath;
    }
    
    $allowedTypes = admin_media_allowed_mime_types();
    
    // Always use finfo_file() to detect MIME type from file content, not client-provided type
    $detectedMimeType = detectUploadMimeType($file['tmp_name'], (string) ($file['name'] ?? ''));
    
    if (!in_array($detectedMimeType, $allowedTypes, true)) {
        return [
            'status'  => 'error',
            'message' => 'Invalid file type. Only images, videos, audio, documents, and archives are allowed.'
        ];
    }
    
    // Raster images only — SVG and other vector formats are not handled by getimagesize()
    if (isRasterImageMime($detectedMimeType)) {
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return [
                'status'  => 'error',
                'message' => 'Invalid image file. File does not appear to be a valid image.'
            ];
        }
    }
    
    $maxBytes = admin_media_configured_max_bytes();
    if (($file['size'] ?? 0) > $maxBytes) {
        $mb = max(1, (int) round($maxBytes / (1024 * 1024)));
        return [
            'status'  => 'error',
            'message' => 'File too large. Maximum size is ' . $mb . 'MB.'
        ];
    }
    
    // Generate unique filename
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid() . '.' . $extension;
    $filepath = $mediaDir . '/' . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'status'  => 'error',
            'message' => 'Failed to save file'
        ];
    }
    
    $mediaId = generateMediaId($filename);
    
    // Get file info
    $fileType = $detectedMimeType !== '' ? $detectedMimeType : (mime_content_type($filepath) ?: 'application/octet-stream');
    $fileSize = filesize($filepath);
    $dimensions = getImageDimensions($filepath, $fileType);
    
    // Save metadata
    $metadata = [
        'id'         => $mediaId,
        'name'       => $file['name'],
        'title'      => $_POST['title'] ?? $file['name'],
        'url'        => '/media/' . ($folder ? $folder . '/' : '') . $filename,
        'type'       => $fileType,
        'size'       => $fileSize,
        'created_at' => date('Y-m-d H:i:s'),
        'uploaded_by'=> 'Admin',
        'folder'     => $folder,
        'alt_text'   => '',
        'focal_point'=> normalizeFocalPoint(null),
        'dimensions' => $dimensions,
        'ext'        => $extension
    ];
    
    saveMetadata($metadata);
    
    return [
        'status' => 'ok',
        'data'   => [
            'id'              => $mediaId,
            'name'            => $file['name'],
            'title'           => $metadata['title'],
            'url'             => $metadata['url'],
            'type'            => $fileType,
            'category'        => getMediaCategory($fileType),
            'size'            => $fileSize,
            'size_formatted'  => formatFileSize($fileSize),
            'uploaded_at'     => $metadata['created_at'],
            'uploaded_by'     => $metadata['uploaded_by'],
            'folder'          => $folder,
            'alt_text'        => $metadata['alt_text'],
            'focal_point'     => $metadata['focal_point'],
            'dimensions'      => $dimensions
        ]
    ];
}

/**
 * @return array{ok:bool,mime?:string,ext?:string,message?:string}
 */
function admin_media_validate_upload_file(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Upload error (code ' . (int) ($file['error'] ?? 0) . ').'];
    }

    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'message' => 'No file uploaded'];
    }

    $allowedTypes = admin_media_allowed_mime_types();
    $detectedMimeType = detectUploadMimeType($file['tmp_name'], (string) ($file['name'] ?? ''));

    if (!in_array($detectedMimeType, $allowedTypes, true)) {
        return [
            'ok' => false,
            'message' => 'Invalid file type. Only images, videos, audio, documents, and archives are allowed.',
        ];
    }

    if (isRasterImageMime($detectedMimeType)) {
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return ['ok' => false, 'message' => 'Invalid image file. File does not appear to be a valid image.'];
        }
    }

    $maxBytes = admin_media_configured_max_bytes();
    if (($file['size'] ?? 0) > $maxBytes) {
        $mb = max(1, (int) round($maxBytes / (1024 * 1024)));

        return ['ok' => false, 'message' => 'File too large. Maximum size is ' . $mb . 'MB.'];
    }

    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

    return ['ok' => true, 'mime' => $detectedMimeType, 'ext' => $extension];
}

function replaceMedia(): array
{
    @set_time_limit(0);
    require_once __DIR__ . '/../core/media_image.php';

    if (admin_media_request_exceeds_post_limit() || admin_media_upload_rejected_by_ini()) {
        return ['status' => 'error', 'message' => admin_media_server_upload_limit_message()];
    }

    if (!admin_media_csrf_valid()) {
        return ['status' => 'error', 'message' => 'Invalid CSRF token'];
    }

    require_once __DIR__ . '/rate_limit.php';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userId = $_SESSION['user']['id'] ?? $ip;
    $rateLimiter = new RateLimiter('admin_media_upload_' . $userId, 30, 300);
    if (!$rateLimiter->check()) {
        return [
            'status' => 'error',
            'message' => 'Too many upload attempts. Please try again later.',
            'retry_after' => $rateLimiter->getRetryAfter(),
        ];
    }

    $mediaId = (string) ($_POST['id'] ?? '');
    if (!validateMediaId($mediaId)) {
        return ['status' => 'error', 'message' => 'Invalid media ID'];
    }

    if (!isset($_FILES['file'])) {
        return ['status' => 'error', 'message' => 'No file uploaded'];
    }

    $validation = admin_media_validate_upload_file($_FILES['file']);
    if (!$validation['ok']) {
        return ['status' => 'error', 'message' => $validation['message'] ?? 'Invalid upload'];
    }

    $file = $_FILES['file'];
    $tempPath = (string) $file['tmp_name'];
    $result = birkly_media_replace_master_from_path(
        $mediaId,
        $tempPath,
        (string) ($file['name'] ?? ''),
        (string) ($validation['mime'] ?? '')
    );

    if (!$result['ok']) {
        return ['status' => 'error', 'message' => $result['message'] ?? 'Replace failed'];
    }

    $item = $result['data'] ?? [];

    return [
        'status' => 'ok',
        'message' => 'Media file replaced successfully',
        'data' => [
            'media' => $item,
            'id' => $item['id'] ?? $mediaId,
            'name' => $item['name'] ?? '',
            'title' => $item['title'] ?? '',
            'url' => $item['url'] ?? '',
            'type' => $item['type'] ?? '',
            'category' => getMediaCategory((string) ($item['type'] ?? '')),
            'size' => $item['size'] ?? 0,
            'size_formatted' => formatFileSize((int) ($item['size'] ?? 0)),
            'folder' => $item['folder'] ?? '',
            'alt_text' => $item['alt_text'] ?? '',
            'focal_point' => normalizeFocalPoint($item['focal_point'] ?? null),
            'crop_rect' => $item['crop_rect'] ?? null,
            'variants' => $item['variants'] ?? null,
            'dimensions' => $item['dimensions'] ?? null,
        ],
    ];
}

function createMediaVariant(): array
{
    require_once __DIR__ . '/../core/media_image.php';

    if (!admin_media_csrf_valid()) {
        return ['status' => 'error', 'message' => 'Invalid CSRF token'];
    }

    $mediaId = (string) ($_POST['id'] ?? '');
    $variant = (string) ($_POST['variant'] ?? 'web');
    if (!in_array($variant, ['web', 'crop'], true)) {
        return ['status' => 'error', 'message' => 'Unsupported variant type'];
    }

    if (!validateMediaId($mediaId)) {
        return ['status' => 'error', 'message' => 'Invalid media ID'];
    }

    $result = $variant === 'crop'
        ? birkly_media_bake_crop_variant($mediaId)
        : birkly_media_create_web_variant($mediaId);
    if (!$result['ok']) {
        return ['status' => 'error', 'message' => $result['message'] ?? 'Could not create variant'];
    }

    $message = $variant === 'crop'
        ? 'Crop variant created'
        : 'Web-optimized variant created';

    return [
        'status' => 'ok',
        'message' => $message,
        'data' => ['media' => $result['data']],
    ];
}

/**
 * @return list<string>
 */
function admin_media_parse_id_list(mixed $raw): array
{
    if (is_array($raw)) {
        $ids = $raw;
    } elseif (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $ids = $decoded;
        } else {
            $ids = preg_split('/[\s,]+/', $raw) ?: [];
        }
    } else {
        $ids = [];
    }

    $out = [];
    foreach ($ids as $id) {
        $id = trim((string) $id);
        if ($id === '' || !validateMediaId($id)) {
            continue;
        }
        $out[$id] = $id;
    }

    return array_values($out);
}

function bulkCreateMediaVariants(): array
{
    require_once __DIR__ . '/../core/media_image.php';

    if (!admin_media_csrf_valid()) {
        return ['status' => 'error', 'message' => 'Invalid CSRF token'];
    }

    $variant = (string) ($_POST['variant'] ?? 'web');
    if ($variant !== 'web') {
        return ['status' => 'error', 'message' => 'Bulk variants support web only'];
    }

    $ids = admin_media_parse_id_list($_POST['ids'] ?? null);
    if ($ids === []) {
        return ['status' => 'error', 'message' => 'No valid media IDs provided'];
    }

    $max = 50;
    if (count($ids) > $max) {
        return ['status' => 'error', 'message' => 'Too many items (max ' . $max . ')'];
    }

    $results = [];
    $okCount = 0;
    $failCount = 0;

    foreach ($ids as $mediaId) {
        $created = birkly_media_create_web_variant($mediaId);
        if (!empty($created['ok'])) {
            $okCount++;
            $results[] = [
                'id' => $mediaId,
                'status' => 'ok',
                'variants' => $created['data']['variants'] ?? null,
            ];
        } else {
            $failCount++;
            $results[] = [
                'id' => $mediaId,
                'status' => 'error',
                'message' => $created['message'] ?? 'Could not create variant',
            ];
        }
    }

    return [
        'status' => 'ok',
        'message' => $okCount . ' web variant(s) created' . ($failCount > 0 ? ', ' . $failCount . ' failed' : ''),
        'data' => [
            'variant' => 'web',
            'succeeded' => $okCount,
            'failed' => $failCount,
            'results' => $results,
        ],
    ];
}

function validateMediaId($mediaId) {
    return birkly_validate_media_id((string) $mediaId);
}

function validateMediaFolder($folder) {
    return birkly_validate_media_folder((string) $folder);
}

/**
 * Stream a media file as an attachment (admin download).
 */
function admin_media_serve_download(): void
{
    $mediaId = (string) ($_GET['id'] ?? '');
    $resolved = birkly_media_resolve_download_file($mediaId);
    if ($resolved === null) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Media not found';

        return;
    }

    $size = filesize($resolved['path']);
    header('Content-Type: ' . $resolved['mime']);
    header('Content-Disposition: ' . birkly_media_content_disposition_attachment($resolved['filename']));
    if ($size !== false) {
        header('Content-Length: ' . (string) $size);
    }
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');

    readfile($resolved['path']);
}

function getMediaItem() {
    $mediaId = $_GET['id'] ?? '';
    if (!validateMediaId($mediaId)) {
        return ['status' => 'error', 'message' => 'Invalid media ID'];
    }

    $mediaDir = realpath(__DIR__ . '/../media');
    if (!$mediaDir) {
        return ['status' => 'error', 'message' => 'Media directory not found'];
    }

    $metadataPath = $mediaDir . '/.metadata.json';
    if (file_exists($metadataPath)) {
        $metadata = json_decode(file_get_contents($metadataPath), true);
        if ($metadata && isset($metadata[$mediaId])) {
            $item = $metadata[$mediaId];
            $ext = isset($item['ext']) ? preg_replace('/[^a-zA-Z0-9]/', '', $item['ext']) : '';
            $folder = $item['folder'] ?? '';
            $filePath = $folder
                ? $mediaDir . '/' . $folder . '/' . $mediaId . ($ext ? '.' . $ext : '')
                : $mediaDir . '/' . $mediaId . ($ext ? '.' . $ext : '');
            $realPath = realpath($filePath);
            if ($realPath && strpos($realPath, $mediaDir) === 0 && is_file($realPath)) {
                return [
                    'status' => 'ok',
                    'data' => [
                        'id' => $item['id'] ?? $mediaId,
                        'name' => $item['name'] ?? $mediaId,
                        'title' => $item['title'] ?? ($item['name'] ?? $mediaId),
                        'url' => $item['url'] ?? '/media/' . $mediaId . ($ext ? '.' . $ext : ''),
                        'type' => $item['type'] ?? mime_content_type($realPath),
                        'size' => $item['size'] ?? filesize($realPath),
                        'size_formatted' => formatFileSize($item['size'] ?? filesize($realPath)),
                        'folder' => $item['folder'] ?? '',
                        'uploaded_at' => $item['created_at'] ?? '',
                        'dimensions' => $item['dimensions'] ?? getImageDimensions($realPath, $item['type'] ?? mime_content_type($realPath)),
                        'alt_text' => $item['alt_text'] ?? '',
                        'focal_point' => normalizeFocalPoint($item['focal_point'] ?? null),
                        'crop_rect' => $item['crop_rect'] ?? null,
                        'variants' => $item['variants'] ?? null,
                    ]
                ];
            }
        }
    }

    $matches = glob($mediaDir . '/' . $mediaId . '.*');
    if ($matches) {
        $realPath = realpath($matches[0]);
        if ($realPath && strpos($realPath, $mediaDir) === 0 && is_file($realPath)) {
            return [
                'status' => 'ok',
                'data' => [
                    'id' => $mediaId,
                    'name' => basename($realPath),
                    'title' => basename($realPath),
                    'url' => '/media/' . basename($realPath),
                    'type' => mime_content_type($realPath),
                    'size' => filesize($realPath),
                    'size_formatted' => formatFileSize(filesize($realPath)),
                    'folder' => '',
                    'uploaded_at' => date('Y-m-d H:i:s', filemtime($realPath)),
                    'dimensions' => getImageDimensions($realPath, mime_content_type($realPath)),
                    'alt_text' => '',
                    'focal_point' => normalizeFocalPoint(null),
                ]
            ];
        }
    }

    return ['status' => 'error', 'message' => 'Media not found'];
}

function deleteMedia() {
    // SECURITY FIX: Require CSRF token for deletions
    if (!admin_media_csrf_valid()) {
        return ['error' => 'Invalid CSRF token'];
    }

    $mediaId = $_POST['id'] ?? '';
    if (!validateMediaId($mediaId)) {
        return ['error' => 'Invalid media ID'];
    }

    $mediaDir = realpath(__DIR__ . '/../media');
    if (!$mediaDir) {
        return ['error' => 'Media directory not found'];
    }
    $metadataPath = $mediaDir . '/.metadata.json';

    // Load metadata to find the file
    if (file_exists($metadataPath)) {
        $metadata = json_decode(file_get_contents($metadataPath), true);
        if (isset($metadata[$mediaId])) {
            $item = $metadata[$mediaId];
            $folder = $item['folder'] ?? '';
            $filepath = $folder
                ? $mediaDir . '/' . $folder . '/' . $mediaId . '.' . $item['ext']
                : $mediaDir . '/' . $mediaId . '.' . $item['ext'];
            $realPath = realpath($filepath);
            if (!$realPath || strpos($realPath, $mediaDir) !== 0) {
                return ['error' => 'Invalid media path'];
            }

            if (file_exists($realPath) && unlink($realPath)) {
                // Remove from metadata
                unset($metadata[$mediaId]);
                file_put_contents($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT));
                
                return ['status' => 'ok', 'message' => 'Media deleted successfully'];
            }
        }
    }
    
    return ['error' => 'Failed to delete media'];
}

function moveMedia() {
    // SECURITY FIX: Require CSRF token
    if (!admin_media_csrf_valid()) {
        return ['error' => 'Invalid CSRF token'];
    }
    
    $mediaId = $_POST['id'] ?? '';
    $folder = $_POST['folder'] ?? '';

    if (!validateMediaId($mediaId)) {
        return ['error' => 'Invalid media ID'];
    }
    if (!validateMediaFolder($folder)) {
        return ['error' => 'Invalid folder name'];
    }

    $mediaDir = realpath(__DIR__ . '/../media');
    if (!$mediaDir) {
        return ['error' => 'Media directory not found'];
    }
    $metadataPath = $mediaDir . '/.metadata.json';
    
    // Load metadata
    if (file_exists($metadataPath)) {
        $metadata = json_decode(file_get_contents($metadataPath), true);
        if (isset($metadata[$mediaId])) {
            $item = $metadata[$mediaId];
            $oldFilepath = $mediaDir . '/' . $mediaId . '.' . $item['ext'];
            
            // Create new folder if it doesn't exist
            if ($folder) {
                $newFolderPath = $mediaDir . '/' . $folder;
                if (!is_dir($newFolderPath)) {
                    mkdir($newFolderPath, 0755, true);
                }
                $newFilepath = $newFolderPath . '/' . $mediaId . '.' . $item['ext'];
            } else {
                $newFilepath = $mediaDir . '/' . $mediaId . '.' . $item['ext'];
            }
            
            if (file_exists($oldFilepath) && rename($oldFilepath, $newFilepath)) {
                // Update metadata
                $metadata[$mediaId]['folder'] = $folder;
                $metadata[$mediaId]['url'] = '/media/' . ($folder ? $folder . '/' : '') . $mediaId . '.' . $item['ext'];
                file_put_contents($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT));
                
                return ['status' => 'ok', 'message' => 'Media moved successfully'];
            }
        }
    }
    
    return ['error' => 'Failed to move media'];
}

function updateMedia() {
    if (!admin_media_csrf_valid()) {
        return ['status' => 'error', 'message' => 'Invalid CSRF token'];
    }

    require_once __DIR__ . '/../core/media_image.php';
    
    $mediaId = $_POST['id'] ?? '';
    $title = $_POST['title'] ?? '';
    $altText = isset($_POST['alt_text']) ? trim((string) $_POST['alt_text']) : null;
    $focalX = $_POST['focal_x'] ?? null;
    $focalY = $_POST['focal_y'] ?? null;
    $cropX = $_POST['crop_x'] ?? null;
    $cropY = $_POST['crop_y'] ?? null;
    $cropW = $_POST['crop_w'] ?? null;
    $cropH = $_POST['crop_h'] ?? null;

    if (!validateMediaId($mediaId)) {
        return ['status' => 'error', 'message' => 'Invalid media ID'];
    }

    $mediaDir = realpath(__DIR__ . '/../media');
    if (!$mediaDir) {
        return ['status' => 'error', 'message' => 'Media directory not found'];
    }
    $metadataPath = $mediaDir . '/.metadata.json';
    
    // Load metadata
    if (file_exists($metadataPath)) {
        $metadata = json_decode(file_get_contents($metadataPath), true);
        
        if (isset($metadata[$mediaId])) {
            // Update metadata
            if ($title) {
                $metadata[$mediaId]['title'] = $title;
            }
            if ($altText !== null) {
                $metadata[$mediaId]['alt_text'] = $altText;
            }
            if ($focalX !== null || $focalY !== null) {
                $metadata[$mediaId]['focal_point'] = normalizeFocalPoint([
                    'x' => $focalX,
                    'y' => $focalY,
                ]);
            }
            if ($cropX !== null || $cropY !== null || $cropW !== null || $cropH !== null) {
                $crop = birkly_media_normalize_crop_rect([
                    'x' => $cropX,
                    'y' => $cropY,
                    'w' => $cropW,
                    'h' => $cropH,
                ]);
                if ($crop === null) {
                    unset($metadata[$mediaId]['crop_rect']);
                } else {
                    $metadata[$mediaId]['crop_rect'] = $crop;
                }
            }

            if (file_put_contents($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT)) === false) {
                return ['status' => 'error', 'message' => 'Could not save media metadata'];
            }
            
            return [
                'status' => 'ok',
                'message' => 'Media updated successfully',
                'data' => [
                    'media' => $metadata[$mediaId],
                ],
            ];
        }
    }
    
    return ['status' => 'error', 'message' => 'Failed to update media'];
}

function getMediaCategory($mimeType) {
    if (strpos($mimeType, 'image/') === 0) return 'image';
    if (strpos($mimeType, 'video/') === 0) return 'video';
    if (strpos($mimeType, 'audio/') === 0) return 'audio';
    if (strpos($mimeType, 'application/pdf') === 0) return 'document';
    if (strpos($mimeType, 'application/msword') === 0 || strpos($mimeType, 'application/vnd.openxmlformats-officedocument') === 0) return 'document';
    if (strpos($mimeType, 'application/zip') === 0 || strpos($mimeType, 'application/x-rar-compressed') === 0) return 'archive';
    return 'file';
}

function getImageDimensions($filepath, $mimeType) {
    if ($mimeType === 'image/svg+xml') {
        return null;
    }
    if (strpos($mimeType, 'image/') === 0) {
        $info = getimagesize($filepath);
        if ($info) {
            return [
                'width' => $info[0],
                'height' => $info[1]
            ];
        }
    }
    return null;
}

if (!function_exists('formatFileSize')) {
    function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

function normalizeFocalPoint($focalPoint) {
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

    $x = max(0, min(100, $x));
    $y = max(0, min(100, $y));

    return ['x' => $x, 'y' => $y];
}

function generateMediaId($filename) {
    $baseName = pathinfo($filename, PATHINFO_FILENAME);
    
    if (strlen($baseName) >= 12 && ctype_alnum($baseName)) {
        return $baseName;
    }
    
    return uniqid();
}

function saveMetadata($metadata) {
    $mediaDir = __DIR__ . '/../media';
    $metadataPath = $mediaDir . '/.metadata.json';
    
    $existing = [];
    if (file_exists($metadataPath)) {
        $existing = json_decode(file_get_contents($metadataPath), true) ?: [];
    }
    
    $existing[$metadata['id']] = $metadata;
    file_put_contents($metadataPath, json_encode($existing, JSON_PRETTY_PRINT));
}

function renameFolder() {
    // SECURITY FIX: Require CSRF token
    if (!admin_media_csrf_valid()) {
        return ['error' => 'Invalid CSRF token'];
    }
    
    $oldName = $_POST['old_name'] ?? '';
    $newName = $_POST['new_name'] ?? '';
    
    if (empty($oldName) || empty($newName)) {
        return ['status' => 'error', 'message' => 'Old and new folder names are required'];
    }
    
    $mediaDir = __DIR__ . '/../media';
    $oldPath = $mediaDir . '/' . $oldName;
    $newPath = $mediaDir . '/' . $newName;
    
    if (!is_dir($oldPath)) {
        return ['status' => 'error', 'message' => 'Folder does not exist'];
    }
    
    if (is_dir($newPath)) {
        return ['status' => 'error', 'message' => 'Folder with new name already exists'];
    }
    
    if (rename($oldPath, $newPath)) {
        // Update metadata files to reflect new folder name
        updateFolderReferences($oldName, $newName);
        
        return [
            'status' => 'ok',
            'message' => 'Folder renamed successfully',
            'data' => [
                'old_name' => $oldName,
                'new_name' => $newName
            ]
        ];
    } else {
        return ['status' => 'error', 'message' => 'Failed to rename folder'];
    }
}

function deleteFolder() {
    // SECURITY FIX: Require CSRF token
    if (!admin_media_csrf_valid()) {
        return ['error' => 'Invalid CSRF token'];
    }
    
    $folderName = $_POST['name'] ?? '';
    $handleFiles = $_POST['handle_files'] ?? 'move'; // 'move' or 'delete'
    
    if (empty($folderName)) {
        return ['status' => 'error', 'message' => 'Folder name is required'];
    }
    
    $mediaDir = __DIR__ . '/../media';
    $folderPath = $mediaDir . '/' . $folderName;
    
    if (!is_dir($folderPath)) {
        return ['status' => 'error', 'message' => 'Folder does not exist'];
    }
    
    // Get all files in the folder
    $files = array_diff(scandir($folderPath), ['.', '..', '.metadata.json']);
    $fileCount = count($files);
    
    if ($fileCount > 0) {
        if ($handleFiles === 'delete') {
            // Delete all files in the folder
            foreach ($files as $file) {
                $filePath = $folderPath . '/' . $file;
                if (is_file($filePath)) {
                    unlink($filePath);
                }
            }
            
            // Remove folder metadata from root metadata
            $metadataPath = $mediaDir . '/.metadata.json';
            if (file_exists($metadataPath)) {
                $metadata = json_decode(file_get_contents($metadataPath), true) ?: [];
                foreach ($metadata as $id => $item) {
                    if (($item['folder'] ?? '') === $folderName) {
                        unset($metadata[$id]);
                    }
                }
                file_put_contents($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT));
            }
            
        } else {
            // Move files to root
            foreach ($files as $file) {
                $oldPath = $folderPath . '/' . $file;
                $newPath = $mediaDir . '/' . $file;
                
                if (is_file($oldPath)) {
                    rename($oldPath, $newPath);
                }
            }
            
            // Update metadata to move files to root
            $metadataPath = $mediaDir . '/.metadata.json';
            if (file_exists($metadataPath)) {
                $metadata = json_decode(file_get_contents($metadataPath), true) ?: [];
                foreach ($metadata as $id => $item) {
                    if (($item['folder'] ?? '') === $folderName) {
                        $metadata[$id]['folder'] = '';
                        $metadata[$id]['url'] = '/media/' . $id . '.' . $item['ext'];
                    }
                }
                file_put_contents($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT));
            }
        }
    }
    
    // Remove folder metadata file if it exists
    $folderMetadataPath = $folderPath . '/.metadata.json';
    if (file_exists($folderMetadataPath)) {
        unlink($folderMetadataPath);
    }
    
    // Delete the folder
    if (rmdir($folderPath)) {
        $message = $fileCount > 0 ? 
            "Folder deleted successfully. {$fileCount} files " . ($handleFiles === 'delete' ? 'deleted' : 'moved to root') . "." :
            "Folder deleted successfully.";
            
        return [
            'status' => 'ok',
            'message' => $message,
            'data' => [
                'name' => $folderName,
                'files_handled' => $fileCount,
                'action' => $handleFiles
            ]
        ];
    } else {
        return ['status' => 'error', 'message' => 'Failed to delete folder'];
    }
}

function moveToFolder() {
    // SECURITY FIX: Require CSRF token
    if (!admin_media_csrf_valid()) {
        return ['error' => 'Invalid CSRF token'];
    }
    
    $mediaId = $_POST['id'] ?? '';
    $folder = $_POST['folder'] ?? '';

    if (!validateMediaId($mediaId)) {
        return ['status' => 'error', 'message' => 'Invalid media ID'];
    }
    if (!validateMediaFolder($folder)) {
        return ['status' => 'error', 'message' => 'Invalid folder name'];
    }

    $mediaDir = realpath(__DIR__ . '/../media');
    if (!$mediaDir) {
        return ['status' => 'error', 'message' => 'Media directory not found'];
    }
    $metadataPath = $mediaDir . '/.metadata.json';
    
    // Load metadata
    if (file_exists($metadataPath)) {
        $metadata = json_decode(file_get_contents($metadataPath), true);
        
        if (isset($metadata[$mediaId])) {
            $item = $metadata[$mediaId];
            
            // Find the current file location
            $currentFolder = $item['folder'] ?? '';
            $oldFilepath = $mediaDir . '/' . ($currentFolder ? $currentFolder . '/' : '') . $mediaId . '.' . $item['ext'];
            
            // Check if file exists
            if (!file_exists($oldFilepath)) {
                return ['status' => 'error', 'message' => 'Media file not found'];
            }
            
            // Create new folder if specified and doesn't exist
            if ($folder) {
                $newFolderPath = $mediaDir . '/' . $folder;
                if (!is_dir($newFolderPath)) {
                    mkdir($newFolderPath, 0755, true);
                }
                $newFilepath = $newFolderPath . '/' . $mediaId . '.' . $item['ext'];
            } else {
                $newFilepath = $mediaDir . '/' . $mediaId . '.' . $item['ext'];
            }
            
            // Move the file
            if (rename($oldFilepath, $newFilepath)) {
                // Update metadata
                $metadata[$mediaId]['folder'] = $folder;
                $metadata[$mediaId]['url'] = '/media/' . ($folder ? $folder . '/' : '') . $mediaId . '.' . $item['ext'];
                file_put_contents($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT));
                
                return [
                    'status' => 'ok',
                    'message' => 'Media moved successfully',
                    'data' => [
                        'id' => $mediaId,
                        'folder' => $folder
                    ]
                ];
            } else {
                return ['status' => 'error', 'message' => 'Failed to move file on disk'];
            }
        }
    }
    
    return ['status' => 'error', 'message' => 'Media not found in metadata'];
}

function updateFolderReferences($oldName, $newName) {
    $mediaDir = __DIR__ . '/../media';
    $metadataPath = $mediaDir . '/.metadata.json';
    
    if (file_exists($metadataPath)) {
        $metadata = json_decode(file_get_contents($metadataPath), true);
        if ($metadata) {
            foreach ($metadata as $id => $item) {
                if (isset($item['folder']) && $item['folder'] === $oldName) {
                    $metadata[$id]['folder'] = $newName;
                    $metadata[$id]['url'] = str_replace('/' . $oldName . '/', '/' . $newName . '/', $item['url']);
                }
            }
            file_put_contents($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT));
        }
    }
}

// HTTP router — only when this file is the web entrypoint (never on MCP require_once).
$__adminMediaHttpEntry = !$__cmsMcpFunctionsOnly
    && empty($GLOBALS['__HARU_INTERNAL_CALL'])
    && empty($GLOBALS['__CMS_MCP_LOAD_FUNCTIONS_ONLY'])
    && PHP_SAPI !== 'cli'
    && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__);

if ($__adminMediaHttpEntry) {
    require_once __DIR__ . '/../core/cors.php';
    set_cors_headers('admin');

    require_once __DIR__ . '/session.php';
    haru_secure_session_start();

    $action = $_GET['action'] ?? 'list';

    if ($action === 'download') {
        requireAuth('read_media');
        admin_media_serve_download();
        exit;
    }

    header('Content-Type: application/json');

    if ($action === 'list' || $action === 'folders' || $action === 'get') {
        requireAuth('read_media');
    } elseif ($action === 'upload' || $action === 'replace') {
        requireAuth('upload');
    } else {
        requireAuth('manage_media');
    }

    switch ($action) {
        case 'list':
            echo json_encode(listMedia());
            break;
        case 'get':
            echo json_encode(getMediaItem());
            break;
        case 'upload':
            echo json_encode(uploadMedia());
            break;
        case 'replace':
            echo json_encode(replaceMedia());
            break;
        case 'create_variant':
            echo json_encode(createMediaVariant());
            break;
        case 'bulk_create_variants':
            echo json_encode(bulkCreateMediaVariants());
            break;
        case 'delete':
            echo json_encode(deleteMedia());
            break;
        case 'folders':
            echo json_encode(listFolders());
            break;
        case 'create_folder':
            echo json_encode(createFolder());
            break;
        case 'move':
            echo json_encode(moveMedia());
            break;
        case 'update':
            echo json_encode(updateMedia());
            break;
        case 'rename_folder':
            echo json_encode(renameFolder());
            break;
        case 'delete_folder':
            echo json_encode(deleteFolder());
            break;
        case 'move_to_folder':
            echo json_encode(moveToFolder());
            break;
        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} 