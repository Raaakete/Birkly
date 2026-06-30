<?php
/**
 * Plugin Scheduled Tasks System for Birkly
 * 
 * Allows plugins to register scheduled tasks.
 */

require_once __DIR__ . '/capabilities.php';

// Global scheduled tasks registry
$haru_plugin_scheduled_tasks = [];

/**
 * Register a scheduled task
 * 
 * @param string $plugin_id Plugin ID
 * @param string $task_id Task identifier
 * @param string $schedule Schedule (cron expression or 'hourly', 'daily', etc.)
 * @param callable $callback Task callback
 * @param array $options Options (capability, description)
 * @return bool True on success
 */
function register_plugin_scheduled_task($plugin_id, $task_id, $schedule, $callback, $options = []) {
    global $haru_plugin_scheduled_tasks;
    
    // Check capability if required
    $required_capability = $options['capability'] ?? null;
    if ($required_capability && !has_plugin_capability($plugin_id, $required_capability)) {
        return false;
    }
    
    $haru_plugin_scheduled_tasks["{$plugin_id}_{$task_id}"] = [
        'plugin_id' => $plugin_id,
        'task_id' => $task_id,
        'schedule' => $schedule,
        'callback' => $callback,
        'description' => $options['description'] ?? '',
        'capability' => $required_capability,
        'last_run' => null,
        'next_run' => null
    ];
    
    return true;
}

/**
 * Get all registered scheduled tasks
 * 
 * @return array All scheduled tasks
 */
function get_plugin_scheduled_tasks() {
    global $haru_plugin_scheduled_tasks;
    return $haru_plugin_scheduled_tasks;
}

/**
 * Get scheduled tasks for a plugin
 * 
 * @param string $plugin_id Plugin ID
 * @return array Plugin's scheduled tasks
 */
function get_plugin_scheduled_tasks_for_plugin($plugin_id) {
    global $haru_plugin_scheduled_tasks;
    
    $tasks = [];
    foreach ($haru_plugin_scheduled_tasks as $key => $task) {
        if ($task['plugin_id'] === $plugin_id) {
            $tasks[$task['task_id']] = $task;
        }
    }
    
    return $tasks;
}

/**
 * Process scheduled tasks (should be called by cron)
 * 
 * @return int Number of tasks executed
 */
function process_plugin_scheduled_tasks() {
    global $haru_plugin_scheduled_tasks;
    
    $executed = 0;
    
    foreach ($haru_plugin_scheduled_tasks as $key => $task) {
        // Check if task should run
        if (!should_run_scheduled_task($task)) {
            continue;
        }
        
        // Check if plugin is active
        global $birkly_plugins;
        if (!isset($birkly_plugins[$task['plugin_id']]) || !($birkly_plugins[$task['plugin_id']]['active'] ?? false)) {
            continue;
        }
        
        // Execute task
        try {
            $callback = $task['callback'];
            if (is_callable($callback)) {
                call_user_func($callback);
                $executed++;
                
                // Update last run time
                $haru_plugin_scheduled_tasks[$key]['last_run'] = date('c');
                $haru_plugin_scheduled_tasks[$key]['next_run'] = calculate_next_run($task['schedule']);
            }
        } catch (Exception $e) {
            error_log("[Plugin Scheduled Task] Error executing task {$key}: " . $e->getMessage());
        }
    }
    
    return $executed;
}

/**
 * Check if a scheduled task should run
 * 
 * @param array $task Task definition
 * @return bool True if should run
 */
function should_run_scheduled_task($task) {
    $schedule = $task['schedule'] ?? '';
    $last_run = $task['last_run'] ?? null;
    
    // If never run, check if schedule matches now
    if ($last_run === null) {
        return matches_schedule($schedule);
    }
    
    // Check if next run time has passed
    $next_run = $task['next_run'] ?? null;
    if ($next_run) {
        return strtotime($next_run) <= time();
    }
    
    // Fallback: check if schedule matches now and enough time has passed
    return matches_schedule($schedule);
}

/**
 * Check if current time matches schedule
 * 
 * @param string $schedule Schedule expression
 * @return bool True if matches
 */
function matches_schedule($schedule) {
    $now = time();
    $current_minute = (int)date('i', $now);
    $current_hour = (int)date('H', $now);
    $current_day = (int)date('d', $now);
    $current_dow = (int)date('w', $now);
    
    // Handle simple schedules
    switch ($schedule) {
        case 'hourly':
            return $current_minute === 0;
        case 'daily':
            return $current_minute === 0 && $current_hour === 0;
        case 'weekly':
            return $current_minute === 0 && $current_hour === 0 && $current_dow === 0;
        case 'monthly':
            return $current_minute === 0 && $current_hour === 0 && $current_day === 1;
    }
    
    // Handle cron expression (basic)
    if (preg_match('/^(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)$/', $schedule, $matches)) {
        list(, $min, $hour, $day, $month, $dow) = $matches;
        return $current_minute == $min &&
               ($hour === '*' || $current_hour == $hour) &&
               ($day === '*' || $current_day == $day) &&
               ($month === '*' || (int)date('m', $now) == $month) &&
               ($dow === '*' || $current_dow == $dow);
    }
    
    return false;
}

/**
 * Calculate next run time for a schedule
 * 
 * @param string $schedule Schedule expression
 * @return string Next run time (ISO 8601)
 */
function calculate_next_run($schedule) {
    $now = time();
    
    switch ($schedule) {
        case 'hourly':
            return date('c', $now + 3600 - ($now % 3600));
        case 'daily':
            return date('c', strtotime('tomorrow 00:00'));
        case 'weekly':
            return date('c', strtotime('next Sunday 00:00'));
        case 'monthly':
            return date('c', strtotime('first day of next month 00:00'));
    }
    
    // For cron expressions, calculate next run (simplified)
    return date('c', $now + 3600); // Default: next hour
}
