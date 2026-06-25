<?php
/**
 * Automation Block Registry for Birkly
 * 
 * Manages available automation blocks (triggers, conditions, actions).
 */

// Global block registry
$haru_automation_blocks = [
    'triggers' => [],
    'conditions' => [],
    'actions' => []
];

/**
 * Register an automation block
 * 
 * @param string $type Block type ('trigger', 'condition', 'action')
 * @param string $block_id Block identifier
 * @param array $config Block configuration:
 *   - 'name' (string): Display name
 *   - 'description' (string): Block description
 *   - 'schema' (array): Configuration schema
 *   - 'handler' (callable): Block execution handler
 *   - 'category' (string): Block category
 * 
 * @return bool True on success
 */
function register_automation_block($type, $block_id, $config) {
    global $haru_automation_blocks;
    
    if (!in_array($type, ['trigger', 'condition', 'action'])) {
        return false;
    }
    
    $type_plural = $type . 's';
    
    if (!isset($haru_automation_blocks[$type_plural])) {
        $haru_automation_blocks[$type_plural] = [];
    }
    
    // Get plugin ID from backtrace
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
    
    $haru_automation_blocks[$type_plural][$block_id] = array_merge($config, [
        'id' => $block_id,
        'type' => $type,
        'plugin_id' => $plugin_id
    ]);
    
    // Invalidate cache when new block is registered
    if (function_exists('invalidate_automation_cache')) {
        invalidate_automation_cache();
    }
    
    return true;
}

/**
 * Get all blocks of a specific type
 * 
 * @param string $type Block type ('trigger', 'condition', 'action')
 * @return array List of blocks
 */
function get_automation_blocks($type) {
    global $haru_automation_blocks;
    
    $type_plural = $type . 's';
    return $haru_automation_blocks[$type_plural] ?? [];
}

/**
 * Get a specific block
 * 
 * @param string $type Block type
 * @param string $block_id Block identifier
 * @return array|null Block configuration or null if not found
 */
function get_automation_block($type, $block_id) {
    global $haru_automation_blocks;
    
    $type_plural = $type . 's';
    return $haru_automation_blocks[$type_plural][$block_id] ?? null;
}

/**
 * Get all registered blocks
 * 
 * @param bool $use_cache Whether to use cache (default: true)
 * @return array All blocks organized by type
 */
function get_all_automation_blocks($use_cache = true) {
    global $haru_automation_blocks;
    
    // Try cache first
    if ($use_cache && function_exists('get_cached_automation_blocks')) {
        $cached = get_cached_automation_blocks();
        if ($cached !== null) {
            return $cached;
        }
    }
    
    $result = $haru_automation_blocks;
    
    // Cache result
    if ($use_cache && function_exists('cache_automation_blocks')) {
        cache_automation_blocks($result);
    }
    
    return $result;
}
