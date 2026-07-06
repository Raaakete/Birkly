<?php
/**
 * Saved commerce fieldtype profiles — configured in Commerce admin, not collection editor.
 */

if (!class_exists('CommerceSchema')) {
    require_once __DIR__ . '/CommerceSchema.php';
}

class CommerceFieldtypeProfileStore
{
    private string $profilesFile;

    public function __construct(?string $dataDir = null)
    {
        $dir = $dataDir ?? commerce_data_dir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->profilesFile = $dir . '/fieldtype_profiles.json';
        if (!is_file($this->profilesFile)) {
            $this->saveProfiles($this->defaultProfilesDocument());
        }
    }

    public function loadProfiles(): array
    {
        $data = json_decode((string) file_get_contents($this->profilesFile), true);
        if (!is_array($data) || empty($data['profiles'])) {
            return $this->defaultProfilesDocument();
        }
        return $data;
    }

    public function saveProfiles(array $data): void
    {
        file_put_contents($this->profilesFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    /** @return list<array<string, mixed>> */
    public function listProfiles(): array
    {
        $data = $this->loadProfiles();
        return array_values($data['profiles'] ?? []);
    }

    public function getProfile(string $id): ?array
    {
        $data = $this->loadProfiles();
        return $data['profiles'][$id] ?? null;
    }

    public function getProfileByTypeKey(string $typeKey): ?array
    {
        foreach ($this->listProfiles() as $profile) {
            if (($profile['type_key'] ?? '') === $typeKey) {
                return $profile;
            }
        }
        if ($typeKey === 'commerce') {
            return $this->getProfile('default');
        }
        if (str_starts_with($typeKey, 'commerce_')) {
            $slug = substr($typeKey, strlen('commerce_'));
            return $this->getProfile($slug);
        }
        return null;
    }

    public function getSettingsForTypeKey(string $typeKey): array
    {
        $profile = $this->getProfileByTypeKey($typeKey);
        if (!$profile) {
            return self::defaultSettings();
        }
        return self::normalizeSettings($profile['settings'] ?? []);
    }

    public function saveProfile(array $profile): array
    {
        $data = $this->loadProfiles();
        $id = trim((string) ($profile['id'] ?? ''));
        if ($id === '') {
            $id = $this->generateId($profile['name'] ?? 'profile');
        }
        if ($id === 'default' && empty($profile['is_default'])) {
            $profile['is_default'] = true;
        }
        if (!empty($profile['is_default'])) {
            $id = 'default';
        }

        $slug = $this->normalizeSlug($profile['slug'] ?? $id);
        if ($id !== 'default') {
            $slug = $this->normalizeSlug($profile['slug'] ?? $slug);
        } else {
            $slug = 'default';
        }

        $typeKey = $id === 'default' ? 'commerce' : 'commerce_' . $slug;

        $saved = [
            'id' => $id,
            'slug' => $slug,
            'name' => trim((string) ($profile['name'] ?? 'Commerce')),
            'type_key' => $typeKey,
            'is_default' => ($id === 'default'),
            'settings' => self::normalizeSettings($profile['settings'] ?? []),
            'updated_at' => date('c'),
        ];

        if (empty($data['profiles'][$id]['created_at'])) {
            $saved['created_at'] = date('c');
        } else {
            $saved['created_at'] = $data['profiles'][$id]['created_at'];
        }

        $data['profiles'][$id] = $saved;
        $this->saveProfiles($data);
        return $saved;
    }

    public function deleteProfile(string $id): bool
    {
        if ($id === 'default') {
            return false;
        }
        $data = $this->loadProfiles();
        if (!isset($data['profiles'][$id])) {
            return false;
        }
        unset($data['profiles'][$id]);
        $this->saveProfiles($data);
        return true;
    }

    public static function defaultSettings(): array
    {
        return [
            'enable_fulfillment' => true,
            'enable_purchase_options' => true,
            'enable_quantity' => true,
            'enable_sku' => true,
            'enable_weight' => true,
            'enable_delivery' => true,
            'default_fulfillment' => 'ship',
            'enabled_fulfillments' => CommerceSchema::FULFILLMENTS,
            'interval_options' => [
                ['value' => 'week', 'label' => 'Weekly'],
                ['value' => 'month', 'label' => 'Monthly'],
                ['value' => 'year', 'label' => 'Yearly'],
            ],
            'currency_options' => ['eur', 'usd', 'gbp', 'jpy'],
            'custom_fields' => [],
        ];
    }

    public static function normalizeSettings(array $settings): array
    {
        $defaults = self::defaultSettings();
        $merged = array_merge($defaults, $settings);

        $fulfillments = $merged['enabled_fulfillments'] ?? CommerceSchema::FULFILLMENTS;
        if (!is_array($fulfillments)) {
            $fulfillments = CommerceSchema::FULFILLMENTS;
        }
        $merged['enabled_fulfillments'] = array_values(array_filter(
            $fulfillments,
            fn ($f) => in_array($f, CommerceSchema::FULFILLMENTS, true)
        ));
        if (empty($merged['enabled_fulfillments'])) {
            $merged['enabled_fulfillments'] = CommerceSchema::FULFILLMENTS;
        }

        $merged['interval_options'] = self::normalizeIntervalOptions($merged['interval_options'] ?? []);
        $merged['currency_options'] = array_values(array_filter(
            array_map('strtolower', (array) ($merged['currency_options'] ?? [])),
            fn ($c) => $c !== ''
        ));
        if (empty($merged['currency_options'])) {
            $merged['currency_options'] = $defaults['currency_options'];
        }

        $custom = [];
        foreach ((array) ($merged['custom_fields'] ?? []) as $cf) {
            if (!is_array($cf)) {
                continue;
            }
            $key = trim((string) ($cf['key'] ?? ''));
            if ($key === '' || !preg_match('/^[a-z0-9_]+$/', $key)) {
                continue;
            }
            $custom[] = [
                'key' => $key,
                'label' => trim((string) ($cf['label'] ?? $key)),
                'type' => in_array($cf['type'] ?? 'text', ['text', 'integer', 'number'], true)
                    ? $cf['type'] : 'text',
                'required' => !empty($cf['required']),
                'help' => trim((string) ($cf['help'] ?? '')),
            ];
        }
        $merged['custom_fields'] = $custom;

        return $merged;
    }

    /** @param mixed $options */
    public static function normalizeIntervalOptions($options): array
    {
        $normalized = [];
        foreach ((array) $options as $opt) {
            if (is_string($opt)) {
                $value = trim($opt);
                if ($value === '') {
                    continue;
                }
                $normalized[] = ['value' => $value, 'label' => self::intervalLabelFromValue($value)];
                continue;
            }
            if (!is_array($opt)) {
                continue;
            }
            $value = trim((string) ($opt['value'] ?? ''));
            if ($value === '') {
                continue;
            }
            $label = trim((string) ($opt['label'] ?? ''));
            if ($label === '') {
                $label = self::intervalLabelFromValue($value);
            }
            $normalized[] = ['value' => $value, 'label' => $label];
        }
        if (empty($normalized)) {
            return self::defaultSettings()['interval_options'];
        }
        return $normalized;
    }

    public static function intervalLabelFromValue(string $value): string
    {
        if (preg_match('/^(\d+)_(day|days|week|weeks|month|months|year|years)$/', $value, $m)) {
            $count = (int) $m[1];
            $unit = rtrim($m[2], 's');
            if ($count === 1) {
                return 'Every ' . $unit;
            }
            return 'Every ' . $count . ' ' . $unit . 's';
        }
        return ucwords(str_replace('_', ' ', $value));
    }

    /** @return list<string> interval values only */
    public static function intervalValues(array $settings): array
    {
        return array_map(
            fn ($opt) => is_array($opt) ? ($opt['value'] ?? '') : (string) $opt,
            self::normalizeIntervalOptions($settings['interval_options'] ?? [])
        );
    }

    private function defaultProfilesDocument(): array
    {
        return [
            'profiles' => [
                'default' => [
                    'id' => 'default',
                    'slug' => 'default',
                    'name' => 'Commerce (Default)',
                    'type_key' => 'commerce',
                    'is_default' => true,
                    'settings' => self::defaultSettings(),
                    'created_at' => date('c'),
                    'updated_at' => date('c'),
                ],
            ],
        ];
    }

    private function generateId(string $name): string
    {
        $base = $this->normalizeSlug($name);
        $data = $this->loadProfiles();
        $id = $base;
        $i = 2;
        while (isset($data['profiles'][$id])) {
            $id = $base . '_' . $i;
            $i++;
        }
        return $id;
    }

    private function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9_]+/', '_', $slug) ?? '';
        $slug = trim($slug, '_');
        if ($slug === '' || $slug === 'default') {
            $slug = 'profile_' . bin2hex(random_bytes(3));
        }
        return $slug;
    }
}
