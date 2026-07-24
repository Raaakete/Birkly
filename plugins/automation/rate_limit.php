<?php
/**
 * Automation Rate Limiting for Birkly
 * 
 * Prevents automations from executing too frequently.
 */

require_once __DIR__ . '/../../core/storage/StorageFactory.php';

/**
 * Check if automation can execute (rate limit check)
 * 
 * @param string $automation_id Automation ID
 * @param int $max_per_hour Maximum executions per hour (default: 100)
 * @return bool True if allowed to execute
 */
function check_automation_rate_limit($automation_id, $max_per_hour = 100) {
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $rate_limits = $storage->read('automation_rate_limits.json') ?? [];
    
    $now = time();
    $hour_ago = $now - 3600;
    
    // Get automation-specific limit if set
    $automation_limit = $rate_limits[$automation_id]['max_per_hour'] ?? $max_per_hour;
    
    // Get execution history
    $executions = $rate_limits[$automation_id]['executions'] ?? [];
    
    // Filter to last hour
    $recent_executions = array_filter($executions, function($timestamp) use ($hour_ago) {
        return $timestamp >= $hour_ago;
    });
    
    // Check if limit exceeded
    if (count($recent_executions) >= $automation_limit) {
        return false;
    }
    
    // Record this execution
    $executions[] = $now;
    
    // Keep only last 24 hours of executions
    $day_ago = $now - 86400;
    $executions = array_filter($executions, function($timestamp) use ($day_ago) {
        return $timestamp >= $day_ago;
    });
    $executions = array_values($executions);
    
    // Save updated rate limit data
    if (!isset($rate_limits[$automation_id])) {
        $rate_limits[$automation_id] = [];
    }
    $rate_limits[$automation_id]['executions'] = $executions;
    $rate_limits[$automation_id]['max_per_hour'] = $automation_limit;
    $rate_limits[$automation_id]['last_checked'] = $now;
    
    $storage->write('automation_rate_limits.json', $rate_limits);
    
    return true;
}

/**
 * Set rate limit for an automation
 * 
 * @param string $automation_id Automation ID
 * @param int $max_per_hour Maximum executions per hour
 * @return bool True on success
 */
function set_automation_rate_limit($automation_id, $max_per_hour) {
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $rate_limits = $storage->read('automation_rate_limits.json') ?? [];
    
    if (!isset($rate_limits[$automation_id])) {
        $rate_limits[$automation_id] = [];
    }
    
    $rate_limits[$automation_id]['max_per_hour'] = $max_per_hour;
    
    return $storage->write('automation_rate_limits.json', $rate_limits);
}

/**
 * Get rate limit status for an automation
 * 
 * @param string $automation_id Automation ID
 * @return array Rate limit status
 */
function get_automation_rate_limit_status($automation_id) {
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $rate_limits = $storage->read('automation_rate_limits.json') ?? [];
    
    $now = time();
    $hour_ago = $now - 3600;
    
    $executions = $rate_limits[$automation_id]['executions'] ?? [];
    $recent_executions = array_filter($executions, function($timestamp) use ($hour_ago) {
        return $timestamp >= $hour_ago;
    });
    
    return [
        'max_per_hour' => $rate_limits[$automation_id]['max_per_hour'] ?? 100,
        'executions_last_hour' => count($recent_executions),
        'can_execute' => count($recent_executions) < ($rate_limits[$automation_id]['max_per_hour'] ?? 100)
    ];
}
