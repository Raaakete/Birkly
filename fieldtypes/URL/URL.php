<?php
namespace HaruCMS\FieldTypes\URL;

use HaruCMS\FieldTypes\BaseField;

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
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .url-input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 2px var(--primary-light);
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

