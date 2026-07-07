<?php
/**
 * Birkly Marketplace — bootstrap: constants, config, core includes.
 */

if (!defined('MARKETPLACE_ROOT')) {
    define('MARKETPLACE_ROOT', dirname(__DIR__));
}

/**
 * Load merged configuration (defaults + optional config.php).
 */
function marketplace_load_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $defaults = require MARKETPLACE_ROOT . '/config.example.php';
    $configFile = MARKETPLACE_ROOT . '/config.php';

    if (is_file($configFile)) {
        $local = require $configFile;
        if (is_array($local)) {
            $config = array_merge($defaults, $local);
            return $config;
        }
    }

    $config = $defaults;
    return $config;
}

/**
 * Read a single config value.
 */
function marketplace_config(string $key, mixed $default = null): mixed
{
    $config = marketplace_load_config();
    return $config[$key] ?? $default;
}

/**
 * Ensure runtime directories exist.
 */
function marketplace_bootstrap_runtime(): void
{
    $dirs = [
        marketplace_config('data_dir'),
        marketplace_config('cache_dir'),
        marketplace_config('logs_dir'),
        marketplace_config('artifacts_dir'),
    ];

    foreach ($dirs as $dir) {
        if (is_string($dir) && $dir !== '' && !is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    $tz = marketplace_config('timezone', 'UTC');
    if (is_string($tz) && $tz !== '') {
        date_default_timezone_set($tz);
    }
}

require_once __DIR__ . '/response.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/router.php';
require_once __DIR__ . '/payloads.php';

marketplace_bootstrap_runtime();
