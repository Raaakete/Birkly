<?php
/**
 * Webhook Inbound Automation Triggers for Birkly
 * 
 * Defines webhook inbound trigger blocks.
 */

require_once __DIR__ . '/../registry.php';

// Webhook Inbound Trigger
register_automation_block('trigger', 'webhook_inbound', [
    'name' => 'Webhook Inbound',
    'description' => 'Fires when a webhook is received from an external system',
    'category' => 'Integration',
    'schema' => [
        'webhook_id' => [
            'type' => 'string',
            'label' => 'Webhook ID',
            'description' => 'The webhook ID to listen for',
            'required' => true
        ],
        'filter_field' => [
            'type' => 'string',
            'label' => 'Filter Field (optional)',
            'description' => 'Field name to filter on in webhook payload'
        ],
        'filter_value' => [
            'type' => 'string',
            'label' => 'Filter Value (optional)',
            'description' => 'Value to match in filter field'
        ]
    ],
    'handler' => function($config, $context) {
        $webhook_id = $config['webhook_id'] ?? '';
        $context_webhook_id = $context['webhook_id'] ?? '';
        
        // Check webhook ID match
        if ($webhook_id !== $context_webhook_id) {
            return false;
        }
        
        // Check field filter if specified
        $filter_field = $config['filter_field'] ?? '';
        $filter_value = $config['filter_value'] ?? '';
        
        if ($filter_field && $filter_value) {
            $field_value = automation_webhook_nested_value($context, $filter_field);
            if ($field_value !== $filter_value) {
                return false;
            }
        }
        
        return true;
    }
]);

function automation_webhook_nested_value(array $context, string $path): string
{
    $keys = explode('.', $path);
    $value = $context;

    foreach ($keys as $key) {
        if (is_array($value) && array_key_exists($key, $value)) {
            $value = $value[$key];
            continue;
        }
        return '';
    }

    return is_scalar($value) ? (string) $value : json_encode($value);
}
