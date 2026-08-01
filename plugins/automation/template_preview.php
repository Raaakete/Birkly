<?php
/**
 * Automation Template Preview for Birkly
 * 
 * Provides template preview functionality for automation templates.
 */

require_once __DIR__ . '/executor.php';

/**
 * Preview a template with sample data
 * 
 * @param string $template Template string with variables
 * @param array $sample_context Sample context data (optional)
 * @return array Result with 'rendered' (string) and 'variables' (array)
 */
function preview_automation_template($template, $sample_context = null) {
    // Generate sample context if not provided
    if ($sample_context === null) {
        $sample_context = generate_sample_automation_context();
    }
    
    // Render template
    $rendered = render_automation_template($template, $sample_context);
    
    // Extract variables from template
    $variables = extract_template_variables($template);
    
    return [
        'rendered' => $rendered,
        'variables' => $variables,
        'sample_context' => $sample_context
    ];
}

/**
 * Generate sample context for template preview
 * 
 * @param string $trigger_type Optional trigger type to generate context for
 * @return array Sample context
 */
function generate_sample_automation_context($trigger_type = null) {
    $context = [
        'entry' => [
            'id' => 'sample_entry_123',
            'title' => 'Sample Entry Title',
            'slug' => 'sample-entry',
            'status' => 'published',
            'collection' => 'blog',
            'created_at' => date('c', strtotime('-5 days')),
            'updated_at' => date('c', strtotime('-1 day')),
            'author' => 'John Doe',
            'author_email' => 'john@example.com',
            'content' => 'This is sample content for the entry.',
            'tags' => ['sample', 'preview', 'test']
        ],
        'collection' => 'blog',
        'user' => [
            'id' => 'user_456',
            'email' => 'user@example.com',
            'name' => 'Jane User'
        ],
        'timestamp' => date('c'),
        'now' => date('Y-m-d H:i:s'),
        'site' => [
            'name' => 'Sample Site',
            'url' => 'https://example.com'
        ],
        'automation' => [
            'id' => 'sample_automation',
            'name' => 'Sample Automation',
        ],
        'automation_name' => 'Sample Automation',
    ];
    
    // Add trigger-specific context
    switch ($trigger_type) {
        case 'entry_created':
            $context['action'] = 'created';
            break;
        case 'entry_updated':
            $context['action'] = 'updated';
            $context['changes'] = [
                'title' => ['old' => 'Old Title', 'new' => 'New Title'],
                'status' => ['old' => 'draft', 'new' => 'published']
            ];
            break;
        case 'entry_deleted':
            $context['action'] = 'deleted';
            break;
        case 'status_changed':
            $context['old_status'] = 'draft';
            $context['new_status'] = 'published';
            $context['from_status'] = 'draft';
            $context['to_status'] = 'published';
            break;
        case 'webhook_inbound':
            $context['webhook_id'] = 'sample_webhook';
            $context['webhook_name'] = 'Sample Webhook';
            $context['payload'] = [
                'event' => 'sample_event',
                'data' => ['key' => 'value']
            ];
            break;
    }

    if (!function_exists('automation_enrich_execution_context')) {
        $contextFile = __DIR__ . '/includes/context.php';
        if (is_file($contextFile)) {
            require_once $contextFile;
        }
    }
    if (function_exists('automation_enrich_execution_context')) {
        $context = automation_enrich_execution_context($context, [
            'id' => 'sample_automation',
            'name' => 'Sample Automation',
            'trigger' => ['type' => $trigger_type ?: 'entry_created'],
        ], $trigger_type ?: 'entry_created');
    }
    
    return $context;
}

/**
 * Extract variables from template string
 * 
 * @param string $template Template string
 * @return array Array of variable names found
 */
function extract_template_variables($template) {
    $variables = [];
    
    // Match {{variable}} or {{variable.path}} patterns
    preg_match_all('/\{\{([^}]+)\}\}/', $template, $matches);
    
    if (!empty($matches[1])) {
        foreach ($matches[1] as $match) {
            $var = trim($match);
            if (!in_array($var, $variables)) {
                $variables[] = $var;
            }
        }
    }
    
    return $variables;
}

/**
 * Validate template syntax
 * 
 * @param string $template Template string
 * @return array Result with 'valid' (bool) and 'errors' (array)
 */
function validate_automation_template($template) {
    $errors = [];
    
    // Check for unmatched braces
    $open_braces = substr_count($template, '{{');
    $close_braces = substr_count($template, '}}');
    
    if ($open_braces !== $close_braces) {
        $errors[] = 'Unmatched template braces ({{ and }})';
    }
    
    // Check for nested braces (not supported)
    if (preg_match('/\{\{[^}]*\{\{/', $template)) {
        $errors[] = 'Nested template variables are not supported';
    }
    
    // Extract and validate variable names
    $variables = extract_template_variables($template);
    foreach ($variables as $var) {
        $var = trim($var);
        // Basic validation: should be alphanumeric with dots and underscores
        if (!preg_match('/^[a-zA-Z0-9_.]+$/', $var)) {
            $errors[] = "Invalid variable name: {$var}";
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'variables' => $variables
    ];
}

/**
 * Get available context variables for a trigger type
 * 
 * @param string $trigger_type Trigger type
 * @return array Available variables with descriptions
 */
function get_automation_context_variables($trigger_type = null) {
    $variables = [
        'entry' => [
            'description' => 'The entry object',
            'fields' => [
                'entry.id' => 'Entry ID',
                'entry.title' => 'Entry title',
                'entry.slug' => 'Entry slug',
                'entry.status' => 'Entry status',
                'entry.collection' => 'Collection slug',
                'entry.created_at' => 'Creation timestamp',
                'entry.updated_at' => 'Last update timestamp',
                'entry.author' => 'Author name',
                'entry.author_email' => 'Author email'
            ]
        ],
        'collection' => [
            'description' => 'Collection slug',
            'type' => 'string'
        ],
        'automation' => [
            'description' => 'The running workflow',
            'fields' => [
                'automation.name' => 'Automation name (good email subject)',
                'automation.id' => 'Automation id'
            ]
        ],
        'trigger_summary' => [
            'description' => 'Human summary, e.g. New entry in "blog": Title',
            'type' => 'string'
        ],
        'entry_admin_url' => [
            'description' => 'Admin link to open the entry in Birkly',
            'type' => 'string'
        ],
        'entry_url' => [
            'description' => 'Alias of entry_admin_url',
            'type' => 'string'
        ],
        'user' => [
            'description' => 'Current user object',
            'fields' => [
                'user.id' => 'User ID',
                'user.email' => 'User email',
                'user.name' => 'User name'
            ]
        ],
        'timestamp' => [
            'description' => 'Current timestamp (ISO 8601)',
            'type' => 'string'
        ],
        'now' => [
            'description' => 'Current local date/time',
            'type' => 'string'
        ],
        'site' => [
            'description' => 'Site information',
            'fields' => [
                'site.name' => 'Site name',
                'site.url' => 'Site URL'
            ]
        ]
    ];
    
    // Add trigger-specific variables
    switch ($trigger_type) {
        case 'entry_updated':
            $variables['changes'] = [
                'description' => 'Changes made to the entry',
                'type' => 'object'
            ];
            break;
        case 'status_changed':
            $variables['old_status'] = [
                'description' => 'Previous status',
                'type' => 'string'
            ];
            $variables['new_status'] = [
                'description' => 'New status',
                'type' => 'string'
            ];
            break;
        case 'webhook_inbound':
            $variables['webhook_id'] = [
                'description' => 'Webhook ID',
                'type' => 'string'
            ];
            $variables['webhook_name'] = [
                'description' => 'Webhook name',
                'type' => 'string'
            ];
            $variables['payload'] = [
                'description' => 'Webhook payload data',
                'type' => 'object'
            ];
            break;
    }
    
    return $variables;
}
