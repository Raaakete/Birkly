<?php
namespace Birkly\FieldTypes\Gallery;

use Birkly\FieldTypes\BaseField;

class Gallery extends BaseField {
    public function getType(): string {
        return 'gallery';
    }

    public function getCategory(): string {
        return 'Media';
    }

    public function getIcon(): string {
        return 'grid';
    }

    public function getDescription(): string {
        return 'Several images in order';
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
        
        $removeIcon = function_exists('birkly_icon') ? birkly_icon('x', ['size' => 13]) : '×';
        $icon = static fn (string $n): string => function_exists('birkly_icon') ? birkly_icon($n, ['size' => 13]) : '';
        $addIcon = $icon('media');
        $uploadIcon = $icon('upload');
        $clearHidden = empty($items) ? ' hidden' : '';

        $previewHtml = '';
        if (!empty($items)) {
            $previewHtml = '<div class="gallery-grid">';
            foreach ($items as $item) {
                $mediaId = $item['id'] ?? '';
                $url = $item['url'] ?? '';
                $name = htmlspecialchars($item['name'] ?? $mediaId);
                $mime = (string) ($item['type'] ?? '');
                $mediaTag = str_starts_with($mime, 'video/')
                    ? "<video src=\"{$url}\" controls preload=\"metadata\"></video>"
                    : "<img src=\"{$url}\" alt=\"{$name}\">";
                $previewHtml .= <<<HTML
                    <div class="gallery-item" data-id="{$mediaId}">
                        {$mediaTag}
                        <button type="button" class="gallery-remove" data-id="{$mediaId}" title="Remove" aria-label="Remove {$name}">{$removeIcon}</button>
                    </div>
                HTML;
            }
            $previewHtml .= '</div>';
        } else {
            $previewHtml = '<div class="gallery-empty">No images yet</div>';
        }
        
        return <<<HTML
        <div class="gallery-field" id="field_{$this->name}">
            <div class="gallery-preview" data-field="{$this->name}">
                {$previewHtml}
            </div>
            <div class="gallery-controls">
                <button type="button" class="button is-small add-gallery-images" data-field="{$this->name}">{$addIcon}Add images</button>
                <button type="button" class="button is-quiet is-small upload-gallery-images" data-field="{$this->name}">{$uploadIcon}Upload</button>
                <button type="button" class="button is-quiet is-small clear-gallery" data-field="{$this->name}"{$clearHidden}>Clear all</button>
            </div>
            <input type="file" id="gallery_input_{$this->name}" multiple accept="image/*,video/mp4,video/webm,video/ogg,video/quicktime,.mp4,.m4v,.webm,.mov,.ogv" hidden>
            <input type="hidden" name="{$this->name}" id="field_{$this->name}_value" value="{$this->encodeValue($value)}">
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
        return htmlspecialchars((string) json_encode($value), ENT_QUOTES, 'UTF-8');
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

    public function getListCSS(): string {
        return <<<'CSS'
        .ft-gallery-stack { flex-wrap: nowrap; gap: 2px; }
        CSS;
    }

    public function getCSS(): string {
        return <<<'CSS'
        /* Thumbnails are wells 8px apart; an empty gallery is a dashed area. */
        .gallery-field {
            display: flex;
            flex-direction: column;
            gap: var(--s-3);
        }
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(132px, 1fr));
            gap: var(--s-2);
        }
        .gallery-item {
            position: relative;
            aspect-ratio: 4 / 3;
            overflow: hidden;
            border-radius: var(--r);
            background: var(--surface-2);
        }
        .gallery-item img,
        .gallery-item video {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .gallery-remove {
            position: absolute;
            inset-block-start: var(--s-1);
            inset-inline-end: var(--s-1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: var(--control-sm);
            height: var(--control-sm);
            border: 0;
            border-radius: var(--r);
            background: var(--surface);
            color: var(--text-2);
            cursor: pointer;
            opacity: 0;
            transition: opacity var(--dur-1) var(--ease);
        }
        .gallery-item:hover .gallery-remove,
        .gallery-remove:focus-visible { opacity: 1; }
        .gallery-remove:hover { color: var(--danger); }
        .gallery-empty {
            padding: var(--s-6);
            border: 1px dashed var(--line-strong);
            border-radius: var(--r);
            color: var(--text-3);
            font-size: var(--size-small);
            text-align: center;
        }
        .gallery-controls {
            display: flex;
            align-items: center;
            gap: var(--s-1);
        }
        .gallery-controls .clear-gallery:hover { color: var(--danger); }
        .gallery-field [hidden] { display: none; }
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

    public function renderListCell($value, array $context = []): string {
        // Ensure value is an array
        if (!is_array($value)) {
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $value = $decoded;
                } else {
                    $value = empty($value) ? [] : [$value];
                }
            } else {
                $value = [];
            }
        }
        
        $count = count($value);
        if ($count === 0) {
            return '<span class="ft-list-empty">–</span>';
        }
        
        // Show max 3 preview images
        $previews = [];
        $previewCount = min(3, $count);
        
        for ($i = 0; $i < $previewCount; $i++) {
            $mediaId = $value[$i] ?? '';
            if (empty($mediaId)) continue;
            
            $mediaInfo = $this->getMediaInfo($mediaId);
            if ($mediaInfo && isset($mediaInfo['url'])) {
                $name = htmlspecialchars($mediaInfo['name'] ?? $mediaId, ENT_QUOTES, 'UTF-8');
                $url = htmlspecialchars($mediaInfo['url'], ENT_QUOTES, 'UTF-8');
                $previews[] = '<img src="' . $url . '" alt="' . $name . '" title="' . $name . '" class="ft-list-image-square">';
            }
        }
        
        $html = '<span class="ft-list-row ft-gallery-stack">';
        $html .= implode('', $previews);
        
        // Add count badge if more than 3 items
        if ($count > 3) {
            $remaining = $count - 3;
            $html .= '<span class="ft-list-badge">+' . $remaining . '</span>';
        }
        
        $html .= '</span>';
        return $html;
    }

    public function getTableMinWidth(): string {
        return '150px'; // Gallery fields need space for thumbnail count display
    }

    public function getConfigUI($fieldIndex, $existingData = []): string {
        $maxImages = htmlspecialchars($existingData['maxImages'] ?? '');
        
        return <<<HTML
            <div class="field-config-section">
                <label>Max Images</label>
                <input type="number" name="fields[{$fieldIndex}][maxImages]" class="input" value="{$maxImages}" min="0" placeholder="No limit">
                <small class="form-text">Maximum number of images (0 = unlimited)</small>
            </div>
        HTML;
    }
}

