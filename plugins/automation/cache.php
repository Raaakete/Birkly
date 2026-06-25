<?php
/**
 * Automation System Caching for Birkly
 */

require_once __DIR__ . '/../../core/cache.php';

define('AUTOMATION_CACHE_PREFIX', 'automation_');
define('AUTOMATION_BLOCKS_CACHE_KEY', AUTOMATION_CACHE_PREFIX . 'blocks');
define('AUTOMATION_LIST_CACHE_KEY', AUTOMATION_CACHE_PREFIX . 'list');
define('AUTOMATION_CACHE_TTL', 3600);

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
    $cache->delete(AUTOMATION_LIST_CACHE_KEY);
    return true;
}

function get_cached_automation_list($filters = []) {
    $cache = new Cache();
    $cache_key = AUTOMATION_LIST_CACHE_KEY . '_' . md5(json_encode($filters));
    return $cache->get($cache_key);
}

function cache_automation_list($filters, $automations) {
    $cache = new Cache();
    $cache_key = AUTOMATION_LIST_CACHE_KEY . '_' . md5(json_encode($filters));
    return $cache->set($cache_key, $automations, 300);
}
