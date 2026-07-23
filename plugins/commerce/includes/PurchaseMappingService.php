<?php
/**
 * Purchase side-effect mappings — grant tiers on paid orders.
 */

class PurchaseMappingService
{
    private string $mappingsFile;

    public function __construct(?string $dataDir = null)
    {
        $dir = $dataDir ?? commerce_data_dir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->mappingsFile = $dir . '/purchase_mappings.json';
        if (!is_file($this->mappingsFile)) {
            file_put_contents($this->mappingsFile, json_encode(['mappings' => []], JSON_PRETTY_PRINT));
        }
    }

    public function loadMappings(): array
    {
        $data = json_decode((string) file_get_contents($this->mappingsFile), true);
        return is_array($data) ? $data : ['mappings' => []];
    }

    public function saveMappings(array $data): void
    {
        file_put_contents($this->mappingsFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function getMappings(): array
    {
        return $this->loadMappings()['mappings'] ?? [];
    }

    public function setMapping(string $key, array $config): void
    {
        $data = $this->loadMappings();
        $data['mappings'][$key] = $config;
        $this->saveMappings($data);
    }

    public function deleteMapping(string $key): bool
    {
        $data = $this->loadMappings();
        if (!isset($data['mappings'][$key])) {
            return false;
        }
        unset($data['mappings'][$key]);
        $this->saveMappings($data);
        return true;
    }

    public static function entryKey(string $collectionSlug, string $entrySlug): string
    {
        return 'entry:' . $collectionSlug . ':' . $entrySlug;
    }

    public static function collectionKey(string $collectionSlug): string
    {
        return 'collection:' . $collectionSlug;
    }

    public static function skuKey(string $sku): string
    {
        return 'sku:' . $sku;
    }

    public static function purchaseOptionKey(string $collectionSlug, string $entrySlug, string $optionId): string
    {
        return 'purchase_option:' . $collectionSlug . ':' . $entrySlug . ':' . $optionId;
    }

    public function createMembershipMapping(array $input): array
    {
        $collection = trim((string) ($input['collection_slug'] ?? ''));
        $entrySlug = trim((string) ($input['entry_slug'] ?? ''));
        $sku = trim((string) ($input['sku'] ?? ''));
        $optionId = trim((string) ($input['purchase_option_id'] ?? ''));
        $tierId = trim((string) ($input['grant_tier'] ?? ''));

        if ($tierId === '') {
            return ['ok' => false, 'error' => 'grant_tier required'];
        }

        $key = '';
        $label = '';
        if ($optionId !== '' && $collection !== '' && $entrySlug !== '') {
            $key = self::purchaseOptionKey($collection, $entrySlug, $optionId);
            $label = $collection . '/' . $entrySlug . ' · ' . $optionId;
        } elseif ($sku !== '') {
            $key = self::skuKey($sku);
            $label = 'SKU ' . $sku;
        } elseif ($collection !== '' && $entrySlug !== '') {
            $key = self::entryKey($collection, $entrySlug);
            $label = $collection . '/' . $entrySlug;
        } elseif ($collection !== '') {
            $key = self::collectionKey($collection);
            $label = 'collection:' . $collection;
        } else {
            return ['ok' => false, 'error' => 'collection_slug, sku, or purchase_option required'];
        }

        $config = [
            'grant_tier' => $tierId,
            'label' => $label,
            'collection_slug' => $collection !== '' ? $collection : null,
            'entry_slug' => $entrySlug !== '' ? $entrySlug : null,
            'sku' => $sku !== '' ? $sku : null,
            'purchase_option_id' => $optionId !== '' ? $optionId : null,
            'updated_at' => date('c'),
        ];
        $this->setMapping($key, $config);

        return ['ok' => true, 'key' => $key, 'config' => $config];
    }

    /**
     * @return string[]
     */
    public function resolveLineMappingKeys(array $line): array
    {
        $collection = (string) ($line['collection_slug'] ?? '');
        $entrySlug = (string) ($line['entry_slug'] ?? '');
        $entryId = (string) ($line['entry_id'] ?? '');
        $sku = (string) ($line['sku'] ?? ($line['commerce']['sku'] ?? ''));
        $optionId = (string) ($line['purchase_option_id'] ?? '');

        if ($entrySlug === '' && $entryId !== '') {
            $entrySlug = $entryId;
        }

        $keys = [];
        if ($optionId !== '' && $collection !== '' && $entrySlug !== '') {
            $keys[] = self::purchaseOptionKey($collection, $entrySlug, $optionId);
        }
        if ($sku !== '') {
            $keys[] = self::skuKey($sku);
        }
        if ($collection !== '' && $entrySlug !== '') {
            $keys[] = self::entryKey($collection, $entrySlug);
        }
        if ($collection !== '') {
            $keys[] = self::collectionKey($collection);
        }

        return array_values(array_unique($keys));
    }

    public function applyPurchaseMappings(array $order): void
    {
        if (($order['status'] ?? '') !== 'paid') {
            return;
        }

        $mappings = $this->getMappings();
        if (empty($mappings)) {
            return;
        }

        $userId = $this->resolveOrderUserId($order);
        if (!$userId) {
            return;
        }

        foreach ($order['lines'] ?? [] as $line) {
            $this->applyLineMappings($order, $line, $userId);
        }
    }

    public function applyLineMappings(array $order, array $line, ?string $userId = null): bool
    {
        if (($order['status'] ?? '') !== 'paid') {
            return false;
        }

        $userId = $userId ?? $this->resolveOrderUserId($order);
        if (!$userId) {
            return false;
        }

        $mappings = $this->getMappings();
        if (empty($mappings)) {
            return false;
        }

        foreach ($this->resolveLineMappingKeys($line) as $key) {
            if (!isset($mappings[$key])) {
                continue;
            }
            $mapping = $mappings[$key];
            if (!empty($mapping['grant_tier'])) {
                $this->grantTier($userId, $mapping['grant_tier']);
                return true;
            }
        }

        return false;
    }

    private function resolveOrderUserId(array $order): ?string
    {
        $userId = $order['customer_user_id'] ?? null;
        if (!$userId && !empty($order['customer_email'])) {
            $userId = $this->resolveUserByEmail($order['customer_email']);
        }
        return $userId ?: null;
    }

    private function grantTier(string $userId, string $tierId): void
    {
        if (!function_exists('birkly_is_plugin_active') || !birkly_is_plugin_active('user_management')) {
            return;
        }
        $tiersFile = null;
        if (function_exists('birkly_plugin_install_path')) {
            $umPath = birkly_plugin_install_path('user_management');
            if ($umPath !== null) {
                $tiersFile = $umPath . '/includes/UserTiers.php';
            }
        }
        if ($tiersFile === null || !is_file($tiersFile)) {
            return;
        }
        require_once $tiersFile;
        try {
            $tiers = new UserTiers();
            $tiers->setUserTier($userId, $tierId);
        } catch (Exception $e) {
            error_log('Commerce purchase mapping tier grant failed: ' . $e->getMessage());
        }
    }

    private function resolveUserByEmail(string $email): ?string
    {
        if (!function_exists('birkly_is_plugin_active') || !birkly_is_plugin_active('user_management')) {
            return null;
        }
        if (function_exists('get_plugin_service')) {
            $service = get_plugin_service('user_service', false, 'commerce');
            if (is_object($service) && method_exists($service, 'getUserByEmail')) {
                try {
                    $user = $service->getUserByEmail($email);
                    return $user['id'] ?? null;
                } catch (Exception $e) {
                    return null;
                }
            }
        }
        if (!function_exists('birkly_plugin_require') || !birkly_plugin_require('user_management', 'includes/UserStorage.php')) {
            return null;
        }
        if (!birkly_plugin_require('user_management', 'includes/UserService.php')) {
            return null;
        }
        try {
            $service = new UserService();
            $user = $service->getUserByEmail($email);
            return $user['id'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }
}
