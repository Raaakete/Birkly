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

    public function applyPurchaseMappings(array $order): void
    {
        if (($order['status'] ?? '') !== 'paid') {
            return;
        }

        $mappings = $this->getMappings();
        if (empty($mappings)) {
            return;
        }

        $userId = $order['customer_user_id'] ?? null;
        if (!$userId && !empty($order['customer_email'])) {
            $userId = $this->resolveUserByEmail($order['customer_email']);
        }
        if (!$userId) {
            return;
        }

        foreach ($order['lines'] ?? [] as $line) {
            $entryKey = 'entry:' . ($line['collection_slug'] ?? '') . ':' . ($line['entry_slug'] ?? $line['entry_id'] ?? '');
            $collKey = 'collection:' . ($line['collection_slug'] ?? '');

            foreach ([$entryKey, $collKey] as $key) {
                if (!isset($mappings[$key])) {
                    continue;
                }
                $mapping = $mappings[$key];
                if (!empty($mapping['grant_tier'])) {
                    $this->grantTier($userId, $mapping['grant_tier']);
                }
            }
        }
    }

    private function grantTier(string $userId, string $tierId): void
    {
        if (!function_exists('birkly_is_plugin_active') || !birkly_is_plugin_active('user_management')) {
            return;
        }
        $tiersFile = dirname(__DIR__, 2) . '/user_management/includes/UserTiers.php';
        if (!is_file($tiersFile)) {
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
        $serviceFile = dirname(__DIR__, 2) . '/user_management/includes/UserService.php';
        if (!is_file($serviceFile)) {
            return null;
        }
        require_once dirname(__DIR__, 2) . '/user_management/includes/UserStorage.php';
        require_once $serviceFile;
        try {
            $service = new UserService();
            $user = $service->getUserByEmail($email);
            return $user['id'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }
}
