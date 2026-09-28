<?php
namespace Birkly\FieldTypes\URL;

use Birkly\FieldTypes\BaseField;

// Prevent fatal "Cannot redeclare class" errors if this file is included twice
// (can happen on case-insensitive filesystems when referenced as Url/Url.php and URL/URL.php)
if (class_exists(__NAMESPACE__ . '\\URL', false)) {
    return;
}

class URL extends BaseField {
    protected $placeholder;
    protected $autoProtocol; // Auto-prepend http:// if missing

    public function __construct(array $config) {
        parent::__construct($config);
        $this->placeholder = $config['placeholder'] ?? 'https://example.com';
        $this->autoProtocol = $config['autoProtocol'] ?? true;
    }

    public function getType(): string {
        return 'url';
    }

    public function getCategory(): string {
        return 'Content & Text';
    }

    public function getIcon(): string {
        return 'link';
    }

    public function getDescription(): string {
        return 'A web address';
    }

    public function render(): string {
        $required = $this->required ? 'required' : '';
        $placeholder = htmlspecialchars($this->placeholder);
        $value = htmlspecialchars($this->value ?? '');
        
        return <<<HTML
        <div class="url-field-wrapper">
            <input type="url" 
                name="{$this->name}" 
                id="field_{$this->name}"
                value="{$value}"
                {$required}
                placeholder="{$placeholder}"
                class="input url-input"
            >
            <div class="url-preview" id="url_preview_{$this->name}" style="display:none;">
                <a href="#" target="_blank" rel="noopener noreferrer" class="url-preview-link">Preview</a>
            </div>
        </div>
        HTML;
    }

    public function validate($value): array {
        $errors = [];

        if ($this->required && empty($value)) {
            $errors[] = "{$this->label} is required";
            return $errors;
        }

        if (!empty($value)) {
            // Normalize URL for validation
            $url = $this->normalizeUrl($value);
            
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $errors[] = "{$this->label} must be a valid URL";
            }
        }

        return $errors;
    }

    private function normalizeUrl($url): string {
        $url = trim($url);
        
        // If auto-protocol is enabled and URL doesn't have a protocol, add http://
        if ($this->autoProtocol && !preg_match('/^https?:\/\//i', $url)) {
            $url = 'http://' . $url;
        }
        
        return $url;
    }

    public function getJavaScript(): string {
        $autoProtocol = $this->autoProtocol ? 'true' : 'false';
        return <<<JS
        // URL field JavaScript
        (function() {
            const fieldName = '{$this->name}';
            const autoProtocol = {$autoProtocol};
            
            function initUrlField() {
                const input = document.getElementById('field_' + fieldName);
                const preview = document.getElementById('url_preview_' + fieldName);
                const previewLink = preview ? preview.querySelector('.url-preview-link') : null;
                
                if (!input) return;
                
                // Update preview on input
                input.addEventListener('input', function() {
                    updatePreview(this.value);
                });
                
                // Normalize URL on blur
                input.addEventListener('blur', function() {
                    let value = this.value.trim();
                    if (value && autoProtocol && !value.match(/^https?:\/\//i)) {
                        value = 'http://' + value;
                        this.value = value;
                    }
                    updatePreview(value);
                });
                
                function updatePreview(url) {
                    if (!preview || !previewLink) return;
                    
                    if (url && isValidUrl(url)) {
                        preview.style.display = 'block';
                        previewLink.href = url;
                        previewLink.textContent = url;
                    } else {
                        preview.style.display = 'none';
                    }
                }
                
                function isValidUrl(string) {
                    try {
                        const url = new URL(string);
                        return url.protocol === 'http:' || url.protocol === 'https:';
                    } catch (_) {
                        return false;
                    }
                }
                
                // Initialize preview if value exists
                if (input.value) {
                    updatePreview(input.value);
                }
            }
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initUrlField);
            } else {
                initUrlField();
            }
        })();
        JS;
    }

    public function getCSS(): string {
        return <<<'CSS'
        .url-field-wrapper {
            position: relative;
        }
        
        .url-input {
            width: 100%;
            padding: var(--space-2, 0.5rem);
            border: 1px solid var(--border-medium, #e5e7eb);
            border-radius: var(--radius-sm, 0.375rem);
            font-size: var(--font-size-base, 1rem);
            background: var(--bg-primary, #ffffff);
            color: var(--text-primary, #20221D);
        }
        
        .url-input:focus {
            border-color: var(--primary-500, #3b82f6);
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .url-preview {
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        
        .url-preview-link {
            color: var(--primary);
            text-decoration: none;
            word-break: break-all;
        }
        
        .url-preview-link:hover {
            text-decoration: underline;
        }
        CSS;
    }

    public function getAdminConfig(): array {
        return [
            'type' => 'url',
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
            'placeholder' => $this->placeholder,
            'autoProtocol' => $this->autoProtocol
        ];
    }

    public function getSchema(): array {
        return [
            'type' => 'string',
            'format' => 'uri',
            'required' => $this->required
        ];
    }

    public function getDefaultValue() {
        return '';
    }

    public function processValue($value) {
        if (empty($value)) {
            return '';
        }
        
        $url = trim($value);
        
        // Normalize URL
        if ($this->autoProtocol && !preg_match('/^https?:\/\//i', $url)) {
            $url = 'http://' . $url;
        }
        
        return $url;
    }

    public function processLoadedValue($value) {
        return $value;
    }

    public function renderListCell($value, array $context = []): string {
        if (empty($value)) {
            return '<span class="ft-list-empty">–</span>';
        }
        
        // Truncate long URLs
        $url = (string)$value;
        $max = 50;
        if (mb_strlen($url) > $max) {
            $url = mb_substr($url, 0, $max - 3) . '...';
        }
        
        return '<span class="ft-list-text">' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    public function getTableMinWidth(): string {
        return '220px'; // URL fields need space for full URLs
    }

    public function getConfigUI($fieldIndex, $existingData = []): string {
        $placeholder = htmlspecialchars($existingData['placeholder'] ?? 'https://example.com');
        $autoProtocol = !isset($existingData['autoProtocol']) || !empty($existingData['autoProtocol']);
        $autoProtocolChecked = $autoProtocol ? 'checked' : '';
        
        return <<<HTML
            <div class="field-config-section">
                <label>Placeholder</label>
                <input type="text" name="fields[{$fieldIndex}][placeholder]" class="input" value="{$placeholder}" placeholder="https://example.com">
            </div>
            <div class="field-config-section">
                <label class="checkbox-label">
                    <input type="checkbox" name="fields[{$fieldIndex}][autoProtocol]" value="1" {$autoProtocolChecked}>
                    <span class="checkmark"></span>
                    <span>Auto-add http:// if missing</span>
                </label>
            </div>
        HTML;
    }
}

