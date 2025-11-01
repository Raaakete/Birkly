<?php
namespace HaruCMS\FieldTypes\Gallery;

use HaruCMS\FieldTypes\BaseField;

class Gallery extends BaseField {
    public function getType(): string {
        return 'gallery';
    }

    public function render(): string {
        $value = $this->value ?? [];
        
        // Ensure value is an array
        if (!is_array($value)) {
            $value = empty($value) ? [] : [$value];
        }
        
        // Get media info for each item
        $items = [];
        foreach ($value as $mediaId) {
            if (!empty($mediaId)) {
                $mediaInfo = $this->getMediaInfo($mediaId);
                if ($mediaInfo) {
                    $items[] = $mediaInfo;
                }
            }
        }
        
        // Build preview HTML
        $previewHtml = '';
        if (!empty($items)) {
            $previewHtml = '<div class="gallery-grid">';
            foreach ($items as $item) {
                $mediaId = $item['id'] ?? '';
                $url = $item['url'] ?? '';
                $name = htmlspecialchars($item['name'] ?? $mediaId);
                $previewHtml .= <<<HTML
                    <div class="gallery-item" data-id="{$mediaId}">
                        <img src="{$url}" alt="{$name}">
                        <button type="button" class="gallery-remove" data-id="{$mediaId}">×</button>
                    </div>
                HTML;
            }
            $previewHtml .= '</div>';
        } else {
            $previewHtml = '<div class="gallery-empty">No images selected</div>';
        }
        
        return <<<HTML
        <div class="gallery-field" id="field_{$this->name}">
            <div class="gallery-preview" data-field="{$this->name}">
                {$previewHtml}
            </div>
            <div class="gallery-controls">
                <button type="button" class="btn btn-secondary add-gallery-images" data-field="{$this->name}">Add Images</button>
                <button type="button" class="btn btn-primary upload-gallery-images" data-field="{$this->name}">Upload Images</button>
                <button type="button" class="btn btn-danger clear-gallery" data-field="{$this->name}" style="display:none;">Clear All</button>
            </div>
            <input type="file" id="gallery_input_{$this->name}" multiple accept="image/*" style="display:none;">
            <input type="hidden" name="{$this->name}" id="field_{$this->name}_value" value='{$this->encodeValue($value)}'>
        </div>
        HTML;
    }
    
    private function getMediaInfo($mediaId) {
        if (empty($mediaId)) return null;
        
        // Prefer metadata if present
        $metadataPath = __DIR__ . '/../../media/.metadata.json';
        if (file_exists($metadataPath)) {
            $metadata = json_decode(file_get_contents($metadataPath), true);
            if ($metadata && isset($metadata[$mediaId])) {
                $mediaInfo = $metadata[$mediaId];
                $filePath = __DIR__ . '/../../media/' . $mediaId . '.' . ($mediaInfo['ext'] ?? '');
                if (isset($mediaInfo['url']) && file_exists($filePath)) {
                    return [
                        'id' => $mediaId,
                        'name' => $mediaInfo['name'] ?? $mediaId,
                        'url' => $mediaInfo['url'],
                        'type' => $mediaInfo['type'] ?? 'image',
                        'size' => $mediaInfo['size'] ?? 0,
                        'ext' => $mediaInfo['ext'] ?? ''
                    ];
                }
            }
        }
        
        // Fallback: find matching file
        $url = $this->findMediaUrlById($mediaId);
        if ($url) {
            $filePath = __DIR__ . '/../../media/' . basename($url);
            return [
                'id' => $mediaId,
                'name' => basename($url),
                'url' => $url,
                'type' => 'image',
                'size' => file_exists($filePath) ? filesize($filePath) : 0,
                'ext' => pathinfo($url, PATHINFO_EXTENSION)
            ];
        }
        
        return null;
    }
    
    private function findMediaUrlById($mediaId) {
        $mediaDir = __DIR__ . '/../../media';
        $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        
        foreach ($extensions as $ext) {
            $filePath = "$mediaDir/$mediaId.$ext";
            if (file_exists($filePath)) {
                return "/media/$mediaId.$ext";
            }
        }
        
        return null;
    }
    
    private function encodeValue($value) {
        return json_encode($value);
    }

    public function validate($value): array {
        $errors = [];
        
        if ($this->required && (empty($value) || (is_array($value) && count($value) === 0))) {
            $errors[] = "{$this->label} is required";
        }
        
        return $errors;
    }

    public function getJavaScript(): string {
        return file_get_contents(__DIR__ . '/fieldtype.js');
    }

    public function getCSS(): string {
        return <<<CSS
        .gallery-field {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .gallery-preview {
            min-height: 100px;
        }
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 12px;
            padding: 12px;
            border: 1px dashed #ddd;
            border-radius: 4px;
        }
        .gallery-item {
            position: relative;
            aspect-ratio: 1;
            overflow: hidden;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .gallery-remove {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: none;
            background: rgba(255, 0, 0, 0.8);
            color: white;
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .gallery-remove:hover {
            background: rgba(255, 0, 0, 1);
        }
        .gallery-empty {
            padding: 24px;
            text-align: center;
            color: #999;
            font-style: italic;
            border: 1px dashed #ddd;
            border-radius: 4px;
        }
        .gallery-controls {
            display: flex;
            gap: 8px;
        }
        CSS;
    }

    public function getAdminConfig(): array {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'type' => 'gallery',
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
            'options' => $this->options ?? []
        ];
    }

    public function getSchema(): array {
        return [
            'name' => $this->name,
            'type' => 'gallery',
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
            'options' => $this->options ?? []
        ];
    }

    public function getDefaultValue() {
        return [];
    }

    public function processValue($value) {
        // Ensure value is an array of media IDs
        if (empty($value)) {
            return [];
        }
        
        if (is_string($value)) {
            // Try to decode JSON
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
            
            // If it's a single ID, wrap in array
            if (preg_match('/^[a-f0-9]+$/', $value)) {
                return [$value];
            }
            
            // Try to extract ID from URL
            if (preg_match('/\/media\/([a-f0-9]+)\.([a-z0-9]+)$/', $value, $matches)) {
                return [$matches[1]];
            }
            
            return [$value];
        }
        
        if (is_array($value)) {
            // Clean each value - extract IDs only
            $cleaned = [];
            foreach ($value as $item) {
                if (empty($item)) continue;
                
                if (is_array($item) && isset($item['id'])) {
                    $cleaned[] = $item['id'];
                } else if (preg_match('/^[a-f0-9]+$/', $item)) {
                    $cleaned[] = $item;
                } else if (preg_match('/\/media\/([a-f0-9]+)\.([a-z0-9]+)$/', $item, $matches)) {
                    $cleaned[] = $matches[1];
                } else {
                    $cleaned[] = $item;
                }
            }
            return $cleaned;
        }
        
        return [];
    }

    public function processLoadedValue($value) {
        // Return as array when loading
        if (empty($value)) {
            return [];
        }
        
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
            return [$value];
        }
        
        if (is_array($value)) {
            return $value;
        }
        
        return [];
    }
}

