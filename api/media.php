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
    
    // Validate file
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        return ['error' => 'Invalid file type. Only images are allowed.'];
    }
    
    if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        return ['error' => 'File too large. Maximum size is 5MB.'];
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