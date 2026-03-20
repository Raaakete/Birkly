<?php
/**
 * Automation System Caching for Birkly
 * 
 * Provides caching for automation queries and block registry.
 */

// Use absolute path resolution to avoid path issues
// Try multiple path resolutions to handle different include contexts
$cachePath = null;
$possiblePaths = [
    dirname(__DIR__) . '/cache.php',
    __DIR__ . '/../cache.php',
    realpath(__DIR__ . '/../cache.php')
];

foreach ($possiblePaths as $path) {
    if ($path && file_exists($path)) {
        $cachePath = $path;
        break;
    }
}

if ($cachePath) {
    @require_once $cachePath;
} else {
    // Last resort: try to find it relative to current working directory
    $fallbackPath = dirname(dirname(__DIR__)) . '/core/cache.php';
    if (file_exists($fallbackPath)) {
        @require_once $fallbackPath;
    } else {
        error_log("Warning: cache.php not found. Tried: " . implode(', ', array_filter($possiblePaths)));
    }
}

// Cache keys
define('AUTOMATION_CACHE_PREFIX', 'automation_');
define('AUTOMATION_BLOCKS_CACHE_KEY', AUTOMATION_CACHE_PREFIX . 'blocks');
define('AUTOMATION_LIST_CACHE_KEY', AUTOMATION_CACHE_PREFIX . 'list');
define('AUTOMATION_CACHE_TTL', 3600); // 1 hour

/**
 * Get cached automation blocks
 * 
 * @return array|null Cached blocks or null if not cached
 */
function get_cached_automation_blocks() {
    $cache = new Cache();
    return $cache->get(AUTOMATION_BLOCKS_CACHE_KEY);
}

/**
 * Cache automation blocks
 * 
 * @param array $blocks Blocks to cache
 * @return bool True on success
 */
function cache_automation_blocks($blocks) {
    $cache = new Cache();
    return $cache->set(AUTOMATION_BLOCKS_CACHE_KEY, $blocks, AUTOMATION_CACHE_TTL);
}

/**
 * Invalidate automation cache
 * 
 * @return bool True on success
 */
function invalidate_automation_cache() {
    $cache = new Cache();
    $cache->delete(AUTOMATION_BLOCKS_CACHE_KEY);
    $cache->delete(AUTOMATION_LIST_CACHE_KEY);
    return true;
}

/**
 * Get cached automation list
 * 
 * @param array $filters Filters used for query
 * @return array|null Cached list or null if not cached
 */
function get_cached_automation_list($filters = []) {
    $cache = new Cache();
    $cache_key = AUTOMATION_LIST_CACHE_KEY . '_' . md5(json_encode($filters));
    return $cache->get($cache_key);
}

/**
 * Cache automation list
 * 
 * @param array $filters Filters used for query
 * @param array $automations Automations to cache
 * @return bool True on success
 */
function cache_automation_list($filters, $automations) {
    $cache = new Cache();
    $cache_key = AUTOMATION_LIST_CACHE_KEY . '_' . md5(json_encode($filters));
    return $cache->set($cache_key, $automations, 300); // 5 minutes for lists
}
