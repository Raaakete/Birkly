<?php
/**
 * Automation System Core for Birkly
 * 
 * Handles automation definitions, storage, and basic execution.
 */

require_once __DIR__ . '/../../core/storage/StorageFactory.php';
require_once __DIR__ . '/../../core/plugins.php';

function automation_dashboard_user_can_view(): bool
{
    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        return false;
    }

    $role = (string) ($_SESSION['user']['role'] ?? '');
    if (in_array($role, ['admin', 'superadmin'], true)) {
        return true;
    }

    $capabilities = $_SESSION['user']['capabilities']
        ?? $_SESSION['user']['plugin_capabilities']['automation']
        ?? [];
    return is_array($capabilities) && in_array('manage_content', $capabilities, true);
}

/**
 * Widget API v2 definition for the automation activity summary.
 *
 * @return list<array<string, mixed>>
 */
function automation_dashboard_widgets(): array
{
    if (!function_exists('automation_plugin_active') || !automation_plugin_active()
        || !automation_dashboard_user_can_view()) {
        return [];
    }

    return [[
        'id' => 'automation-runs',
        'title' => 'Automation runs',
        'roles' => ['admin', 'editor'],
        'size' => 'md',
        'priority' => 90,
        'endpoint' => '/api/automation.php?action=dashboard_summary',
        'mount' => 'automationRuns',
        'refresh' => 30,
    ]];
}

/**
 * Get all automations
 * 
 * @param array $filters Optional filters (enabled, trigger_type, etc.)
 * @param bool $use_cache Whether to use cache (default: true)
 * @return array List of automations
 */
function get_automations($filters = [], $use_cache = true) {
    // Try cache first
    if ($use_cache && function_exists('get_cached_automation_list')) {
        $cached = get_cached_automation_list($filters);
        if ($cached !== null) {
            return $cached;
        }
    }
    
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $automations = $storage->read('automations.json') ?? [];
    
    // Apply filters
    if (!empty($filters)) {
        $automations = array_filter($automations, function($automation) use ($filters) {
            foreach ($filters as $key => $value) {
                if (($automation[$key] ?? null) !== $value) {
                    return false;
                }
            }
            return true;
        });
    }
    
    $result = array_values($automations);
    
    // Cache result
    if ($use_cache && function_exists('cache_automation_list')) {
        cache_automation_list($filters, $result);
    }
    
    return $result;
}

/**
 * Get a single automation by ID
 * 
 * @param string $automation_id Automation ID
 * @return array|null Automation data or null if not found
 */
function get_automation($automation_id) {
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $automations = $storage->read('automations.json') ?? [];
    
    foreach ($automations as $automation) {
        if (($automation['id'] ?? '') === $automation_id) {
            return $automation;
        }
    }
    
    return null;
}

/**
 * Create a new automation
 * 
 * @param array $data Automation data
 * @return array|false Created automation with ID, or false on failure
 */
function create_automation($data) {
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $automations = $storage->read('automations.json') ?? [];
    
    // Generate ID
    $automation_id = $data['id'] ?? uniqid('auto_', true);
    
    $automation = [
        'id' => $automation_id,
        'name' => $data['name'] ?? 'Untitled Automation',
        'enabled' => $data['enabled'] ?? true,
        'trigger' => $data['trigger'] ?? [],
        'conditions' => $data['conditions'] ?? [],
        'actions' => $data['actions'] ?? [],
        'stop_on_failure' => $data['stop_on_failure'] ?? false,
        'created_at' => date('c'),
        'updated_at' => date('c'),
        'created_by' => $_SESSION['user']['id'] ?? 'system'
    ];
    
    $automations[] = $automation;
    
    if ($storage->write('automations.json', $automations)) {
        // Invalidate cache
        if (function_exists('invalidate_automation_cache')) {
            invalidate_automation_cache();
        }
        
        // Fire hook
        do_action('automation_created', $automation);
        return $automation;
    }
    
    return false;
}

/**
 * Update an automation
 * 
 * @param string $automation_id Automation ID
 * @param array $data Updated data
 * @return array|false Updated automation or false on failure
 */
function update_automation($automation_id, $data) {
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $automations = $storage->read('automations.json') ?? [];
    
    $found = false;
    foreach ($automations as &$automation) {
        if (($automation['id'] ?? '') === $automation_id) {
            // Merge data (preserve system fields)
            $automation = array_merge($automation, $data);
            $automation['updated_at'] = date('c');
            $automation['id'] = $automation_id; // Preserve ID
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        return false;
    }
    
    if ($storage->write('automations.json', $automations)) {
        // Invalidate cache
        if (function_exists('invalidate_automation_cache')) {
            invalidate_automation_cache();
        }
        
        // Fire hook
        do_action('automation_updated', $automation_id, $automation);
        return $automation;
    }
    
    return false;
}

/**
 * Delete an automation
 * 
 * @param string $automation_id Automation ID
 * @return bool True on success
 */
function delete_automation($automation_id) {
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $automations = $storage->read('automations.json') ?? [];
    
    $automation = null;
    $filtered = [];
    foreach ($automations as $auto) {
        if (($auto['id'] ?? '') === $automation_id) {
            $automation = $auto;
        } else {
            $filtered[] = $auto;
        }
    }
    
    if ($automation === null) {
        return false;
    }
    
    if ($storage->write('automations.json', $filtered)) {
        // Invalidate cache
        if (function_exists('invalidate_automation_cache')) {
            invalidate_automation_cache();
        }
        
        // Fire hook
        do_action('automation_deleted', $automation_id, $automation);
        return true;
    }
    
    return false;
}

/**
 * Log automation execution
 * 
 * @param string $automation_id Automation ID
 * @param array $context Execution context
 * @param array $results Action results
 * @param array $errors Errors encountered
 * @return bool True on success
 */
function log_automation_execution($automation_id, $context, $results = [], $errors = []) {
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $logs = $storage->read('automation_logs.json') ?? [];
    
    $log = [
        'automation_id' => $automation_id,
        'context' => $context,
        'results' => $results,
        'errors' => $errors,
        'status' => empty($errors) ? 'success' : 'error',
        'executed_at' => date('c'),
        'runtime_ms' => $context['runtime_ms'] ?? 0
    ];
    
    $logs[] = $log;
    
    // Keep only last 10000 entries
    if (count($logs) > 10000) {
        $logs = array_slice($logs, -10000);
    }
    
    return $storage->write('automation_logs.json', $logs);
}

/**
 * Get automation execution logs
 * 
 * @param string|null $automation_id Optional automation ID to filter
 * @param int $limit Maximum number of logs to return
 * @return array List of execution logs
 */
function get_automation_logs($automation_id = null, $limit = 100) {
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $logs = $storage->read('automation_logs.json') ?? [];
    
    // Filter by automation ID if provided
    if ($automation_id) {
        $logs = array_filter($logs, function($log) use ($automation_id) {
            return ($log['automation_id'] ?? '') === $automation_id;
        });
    }
    
    // Sort by executed_at (newest first)
    usort($logs, function($a, $b) {
        $timeA = strtotime($a['executed_at'] ?? '1970-01-01');
        $timeB = strtotime($b['executed_at'] ?? '1970-01-01');
        return $timeB <=> $timeA;
    });
    
    // Limit results
    return array_slice($logs, 0, $limit);
}
