<?php
namespace Birkly\FieldTypes\Map;

use Birkly\FieldTypes\BaseField;

class Map extends BaseField {
    public function getType(): string {
        return 'map';
    }

    public function getCategory(): string {
        return 'Location';
    }

    public function render(): string {
        $value = $this->value ?? '';
        
        // Parse value - can be JSON string or array
        $location = null;
        if (is_string($value) && !empty($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $location = $decoded;
            } else if (preg_match('/^[\d\.]+,\s*[\d\.]+$/', $value)) {
                // Handle "lat, lng" format
                $parts = explode(',', $value);
                $location = [
                    'lat' => floatval(trim($parts[0])),
                    'lng' => floatval(trim($parts[1]) ?? 0)
                ];
            }
        } else if (is_array($value) && !empty($value)) {
            $location = $value;
        }
        
        $lat = $location['lat'] ?? ($location[0] ?? 0);
        $lng = $location['lng'] ?? ($location[1] ?? 0);
        $address = $location['address'] ?? '';
        
        // Default to a reasonable center if no location
        if ($lat == 0 && $lng == 0) {
            $lat = 51.505; // London
            $lng = -0.09;
        }
        
        return <<<HTML
        <div class="map-field" id="field_{$this->name}">
            <div class="map-preview" id="map_{$this->name}" style="width: 100%; height: 300px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 12px;"></div>
            <div class="map-controls">
                <div class="map-inputs" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group">
                        <label for="map_lat_{$this->name}">Latitude</label>
                        <input type="number" step="any" id="map_lat_{$this->name}" class="input" value="{$lat}" placeholder="51.505">
                    </div>
                    <div class="form-group">
                        <label for="map_lng_{$this->name}">Longitude</label>
                        <input type="number" step="any" id="map_lng_{$this->name}" class="input" value="{$lng}" placeholder="-0.09">
                    </div>
                </div>
                <div class="form-group">
                    <label for="map_address_{$this->name}">Address (optional)</label>
                    <input type="text" id="map_address_{$this->name}" class="input" value="{$address}" placeholder="Enter address or search...">
                    <button type="button" class="btn btn-secondary" id="map_search_{$this->name}" style="margin-top: 8px;">Search Location</button>
                </div>
                <button type="button" class="btn btn-primary" id="map_get_location_{$this->name}">Get Current Location</button>
            </div>
            <input type="hidden" name="{$this->name}" id="field_{$this->name}_value" value='{$this->encodeValue($location)}'>
        </div>
        HTML;
    }
    
    private function encodeValue($location) {
        if (empty($location) || (!isset($location['lat']) && !isset($location[0]))) {
            return '';
        }
        return json_encode($location);
    }

    public function validate($value): array {
        $errors = [];
        
        if ($this->required && empty($value)) {
            $errors[] = "{$this->label} is required";
        } else if (!empty($value)) {
            // Validate location format
            $location = $this->parseValue($value);
            if (!$location || !isset($location['lat']) || !isset($location['lng'])) {
                $errors[] = "{$this->label} must be a valid location";
            }
        }
        
        return $errors;
    }
    
    private function parseValue($value) {
        if (empty($value)) return null;
        
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        
        if (is_array($value)) {
            return $value;
        }
        
        return null;
    }

    public function getJavaScript(): string {
        return file_get_contents(__DIR__ . '/fieldtype.js');
    }

    public function getCSS(): string {
        return <<<CSS
        .map-field {
            display: flex;
            flex-direction: column;
            gap: var(--space-3, 0.75rem);
        }
        .map-preview {
            background: var(--bg-secondary, #fafafa);
            position: relative;
        }
        .map-controls {
            display: flex;
            flex-direction: column;
            gap: var(--space-3, 0.75rem);
        }
        .map-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-3, 0.75rem);
        }
        CSS;
    }

    public function getAdminConfig(): array {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'type' => 'map',
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
            'options' => [
                'default_zoom' => $this->options['default_zoom'] ?? 13,
                'show_address' => $this->options['show_address'] ?? true
            ]
        ];
    }

    public function getSchema(): array {
        return [
            'name' => $this->name,
            'type' => 'map',
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
            'options' => $this->options ?? []
        ];
    }

    public function getDefaultValue() {
        return null;
    }

    public function processValue($value) {
        $location = $this->parseValue($value);
        if ($location) {
            // Ensure we have lat and lng keys
            $result = [
                'lat' => $location['lat'] ?? $location[0] ?? 0,
                'lng' => $location['lng'] ?? $location[1] ?? 0
            ];
            if (isset($location['address'])) {
                $result['address'] = $location['address'];
            }
            return $result;
        }
        return null;
    }

    public function processLoadedValue($value) {
        return $this->parseValue($value);
    }

    public function renderListCell($value, array $context = []): string {
        $location = $this->parseValue($value);
        if (!$location) {
            return '<span class="ft-list-empty">–</span>';
        }
        
        $lat = $location['lat'] ?? $location[0] ?? 0;
        $lng = $location['lng'] ?? $location[1] ?? 0;
        $address = $location['address'] ?? '';
        
        if ($address) {
            // Truncate address if too long
            if (mb_strlen($address) > 40) {
                $address = mb_substr($address, 0, 37) . '...';
            }
            return '<span class="ft-list-text">' . htmlspecialchars($address, ENT_QUOTES, 'UTF-8') . '</span>';
        }
        
        // Show coordinates
        $coords = number_format($lat, 6) . ', ' . number_format($lng, 6);
        return '<span class="ft-list-text" style="font-family:monospace;font-size:11px;">' . htmlspecialchars($coords, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    public function getTableMinWidth(): string {
        return '180px'; // Map fields need space for coordinates display
    }

    public function outputsTrustedHtml(): bool
    {
        return true;
    }

    public function formatValueForTemplate($value, array $fieldConfig = [], array $context = []): string
    {
        require_once dirname(__DIR__, 2) . '/core/map_presentation.php';
        return birkly_map_render_html($value);
    }
}

