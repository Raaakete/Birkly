<?php
namespace Birkly\FieldTypes\Color;

use Birkly\FieldTypes\BaseField;

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

    public function getIcon(): string {
        return 'palette';
    }

    public function getDescription(): string {
        return 'A colour value';
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
                        class="input is-mono color-hex-input"
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
                        class="input is-mono color-rgba-input"
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
        /* One row: swatch, hex, rgba. The swatch opens the native picker. */
        .color-field-wrapper {
            display: flex;
            align-items: flex-end;
            gap: var(--s-2);
        }
        .color-picker-label {
            display: block;
            flex: none;
            cursor: pointer;
        }
        .color-picker {
            display: block;
            width: var(--control);
            height: var(--control);
            padding: 0;
            border: 0;
            border-radius: var(--r);
            background: none;
            cursor: pointer;
        }
        .color-picker::-webkit-color-swatch-wrapper { padding: 0; }
        .color-picker::-webkit-color-swatch { border: 0; border-radius: var(--r); box-shadow: inset 0 0 0 1px var(--line); }
        .color-picker::-moz-color-swatch { border: 0; border-radius: var(--r); }
        .color-picker:focus-visible { outline: var(--focus-outline); outline-offset: 1px; }
        .color-inputs-section {
            display: flex;
            flex: 1 1 auto;
            gap: var(--s-2);
            min-width: 0;
        }
        .color-input-group {
            display: flex;
            flex: 1 1 0;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }
        .color-input-label {
            color: var(--text-3);
            font: var(--weight-regular) var(--size-caption) / var(--lh-caption) var(--font-sans);
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

    public function renderListCell($value, array $context = []): string {
        if (empty($value)) {
            return '<span class="ft-list-empty">–</span>';
        }
        
        // Show color swatch with hex code
        $hex = $this->normalizeToHex($value);
        return '<div style="display:flex;align-items:center;gap:6px;"><span style="display:inline-block;width:20px;height:20px;background-color:' . htmlspecialchars($hex, ENT_QUOTES, 'UTF-8') . ';border-radius:4px;border:1px solid var(--border-light, #e5e7eb);flex-shrink:0;"></span><span class="ft-list-text" style="font-family:monospace;font-size:11px;">' . htmlspecialchars($hex, ENT_QUOTES, 'UTF-8') . '</span></div>';
    }

    public function getTableMinWidth(): string {
        return '80px'; // Color fields are narrow (just hex code display)
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

