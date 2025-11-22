<?php
header('Content-Type: application/json');

// Enable CORS for development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        echo json_encode(listMedia());
        break;
    case 'upload':
        echo json_encode(uploadMedia());
        break;
    case 'get':
        echo json_encode(getMedia());
        break;
    case 'delete':
        echo json_encode(deleteMedia());
        break;
    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

function listMedia() {
    $mediaDir = __DIR__ . '/../media';
    $metadataPath = $mediaDir . '/.metadata.json';
    $media = [];
    
    // Create media directory if it doesn't exist
    if (!is_dir($mediaDir)) {
        mkdir($mediaDir, 0755, true);
    }
    
    // Try to load from metadata files (main and subfolders)
    $media = loadFromMetadataFiles($mediaDir);
    
    // If no metadata or no media found, fallback to file system scan
    if (empty($media)) {
        $media = scanMediaDirectory($mediaDir);
    }
    
    return [
        'status' => 'ok',
        'data' => $media
    ];
}

function loadFromMetadataFiles($mediaDir) {
    $media = [];
    
    // Load from main metadata file
    $metadataPath = $mediaDir . '/.metadata.json';
    if (file_exists($metadataPath)) {
        $metadata = json_decode(file_get_contents($metadataPath), true);
        if ($metadata) {
            foreach ($metadata as $id => $item) {
                // Check if file still exists
                $filePath = $mediaDir . '/' . $id . '.' . $item['ext'];
                if (file_exists($filePath)) {
                    $media[] = [
                        'id' => $item['id'],
                        'name' => $item['name'],
                        'url' => $item['url'],
                        'type' => $item['type'],
                        'size' => $item['size'],
                        'size_formatted' => formatFileSize($item['size']),
                        'uploaded_at' => $item['created_at'],
                        'folder' => $item['folder'] ?? ''
                    ];
                }
            }
        }
    }
    
    // Load from subfolder metadata files
    $subfolders = glob($mediaDir . '/*', GLOB_ONLYDIR);
    foreach ($subfolders as $subfolder) {
        $folderName = basename($subfolder);
        $subMetadataPath = $subfolder . '/.metadata.json';
        
        if (file_exists($subMetadataPath)) {
            $metadata = json_decode(file_get_contents($subMetadataPath), true);
            if ($metadata) {
                foreach ($metadata as $id => $item) {
                    // Check if file still exists
                    $filePath = $subfolder . '/' . $id . '.' . $item['ext'];
                    if (file_exists($filePath)) {
                        $media[] = [
                            'id' => $item['id'],
                            'name' => $item['name'],
                            'url' => '/media/' . $folderName . '/' . $id . '.' . $item['ext'],
                            'type' => $item['type'],
                            'size' => $item['size'],
                            'size_formatted' => formatFileSize($item['size']),
                            'uploaded_at' => $item['created_at'],
                            'folder' => $folderName
                        ];
                    }
                }
            }
        }
    }
    
    return $media;
}

function scanMediaDirectory($dir, $folder = '') {
    $media = [];
    $files = glob($dir . '/*');
    
    foreach ($files as $file) {
        if (is_file($file)) {
            $filename = basename($file);
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            // Only include image files for now
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                // Extract ID from filename (assuming format: ID.ext)
                $mediaId = pathinfo($filename, PATHINFO_FILENAME);
                
                $media[] = [
                    'id' => $mediaId,
                    'name' => $filename,
                    'url' => '/media/' . ($folder ? $folder . '/' : '') . $filename,
                    'type' => 'image/' . $extension,
                    'size' => filesize($file),
                    'size_formatted' => formatFileSize(filesize($file)),
                    'uploaded_at' => date('Y-m-d H:i:s', filemtime($file)),
                    'folder' => $folder
                ];
            }
        } elseif (is_dir($file) && basename($file) !== '.') {
            // Recursively scan subdirectories
            $subfolder = basename($file);
            $subfolderPath = $folder ? $folder . '/' . $subfolder : $subfolder;
            $media = array_merge($media, scanMediaDirectory($file, $subfolderPath));
        }
    }
    
    return $media;
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

function uploadMedia() {
    if (!isset($_FILES['file'])) {
        return ['error' => 'No file uploaded'];
    }
    
    $file = $_FILES['file'];
    $mediaDir = __DIR__ . '/../media';
    
    // Create media directory if it doesn't exist
    if (!is_dir($mediaDir)) {
        mkdir($mediaDir, 0755, true);
    }
    
    // Validate file type - allow safe file types only
    $allowedMimeTypes = [
        // Images
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        // Documents
        'application/pdf',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        // Text
        'text/plain', 'text/csv',
        // Archives
        'application/zip', 'application/x-rar-compressed', 'application/x-tar', 'application/gzip',
        // Audio/Video (common formats)
        'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg',
        'video/mp4', 'video/webm', 'video/ogg'
    ];
    
    // Also check file extension as additional validation
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt', 'csv',
        'zip', 'rar', 'tar', 'gz',
        'mp3', 'wav', 'ogg', 'mp4', 'webm'
    ];
    
    // Block dangerous file types
    $dangerousExtensions = ['exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js', 'jar', 'sh', 'php', 'asp', 'aspx', 'jsp'];
    if (in_array($extension, $dangerousExtensions)) {
        return ['error' => 'File type not allowed for security reasons.'];
    }
    
    // Validate MIME type
    if (!in_array($file['type'], $allowedMimeTypes)) {
        return ['error' => 'Invalid file type. Only images, documents, and media files are allowed.'];
    }
    
    // Validate extension
    if (!in_array($extension, $allowedExtensions)) {
        return ['error' => 'Invalid file extension. Only safe file types are allowed.'];
    }
    
    // File size limit: 10MB for images, 50MB for other files
    $maxSize = in_array($file['type'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml']) 
        ? 10 * 1024 * 1024  // 10MB for images
        : 50 * 1024 * 1024; // 50MB for other files
    
    if ($file['size'] > $maxSize) {
        $maxSizeMB = $maxSize / (1024 * 1024);
        return ['error' => "File too large. Maximum size is {$maxSizeMB}MB."];
    }
    
    // Generate unique filename
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid() . '.' . $extension;
    $filepath = $mediaDir . '/' . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $mediaId = generateMediaId($filename);
        
        return [
            'status' => 'ok',
            'data' => [
                'id' => $mediaId,
                'name' => $file['name'],
                'url' => '/media/' . $filename,
                'type' => $file['type'],
                'size' => $file['size'],
                'uploaded_at' => date('Y-m-d H:i:s')
            ]
        ];
    } else {
        return ['error' => 'Failed to save file'];
    }
}

function getMedia() {
    $mediaId = $_GET['id'] ?? '';
    if (empty($mediaId)) {
        return ['status' => 'error', 'message' => 'No media ID provided'];
    }
    
    // Validate media ID to prevent path traversal attacks
    // Media IDs are generated using uniqid() which produces alphanumeric strings
    // Only allow alphanumeric characters (and optionally underscores/hyphens for compatibility)
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $mediaId)) {
        return ['status' => 'error', 'message' => 'Invalid media ID format'];
    }
    
    // Additional security: ensure no path traversal characters
    if (strpos($mediaId, '..') !== false || strpos($mediaId, '/') !== false || strpos($mediaId, '\\') !== false) {
        return ['status' => 'error', 'message' => 'Invalid media ID format'];
    }
    
    $mediaDir = __DIR__ . '/../media';
    $metadataPath = $mediaDir . '/.metadata.json';
    
    // Try to load from metadata
    if (file_exists($metadataPath)) {
        $metadata = json_decode(file_get_contents($metadataPath), true);
        if ($metadata && isset($metadata[$mediaId])) {
            $item = $metadata[$mediaId];
            // Sanitize extension to prevent path traversal
            $ext = isset($item['ext']) ? preg_replace('/[^a-zA-Z0-9]/', '', $item['ext']) : '';
            $filePath = $mediaDir . '/' . $mediaId . ($ext ? '.' . $ext : '');
            // Additional security: ensure resolved path is within media directory
            $realPath = realpath($filePath);
            $realMediaDir = realpath($mediaDir);
            if ($realPath && $realMediaDir && strpos($realPath, $realMediaDir) === 0 && file_exists($filePath)) {
                return [
                    'status' => 'ok',
                    'data' => [
                        'id' => $item['id'] ?? $mediaId,
                        'name' => $item['name'] ?? $mediaId,
                        'url' => $item['url'] ?? '/media/' . $mediaId . ($ext ? '.' . $ext : ''),
                        'type' => $item['type'] ?? mime_content_type($filePath),
                        'size' => $item['size'] ?? filesize($filePath),
                        'size_formatted' => formatFileSize($item['size'] ?? filesize($filePath))
                    ]
                ];
            }
        }
    }
    
    // Fallback: find file by ID
    // Use basename() to prevent path traversal even if glob somehow allows it
    $searchPattern = $mediaDir . '/' . $mediaId . '.*';
    $matches = glob($searchPattern);
    if ($matches && count($matches) > 0) {
        $filePath = $matches[0];
        // Additional security: ensure resolved path is within media directory
        $realPath = realpath($filePath);
        $realMediaDir = realpath($mediaDir);
        if ($realPath && $realMediaDir && strpos($realPath, $realMediaDir) === 0) {
            $filename = basename($filePath);
            return [
                'status' => 'ok',
                'data' => [
                    'id' => $mediaId,
                    'name' => $filename,
                    'url' => '/media/' . $filename,
                    'type' => mime_content_type($filePath),
                    'size' => filesize($filePath),
                    'size_formatted' => formatFileSize(filesize($filePath))
                ]
            ];
        }
    }
    
    return ['status' => 'error', 'message' => 'Media not found'];
}

function deleteMedia() {
    $mediaId = $_POST['id'] ?? '';
    if (empty($mediaId)) {
        return ['error' => 'No media ID provided'];
    }
    
    // In a real system, you'd look up the file by ID
    // For now, we'll assume the ID is the filename
    $mediaDir = __DIR__ . '/../media';
    $filepath = $mediaDir . '/' . $mediaId;
    
    if (file_exists($filepath) && unlink($filepath)) {
        return ['status' => 'ok', 'message' => 'Media deleted successfully'];
    } else {
        return ['error' => 'Failed to delete media'];
    }
}

function generateMediaId($filename) {
    // Extract ID from filename if it already has one (format: ID.ext)
    $baseName = pathinfo($filename, PATHINFO_FILENAME);
    
    // If filename looks like it already has an ID (12+ chars, alphanumeric), use it
    if (strlen($baseName) >= 12 && ctype_alnum($baseName)) {
        return $baseName;
    }
    
    // Otherwise generate a new unique ID
    return uniqid();
}
?> 