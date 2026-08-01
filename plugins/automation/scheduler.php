<?php
require_once __DIR__ . '/cms_paths.php';

/**
 * Automation Scheduler for Birkly
 * 
 * Handles scheduled (cron-like) automation triggers.
 */

require_once automation_cms_root() . '/core/storage/StorageFactory.php';
require_once __DIR__ . '/automation.php';
require_once __DIR__ . '/queue.php';
require_once __DIR__ . '/blocks/triggers_scheduled.php';

/**
 * Process scheduled automations
 * 
 * This should be called periodically (e.g., via cron every minute)
 * 
 * @return int Number of scheduled automations triggered
 */
function process_scheduled_automations() {
    // Get all enabled automations with scheduled triggers
    $automations = get_automations(['enabled' => true]);
    $triggered = 0;
    
    foreach ($automations as $automation) {
        $trigger = $automation['trigger'] ?? [];
        $trigger_type = $trigger['type'] ?? '';
        
        if ($trigger_type !== 'scheduled') {
            continue;
        }
        
        $trigger_config = $trigger['config'] ?? [];
        
        // Check if this scheduled trigger should fire
        if (check_scheduled_trigger($trigger_config)) {
            // Check last execution time to avoid duplicate executions
            $last_execution = get_last_scheduled_execution($automation['id']);
            $now = time();
            
            // Only fire if not executed in the last minute (prevents duplicate runs)
            if ($last_execution === null || ($now - $last_execution) >= 60) {
                // Enqueue for execution
                enqueue_automation('scheduled', [
                    'automation_id' => $automation['id'],
                    'schedule' => $trigger_config['schedule'] ?? '',
                    'triggered_at' => date('c')
                ]);
                
                // Record execution time
                record_scheduled_execution($automation['id'], $now);
                
                $triggered++;
            }
        }
    }
    
    return $triggered;
}

/**
 * Get last scheduled execution time for an automation
 * 
 * @param string $automation_id Automation ID
 * @return int|null Timestamp or null if never executed
 */
function get_last_scheduled_execution($automation_id) {
    $storage = automation_storage();
    $scheduler_state = $storage->read('automation_scheduler_state.json') ?? [];
    
    return $scheduler_state[$automation_id]['last_execution'] ?? null;
}

/**
 * Record scheduled execution time
 * 
 * @param string $automation_id Automation ID
 * @param int $timestamp Execution timestamp
 * @return bool True on success
 */
function record_scheduled_execution($automation_id, $timestamp) {
    $storage = automation_storage();
    $scheduler_state = $storage->read('automation_scheduler_state.json') ?? [];
    
    if (!isset($scheduler_state[$automation_id])) {
        $scheduler_state[$automation_id] = [];
    }
    
    $scheduler_state[$automation_id]['last_execution'] = $timestamp;
    $scheduler_state[$automation_id]['last_execution_date'] = date('c', $timestamp);
    
    return $storage->write('automation_scheduler_state.json', $scheduler_state);
}
