<?php
namespace HaruCMS\FieldTypes\Color;

use HaruCMS\FieldTypes\BaseField;

class Color extends BaseField {
    protected $format; // hex, rgb, hsl
    protected $alpha; // Allow alpha channel

    public function __construct(array $config) {
        parent::__construct($config);
        $this->format = $config['format'] ?? 'hex';
        $this->alpha = $config['alpha'] ?? false;
    }

    public function getType(): string {
        return 'color';
    }

    public function getCategory(): string {
        return 'Selection';
    }

    public function render(): string {
        $required = $this->required ? 'required' : '';
        $value = htmlspecialchars($this->value ?? '#000000');
        
        // Ensure value is in hex format for color input
        $hexValue = $this->normalizeToHex($value);
        
        $format = $this->format ?? 'hex';
        $rgbaValue = $this->hexToRgba($hexValue);
        
        return <<<HTML
        <div class="color-field-wrapper">
            <div class="color-picker-section">
                <label class="color-picker-label" for="field_{$this->name}_color" title="Click to pick a color">
                    <input type="color" 
                        name="{$this->name}_color" 
                        id="field_{$this->name}_color"
                        value="{$hexValue}"
                        class="color-picker"
                    >
                </label>
            </div>
            <div class="color-inputs-section">
                <div class="color-input-group">
                    <label class="color-input-label">Hex</label>
                    <input type="text" 
                        name="{$this->name}_hex" 
                        id="field_{$this->name}_hex"
                        value="{$hexValue}"
                        class="input color-hex-input"
                        pattern="^#[0-9A-Fa-f]{6}$"
                        placeholder="#000000"
                        title="Hex color code (e.g., #FF0000)"
                    >
                </div>
                <div class="color-input-group">
                    <label class="color-input-label">RGBA</label>
                    <input type="text" 
                        name="{$this->name}_rgba" 
                        id="field_{$this->name}_rgba"
                        value="{$rgbaValue}"
                        class="input color-rgba-input"
                        pattern="^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*[\d.]+)?\)$"
                        placeholder="rgba(0, 0, 0, 1)"
                        title="RGBA color (e.g., rgba(255, 0, 0, 1))"
                    >
                </div>
            </div>
            <input type="hidden" name="{$this->name}" id="field_{$this->name}" value="{$hexValue}">
        </div>
        HTML;
    }

    private function normalizeToHex($value): string {
        $value = trim($value);
        
        // If already hex, return it
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
            return strtoupper($value);
        }
        
        // Try to convert from rgb/rgba
        if (preg_match('/rgba?\((\d+),\s*(\d+),\s*(\d+)/', $value, $matches)) {
            $r = str_pad(dechex((int)$matches[1]), 2, '0', STR_PAD_LEFT);
            $g = str_pad(dechex((int)$matches[2]), 2, '0', STR_PAD_LEFT);
            $b = str_pad(dechex((int)$matches[3]), 2, '0', STR_PAD_LEFT);
            return '#' . strtoupper($r . $g . $b);
        }
        
        // Default to black
        return '#000000';
    }
    
    private function hexToRgba($hex): string {
        $hex = str_replace('#', '', $hex);
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return "rgba({$r}, {$g}, {$b}, 1)";
    }

    public function validate($value): array {
        $errors = [];

        if ($this->required && empty($value)) {
            $errors[] = "{$this->label} is required";
            return $errors;
        }

        if (!empty($value)) {
            // Validate hex format
            if ($this->format === 'hex' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
                $errors[] = "{$this->label} must be a valid hex color (e.g., #FF0000)";
            }
        }

        return $errors;
    }

    public function getJavaScript(): string {
        return <<<JS
        // Color field JavaScript
        (function() {
            const fieldName = '{$this->name}';
            let initialized = false;
            
            function hexToRgba(hex) {
                hex = hex.replace('#', '');
                const r = parseInt(hex.substring(0, 2), 16);
                const g = parseInt(hex.substring(2, 4), 16);
                const b = parseInt(hex.substring(4, 6), 16);
                return 'rgba(' + r + ', ' + g + ', ' + b + ', 1)';
            }
            
            function rgbaToHex(rgba) {
                const match = rgba.match(/rgba?\\(\\s*(\\d+),\\s*(\\d+),\\s*(\\d+)/);
                if (!match) return '#000000';
                const r = parseInt(match[1]).toString(16).padStart(2, '0');
                const g = parseInt(match[2]).toString(16).padStart(2, '0');
                const b = parseInt(match[3]).toString(16).padStart(2, '0');
                return '#' + r + g + b;
            }
            
            function initColorField() {
                if (initialized) return;
                
                const colorPicker = document.getElementById('field_' + fieldName + '_color');
                const hexInput = document.getElementById('field_' + fieldName + '_hex');
                const rgbaInput = document.getElementById('field_' + fieldName + '_rgba');
                const hiddenInput = document.getElementById('field_' + fieldName);
                
                if (!colorPicker || !hexInput || !rgbaInput || !hiddenInput) {
                    return false;
                }
                
                initialized = true;
                
                function updateAllFromHex(hex) {
                    hex = hex.toUpperCase();
                    colorPicker.value = hex;
                    hexInput.value = hex;
                    rgbaInput.value = hexToRgba(hex);
                    hiddenInput.value = hex;
                }
                
                function updateAllFromRgba(rgba) {
                    const hex = rgbaToHex(rgba);
                    colorPicker.value = hex;
                    hexInput.value = hex.toUpperCase();
                    rgbaInput.value = rgba;
                    hiddenInput.value = hex;
                }
                
                // Initialize values on load
                const initialHex = colorPicker.value || hexInput.value || hiddenInput.value || '#000000';
                updateAllFromHex(initialHex);
                
                // Color picker changes
                colorPicker.addEventListener('input', function() {
                    updateAllFromHex(this.value);
                });
                
                colorPicker.addEventListener('change', function() {
                    updateAllFromHex(this.value);
                });
                
                // Hex input changes
                hexInput.addEventListener('input', function() {
                    let value = this.value.trim().toUpperCase();
                    if (value && !value.startsWith('#')) {
                        value = '#' + value;
                    }
                    if (/^#[0-9A-Fa-f]{6}$/i.test(value)) {
                        updateAllFromHex(value);
                    }
                });
                
                hexInput.addEventListener('blur', function() {
                    let value = this.value.trim().toUpperCase();
                    if (!value.startsWith('#')) {
                        value = '#' + value;
                    }
                    if (value.length === 4) {
                        value = '#' + value[1] + value[1] + value[2] + value[2] + value[3] + value[3];
                    }
                    if (/^#[0-9A-Fa-f]{6}$/i.test(value)) {
                        this.value = value;
                        updateAllFromHex(value);
                    }
                });
                
                // RGBA input changes
                rgbaInput.addEventListener('input', function() {
                    const value = this.value.trim();
                    if (/^rgba?\\(\\s*\\d+,\\s*\\d+,\\s*\\d+(?:,\\s*[\\d.]+)?\\)$/i.test(value)) {
                        updateAllFromRgba(value);
                    }
                });
                
                rgbaInput.addEventListener('blur', function() {
                    const value = this.value.trim();
                    if (/^rgba?\\(\\s*\\d+,\\s*\\d+,\\s*\\d+(?:,\\s*[\\d.]+)?\\)$/i.test(value)) {
                        updateAllFromRgba(value);
                    }
                });
                
                return true;
            }
            
            // Try multiple initialization strategies
            function tryInit() {
                if (initColorField()) {
                    return true;
                }
                return false;
            }
            
            // Try immediately
            if (tryInit()) return;
            
            // Try on DOMContentLoaded
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    if (!tryInit()) {
                        // Try with delays if still not ready
                        setTimeout(tryInit, 50);
                        setTimeout(tryInit, 200);
                        setTimeout(tryInit, 500);
                    }
                });
            } else {
                // DOM already loaded, try with delays
                setTimeout(tryInit, 0);
                setTimeout(tryInit, 50);
                setTimeout(tryInit, 200);
                setTimeout(tryInit, 500);
            }
            
            // Also listen for fieldtype init events
            document.addEventListener('fieldtype:init', function(e) {
                if (e.detail && e.detail.fieldtype === 'color' && e.detail.fieldName === fieldName) {
                    tryInit();
                }
            });
            
            // Expose init function globally for manual initialization
            window['initColorField_' + fieldName] = initColorField;
        })();
        JS;
    }

    public function getCSS(): string {
        return <<<'CSS'
        .color-field-wrapper {
            display: flex !important;
            flex-direction: column !important;
            gap: 1rem !important;
        }
        
        .color-picker-section {
            display: flex !important;
            justify-content: center !important;
        }
        
        .color-picker-label {
            cursor: pointer !important;
            display: inline-block !important;
        }
        
        .color-picker {
            width: 80px !important;
            height: 80px !important;
            border: 3px solid var(--border-color, #ddd) !important;
            border-radius: 12px !important;
            cursor: pointer !important;
            padding: 0 !important;
            transition: all 0.3s !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
        }
        
        .color-picker:hover {
            border-color: var(--primary, #007bff) !important;
            transform: scale(1.05) !important;
            box-shadow: 0 6px 16px rgba(0,0,0,0.15) !important;
        }
        
        .color-inputs-section {
            display: flex !important;
            flex-direction: row !important;
            gap: 0.75rem !important;
        }
        
        .color-input-group {
            display: flex !important;
            flex-direction: column !important;
            gap: 0.5rem !important;
            flex: 1 !important;
        }
        
        .color-input-label {
            font-size: 0.875rem !important;
            font-weight: 500 !important;
            color: #666 !important;
        }
        
        .color-hex-input,
        .color-rgba-input {
            width: 100% !important;
            padding: 0.625rem !important;
            border: 1px solid var(--border-color, #ddd) !important;
            border-radius: 6px !important;
            font-size: 0.9375rem !important;
            font-family: 'Courier New', monospace !important;
            transition: all 0.2s !important;
        }
        
        .color-hex-input:focus,
        .color-rgba-input:focus {
            border-color: var(--primary, #007bff) !important;
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1) !important;
        }
        CSS;
    }

    public function getAdminConfig(): array {
        return [
            'type' => 'color',
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
            'format' => $this->format,
            'alpha' => $this->alpha
        ];
    }

    public function getSchema(): array {
        return [
            'type' => 'string',
            'format' => 'color',
            'required' => $this->required
        ];
    }

    public function getDefaultValue() {
        return '#000000';
    }

    public function processValue($value) {
        if (empty($value)) {
            return $this->getDefaultValue();
        }
        
        $value = trim($value);
        
        // Normalize to uppercase hex
        if (preg_match('/^#[0-9A-Fa-f]{6}$/i', $value)) {
            return strtoupper($value);
        }
        
        return $this->normalizeToHex($value);
    }

    public function processLoadedValue($value) {
        return $value;
    }

    public function getConfigUI($fieldIndex, $existingData = []): string {
        $format = $existingData['format'] ?? 'hex';
        $alpha = !empty($existingData['alpha']);
        $formatSelected = [
            'hex' => $format === 'hex' ? 'selected' : '',
            'rgb' => $format === 'rgb' ? 'selected' : '',
            'hsl' => $format === 'hsl' ? 'selected' : '',
        ];
        $alphaChecked = $alpha ? 'checked' : '';
        
        return <<<HTML
            <div class="field-config-section">
                <label>Color Format</label>
                <select name="fields[{$fieldIndex}][format]" class="input">
                    <option value="hex" {$formatSelected['hex']}>Hex (#RRGGBB)</option>
                    <option value="rgb" {$formatSelected['rgb']}>RGB</option>
                    <option value="hsl" {$formatSelected['hsl']}>HSL</option>
                </select>
            </div>
            <div class="field-config-section">
                <label class="checkbox-label">
                    <input type="checkbox" name="fields[{$fieldIndex}][alpha]" value="1" {$alphaChecked}>
                    <span class="checkmark"></span>
                    <span>Allow Alpha Channel (Transparency)</span>
                </label>
            </div>
        HTML;
    }
}

