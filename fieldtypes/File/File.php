<?php
namespace HaruCMS\FieldTypes\File;

use HaruCMS\FieldTypes\BaseField;

class File extends BaseField {
    public function getType(): string {
        return 'file';
    }

    public function render(): string {
        $value = $this->value ?? '';
        $fileId = $this->extractFileId($value);
        
        // Try to get file info if we have an ID
        $fileInfo = $this->getFileInfo($fileId);
        $preview = '';
        
        if ($fileInfo && isset($fileInfo['url'])) {
            $fileName = htmlspecialchars($fileInfo['name'] ?? $fileId);
            $fileSize = $this->formatFileSize($fileInfo['size'] ?? 0);
            $fileType = $fileInfo['type'] ?? 'unknown';
            
            // Show image preview for images, icon for others
            if ($this->isImage($fileType)) {
                $preview = "<img src=\"{$fileInfo['url']}\" alt=\"{$fileName}\" style=\"max-width:100px;max-height:100px;object-fit:cover;border-radius:4px;\">";
            } else {
                $preview = "<div class=\"file-preview\">
                    <div class=\"file-icon\">📄</div>
                    <div class=\"file-name\">{$fileName}</div>
                    <div class=\"file-size\">{$fileSize}</div>
                </div>";
            }
        } else if ($fileId) {
            $preview = "<div class=\"file-placeholder\">File ID: {$fileId}</div>";
        } else {
            $preview = '<div class="file-placeholder">No file selected</div>';
        }
        
        $multi = $this->options['multiple'] ?? false;
        $accept = $this->options['accept'] ?? '*/*';
        
        return <<<HTML
        <div class="file-field" id="field_{$this->name}">
            <div class="file-preview-container" data-field="{$this->name}">
                {$preview}
            </div>
            <div class="file-controls">
                <button type="button" class="btn btn-secondary select-file-btn" data-field="{$this->name}">Select File</button>
                <button type="button" class="btn btn-primary upload-file-btn" data-field="{$this->name}">Upload File</button>
                <button type="button" class="btn btn-danger remove-file-btn" data-field="{$this->name}" style="display:none;">Remove</button>
            </div>
            <input type="file" id="file_input_{$this->name}" accept="{$accept}" style="display:none;" {$multi ? 'multiple' : ''}>
            <input type="hidden" name="{$this->name}" id="field_{$this->name}_value" value="{$fileId}">
        </div>
        HTML;
    }
    
    private function extractFileId($value) {
        if (empty($value)) return '';
        
        // If it's an array, get the first element
        if (is_array($value)) {
            $value = $value[0] ?? '';
        }
        
        // If it's already just an ID (no slashes), return it
        if (preg_match('/^[a-f0-9]+$/', $value)) {
            return $value;
        }
        
        // If it's a full URL, extract the ID
        if (preg_match('/\/media\/([a-f0-9]+)\.([a-z0-9]+)$/', $value, $matches)) {
            return $matches[1];
        }
        
        // If it's a path without extension, extract the ID
        if (preg_match('/\/media\/([a-f0-9]+)$/', $value, $matches)) {
            return $matches[1];
        }
        
        // If it's just a filename with extension, extract the ID
        if (preg_match('/^([a-f0-9]+)\.([a-z0-9]+)$/', $value, $matches)) {
            return $matches[1];
        }
        
        return $value; // Return as-is if we can't parse it
    }
    
    private function getFileInfo($fileId) {
        if (empty($fileId)) return null;
        
        // Prefer metadata if present, else fall back to filesystem lookup
        $metadataPath = __DIR__ . '/../../media/.metadata.json';
        if (file_exists($metadataPath)) {
            $metadata = json_decode(file_get_contents($metadataPath), true);
            if ($metadata && isset($metadata[$fileId])) {
                $fileInfo = $metadata[$fileId];
                $filePath = __DIR__ . '/../../media/' . $fileId . '.' . ($fileInfo['ext'] ?? '');
                if (isset($fileInfo['url']) && file_exists($filePath)) {
                    return [
                        'id' => $fileId,
                        'name' => $fileInfo['name'] ?? $fileId,
                        'url' => $fileInfo['url'],
                        'type' => $fileInfo['type'] ?? '',
                        'size' => $fileInfo['size'] ?? 0,
                        'ext' => $fileInfo['ext'] ?? ''
                    ];
                }
            }
        }
        
        // Fallback: find a matching file by ID and common extensions
        $url = $this->findFileUrlById($fileId);
        if ($url) {
            $filePath = __DIR__ . '/../../media/' . basename($url);
            $size = file_exists($filePath) ? filesize($filePath) : 0;
            return [
                'id' => $fileId,
                'name' => basename($url),
                'url' => $url,
                'type' => mime_content_type($filePath) ?: 'unknown',
                'size' => $size,
                'ext' => pathinfo($url, PATHINFO_EXTENSION)
            ];
        }
        
        return null;
    }
    
    private function findFileUrlById($fileId) {
        $mediaDir = __DIR__ . '/../../media';
        $extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar', 'json', 'xml', 'csv'];
        
        foreach ($extensions as $ext) {
            $filePath = "$mediaDir/$fileId.$ext";
            if (file_exists($filePath)) {
                return "/media/$fileId.$ext";
            }
        }
        
        return null;
    }
    
    private function isImage($mimeType) {
        return strpos($mimeType, 'image/') === 0;
    }
    
    private function formatFileSize($bytes) {
        if ($bytes == 0) return '0 B';
        $k = 1024;
        $sizes = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }

    public function validate($value): array {
        $errors = [];
        
        if ($this->required && empty($value)) {
            $errors[] = "{$this->label} is required";
        }
        
        return $errors;
    }

    public function getJavaScript(): string {
        return file_get_contents(__DIR__ . '/fieldtype.js');
    }

    public function getCSS(): string {
        return <<<CSS
        .file-field {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .file-preview-container {
            min-height: 100px;
            border: 1px dashed #ddd;
            border-radius: 4px;
            padding: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .file-preview {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        .file-icon {
            font-size: 48px;
        }
        .file-name {
            font-weight: 500;
            color: #333;
        }
        .file-size {
            font-size: 0.875em;
            color: #666;
        }
        .file-placeholder {
            color: #999;
            font-style: italic;
        }
        .file-controls {
            display: flex;
            gap: 8px;
        }
        CSS;
    }

    public function getAdminConfig(): array {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'type' => 'file',
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
            'options' => [
                'multiple' => $this->options['multiple'] ?? false,
                'accept' => $this->options['accept'] ?? '*/*'
            ]
        ];
    }

    public function getSchema(): array {
        return [
            'name' => $this->name,
            'type' => 'file',
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
            'options' => $this->options ?? []
        ];
    }

    public function getDefaultValue() {
        return '';
    }

    public function processValue($value) {
        // Clean up the value - extract just the file ID
        if (empty($value)) return '';
        
        // If it's an array, get the first element
        if (is_array($value)) {
            $value = $value[0] ?? '';
        }
        
        // If it's already just an ID (no slashes), return it
        if (preg_match('/^[a-f0-9]+$/', $value)) {
            return $value;
        }
        
        // If it's a full URL, extract the ID
        if (preg_match('/\/media\/([a-f0-9]+)\.([a-z0-9]+)$/', $value, $matches)) {
            return $matches[1];
        }
        
        // If it's a path without extension, extract the ID
        if (preg_match('/\/media\/([a-f0-9]+)$/', $value, $matches)) {
            return $matches[1];
        }
        
        // If it's just a filename with extension, extract the ID
        if (preg_match('/^([a-f0-9]+)\.([a-z0-9]+)$/', $value, $matches)) {
            return $matches[1];
        }
        
        return $value; // Return as-is if we can't parse it
    }

    public function processLoadedValue($value) {
        // Return as-is when loading
        return $value;
    }
}

