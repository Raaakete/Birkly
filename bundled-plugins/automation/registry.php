<?php
/**
 * Automation Block Registry for Birkly
 * 
 * Manages available automation blocks (triggers, conditions, actions).
 */

$birkly_automation_blocks = [
    'triggers' => [],
    'conditions' => [],
    'actions' => []
];

function register_automation_block($type, $block_id, $config) {
    global $birkly_automation_blocks;
    
    if (!in_array($type, ['trigger', 'condition', 'action'])) {
        return false;
    }
    
    $type_plural = $type . 's';
    
    if (!isset($birkly_automation_blocks[$type_plural])) {
        $birkly_automation_blocks[$type_plural] = [];
    }
    
    $plugin_id = null;
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    if (isset($backtrace[1]['file'])) {
        $file = $backtrace[1]['file'];
        global $birkly_plugins;
        if (isset($birkly_plugins)) {
            foreach ($birkly_plugins as $pid => $plugin) {
                if (strpos($file, $plugin['path'] ?? '') !== false) {
                    $plugin_id = $pid;
                    break;
                }
            }
        }
    }
    
    $birkly_automation_blocks[$type_plural][$block_id] = array_merge($config, [
        'id' => $block_id,
        'type' => $type,
        'plugin_id' => $plugin_id
    ]);
    
    if (function_exists('invalidate_automation_cache')) {
        invalidate_automation_cache();
    }
    
    return true;
}

function get_automation_blocks($type) {
    global $birkly_automation_blocks;
    $type_plural = $type . 's';
    return $birkly_automation_blocks[$type_plural] ?? [];
}

function get_automation_block($type, $block_id) {
    global $birkly_automation_blocks;
    $type_plural = $type . 's';
    return $birkly_automation_blocks[$type_plural][$block_id] ?? null;
}

function get_all_automation_blocks($use_cache = true) {
    global $birkly_automation_blocks;
    
    if ($use_cache && function_exists('get_cached_automation_blocks')) {
        $cached = get_cached_automation_blocks();
        if ($cached !== null) {
            return $cached;
        }
    }
    
    $result = $birkly_automation_blocks;
    
    if ($use_cache && function_exists('cache_automation_blocks')) {
        cache_automation_blocks($result);
    }
    
    return $result;
}
