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

    // Leaflet ships inside this fieldtype (leaflet/); tiles are images (img-src https:).
    public static function adminCspSources(): array {
        return ['connect-src' => ['https://nominatim.openstreetmap.org']];
    }

    public function getIcon(): string {
        return 'map-pin';
    }

    public function getDescription(): string {
        return 'A location on a map';
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
        
        $name = htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8');
        $address = htmlspecialchars((string) $address, ENT_QUOTES, 'UTF-8');
        $icon = static fn (string $n): string => function_exists('birkly_icon') ? birkly_icon($n, ['size' => 13]) : '';
        $searchIcon = $icon('search');
        $pinIcon = $icon('map-pin');

        return <<<HTML
        <div class="map-field" id="field_{$name}">
            <div class="map-preview" id="map_{$name}" hidden></div>
            <div class="map-inputs">
                <div class="map-input">
                    <label for="map_lat_{$name}">Latitude</label>
                    <input type="number" step="any" id="map_lat_{$name}" class="input is-mono" value="{$lat}" placeholder="51.505">
                </div>
                <div class="map-input">
                    <label for="map_lng_{$name}">Longitude</label>
                    <input type="number" step="any" id="map_lng_{$name}" class="input is-mono" value="{$lng}" placeholder="-0.09">
                </div>
            </div>
            <div class="map-input">
                <label for="map_address_{$name}">Address</label>
                <div class="map-address">
                    <input type="text" id="map_address_{$name}" class="input" value="{$address}" placeholder="Street, city">
                    <button type="button" class="btn btn-secondary btn-sm" id="map_search_{$name}">{$searchIcon}Search</button>
                </div>
            </div>
            <div class="map-actions">
                <button type="button" class="btn btn-ghost btn-sm" id="map_get_location_{$name}">{$pinIcon}Use my location</button>
            </div>
            <input type="hidden" name="{$name}" id="field_{$name}_value" value="{$this->encodeValue($location)}">
        </div>
        HTML;
    }
    
    private function encodeValue($location) {
        if (empty($location) || (!isset($location['lat']) && !isset($location[0]))) {
            return '';
        }
        return htmlspecialchars((string) json_encode($location), ENT_QUOTES, 'UTF-8');
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
        return <<<'CSS'
        .map-field {
            display: flex;
            flex-direction: column;
            gap: var(--s-3);
        }
        .map-preview {
            height: 240px;
            overflow: hidden;
            border-radius: var(--r);
            background: var(--surface-2);
        }
        .map-preview[hidden] { display: none; }
        .map-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--s-2);
        }
        .map-input {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }
        .map-input > label {
            color: var(--text-3);
            font: var(--weight-regular) var(--size-caption) / var(--lh-caption) var(--font-sans);
        }
        .map-address {
            display: flex;
            gap: var(--s-1);
        }
        .map-address .input { flex: 1 1 auto; min-width: 0; }
        .map-address .btn { flex: none; height: var(--control); }
        .map-actions { display: flex; }
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
        return '<span class="ft-list-text ft-list-mono">' . htmlspecialchars($coords, ENT_QUOTES, 'UTF-8') . '</span>';
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

