<?php
/**
 * Encrypted credential storage for live Shopify/WooCommerce/Etsy/Square import.
 * Stored in settings/commerce_import.json (not committed).
 */

if (!class_exists('PaymentsCredentialStore')) {
    require_once __DIR__ . '/CredentialStore.php';
}

class CommerceImportCredentialStore
{
    private string $settingsDir;
    private string $settingsFile;
    private PaymentsCredentialStore $crypto;

    public function __construct(?string $settingsDir = null, ?PaymentsCredentialStore $crypto = null)
    {
        $root = dirname(__DIR__, 3);
        $this->settingsDir = $settingsDir ?? $root . '/settings';
        $this->settingsFile = $this->settingsDir . '/commerce_import.json';
        $this->crypto = $crypto ?? new PaymentsCredentialStore();
    }

    /** @return array<string, mixed> */
    public function load(): array
    {
        if (!is_file($this->settingsFile)) {
            return $this->defaultConfig();
        }
        $data = json_decode((string) file_get_contents($this->settingsFile), true);
        return is_array($data) ? $data : $this->defaultConfig();
    }

    /** @param array<string, mixed> $config */
    public function save(array $config): array
    {
        $dir = dirname($this->settingsFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $config['updated_at'] = date('c');
        file_put_contents($this->settingsFile, json_encode($config, JSON_PRETTY_PRINT));
        chmod($this->settingsFile, 0600);

        return $config;
    }

    /** @return array<string, mixed> */
    private function defaultConfig(): array
    {
        return [
            'shopify' => $this->defaultPlatformConfig('shopify'),
            'woocommerce' => $this->defaultPlatformConfig('woocommerce'),
            'etsy' => $this->defaultPlatformConfig('etsy'),
            'square' => $this->defaultPlatformConfig('square'),
            'amazon' => $this->defaultPlatformConfig('amazon'),
            'ebay' => $this->defaultPlatformConfig('ebay'),
            'cron_token' => '',
            'updated_at' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function defaultPlatformConfig(string $platform): array
    {
        $base = [
            'enabled' => false,
            'collection' => 'products',
            'default_currency' => 'eur',
            'schedule_enabled' => false,
            'last_sync_at' => null,
            'last_sync_status' => null,
            'last_sync_message' => null,
            'last_import_id' => null,
            'webhook_secret_enc' => '',
        ];

        switch ($platform) {
            case 'shopify':
                $base['shop_domain'] = '';
                $base['access_token_enc'] = '';
                break;
            case 'woocommerce':
                $base['site_url'] = '';
                $base['consumer_key_enc'] = '';
                $base['consumer_secret_enc'] = '';
                break;
            case 'etsy':
                $base['shop_id'] = '';
                $base['api_key_enc'] = '';
                $base['access_token_enc'] = '';
                break;
            case 'square':
                $base['location_id'] = '';
                $base['access_token_enc'] = '';
                break;
            case 'amazon':
                $base['seller_id'] = '';
                $base['marketplace_id'] = '';
                $base['refresh_token_enc'] = '';
                break;
            case 'ebay':
                $base['marketplace_id'] = 'EBAY_US';
                $base['access_token_enc'] = '';
                break;
        }

        return $base;
    }

    public function getCronToken(): string
    {
        $config = $this->load();
        if (!empty($config['cron_token']) && is_string($config['cron_token'])) {
            return $config['cron_token'];
        }
        $config['cron_token'] = bin2hex(random_bytes(32));
        $this->save($config);

        return $config['cron_token'];
    }

    public function cronTokenMatches(string $provided): bool
    {
        if ($provided === '') {
            return false;
        }

        return hash_equals($this->getCronToken(), $provided);
    }

    /** @return array<string, mixed> */
    public function getPlatformConfig(string $platform, bool $includeSecrets = false): array
    {
        $config = $this->load();
        $platformConfig = is_array($config[$platform] ?? null) ? $config[$platform] : $this->defaultPlatformConfig($platform);
        if ($includeSecrets) {
            return $this->decryptPlatformSecrets($platform, $platformConfig);
        }

        return $this->publicPlatformConfig($platform, $platformConfig);
    }

    /**
     * @param array<string, mixed> $patch
     * @return array<string, mixed>
     */
    public function savePlatformConfig(string $platform, array $patch): array
    {
        $config = $this->load();
        $current = is_array($config[$platform] ?? null) ? $config[$platform] : $this->defaultPlatformConfig($platform);

        foreach (['enabled', 'collection', 'default_currency', 'schedule_enabled', 'shop_domain', 'site_url', 'shop_id', 'location_id', 'seller_id', 'marketplace_id'] as $key) {
            if (array_key_exists($key, $patch)) {
                $current[$key] = $patch[$key];
            }
        }

        if ($platform === 'shopify' && isset($patch['access_token']) && is_string($patch['access_token']) && $patch['access_token'] !== '') {
            $current['access_token_enc'] = $this->crypto->encrypt($patch['access_token']);
        }
        if ($platform === 'woocommerce') {
            if (isset($patch['consumer_key']) && is_string($patch['consumer_key']) && $patch['consumer_key'] !== '') {
                $current['consumer_key_enc'] = $this->crypto->encrypt($patch['consumer_key']);
            }
            if (isset($patch['consumer_secret']) && is_string($patch['consumer_secret']) && $patch['consumer_secret'] !== '') {
                $current['consumer_secret_enc'] = $this->crypto->encrypt($patch['consumer_secret']);
            }
        }
        if ($platform === 'etsy') {
            if (isset($patch['api_key']) && is_string($patch['api_key']) && $patch['api_key'] !== '') {
                $current['api_key_enc'] = $this->crypto->encrypt($patch['api_key']);
            }
            if (isset($patch['access_token']) && is_string($patch['access_token']) && $patch['access_token'] !== '') {
                $current['access_token_enc'] = $this->crypto->encrypt($patch['access_token']);
            }
        }
        if ($platform === 'square' && isset($patch['access_token']) && is_string($patch['access_token']) && $patch['access_token'] !== '') {
            $current['access_token_enc'] = $this->crypto->encrypt($patch['access_token']);
        }
        if ($platform === 'amazon' && isset($patch['refresh_token']) && is_string($patch['refresh_token']) && $patch['refresh_token'] !== '') {
            $current['refresh_token_enc'] = $this->crypto->encrypt($patch['refresh_token']);
        }
        if ($platform === 'ebay' && isset($patch['access_token']) && is_string($patch['access_token']) && $patch['access_token'] !== '') {
            $current['access_token_enc'] = $this->crypto->encrypt($patch['access_token']);
        }
        if (isset($patch['webhook_secret']) && is_string($patch['webhook_secret']) && $patch['webhook_secret'] !== '') {
            $current['webhook_secret_enc'] = $this->crypto->encrypt($patch['webhook_secret']);
        }

        $config[$platform] = $current;
        $this->save($config);

        return $this->publicPlatformConfig($platform, $current);
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public function recordSyncResult(string $platform, array $result): array
    {
        $config = $this->load();
        $current = is_array($config[$platform] ?? null) ? $config[$platform] : $this->defaultPlatformConfig($platform);
        $current['last_sync_at'] = date('c');
        $current['last_sync_status'] = ($result['ok'] ?? false) ? ($result['status'] ?? 'success') : 'failed';
        $current['last_sync_message'] = $result['message'] ?? ($result['error'] ?? null);
        if (!empty($result['import_id'])) {
            $current['last_import_id'] = $result['import_id'];
        }
        $config[$platform] = $current;
        $this->save($config);

        return $this->publicPlatformConfig($platform, $current);
    }

    /** @return array<string, mixed> */
    public function getPublicConfig(): array
    {
        $config = $this->load();

        return [
            'shopify' => $this->publicPlatformConfig('shopify', is_array($config['shopify'] ?? null) ? $config['shopify'] : []),
            'woocommerce' => $this->publicPlatformConfig('woocommerce', is_array($config['woocommerce'] ?? null) ? $config['woocommerce'] : []),
            'etsy' => $this->publicPlatformConfig('etsy', is_array($config['etsy'] ?? null) ? $config['etsy'] : []),
            'square' => $this->publicPlatformConfig('square', is_array($config['square'] ?? null) ? $config['square'] : []),
            'amazon' => $this->publicPlatformConfig('amazon', is_array($config['amazon'] ?? null) ? $config['amazon'] : []),
            'ebay' => $this->publicPlatformConfig('ebay', is_array($config['ebay'] ?? null) ? $config['ebay'] : []),
            'cron_token' => $this->getCronToken(),
            'updated_at' => $config['updated_at'] ?? null,
        ];
    }

    /** @param array<string, mixed> $platformConfig */
    private function publicPlatformConfig(string $platform, array $platformConfig): array
    {
        $out = [
            'enabled' => (bool) ($platformConfig['enabled'] ?? false),
            'collection' => (string) ($platformConfig['collection'] ?? 'products'),
            'default_currency' => strtolower((string) ($platformConfig['default_currency'] ?? 'eur')),
            'schedule_enabled' => (bool) ($platformConfig['schedule_enabled'] ?? false),
            'last_sync_at' => $platformConfig['last_sync_at'] ?? null,
            'last_sync_status' => $platformConfig['last_sync_status'] ?? null,
            'last_sync_message' => $platformConfig['last_sync_message'] ?? null,
            'last_import_id' => $platformConfig['last_import_id'] ?? null,
            'connected' => $this->platformHasCredentials($platform, $platformConfig),
            'webhook_configured' => !empty($platformConfig['webhook_secret_enc']),
        ];

        if ($platform === 'shopify') {
            $out['shop_domain'] = (string) ($platformConfig['shop_domain'] ?? '');
        } elseif ($platform === 'woocommerce') {
            $out['site_url'] = (string) ($platformConfig['site_url'] ?? '');
        } elseif ($platform === 'etsy') {
            $out['shop_id'] = (string) ($platformConfig['shop_id'] ?? '');
        } elseif ($platform === 'square') {
            $out['location_id'] = (string) ($platformConfig['location_id'] ?? '');
        } elseif ($platform === 'amazon') {
            $out['seller_id'] = (string) ($platformConfig['seller_id'] ?? '');
            $out['marketplace_id'] = (string) ($platformConfig['marketplace_id'] ?? '');
        } elseif ($platform === 'ebay') {
            $out['marketplace_id'] = (string) ($platformConfig['marketplace_id'] ?? 'EBAY_US');
        }

        return $out;
    }

    /** @param array<string, mixed> $platformConfig */
    private function platformHasCredentials(string $platform, array $platformConfig): bool
    {
        if ($platform === 'shopify') {
            return !empty($platformConfig['access_token_enc']) && trim((string) ($platformConfig['shop_domain'] ?? '')) !== '';
        }
        if ($platform === 'woocommerce') {
            return !empty($platformConfig['consumer_key_enc'])
                && !empty($platformConfig['consumer_secret_enc'])
                && trim((string) ($platformConfig['site_url'] ?? '')) !== '';
        }
        if ($platform === 'etsy') {
            return !empty($platformConfig['api_key_enc'])
                && !empty($platformConfig['access_token_enc'])
                && trim((string) ($platformConfig['shop_id'] ?? '')) !== '';
        }
        if ($platform === 'square') {
            return !empty($platformConfig['access_token_enc']);
        }
        if ($platform === 'amazon') {
            return !empty($platformConfig['refresh_token_enc'])
                && trim((string) ($platformConfig['seller_id'] ?? '')) !== ''
                && trim((string) ($platformConfig['marketplace_id'] ?? '')) !== '';
        }
        if ($platform === 'ebay') {
            return !empty($platformConfig['access_token_enc']);
        }

        return false;
    }

    /** @param array<string, mixed> $platformConfig */
    private function decryptPlatformSecrets(string $platform, array $platformConfig): array
    {
        $out = $platformConfig;
        if ($platform === 'shopify' && !empty($platformConfig['access_token_enc'])) {
            $out['access_token'] = $this->crypto->decrypt((string) $platformConfig['access_token_enc']);
        }
        if ($platform === 'woocommerce') {
            if (!empty($platformConfig['consumer_key_enc'])) {
                $out['consumer_key'] = $this->crypto->decrypt((string) $platformConfig['consumer_key_enc']);
            }
            if (!empty($platformConfig['consumer_secret_enc'])) {
                $out['consumer_secret'] = $this->crypto->decrypt((string) $platformConfig['consumer_secret_enc']);
            }
        }
        if ($platform === 'etsy') {
            if (!empty($platformConfig['api_key_enc'])) {
                $out['api_key'] = $this->crypto->decrypt((string) $platformConfig['api_key_enc']);
            }
            if (!empty($platformConfig['access_token_enc'])) {
                $out['access_token'] = $this->crypto->decrypt((string) $platformConfig['access_token_enc']);
            }
        }
        if ($platform === 'square' && !empty($platformConfig['access_token_enc'])) {
            $out['access_token'] = $this->crypto->decrypt((string) $platformConfig['access_token_enc']);
        }
        if ($platform === 'amazon' && !empty($platformConfig['refresh_token_enc'])) {
            $out['refresh_token'] = $this->crypto->decrypt((string) $platformConfig['refresh_token_enc']);
        }
        if ($platform === 'ebay' && !empty($platformConfig['access_token_enc'])) {
            $out['access_token'] = $this->crypto->decrypt((string) $platformConfig['access_token_enc']);
        }
        if (!empty($platformConfig['webhook_secret_enc'])) {
            $out['webhook_secret'] = $this->crypto->decrypt((string) $platformConfig['webhook_secret_enc']);
        }
        unset($out['access_token_enc'], $out['consumer_key_enc'], $out['consumer_secret_enc'], $out['api_key_enc'], $out['webhook_secret_enc'], $out['refresh_token_enc']);

        return $out;
    }
}
