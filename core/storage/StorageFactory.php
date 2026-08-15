<?php
/**
 * Storage Factory for Birkly
 *
 * Creates and manages storage driver instances.
 * Provides a single point of access for storage throughout the application.
 */

require_once __DIR__ . '/StorageInterface.php';
require_once __DIR__ . '/FlatFileStorage.php';
require_once __DIR__ . '/MySqlStorage.php';
require_once __DIR__ . '/PostgresStorage.php';

class StorageFactory
{
    private static $instance = null;
    private static $defaultStorage = null;
    private static $storageConfig = null;

    /**
     * Resolve storage config file path (settings/storage.json at project root).
     */
    public static function getConfigFilePath(): string
    {
        return dirname(__DIR__, 2) . '/settings/storage.json';
    }

    /**
     * Get the singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get the default storage instance
     *
     * @param string|null $basePath Optional base path for flat-file storage
     * @return StorageInterface
     */
    public static function getStorage($basePath = null)
    {
        if (self::$defaultStorage === null) {
            self::loadStorageConfig();
            self::$defaultStorage = self::createDriver(
                self::$storageConfig['driver'] ?? 'flatfile',
                self::$storageConfig,
                $basePath
            );
        }

        return self::$defaultStorage;
    }

    /**
     * Create a storage instance for a specific path/collection
     *
     * @param string $basePath Base path for the storage instance
     * @return StorageInterface
     */
    public static function createStorage($basePath)
    {
        self::loadStorageConfig();
        return self::createDriver(
            self::$storageConfig['driver'] ?? 'flatfile',
            self::$storageConfig,
            $basePath
        );
    }

    /**
     * Create a driver instance from config.
     */
    public static function createDriver(string $driver, array $config, $basePath = null): StorageInterface
    {
        switch ($driver) {
            case 'mysql':
                return new MySqlStorage($config);
            case 'postgresql':
            case 'postgres':
                return new PostgresStorage($config);
            case 'flatfile':
            default:
                $storagePath = $basePath ?? $config['base_path'] ?? self::defaultFlatFilePath();
                return new FlatFileStorage($storagePath);
        }
    }

    /**
     * Test database connection for mysql/postgresql.
     */
    public static function testDatabaseConnection(array $config): array
    {
        $driver = self::normalizeDriver($config['driver'] ?? $config['backend'] ?? '');
        if (!in_array($driver, ['mysql', 'postgresql'], true)) {
            $driver = self::normalizeDriver($config['configured_driver'] ?? '');
        }
        if ($driver === 'mysql') {
            return MySqlStorage::testConnection(self::normalizeDbConfig(array_merge($config, ['driver' => $driver])));
        }
        if ($driver === 'postgresql') {
            return PostgresStorage::testConnection(self::normalizeDbConfig(array_merge($config, ['driver' => $driver])));
        }
        return ['ok' => false, 'message' => 'Unsupported database driver'];
    }

    /**
     * Normalize UI/API config keys to driver config.
     */
    public static function normalizeDbConfig(array $config): array
    {
        return [
            'driver' => self::normalizeDriver($config['driver'] ?? $config['backend'] ?? ''),
            'host' => $config['host'] ?? $config['db_host'] ?? 'localhost',
            'port' => (int) ($config['port'] ?? $config['db_port'] ?? 0),
            'database' => $config['database'] ?? $config['db_name'] ?? $config['name'] ?? '',
            'username' => $config['username'] ?? $config['db_user'] ?? $config['user'] ?? '',
            'password' => $config['password'] ?? $config['db_password'] ?? '',
            'prefix' => $config['prefix'] ?? $config['db_prefix'] ?? 'birkly_',
        ];
    }

    public static function normalizeDriver(string $driver): string
    {
        $driver = strtolower(trim($driver));
        if ($driver === 'postgres') {
            return 'postgresql';
        }
        return $driver;
    }

    public static function buildStorageConfigFromInput(array $input): array
    {
        $existing = self::loadStorageConfig(true);
        $requestedDriver = self::normalizeDriver(
            $input['storage_backend'] ?? $input['driver'] ?? $input['backend'] ?? ($existing['configured_driver'] ?? $existing['driver'] ?? 'flatfile')
        );
        $activeDriver = self::normalizeDriver((string) ($existing['driver'] ?? 'flatfile'));

        $password = $existing['password'] ?? '';
        if (array_key_exists('db_password', $input)) {
            if ($input['db_password'] !== '') {
                $password = (string) $input['db_password'];
            }
        } elseif (array_key_exists('password', $input) && $input['password'] !== '') {
            $password = (string) $input['password'];
        }

        $config = [
            'base_path' => $existing['base_path'] ?? self::defaultFlatFilePath(),
            'host' => $input['db_host'] ?? $input['host'] ?? ($existing['host'] ?? 'localhost'),
            'port' => (int) ($input['db_port'] ?? $input['port'] ?? ($existing['port'] ?? ($requestedDriver === 'postgresql' ? 5432 : 3306))),
            'database' => $input['db_name'] ?? $input['database'] ?? $input['name'] ?? ($existing['database'] ?? ''),
            'username' => $input['db_user'] ?? $input['username'] ?? $input['user'] ?? ($existing['username'] ?? ''),
            'password' => $password,
            'prefix' => $input['db_prefix'] ?? $input['prefix'] ?? ($existing['prefix'] ?? 'birkly_'),
            'configured_driver' => $requestedDriver,
            '_updated_at' => date('c'),
        ];

        // Keep flat-file active until migration succeeds; SQL installs keep their driver.
        if ($activeDriver === 'flatfile' && in_array($requestedDriver, ['mysql', 'postgresql'], true)) {
            $config['driver'] = 'flatfile';
        } elseif ($requestedDriver === 'flatfile') {
            $config['driver'] = 'flatfile';
        } else {
            $config['driver'] = $requestedDriver;
        }

        return $config;
    }

    /**
     * Normalize migration/test request fields to storage config keys.
     */
    public static function mergeDatabaseInput(array $base, array $input): array
    {
        $merged = $base;
        $fieldMap = [
            'host' => 'host',
            'db_host' => 'host',
            'port' => 'port',
            'db_port' => 'port',
            'name' => 'database',
            'db_name' => 'database',
            'database' => 'database',
            'user' => 'username',
            'db_user' => 'username',
            'username' => 'username',
            'password' => 'password',
            'db_password' => 'password',
            'prefix' => 'prefix',
            'db_prefix' => 'prefix',
        ];

        foreach ($fieldMap as $inputKey => $configKey) {
            if (!array_key_exists($inputKey, $input)) {
                continue;
            }
            $value = $input[$inputKey];
            if ($configKey === 'password') {
                if ($value === '') {
                    continue;
                }
            } elseif ($value === null || $value === '') {
                continue;
            }
            $merged[$configKey] = $value;
        }

        if (isset($input['backend']) || isset($input['driver']) || isset($input['storage_backend'])) {
            $merged['storage_backend'] = $input['storage_backend'] ?? $input['backend'] ?? $input['driver'];
        }

        return $merged;
    }

    /**
     * Activate a configured SQL backend after a successful migration.
     */
    public static function activateMigratedDriver(array $config, string $driver): array
    {
        $driver = self::normalizeDriver($driver);
        $config['driver'] = $driver;
        $config['configured_driver'] = $driver;
        $config['_updated_at'] = date('c');

        return $config;
    }

    /**
     * Load storage configuration from settings
     *
     * @param bool $returnConfig When true, returns config array instead of setting static property only
     */
    public static function loadStorageConfig(bool $returnConfig = false)
    {
        if (self::$storageConfig !== null) {
            if ($returnConfig) {
                return self::$storageConfig;
            }
            return;
        }

        $configFile = self::getConfigFilePath();
        $config = null;

        if (file_exists($configFile)) {
            $decoded = json_decode(file_get_contents($configFile), true);
            if (is_array($decoded)) {
                $config = self::normalizeLoadedConfig($decoded);
            }
        }

        if ($config === null) {
            $config = [
                'driver' => 'flatfile',
                'base_path' => self::defaultFlatFilePath(),
            ];
        }

        if ($returnConfig) {
            return $config;
        }

        self::$storageConfig = $config;
    }

    private static function normalizeLoadedConfig(array $config): array
    {
        if (isset($config['backend']) && !isset($config['driver'])) {
            $config['driver'] = self::normalizeDriver((string) $config['backend']);
        }
        $config['driver'] = self::normalizeDriver((string) ($config['driver'] ?? 'flatfile'));
        if (isset($config['configured_driver'])) {
            $config['configured_driver'] = self::normalizeDriver((string) $config['configured_driver']);
        } else {
            $config['configured_driver'] = $config['driver'];
        }

        if (!isset($config['base_path'])) {
            $config['base_path'] = self::defaultFlatFilePath();
        }

        return $config;
    }

    private static function defaultFlatFilePath(): string
    {
        $defaultPath = realpath(dirname(__DIR__, 2) . '/content');
        if ($defaultPath === false) {
            $defaultPath = dirname(__DIR__, 2) . '/content';
        }
        return $defaultPath;
    }

    /**
     * Save storage configuration
     *
     * @param array $config Storage configuration
     * @return bool True on success
     */
    public static function saveStorageConfig($config)
    {
        $configFile = self::getConfigFilePath();
        $settingsDir = dirname($configFile);

        if (!file_exists($settingsDir)) {
            mkdir($settingsDir, 0755, true);
        }

        $normalized = self::normalizeLoadedConfig($config);
        $result = file_put_contents($configFile, json_encode($normalized, JSON_PRETTY_PRINT));

        if ($result !== false) {
            self::$storageConfig = $normalized;
            self::$defaultStorage = null;
            return true;
        }

        return false;
    }

    /**
     * Reset the storage instance (useful for testing or reconfiguration)
     */
    public static function reset()
    {
        self::$defaultStorage = null;
        self::$storageConfig = null;
    }
}

/**
 * Global helper function to get storage instance
 *
 * @param string|null $basePath Optional base path
 * @return StorageInterface
 */
function get_storage($basePath = null)
{
    return StorageFactory::getStorage($basePath);
}
