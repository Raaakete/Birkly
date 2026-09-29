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

    public function getIcon(): string {
        return 'content';
    }

    public function getDescription(): string {
        return 'An uploaded file or image';
    }

    public function render(): string {
        $value = $this->value ?? '';
        $fileId = $this->extractFileId($value);
        
        // Try to get file info if we have an ID
        $fileInfo = $this->getFileInfo($fileId);
        $name = htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8');
        $idAttr = htmlspecialchars((string) $fileId, ENT_QUOTES, 'UTF-8');
        $preview = '<div class="file-placeholder">No file yet</div>';
        $meta = '';
        $filled = false;

        if ($fileInfo && isset($fileInfo['url'])) {
            $filled = true;
            $fileName = htmlspecialchars($fileInfo['name'] ?? $fileId, ENT_QUOTES, 'UTF-8');
            $url = htmlspecialchars($fileInfo['url'], ENT_QUOTES, 'UTF-8');
            if ($this->isImage($fileInfo['type'] ?? '')) {
                $preview = "<img class=\"file-cover\" src=\"{$url}\" alt=\"{$fileName}\">";
            } else {
                $preview = "<div class=\"file-doc\"><span class=\"file-name\">{$fileName}</span></div>";
            }
            $meta = $fileName . ' · ' . htmlspecialchars($this->describeFile($fileInfo), ENT_QUOTES, 'UTF-8');
        } elseif ($fileId) {
            $filled = true;
            $preview = "<div class=\"file-doc\"><span class=\"file-name\">{$idAttr}</span></div>";
        }

        $state = $filled ? 'filled' : 'empty';
        $selectLabel = $filled ? 'Replace' : 'Choose file';
        $removeHidden = $filled ? '' : ' hidden';
        $metaHidden = $meta !== '' ? '' : ' hidden';
        $icon = static fn (string $n): string => function_exists('birkly_icon') ? birkly_icon($n, ['size' => 13]) : '';
        $multi = $this->options['multiple'] ?? false;
        $defaultAccept = function_exists('birkly_media_upload_accept') ? birkly_media_upload_accept() : '*/*';
        $accept = htmlspecialchars($this->options['accept'] ?? $defaultAccept, ENT_QUOTES, 'UTF-8');
        $multipleAttr = $multi ? 'multiple' : '';
        $mediaIcon = $icon('media');
        $uploadIcon = $icon('upload');
        $trashIcon = $icon('trash');

        return <<<HTML
        <div class="file-field" id="field_{$name}" data-state="{$state}">
            <div class="file-preview-container" data-field="{$name}">
                {$preview}
            </div>
            <span class="file-meta"{$metaHidden}>{$meta}</span>
            <div class="file-controls">
                <button type="button" class="button is-small select-file-btn" data-field="{$name}">{$mediaIcon}<span class="file-select-label">{$selectLabel}</span></button>
                <button type="button" class="button is-quiet is-small upload-file-btn" data-field="{$name}">{$uploadIcon}<span class="file-upload-label">Upload</span></button>
                <button type="button" class="icon-button remove-file-btn" data-field="{$name}" title="Remove file" aria-label="Remove file"{$removeHidden}>{$trashIcon}</button>
            </div>
            <input type="file" id="file_input_{$name}" accept="{$accept}" hidden {$multipleAttr}>
            <input type="hidden" name="{$name}" id="field_{$name}_value" value="{$idAttr}">
        </div>
        HTML;
    }

    /** "1600 × 900" for images, the size otherwise. */
    private function describeFile(array $fileInfo): string {
        $dims = $fileInfo['dimensions'] ?? null;
        if (is_array($dims) && !empty($dims['width']) && !empty($dims['height'])) {
            return (int) $dims['width'] . ' × ' . (int) $dims['height'];
        }
        return $this->formatFileSize($fileInfo['size'] ?? 0);
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
                        'ext' => $fileInfo['ext'] ?? '',
                        'dimensions' => $fileInfo['dimensions'] ?? null
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
        $extensions = [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar', 'json', 'xml', 'csv',
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
            'mp4', 'm4v', 'webm', 'ogv', 'mov', 'mp3', 'wav', 'ogg',
        ];
        
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
        return <<<'CSS'
        /* Filled: a well with the file, its meta and actions (Birch cover). Empty: a dashed drop area. */
        .file-field {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: var(--s-3);
            min-height: 140px;
            padding: var(--s-4);
            border-radius: var(--r);
        }
        .file-field[data-state="empty"] {
            border: 1px dashed var(--line-strong);
            background: none;
        }
        .file-field[data-state="filled"] {
            padding: 0;
            min-height: 0;
            overflow: hidden;
            background: var(--surface-2);
        }
        .file-preview-container {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
        }
        .file-cover {
            display: block;
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .file-doc {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 120px;
            color: var(--text-2);
            font: var(--weight-medium) var(--size-body) / var(--lh-body) var(--font-sans);
        }
        .file-placeholder {
            color: var(--text-3);
            font-size: var(--size-small);
        }
        .file-meta {
            position: absolute;
            inset-block-end: var(--s-2);
            inset-inline-start: var(--s-2);
            max-width: 55%;
            overflow: hidden;
            padding: 2px 6px;
            border-radius: var(--r-inset);
            background: var(--surface);
            color: var(--text-2);
            font: var(--weight-regular) var(--size-mono) / var(--lh-mono) var(--font-mono);
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .file-controls {
            display: flex;
            align-items: center;
            gap: var(--s-1);
        }
        .file-field[data-state="filled"] .file-controls {
            position: absolute;
            inset-block-end: var(--s-2);
            inset-inline-end: var(--s-2);
        }
        .file-field[data-state="filled"] .file-controls :is(.btn, .button),
        .file-field[data-state="filled"] .file-controls .icon-button {
            background: var(--surface);
        }
        .file-field[data-state="filled"] .upload-file-btn { display: none; }
        .file-controls .icon-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: var(--control-sm);
            height: var(--control-sm);
            border: 0;
            border-radius: var(--r);
            color: var(--text-2);
        }
        .file-controls .icon-button:hover { color: var(--danger); }
        .file-field [hidden] { display: none; }
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

