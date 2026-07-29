<?php
/**
 * Scheduled Automation Triggers for Birkly
 * 
 * Defines cron-like scheduled trigger blocks.
 */

require_once __DIR__ . '/../registry.php';

// Scheduled Trigger (cron-like)
register_automation_block('trigger', 'scheduled', [
    'name' => 'Scheduled (Cron)',
    'description' => 'Fires on a schedule (e.g., daily at 9am, every hour)',
    'category' => 'System',
    'schema' => [
        'schedule' => [
            'type' => 'select',
            'label' => 'Schedule',
            'options' => [
                ['value' => 'hourly', 'label' => 'Every Hour'],
                ['value' => 'daily', 'label' => 'Daily'],
                ['value' => 'weekly', 'label' => 'Weekly'],
                ['value' => 'monthly', 'label' => 'Monthly'],
                ['value' => 'custom', 'label' => 'Custom Cron Expression']
            ],
            'required' => true
        ],
        'time' => [
            'type' => 'time',
            'label' => 'Time (for daily/weekly/monthly)',
            'description' => 'Time of day (HH:MM format, e.g., 09:00)'
        ],
        'day_of_week' => [
            'type' => 'select',
            'label' => 'Day of Week (for weekly)',
            'options' => [
                ['value' => '0', 'label' => 'Sunday'],
                ['value' => '1', 'label' => 'Monday'],
                ['value' => '2', 'label' => 'Tuesday'],
                ['value' => '3', 'label' => 'Wednesday'],
                ['value' => '4', 'label' => 'Thursday'],
                ['value' => '5', 'label' => 'Friday'],
                ['value' => '6', 'label' => 'Saturday']
            ]
        ],
        'day_of_month' => [
            'type' => 'number',
            'label' => 'Day of Month (for monthly)',
            'description' => 'Day of month (1-31)'
        ],
        'cron_expression' => [
            'type' => 'string',
            'label' => 'Cron Expression (for custom)',
            'description' => 'Cron expression (e.g., "0 9 * * *" for daily at 9am)',
            'placeholder' => '0 9 * * *'
        ]
    ],
    'handler' => function($config, $context) {
        // This is handled by the scheduler, not evaluated here
        return true;
    }
]);

/**
 * Check if a scheduled trigger should fire
 * 
 * @param array $trigger_config Trigger configuration
 * @return bool True if should fire
 */
function check_scheduled_trigger($trigger_config) {
    $schedule = $trigger_config['schedule'] ?? '';
    $now = time();
    $current_hour = (int)date('H', $now);
    $current_minute = (int)date('i', $now);
    $current_day = (int)date('d', $now);
    $current_dow = (int)date('w', $now); // 0 = Sunday
    
    switch ($schedule) {
        case 'hourly':
            // Fire at the top of every hour
            return $current_minute === 0;
            
        case 'daily':
            $time = $trigger_config['time'] ?? '00:00';
            list($hour, $minute) = explode(':', $time);
            return $current_hour === (int)$hour && $current_minute === (int)$minute;
            
        case 'weekly':
            $day_of_week = $trigger_config['day_of_week'] ?? '0';
            $time = $trigger_config['time'] ?? '00:00';
            list($hour, $minute) = explode(':', $time);
            return $current_dow === (int)$day_of_week 
                && $current_hour === (int)$hour 
                && $current_minute === (int)$minute;
            
        case 'monthly':
            $day_of_month = $trigger_config['day_of_month'] ?? 1;
            $time = $trigger_config['time'] ?? '00:00';
            list($hour, $minute) = explode(':', $time);
            return $current_day === (int)$day_of_month 
                && $current_hour === (int)$hour 
                && $current_minute === (int)$minute;
            
        case 'custom':
            // Basic cron expression parsing (minute hour day month day-of-week)
            $cron = $trigger_config['cron_expression'] ?? '';
            if (empty($cron)) {
                return false;
            }
            return parse_cron_expression($cron, $now);
            
        default:
            return false;
    }
}

/**
 * Parse a simple cron expression
 * 
 * @param string $expression Cron expression (e.g., "0 9 * * *")
 * @param int $timestamp Current timestamp
 * @return bool True if matches
 */
function parse_cron_expression($expression, $timestamp) {
    $parts = preg_split('/\s+/', trim($expression));
    if (count($parts) !== 5) {
        return false;
    }
    
    list($minute, $hour, $day, $month, $dow) = $parts;
    
    $current_minute = (int)date('i', $timestamp);
    $current_hour = (int)date('H', $timestamp);
    $current_day = (int)date('d', $timestamp);
    $current_month = (int)date('m', $timestamp);
    $current_dow = (int)date('w', $timestamp);
    
    $match_minute = ($minute === '*' || $minute == $current_minute);
    $match_hour = ($hour === '*' || $hour == $current_hour);
    $match_day = ($day === '*' || $day == $current_day);
    $match_month = ($month === '*' || $month == $current_month);
    $match_dow = ($dow === '*' || $dow == $current_dow);
    
    return $match_minute && $match_hour && $match_day && $match_month && $match_dow;
}
