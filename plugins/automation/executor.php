<?php
/**
 * Automation Execution Engine for Birkly
 * 
 * Executes automations when triggers fire.
 */

require_once __DIR__ . '/automation.php';
require_once __DIR__ . '/registry.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/security.php';
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
        $trigger_config = $trigger['config'] ?? [];
        if (!empty($trigger_config['collection'])) {
            $wanted = is_array($trigger_config['collection'])
                ? (string) ($trigger_config['collection']['slug'] ?? $trigger_config['collection']['id'] ?? '')
                : (string) $trigger_config['collection'];
            $collection = (string) ($context['collection'] ?? $context['entry']['collection'] ?? $context['parent_collection'] ?? '');
            if ($wanted !== '' && $collection !== $wanted) {
                continue;
            }
        }

        // Sub-entry triggers: also check sub_collection filter
        if (in_array($trigger_type, ['sub_entry_created', 'sub_entry_updated', 'sub_entry_deleted'])) {
            if (!empty($trigger_config['sub_collection'])) {
                $sub_collection = $context['sub_collection'] ?? '';
                if ($sub_collection !== $trigger_config['sub_collection']) {
                    continue;
                }
            }
        }

        // Legacy: check collection stored directly on trigger root (backwards compat)
        if (empty($trigger_config['collection']) && isset($trigger['collection']) && !empty($trigger['collection'])) {
            $collection = $context['collection'] ?? $context['entry']['collection'] ?? '';
            if ($collection !== $trigger['collection']) {
                continue;
            }
        }

        $trigger_block = get_automation_block('trigger', $trigger_type);
        $trigger_handler = $trigger_block['handler'] ?? null;
        if (is_callable($trigger_handler)) {
            try {
                if (!call_user_func($trigger_handler, $trigger_config, $context)) {
                    continue;
                }
            } catch (Exception $e) {
                error_log('[Automation] Trigger handler error: ' . $e->getMessage());
                continue;
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
    $stop_on_failure = $automation['stop_on_failure'] ?? false;
    $is_test_mode = !empty($context['_test_mode']);
    $is_dry_run = !empty($context['_dry_run']);

    // Loop prevention: check if this automation was triggered by another automation
    $origin_automation = $context['origin_automation'] ?? null;
    if ($origin_automation === $automation_id) {
        error_log("[Automation] Loop prevention: Automation $automation_id triggered itself");
        return false;
    }

    // Rate limiting check. Test/dry-run paths must be side-effect free.
    if (!$is_test_mode && !$is_dry_run && !check_automation_rate_limit($automation_id)) {
        error_log("[Automation] Rate limit exceeded for automation $automation_id");
        return false;
    }

    $results = [];
    $errors = [];
    $graph = is_array($automation['graph'] ?? null) ? $automation['graph'] : null;
    $use_graph = is_array($graph)
        && !empty($graph['nodes'])
        && is_array($graph['nodes']);

    if ($use_graph) {
        $walk = execute_automation_graph($automation, $context, $results, $errors, $stop_on_failure, $is_test_mode, $is_dry_run);
        $conditions_met = $walk['conditions_met'];
    } else {
        $conditions_met = execute_automation_linear($automation, $context, $results, $errors, $stop_on_failure, $is_test_mode, $is_dry_run);
    }

    if (!$conditions_met) {
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

    if (!$is_test_mode && function_exists('log_automation_execution')) {
        $runtime = (microtime(true) - $start_time) * 1000;
        log_automation_execution($automation_id, array_merge($context, [
            'automation_name' => $automation['name'] ?? '',
            'conditions_met' => true,
            'runtime_ms' => $runtime
        ]), $results, $errors);
    }

    return empty($errors);
}

/**
 * Legacy linear path: AND conditions, then all actions.
 */
function execute_automation_linear($automation, $context, &$results, &$errors, $stop_on_failure, $is_test_mode, $is_dry_run): bool
{
    $conditions = $automation['conditions'] ?? [];
    $actions = $automation['actions'] ?? [];
    $conditions_met = true;

    if (empty($context['_skip_conditions'])) {
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
                    break;
                }
            } catch (Exception $e) {
                $errors[] = "Condition error: " . $e->getMessage();
                $conditions_met = false;
                break;
            }
        }
    }

    if (!$conditions_met) {
        return false;
    }

    usort($actions, function ($a, $b) {
        $priority_a = $a['priority'] ?? $a['config']['priority'] ?? 10;
        $priority_b = $b['priority'] ?? $b['config']['priority'] ?? 10;
        return $priority_b <=> $priority_a;
    });

    foreach ($actions as $action) {
        execute_automation_action_step($action, $context, $automation['id'] ?? '', $results, $errors, $stop_on_failure, $is_test_mode, $is_dry_run);
        if ($stop_on_failure && !empty($errors)) {
            break;
        }
    }

    return true;
}

/**
 * Graph path: walk edges; condition nodes branch on true/false.
 *
 * @return array{conditions_met: bool}
 */
function execute_automation_graph($automation, $context, &$results, &$errors, $stop_on_failure, $is_test_mode, $is_dry_run): array
{
    $graph = $automation['graph'];
    $nodes = [];
    foreach ($graph['nodes'] ?? [] as $node) {
        if (!empty($node['id'])) {
            $nodes[(string) $node['id']] = $node;
        }
    }

    $outgoing = [];
    foreach ($graph['edges'] ?? [] as $edge) {
        $from = (string) ($edge['from'] ?? '');
        if ($from === '' || !isset($nodes[$from])) {
            continue;
        }
        $outgoing[$from][] = [
            'to' => (string) ($edge['to'] ?? ''),
            'branch' => (string) ($edge['branch'] ?? 'default'),
        ];
    }

    $start = null;
    foreach ($nodes as $id => $node) {
        if (($node['kind'] ?? '') === 'trigger') {
            $start = $id;
            break;
        }
    }
    if ($start === null) {
        $errors[] = 'Graph has no trigger node';
        return ['conditions_met' => false];
    }

    $current = $start;
    $visited = [];
    $conditions_met = true;
    $ran_action = false;
    $guard = 0;

    while ($current !== null && $guard < 200) {
        $guard++;
        if (isset($visited[$current])) {
            $errors[] = 'Graph cycle detected during execution';
            break;
        }
        $visited[$current] = true;

        $node = $nodes[$current] ?? null;
        if (!$node) {
            break;
        }

        $kind = $node['kind'] ?? '';
        $type = $node['type'] ?? '';
        $config = is_array($node['config'] ?? null) ? $node['config'] : [];

        if ($kind === 'condition') {
            if (!empty($context['_skip_conditions'])) {
                $passed = true;
            } else {
                $block = get_automation_block('condition', $type);
                $handler = $block['handler'] ?? null;
                if (!$block || !is_callable($handler)) {
                    $errors[] = "Unknown condition type: $type";
                    $conditions_met = false;
                    break;
                }
                try {
                    $passed = (bool) call_user_func($handler, $config, $context);
                } catch (Exception $e) {
                    $errors[] = 'Condition error: ' . $e->getMessage();
                    $conditions_met = false;
                    break;
                }
            }

            $branch = $passed ? 'true' : 'false';
            $next = automation_graph_next_edge($outgoing[$current] ?? [], $branch);
            if ($next === null && !$passed) {
                // No else path — treat as stop (conditions not leading to actions).
                $conditions_met = $ran_action ? true : false;
                break;
            }
            $current = $next;
            continue;
        }

        if ($kind === 'action') {
            execute_automation_action_step(
                ['type' => $type, 'config' => $config],
                $context,
                $automation['id'] ?? '',
                $results,
                $errors,
                $stop_on_failure,
                $is_test_mode,
                $is_dry_run
            );
            $ran_action = true;
            if ($stop_on_failure && !empty($errors)) {
                break;
            }
        }

        // trigger or action: follow default (or true) edge
        $current = automation_graph_next_edge($outgoing[$current] ?? [], 'default');
    }

    if (!$ran_action && empty($errors)) {
        // Graph ended without actions (e.g. else path missing) — not a hard failure if intentional.
        $conditions_met = false;
    }

    return ['conditions_met' => $conditions_met || $ran_action];
}

/**
 * @param array<int, array{to:string,branch:string}> $edges
 */
function automation_graph_next_edge(array $edges, string $preferredBranch): ?string
{
    foreach ($edges as $edge) {
        if (($edge['branch'] ?? 'default') === $preferredBranch && ($edge['to'] ?? '') !== '') {
            return $edge['to'];
        }
    }
    if ($preferredBranch === 'true' || $preferredBranch === 'default') {
        foreach ($edges as $edge) {
            $branch = $edge['branch'] ?? 'default';
            if (($branch === 'default' || $branch === 'true') && ($edge['to'] ?? '') !== '') {
                return $edge['to'];
            }
        }
    }
    return null;
}

function execute_automation_action_step($action, $context, $automation_id, &$results, &$errors, $stop_on_failure, $is_test_mode, $is_dry_run): void
{
    $action_type = $action['type'] ?? '';
    $action_config = $action['config'] ?? [];

    $block = get_automation_block('action', $action_type);
    if (!$block) {
        $errors[] = "Unknown action type: $action_type";
        return;
    }

    $handler = $block['handler'] ?? null;
    if (!$handler || !is_callable($handler)) {
        $errors[] = "Invalid action handler: $action_type";
        return;
    }

    try {
        $action_context = array_merge($context, [
            'origin_automation' => $automation_id
        ]);

        if ($is_test_mode || $is_dry_run) {
            automation_validate_action_config($block, $action_config, $action_type);
            $detail = 'Would execute action "' . $action_type . '" (not executed - dry run)';
            if (!function_exists('automation_describe_dry_run_action')) {
                $setupHealth = __DIR__ . '/includes/setup_health.php';
                if (is_file($setupHealth)) {
                    require_once $setupHealth;
                }
            }
            if (function_exists('automation_describe_dry_run_action')) {
                $detail = automation_describe_dry_run_action($action_type, $action_config, $action_context);
            }
            $action_result = [
                'simulated' => true,
                'action_type' => $action_type,
                'detail' => $detail,
                'would_execute' => false,
            ];
        } else {
            $action_result = call_user_func($handler, $action_config, $action_context);
        }

        $results[] = [
            'action_type' => $action_type,
            'success' => true,
            'result' => $action_result
        ];
    } catch (Exception $e) {
        $error_msg = 'Action error: ' . $e->getMessage();
        $errors[] = $error_msg;
        $results[] = [
            'action_type' => $action_type,
            'success' => false,
            'error' => $error_msg
        ];
    }
}

/**
 * Validate required action config without executing side effects.
 *
 * @param array $block Registered action block metadata
 * @param array $config Action config
 * @param string $action_type Action identifier
 * @throws Exception
 */
function automation_validate_action_config($block, $config, $action_type) {
    $schema = $block['schema'] ?? [];
    foreach ($schema as $field => $field_schema) {
        if (!empty($field_schema['required']) && trim((string)($config[$field] ?? '')) === '') {
            throw new Exception("Action configuration field '{$field}' is required for {$action_type}");
        }
    }
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
