<?php
/**
 * Automation System Caching for Birkly
 */

require_once __DIR__ . '/cms_paths.php';
require_once automation_cms_root() . '/core/cache.php';

define('AUTOMATION_CACHE_PREFIX', 'automation_');
define('AUTOMATION_BLOCKS_CACHE_KEY', AUTOMATION_CACHE_PREFIX . 'blocks');
define('AUTOMATION_LIST_CACHE_KEY', AUTOMATION_CACHE_PREFIX . 'list');
define('AUTOMATION_LIST_CACHE_INDEX_KEY', AUTOMATION_LIST_CACHE_KEY . '_index');
define('AUTOMATION_CACHE_TTL', 3600);

/**
 * Build a stable cache key for automation list queries.
 */
function automation_list_cache_key($filters = []) {
    if (!is_array($filters)) {
        $filters = [];
    }
    ksort($filters);
    return AUTOMATION_LIST_CACHE_KEY . '_' . md5(json_encode($filters));
}

function get_cached_automation_blocks() {
    $cache = new Cache();
    return $cache->get(AUTOMATION_BLOCKS_CACHE_KEY);
}

function cache_automation_blocks($blocks) {
    $cache = new Cache();
    return $cache->set(AUTOMATION_BLOCKS_CACHE_KEY, $blocks, AUTOMATION_CACHE_TTL);
}

function invalidate_automation_cache() {
    $cache = new Cache();
    $cache->delete(AUTOMATION_BLOCKS_CACHE_KEY);
    invalidate_automation_list_cache();
    return true;
}

/**
 * Invalidate cached automation lists (all tracked filter keys).
 */
function invalidate_automation_list_cache($filters = null) {
    $cache = new Cache();

    if ($filters !== null) {
        $cache->delete(automation_list_cache_key($filters));
        return true;
    }

    $index = $cache->get(AUTOMATION_LIST_CACHE_INDEX_KEY);
    if (is_array($index)) {
        foreach ($index as $cacheKey) {
            $cache->delete($cacheKey);
        }
    }

    // Always clear the default (empty filters) list key and legacy bare key.
    $cache->delete(automation_list_cache_key([]));
    $cache->delete(AUTOMATION_LIST_CACHE_KEY);
    $cache->delete(AUTOMATION_LIST_CACHE_INDEX_KEY);

    return true;
}

function get_cached_automation_list($filters = []) {
    $cache = new Cache();
    $cache_key = automation_list_cache_key($filters);
    return $cache->get($cache_key);
}

function cache_automation_list($filters, $automations) {
    $cache = new Cache();
    $cache_key = automation_list_cache_key($filters);

    $index = $cache->get(AUTOMATION_LIST_CACHE_INDEX_KEY);
    if (!is_array($index)) {
        $index = [];
    }
    if (!in_array($cache_key, $index, true)) {
        $index[] = $cache_key;
        $cache->set(AUTOMATION_LIST_CACHE_INDEX_KEY, $index, AUTOMATION_CACHE_TTL);
    }

    return $cache->set($cache_key, $automations, 300);
}
