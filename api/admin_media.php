<?php

$__cmsMcpFunctionsOnly = !empty($GLOBALS['__CMS_MCP_LOAD_FUNCTIONS_ONLY']);

require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../core/media_storage.php';

if (!$__cmsMcpFunctionsOnly) {
    header('Content-Type: application/json');

    // SECURITY FIX: Replace wildcard CORS with origin whitelist
    require_once __DIR__ . '/../core/cors.php';
    set_cors_headers('admin');

    require_once __DIR__ . '/session.php';
    haru_secure_session_start();

    $action = $_GET['action'] ?? 'list';

    // SECURITY: enforce permissions by media action
    // - list/folders: read_media (viewer allowed)
    // - upload: upload
    // - all other actions mutate media/folders: manage_media
    if ($action === 'list' || $action === 'folders' || $action === 'get') {
        requireAuth('read_media');
    } elseif ($action === 'upload') {
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
    return;
}

if ($__cmsMcpFunctionsOnly) {
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

function scanFoldersRecursively($baseDir, $relativePath) {
    $folders = [];
    $fullPath = $relativePath ? $baseDir . '/' . $relativePath : $baseDir;
    
    if (is_dir($fullPath)) {
        $items = scandir($fullPath);
        foreach ($items as $item) {
            if ($item !== '.' && $item !== '..' && is_dir($fullPath . '/' . $item)) {
                $folderPath = $relativePath ? $relativePath . '/' . $item : $item;
                $folderFullPath = $fullPath . '/' . $item;
                
                // Count actual files in this folder (not metadata-based)
                $count = 0;
                $files = glob($folderFullPath . '/*');
                foreach ($files as $file) {
                    if (is_file($file) && !str_ends_with($file, '.metadata.json')) {
                        $count++;
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
 * Detect MIME type for uploads, normalizing SVG which finfo may report as text/xml.
 */
function detectUploadMimeType(string $tmpPath, string $originalName): string
{
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detected = $finfo ? (finfo_file($finfo, $tmpPath) ?: '') : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext !== 'svg') {
        return (string) $detected;
    }

    $head = @file_get_contents($tmpPath, false, null, 0, 8192);
    if ($head !== false && stripos($head, '<svg') !== false) {
        return 'image/svg+xml';
    }

    return (string) $detected;
}

function isRasterImageMime(string $mimeType): bool
{
    return str_starts_with($mimeType, 'image/') && $mimeType !== 'image/svg+xml';
}

function uploadMedia() {
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
    
    // SECURITY FIX: Validate file type using detected MIME type from file content
    $allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'video/mp4', 'video/webm', 'video/ogg',
        'audio/mpeg', 'audio/wav', 'audio/ogg',
        'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip', 'application/x-rar-compressed'
    ];
    
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
    
    if ($file['size'] > 50 * 1024 * 1024) { // 50MB limit
        return [
            'status'  => 'error',
            'message' => 'File too large. Maximum size is 50MB.'
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
    $fileType = mime_content_type($filepath);
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

function validateMediaId($mediaId) {
    return birkly_validate_media_id((string) $mediaId);
}

function validateMediaFolder($folder) {
    return birkly_validate_media_folder((string) $folder);
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
    
    $mediaId = $_POST['id'] ?? '';
    $title = $_POST['title'] ?? '';
    $altText = isset($_POST['alt_text']) ? trim((string) $_POST['alt_text']) : null;
    $focalX = $_POST['focal_x'] ?? null;
    $focalY = $_POST['focal_y'] ?? null;

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
            
            file_put_contents($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT));
            
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
?> 