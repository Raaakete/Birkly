<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
// File upload security and processing for Birkly

require_once 'storage.php';
require_once 'validate.php';

class FileUpload {
    private $settings;
    private $storage;
    private $errors = [];

    public function __construct() {
        $settingsStorage = new Storage('../settings');
        $this->settings = $settingsStorage->read('media.json') ?? [
            'max_upload_size' => 5242880, // 5MB
            'allowed_types' => ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'],
            'image_quality' => 85,
            'create_thumbnails' => true,
            'thumbnail_size' => [150, 150]
        ];
        $this->storage = new Storage('../media');
    }

    public function validateFile($file) {
        $this->errors = [];

        // Check if file was uploaded
        if (!isset($file) || !is_array($file)) {
            $this->errors[] = 'No file uploaded';
            return false;
        }

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->getUploadErrorMessage($file['error']);
            return false;
        }

        // Check file size
        if ($file['size'] > $this->settings['max_upload_size']) {
            $this->errors[] = 'File too large (max ' . ($this->settings['max_upload_size'] / 1024 / 1024) . 'MB)';
            return false;
        }

        // SECURITY FIX: Validate MIME type from file content, not client-provided type
        // Client-provided $file['type'] can be spoofed - always use finfo_file()
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        // Validate detected MIME type (from file content)
        if (!in_array($detectedMimeType, $this->settings['allowed_types'])) {
            $this->errors[] = 'Invalid file type';
            return false;
        }
        
        // Cross-validate: Check that client-provided type matches detected type
        // If they don't match, it could be a spoofing attempt
        $clientMimeType = $file['type'] ?? '';
        if (!empty($clientMimeType) && $clientMimeType !== $detectedMimeType) {
            // Log potential spoofing attempt but don't block (client type might be wrong due to browser)
            error_log("File upload MIME type mismatch: client={$clientMimeType}, detected={$detectedMimeType}, file={$file['name']}");
        }

        // Additional image validation
        if (strpos($mimeType, 'image/') === 0) {
            if (!$this->validateImage($file['tmp_name'])) {
                return false;
            }
        }

        return true;
    }

    private function validateImage($filePath) {
        // Check if file is actually an image
        $imageInfo = getimagesize($filePath);
        if ($imageInfo === false) {
            $this->errors[] = 'Invalid image file';
            return false;
        }

        // Check for malicious content
        if (!$this->isValidImageContent($filePath)) {
            $this->errors[] = 'Invalid image content';
            return false;
        }

        return true;
    }

    private function isValidImageContent($filePath) {
        // Read first few bytes to check for image signatures
        $handle = fopen($filePath, 'rb');
        if (!$handle) return false;

        $header = fread($handle, 8);
        fclose($handle);

        // Check for common image signatures
        $signatures = [
            'jpeg' => "\xFF\xD8\xFF",
            'png' => "\x89PNG\r\n\x1A\n",
            'gif' => "GIF87a",
            'gif2' => "GIF89a"
        ];

        foreach ($signatures as $type => $signature) {
            if (strpos($header, $signature) === 0) {
                return true;
            }
        }

        return false;
    }

    public function processFile($file) {
        if (!$this->validateFile($file)) {
            return false;
        }

        // Generate safe filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $extension;
        $targetPath = 'uploads/' . $filename;

        // Create upload directory if it doesn't exist
        if (!file_exists('../media/uploads')) {
            mkdir('../media/uploads', 0755, true);
        }

        // Move file to final location
        if (!move_uploaded_file($file['tmp_name'], '../media/' . $targetPath)) {
            $this->errors[] = 'Failed to save file';
            return false;
        }

        // Process image if needed
        if (strpos($file['type'], 'image/') === 0 && $this->settings['create_thumbnails']) {
            $this->createThumbnail('../media/' . $targetPath);
        }

        // Create metadata
        $metadata = [
            'id' => uniqid(),
            'filename' => $filename,
            'original_name' => $file['name'],
            'type' => $file['type'],
            'size' => $file['size'],
            'path' => $targetPath,
            'uploaded_at' => date('c'),
            'uploaded_by' => $_SESSION['user'] ?? 'system'
        ];

        // Save metadata
        $this->storage->write('metadata/' . $metadata['id'] . '.json', $metadata);

        return $metadata;
    }

    private function createThumbnail($sourcePath) {
        $imageInfo = getimagesize($sourcePath);
        if ($imageInfo === false) return false;

        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $type = $imageInfo[2];

        // Calculate thumbnail dimensions
        $maxWidth = $this->settings['thumbnail_size'][0];
        $maxHeight = $this->settings['thumbnail_size'][1];
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = round($width * $ratio);
        $newHeight = round($height * $ratio);

        // Create thumbnail
        $thumbnail = imagecreatetruecolor($newWidth, $newHeight);

        // Load source image
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($sourcePath);
                break;
            default:
                return false;
        }

        // Resize image
        imagecopyresampled(
            $thumbnail, $source,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $width, $height
        );

        // Save thumbnail
        $thumbnailPath = dirname($sourcePath) . '/thumbnails/' . basename($sourcePath);
        if (!file_exists(dirname($thumbnailPath))) {
            mkdir(dirname($thumbnailPath), 0755, true);
        }

        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($thumbnail, $thumbnailPath, $this->settings['image_quality']);
                break;
            case IMAGETYPE_PNG:
                imagepng($thumbnail, $thumbnailPath, round(9 * $this->settings['image_quality'] / 100));
                break;
            case IMAGETYPE_GIF:
                imagegif($thumbnail, $thumbnailPath);
                break;
        }

        // Clean up
        imagedestroy($source);
        imagedestroy($thumbnail);

        return true;
    }

    private function getUploadErrorMessage($error) {
        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File exceeds upload_max_filesize directive';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds MAX_FILE_SIZE directive';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing a temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'A PHP extension stopped the file upload';
            default:
                return 'Unknown upload error';
        }
    }

    public function getErrors() {
        return $this->errors;
    }
} 