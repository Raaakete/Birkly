<?php
/**
 * Sub-Entries Caching for Birkly
 * 
 * Provides caching for sub-entry queries.
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
define('SUB_ENTRIES_CACHE_PREFIX', 'sub_entries_');
define('SUB_ENTRIES_CACHE_TTL', 300); // 5 minutes

/**
 * Get cache key for sub-entries query
 * 
 * @param string $parent_collection Parent collection
 * @param string $parent_entry_id Parent entry ID
 * @param string $sub_collection Sub-collection
 * @param array $filters Filters
 * @param array $options Options
 * @return string Cache key
 */
function get_sub_entries_cache_key($parent_collection, $parent_entry_id, $sub_collection, $filters = [], $options = []) {
    $key_data = [
        'parent_collection' => $parent_collection,
        'parent_entry_id' => $parent_entry_id,
        'sub_collection' => $sub_collection,
        'filters' => $filters,
        'options' => $options
    ];
    return SUB_ENTRIES_CACHE_PREFIX . md5(json_encode($key_data));
}

/**
 * Get cached sub-entries
 * 
 * @param string $cache_key Cache key
 * @return array|null Cached entries or null
 */
function get_cached_sub_entries($cache_key) {
    $cache = new Cache();
    return $cache->get($cache_key);
}

/**
 * Cache sub-entries
 * 
 * @param string $cache_key Cache key
 * @param array $entries Entries to cache
 * @return bool True on success
 */
function cache_sub_entries($cache_key, $entries) {
    $cache = new Cache();
    return $cache->set($cache_key, $entries, SUB_ENTRIES_CACHE_TTL);
}

/**
 * Invalidate sub-entries cache for a parent entry
 * 
 * @param string $parent_collection Parent collection
 * @param string $parent_entry_id Parent entry ID
 * @param string|null $sub_collection Optional sub-collection to invalidate
 * @return bool True on success
 */
function invalidate_sub_entries_cache($parent_collection, $parent_entry_id, $sub_collection = null) {
    $cache = new Cache();
    
    // Invalidate all sub-entries for this parent
    $pattern = SUB_ENTRIES_CACHE_PREFIX . md5(json_encode([
        'parent_collection' => $parent_collection,
        'parent_entry_id' => $parent_entry_id,
        'sub_collection' => $sub_collection ?? '*'
    ]));
    
    // Since we can't pattern match easily, we'll use a simpler approach:
    // Store cache keys in a registry and invalidate by pattern
    $cache_registry = $cache->get('sub_entries_cache_registry') ?? [];
    $keys_to_delete = [];
    
    foreach ($cache_registry as $key) {
        if (strpos($key, SUB_ENTRIES_CACHE_PREFIX) === 0) {
            $key_data = json_decode(base64_decode(str_replace(SUB_ENTRIES_CACHE_PREFIX, '', $key)), true);
            if ($key_data && 
                $key_data['parent_collection'] === $parent_collection &&
                $key_data['parent_entry_id'] === $parent_entry_id &&
                ($sub_collection === null || $key_data['sub_collection'] === $sub_collection)) {
                $keys_to_delete[] = $key;
            }
        }
    }
    
    foreach ($keys_to_delete as $key) {
        $cache->delete($key);
    }
    
    return true;
}
