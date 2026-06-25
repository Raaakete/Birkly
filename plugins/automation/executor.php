<?php
/**
 * Automation Execution Engine for Birkly
 * 
 * Executes automations when triggers fire.
 */

require_once __DIR__ . '/automation.php';
require_once __DIR__ . '/registry.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/blocks/triggers.php';
require_once __DIR__ . '/blocks/conditions.php';
require_once __DIR__ . '/blocks/actions.php';

/**
 * Execute automations for a trigger
 * 
 * @param string $trigger_type Trigger type (e.g., 'entry_created')
 * @param array $context Trigger context data
 * @return int Number of automations executed
 */
function execute_automations($trigger_type, $context = []) {
    // Enrich context with plugin data
    if (function_exists('enrich_automation_context')) {
        $context = enrich_automation_context($context);
    }
    
    // Get all enabled automations that match this trigger
    $automations = get_automations([
        'enabled' => true
    ]);
    
    $executed_count = 0;
    
    foreach ($automations as $automation) {
        $trigger = $automation['trigger'] ?? [];
        $trigger_config = $trigger['type'] ?? '';
        
        // Check if automation matches this trigger
        if ($trigger_config !== $trigger_type) {
            continue;
        }
        
        // Special handling for scheduled triggers
        if ($trigger_type === 'scheduled') {
            // For scheduled triggers, check if this is the right automation
            $context_automation_id = $context['automation_id'] ?? '';
            if ($context_automation_id && $context_automation_id !== $automation['id']) {
                continue; // Skip if not the automation that was scheduled
            }
        }
        
        // Special handling for webhook inbound triggers
        if ($trigger_type === 'webhook_inbound') {
            $webhook_id = $trigger['config']['webhook_id'] ?? '';
            $context_webhook_id = $context['webhook_id'] ?? '';
            if ($webhook_id !== $context_webhook_id) {
                continue; // Skip if webhook ID doesn't match
            }
        }
        
        // Special handling for plugin event triggers
        if ($trigger_type === 'plugin_event') {
            $event_name = $trigger['config']['event_name'] ?? '';
            $context_event = $context['plugin_event'] ?? '';
            if ($event_name !== $context_event) {
                continue; // Skip if event name doesn't match
            }
        }
        
        // Check collection filter if specified
        if (isset($trigger['collection']) && !empty($trigger['collection'])) {
            $collection = $context['collection'] ?? $context['entry']['collection'] ?? '';
            if ($collection !== $trigger['collection']) {
                continue; // Skip if collection doesn't match
            }
        }
        
        // Execute automation
        $result = execute_automation($automation, $context);
        if ($result) {
            $executed_count++;
        }
    }
    
    return $executed_count;
}

/**
 * Execute a single automation
 * 
 * @param array $automation Automation definition
 * @param array $context Execution context
 * @return bool True if executed successfully
 */
function execute_automation($automation, $context = []) {
    $start_time = microtime(true);
    $automation_id = $automation['id'] ?? '';
    $conditions = $automation['conditions'] ?? [];
    $actions = $automation['actions'] ?? [];
    $stop_on_failure = $automation['stop_on_failure'] ?? false;
    
    // Loop prevention: check if this automation was triggered by another automation
    $origin_automation = $context['origin_automation'] ?? null;
    if ($origin_automation === $automation_id) {
        // Prevent infinite loops
        error_log("[Automation] Loop prevention: Automation $automation_id triggered itself");
        return false;
    }
    
    // Rate limiting check
    if (!check_automation_rate_limit($automation_id)) {
        error_log("[Automation] Rate limit exceeded for automation $automation_id");
        return false;
    }
    
    $results = [];
    $errors = [];
    
    // Evaluate conditions
    $conditions_met = true;
    foreach ($conditions as $condition) {
        $condition_type = $condition['type'] ?? '';
        $condition_config = $condition['config'] ?? [];
        
        $block = get_automation_block('condition', $condition_type);
        if (!$block) {
            $errors[] = "Unknown condition type: $condition_type";
            $conditions_met = false;
            break;
        }
        
        $handler = $block['handler'] ?? null;
        if (!$handler || !is_callable($handler)) {
            $errors[] = "Invalid condition handler: $condition_type";
            $conditions_met = false;
            break;
        }
        
        try {
            $result = call_user_func($handler, $condition_config, $context);
            if (!$result) {
                $conditions_met = false;
                break; // Condition not met, skip this automation
            }
        } catch (Exception $e) {
            $errors[] = "Condition error: " . $e->getMessage();
            $conditions_met = false;
            break;
        }
    }
    
    // If conditions not met, don't execute actions
    if (!$conditions_met) {
        $is_test_mode = $context['_test_mode'] ?? false;
        if (!$is_test_mode && function_exists('log_automation_execution')) {
            $runtime = (microtime(true) - $start_time) * 1000;
            log_automation_execution($automation_id, array_merge($context, [
                'automation_name' => $automation['name'] ?? '',
                'conditions_met' => false,
                'runtime_ms' => $runtime
            ]), $results, $errors);
        }
        return false;
    }
    
    // Sort actions by priority (if specified)
    usort($actions, function($a, $b) {
        $priority_a = $a['priority'] ?? $a['config']['priority'] ?? 10;
        $priority_b = $b['priority'] ?? $b['config']['priority'] ?? 10;
        return $priority_b <=> $priority_a; // Higher priority first
    });
    
    // Execute actions
    foreach ($actions as $action) {
        $action_type = $action['type'] ?? '';
        $action_config = $action['config'] ?? [];
        
        $block = get_automation_block('action', $action_type);
        if (!$block) {
            $errors[] = "Unknown action type: $action_type";
            if ($stop_on_failure) {
                break;
            }
            continue;
        }
        
        $handler = $block['handler'] ?? null;
        if (!$handler || !is_callable($handler)) {
            $errors[] = "Invalid action handler: $action_type";
            if ($stop_on_failure) {
                break;
            }
            continue;
        }
        
        try {
            // Add origin automation to context to prevent loops
            $action_context = array_merge($context, [
                'origin_automation' => $automation_id
            ]);
            
            // In test mode, skip actual execution for certain actions
            $is_test_mode = $context['_test_mode'] ?? false;
            $is_dry_run = $context['_dry_run'] ?? false;
            
            if ($is_test_mode || $is_dry_run) {
                // For test mode, simulate action execution
                // Skip actual email/webhook sends, but validate configuration
                if (in_array($action_type, ['email', 'webhook', 'slack', 'teams'])) {
                    // Validate configuration without sending
                    if (empty($action_config)) {
                        throw new Exception("Action configuration is required for {$action_type}");
                    }
                    $action_result = ['simulated' => true, 'action_type' => $action_type];
                } else {
                    // For other actions, execute normally (they're safe)
                    $action_result = call_user_func($handler, $action_config, $action_context);
                }
            } else {
                // Normal execution
                $action_result = call_user_func($handler, $action_config, $action_context);
            }
            
            $results[] = [
                'action_type' => $action_type,
                'success' => true,
                'result' => $action_result
            ];
        } catch (Exception $e) {
            $error_msg = "Action error: " . $e->getMessage();
            $errors[] = $error_msg;
            $results[] = [
                'action_type' => $action_type,
                'success' => false,
                'error' => $error_msg
            ];
            
            if ($stop_on_failure) {
                break; // Stop executing remaining actions
            }
        }
    }
    
    // Log execution (skip in test mode)
    $is_test_mode = $context['_test_mode'] ?? false;
    if (!$is_test_mode && function_exists('log_automation_execution')) {
        $runtime = (microtime(true) - $start_time) * 1000;
        log_automation_execution($automation_id, array_merge($context, [
            'automation_name' => $automation['name'] ?? '',
            'conditions_met' => true,
            'runtime_ms' => $runtime
        ]), $results, $errors);
    }
    
    return true;
}

/**
 * Render template with context variables
 * 
 * @param string $template Template string with {{variable}} placeholders
 * @param array $context Context data
 * @return string Rendered template
 */
function render_automation_template($template, $context = []) {
    // Extract variables from template
    preg_match_all('/\{\{([^}]+)\}\}/', $template, $matches);
    
    $rendered = $template;
    
    foreach ($matches[1] as $var) {
        $var = trim($var);
        $value = get_nested_value($context, $var);
        $rendered = str_replace('{{' . $var . '}}', $value, $rendered);
    }
    
    return $rendered;
}

/**
 * Get nested value from array using dot notation
 * 
 * @param array $array Array to search
 * @param string $path Dot-notation path (e.g., 'entry.title')
 * @return mixed Value or empty string if not found
 */
function get_nested_value($array, $path) {
    $keys = explode('.', $path);
    $value = $array;
    
    foreach ($keys as $key) {
        if (is_array($value) && isset($value[$key])) {
            $value = $value[$key];
        } else {
            return '';
        }
    }
    
    return is_scalar($value) ? $value : json_encode($value);
}
