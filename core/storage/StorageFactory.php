<?php
/**
 * Storage Factory for Birkly
 * 
 * Creates and manages storage driver instances.
 * Provides a single point of access for storage throughout the application.
 */

require_once __DIR__ . '/StorageInterface.php';
require_once __DIR__ . '/FlatFileStorage.php';

class StorageFactory {
    private static $instance = null;
    private static $defaultStorage = null;
    private static $storageConfig = null;
    
    /**
     * Get the singleton instance
     */
    public static function getInstance() {
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
    public static function getStorage($basePath = null) {
        if (self::$defaultStorage === null) {
            // Load storage configuration
            self::loadStorageConfig();
            
            // Determine which driver to use
            $driver = self::$storageConfig['driver'] ?? 'flatfile';
            
            switch ($driver) {
                case 'flatfile':
                default:
                    $storagePath = $basePath ?? self::$storageConfig['base_path'] ?? '../content';
                    self::$defaultStorage = new FlatFileStorage($storagePath);
                    break;
                    
                // Future drivers will be added here:
                // case 'postgres':
                //     self::$defaultStorage = new PostgresStorage(self::$storageConfig);
                //     break;
                // case 'mysql':
                //     self::$defaultStorage = new MySqlStorage(self::$storageConfig);
                //     break;
            }
        }
        
        return self::$defaultStorage;
    }
    
    /**
     * Create a storage instance for a specific path/collection
     * 
     * @param string $basePath Base path for the storage instance
     * @return StorageInterface
     */
    public static function createStorage($basePath) {
        self::loadStorageConfig();
        $driver = self::$storageConfig['driver'] ?? 'flatfile';
        
        switch ($driver) {
            case 'flatfile':
            default:
                return new FlatFileStorage($basePath);
        }
    }
    
    /**
     * Load storage configuration from settings
     */
    private static function loadStorageConfig() {
        if (self::$storageConfig !== null) {
            return;
        }
        
        $configFile = __DIR__ . '/../settings/storage.json';
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
            if ($config) {
                self::$storageConfig = $config;
                return;
            }
        }
        
        // Default configuration - use absolute path relative to core directory
        $defaultPath = realpath(__DIR__ . '/../../content');
        if ($defaultPath === false) {
            $defaultPath = __DIR__ . '/../../content';
        }
        self::$storageConfig = [
            'driver' => 'flatfile',
            'base_path' => $defaultPath
        ];
    }
    
    /**
     * Save storage configuration
     * 
     * @param array $config Storage configuration
     * @return bool True on success
     */
    public static function saveStorageConfig($config) {
        $configFile = __DIR__ . '/../settings/storage.json';
        $settingsDir = dirname($configFile);
        
        if (!file_exists($settingsDir)) {
            mkdir($settingsDir, 0755, true);
        }
        
        $result = file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
        
        if ($result !== false) {
            self::$storageConfig = $config;
            // Reset default storage so it will be recreated with new config
            self::$defaultStorage = null;
            return true;
        }
        
        return false;
    }
    
    /**
     * Reset the storage instance (useful for testing or reconfiguration)
     */
    public static function reset() {
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
function get_storage($basePath = null) {
    return StorageFactory::getStorage($basePath);
}
