<?php
/**
 * Core Automation Conditions for Birkly
 * 
 * Defines built-in condition blocks.
 */

require_once __DIR__ . '/../registry.php';
require_once __DIR__ . '/../executor.php';

// Field Match Condition
register_automation_block('condition', 'field_match', [
    'name' => 'Field Matches',
    'description' => 'Check if a field matches a value',
    'category' => 'Content',
    'schema' => [
        'field' => [
            'type' => 'string',
            'label' => 'Field Name',
            'required' => true
        ],
        'operator' => [
            'type' => 'select',
            'label' => 'Operator',
            'options' => [
                ['value' => 'equals', 'label' => 'Equals'],
                ['value' => 'contains', 'label' => 'Contains'],
                ['value' => 'greater_than', 'label' => 'Greater Than'],
                ['value' => 'less_than', 'label' => 'Less Than'],
                ['value' => 'not_equals', 'label' => 'Not Equals']
            ],
            'default' => 'equals'
        ],
        'value' => [
            'type' => 'string',
            'label' => 'Value',
            'required' => true
        ]
    ],
    'handler' => function($config, $context) {
        $field = $config['field'] ?? '';
        $operator = $config['operator'] ?? 'equals';
        $value = $config['value'] ?? '';
        
        if (empty($field)) {
            return false;
        }
        
        $entry = $context['entry'] ?? [];
        $field_value = $entry[$field] ?? '';
        
        switch ($operator) {
            case 'equals':
                return $field_value == $value;
            case 'not_equals':
                return $field_value != $value;
            case 'contains':
                return is_string($field_value) && strpos($field_value, $value) !== false;
            case 'greater_than':
                return is_numeric($field_value) && is_numeric($value) && $field_value > $value;
            case 'less_than':
                return is_numeric($field_value) && is_numeric($value) && $field_value < $value;
            default:
                return false;
        }
    }
]);

// Status Check Condition
register_automation_block('condition', 'status_check', [
    'name' => 'Status Is',
    'description' => 'Check if entry has a specific status',
    'category' => 'Content',
    'schema' => [
        'status' => [
            'type' => 'select',
            'label' => 'Status',
            'options' => [
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'published', 'label' => 'Published'],
                ['value' => 'pending_review', 'label' => 'Pending Review'],
                ['value' => 'approved', 'label' => 'Approved'],
                ['value' => 'rejected', 'label' => 'Rejected']
            ],
            'required' => true
        ]
    ],
    'handler' => function($config, $context) {
        $status = $config['status'] ?? '';
        $entry = $context['entry'] ?? [];
        $entry_status = $entry['status'] ?? 'draft';
        
        return $entry_status === $status;
    }
]);

// Collection Filter Condition
register_automation_block('condition', 'collection_filter', [
    'name' => 'Collection Is',
    'description' => 'Check if entry belongs to a specific collection',
    'category' => 'Content',
    'schema' => [
        'collection' => [
            'type' => 'collection_picker',
            'label' => 'Collection',
            'required' => true,
            'description' => 'Choose which collection this condition checks'
        ]
    ],
    'handler' => function($config, $context) {
        $collection = $config['collection'] ?? '';
        $context_collection = $context['collection'] ?? $context['entry']['collection'] ?? '';
        
        return $context_collection === $collection;
    }
]);

// Regex Match Condition
register_automation_block('condition', 'regex_match', [
    'name' => 'Field Matches Regex',
    'description' => 'Check if a field matches a regular expression pattern',
    'category' => 'Content',
    'schema' => [
        'field' => [
            'type' => 'string',
            'label' => 'Field Name',
            'required' => true
        ],
        'pattern' => [
            'type' => 'string',
            'label' => 'Regex Pattern',
            'required' => true
        ]
    ],
    'handler' => function($config, $context) {
        $field = $config['field'] ?? '';
        $pattern = $config['pattern'] ?? '';
        
        if (empty($field) || empty($pattern)) {
            return false;
        }
        
        $entry = $context['entry'] ?? [];
        $field_value = (string)($entry[$field] ?? '');
        
        return preg_match($pattern, $field_value) === 1;
    }
]);

// Rate Limit Guard Condition
register_automation_block('condition', 'rate_limit_guard', [
    'name' => 'Rate Limit Check',
    'description' => 'Check if rate limit has not been exceeded',
    'category' => 'System',
    'schema' => [
        'limit' => [
            'type' => 'number',
            'label' => 'Maximum Executions',
            'required' => true
        ],
        'period' => [
            'type' => 'select',
            'label' => 'Time Period',
            'options' => [
                ['value' => 'hour', 'label' => 'Per Hour'],
                ['value' => 'day', 'label' => 'Per Day'],
                ['value' => 'week', 'label' => 'Per Week']
            ],
            'default' => 'hour'
        ],
        'key' => [
            'type' => 'string',
            'label' => 'Rate Limit Key',
            'description' => 'Optional key to group rate limits (e.g., by collection, user, etc.)',
            'placeholder' => '{{collection}}'
        ]
    ],
    'handler' => function($config, $context) {
        if (!empty($context['_dry_run']) || !empty($context['_test_mode'])) {
            return true;
        }

        $limit = (int)($config['limit'] ?? 100);
        $key_template = $config['key'] ?? '';
        
        // Generate rate limit key
        $key = 'automation_rate_limit';
        if ($key_template) {
            $key .= '_' . md5(render_automation_template($key_template, $context));
        }
        
        // Check rate limit
        if (file_exists(__DIR__ . '/../rate_limit.php')) {
            require_once __DIR__ . '/../rate_limit.php';
            return check_automation_rate_limit($key, $limit);
        }
        
        // Fallback: always allow if rate limit system not available
        return true;
    }
]);

// JSON Path Condition (basic implementation)
register_automation_block('condition', 'json_path', [
    'name' => 'JSON Path Check',
    'description' => 'Check a value using JSON path (e.g., entry.meta.tags)',
    'category' => 'Content',
    'schema' => [
        'path' => [
            'type' => 'string',
            'label' => 'JSON Path',
            'description' => 'Dot-notation path (e.g., entry.meta.tags)',
            'required' => true
        ],
        'operator' => [
            'type' => 'select',
            'label' => 'Operator',
            'options' => [
                ['value' => 'exists', 'label' => 'Exists'],
                ['value' => 'equals', 'label' => 'Equals'],
                ['value' => 'contains', 'label' => 'Contains']
            ],
            'default' => 'exists'
        ],
        'value' => [
            'type' => 'string',
            'label' => 'Value (for equals/contains)'
        ]
    ],
    'handler' => function($config, $context) {
        $path = $config['path'] ?? '';
        $operator = $config['operator'] ?? 'exists';
        $value = $config['value'] ?? '';
        
        if (empty($path)) {
            return false;
        }
        
        // Get value using nested path
        $path_value = get_nested_value($context, $path);
        
        switch ($operator) {
            case 'exists':
                return $path_value !== '';
            case 'equals':
                return $path_value == $value;
            case 'contains':
                return is_string($path_value) && strpos($path_value, $value) !== false;
            default:
                return false;
        }
    }
]);
