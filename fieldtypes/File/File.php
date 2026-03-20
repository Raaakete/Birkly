<?php
namespace Birkly\FieldTypes\File;

use Birkly\FieldTypes\BaseField;

class File extends BaseField {
    public function getType(): string {
        return 'file';
    }

    public function getCategory(): string {
        return 'Media';
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
        $multipleAttr = $multi ? 'multiple' : '';
        
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
            <input type="file" id="file_input_{$this->name}" accept="{$accept}" style="display:none;" {$multipleAttr}>
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
            gap: var(--space-3, 0.75rem);
        }
        .file-preview-container {
            min-height: 100px;
            border: 1px dashed var(--border-medium, #e5e7eb);
            border-radius: var(--radius-sm, 0.375rem);
            padding: var(--space-3, 0.75rem);
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-secondary, #fafafa);
        }
        .file-preview {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: var(--space-2, 0.5rem);
        }
        .file-icon {
            font-size: 3rem;
            color: var(--text-secondary, #72746F);
        }
        .file-name {
            font-weight: var(--font-medium, 500);
            color: var(--text-primary, #20221D);
        }
        .file-size {
            font-size: 0.875em;
            color: var(--text-secondary, #72746F);
        }
        .file-placeholder {
            color: var(--text-tertiary, #9ca3af);
            font-style: italic;
        }
        .file-controls {
            display: flex;
            gap: var(--space-2, 0.5rem);
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

    public function renderListCell($value, array $context = []): string {
        $fileId = $this->extractFileId($value);
        if (empty($fileId)) {
            return '<span class="ft-list-empty">–</span>';
        }
        
        $fileInfo = $this->getFileInfo($fileId);
        if ($fileInfo && isset($fileInfo['url'])) {
            // Check if it's an image
            if ($this->isImage($fileInfo['type'] ?? '')) {
                // Show image preview
                $url = htmlspecialchars($fileInfo['url'], ENT_QUOTES, 'UTF-8');
                $name = htmlspecialchars($fileInfo['name'] ?? $fileId, ENT_QUOTES, 'UTF-8');
                return '<img src="' . $url . '" alt="' . $name . '" class="ft-list-image-square">';
            } else {
                // Show file name for non-images
                $name = htmlspecialchars($fileInfo['name'] ?? $fileId, ENT_QUOTES, 'UTF-8');
                if (mb_strlen($name) > 30) {
                    $name = mb_substr($name, 0, 27) . '...';
                }
                return '<span class="ft-list-text">' . $name . '</span>';
            }
        }
        
        return '<span class="ft-list-badge">' . htmlspecialchars($fileId, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    public function getTableMinWidth(): string {
        return '140px'; // File fields need space for filename display
    }

    public function getConfigUI($fieldIndex, $existingData = []): string {
        $options = $existingData['options'] ?? [];
        $multiple = !empty($options['multiple']);
        $accept = htmlspecialchars($options['accept'] ?? '*/*');
        $multipleChecked = $multiple ? 'checked' : '';
        
        return <<<HTML
            <div class="field-config-section">
                <label>Accepted File Types</label>
                <input type="text" name="fields[{$fieldIndex}][accept]" class="input" value="{$accept}" placeholder="*/* or image/*, .pdf, etc.">
                <small class="form-text">Comma-separated list (e.g., image/*, .pdf, .doc)</small>
            </div>
            <div class="field-config-section">
                <label class="checkbox-label">
                    <input type="checkbox" name="fields[{$fieldIndex}][multiple]" value="1" {$multipleChecked}>
                    <span class="checkmark"></span>
                    <span>Allow Multiple Files</span>
                </label>
            </div>
        HTML;
    }
}

