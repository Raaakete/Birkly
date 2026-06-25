<?php
/**
 * Core Automation Actions for Birkly
 * 
 * Defines built-in action blocks.
 */

require_once __DIR__ . '/../registry.php';
require_once __DIR__ . '/../executor.php';

// Email Action
register_automation_block('action', 'email', [
    'name' => 'Send Email',
    'description' => 'Send an email notification',
    'category' => 'Communication',
    'schema' => [
        'to' => [
            'type' => 'email',
            'label' => 'To Email',
            'required' => true
        ],
        'subject' => [
            'type' => 'string',
            'label' => 'Subject',
            'required' => true
        ],
        'body' => [
            'type' => 'textarea',
            'label' => 'Message Body',
            'required' => true
        ]
    ],
    'handler' => function($config, $context) {
        $to = render_automation_template($config['to'] ?? '', $context);
        $subject = render_automation_template($config['subject'] ?? '', $context);
        $body = render_automation_template($config['body'] ?? '', $context);
        
        if (empty($to) || empty($subject)) {
            throw new Exception('Email to and subject are required');
        }
        
        $headers = "From: Birkly <noreply@birkly.local>\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        $result = @mail($to, $subject, nl2br(htmlspecialchars($body)), $headers);
        
        if (!$result) {
            throw new Exception('Failed to send email');
        }
        
        return ['sent' => true, 'to' => $to];
    }
]);

// Webhook Action
register_automation_block('action', 'webhook', [
    'name' => 'Send Webhook',
    'description' => 'Send HTTP request to a webhook URL',
    'category' => 'Integration',
    'schema' => [
        'url' => [
            'type' => 'url',
            'label' => 'Webhook URL',
            'required' => true
        ],
        'method' => [
            'type' => 'select',
            'label' => 'HTTP Method',
            'options' => [
                ['value' => 'POST', 'label' => 'POST'],
                ['value' => 'GET', 'label' => 'GET'],
                ['value' => 'PUT', 'label' => 'PUT']
            ],
            'default' => 'POST'
        ],
        'payload' => [
            'type' => 'textarea',
            'label' => 'Payload (JSON)',
            'description' => 'JSON payload with template variables (e.g., {"title": "{{entry.title}}"}'
        ]
    ],
    'handler' => function($config, $context) {
        $url = render_automation_template($config['url'] ?? '', $context);
        $method = $config['method'] ?? 'POST';
        $payload_text = $config['payload'] ?? '{}';
        
        if (empty($url)) {
            throw new Exception('Webhook URL is required');
        }
        
        // Render payload template
        $payload_json = render_automation_template($payload_text, $context);
        $payload = json_decode($payload_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // If not valid JSON, treat as plain text
            $payload = $payload_json;
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        if ($method === 'POST' || $method === 'PUT') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($payload) ? json_encode($payload) : $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
        }
        
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('Webhook request failed: ' . $error);
        }
        
        if ($http_code < 200 || $http_code >= 300) {
            throw new Exception("Webhook returned HTTP $http_code");
        }
        
        return ['success' => true, 'http_code' => $http_code, 'response' => $response];
    }
]);

// Set Status Action
register_automation_block('action', 'set_status', [
    'name' => 'Set Entry Status',
    'description' => 'Change the status of an entry',
    'category' => 'Content',
    'schema' => [
        'status' => [
            'type' => 'select',
            'label' => 'New Status',
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
        $entry_id = $context['entry']['id'] ?? '';
        $collection = $context['collection'] ?? $context['entry']['collection'] ?? '';
        
        if (empty($entry_id) || empty($collection) || empty($status)) {
            throw new Exception('Entry ID, collection, and status are required');
        }
        
        require_once __DIR__ . '/../../../api/admin_entries.php';
        require_once __DIR__ . '/../../../core/storage/StorageFactory.php';
        
        $storage = get_storage();
        $entry_path = "collections/{$collection}/entries/{$entry_id}.json";
        $entry = $storage->read($entry_path);
        
        if (!$entry) {
            throw new Exception('Entry not found');
        }
        
        $entry['status'] = $status;
        $entry['updated_at'] = date('c');
        
        if (!$storage->write($entry_path, $entry)) {
            throw new Exception('Failed to update entry status');
        }
        
        return ['status' => $status, 'entry_id' => $entry_id];
    }
]);

// Set Field Action
register_automation_block('action', 'set_field', [
    'name' => 'Set Entry Field',
    'description' => 'Set a field value on an entry',
    'category' => 'Content',
    'schema' => [
        'field' => [
            'type' => 'string',
            'label' => 'Field Name',
            'required' => true
        ],
        'value' => [
            'type' => 'string',
            'label' => 'Field Value',
            'required' => true
        ]
    ],
    'handler' => function($config, $context) {
        $field = $config['field'] ?? '';
        $value = render_automation_template($config['value'] ?? '', $context);
        $entry_id = $context['entry']['id'] ?? '';
        $collection = $context['collection'] ?? $context['entry']['collection'] ?? '';
        
        if (empty($entry_id) || empty($collection) || empty($field)) {
            throw new Exception('Entry ID, collection, and field are required');
        }
        
        require_once __DIR__ . '/../../../core/storage/StorageFactory.php';
        
        $storage = get_storage();
        $entry_path = "collections/{$collection}/entries/{$entry_id}.json";
        $entry = $storage->read($entry_path);
        
        if (!$entry) {
            throw new Exception('Entry not found');
        }
        
        $entry[$field] = $value;
        $entry['updated_at'] = date('c');
        
        if (!$storage->write($entry_path, $entry)) {
            throw new Exception('Failed to update entry field');
        }
        
        return ['field' => $field, 'value' => $value, 'entry_id' => $entry_id];
    }
]);

// Log Action (for testing)
register_automation_block('action', 'log', [
    'name' => 'Log Message',
    'description' => 'Log a message (for testing and debugging)',
    'category' => 'Utility',
    'schema' => [
        'message' => [
            'type' => 'string',
            'label' => 'Log Message',
            'required' => true
        ]
    ],
    'handler' => function($config, $context) {
        $message = render_automation_template($config['message'] ?? '', $context);
        error_log('[Automation] ' . $message);
        return ['logged' => true, 'message' => $message];
    }
]);

// Create Entry Action
register_automation_block('action', 'create_entry', [
    'name' => 'Create Entry',
    'description' => 'Create a new entry in a collection',
    'category' => 'Content',
    'schema' => [
        'collection' => [
            'type' => 'string',
            'label' => 'Collection Slug',
            'required' => true
        ],
        'fields' => [
            'type' => 'textarea',
            'label' => 'Entry Fields (JSON)',
            'description' => 'JSON object with field values. Use {{variable}} for templates.',
            'required' => true
        ],
        'status' => [
            'type' => 'select',
            'label' => 'Status',
            'options' => [
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'published', 'label' => 'Published'],
                ['value' => 'pending_review', 'label' => 'Pending Review']
            ],
            'default' => 'draft'
        ]
    ],
    'handler' => function($config, $context) {
        $collection = render_automation_template($config['collection'] ?? '', $context);
        $fields_json = render_automation_template($config['fields'] ?? '{}', $context);
        $status = $config['status'] ?? 'draft';
        
        if (empty($collection)) {
            throw new Exception('Collection is required');
        }
        
        // Parse fields JSON
        $fields = json_decode($fields_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid fields JSON: ' . json_last_error_msg());
        }
        
        // Add status
        $fields['status'] = $status;
        
        // Create entry via API
        require_once __DIR__ . '/../../../api/admin_entries.php';
        require_once __DIR__ . '/../../../core/storage/StorageFactory.php';
        
        $storage = get_storage();
        $entry_id = uniqid('entry_', true);
        
        // Auto-fill title with entry ID if not provided
        if (empty(trim($fields['title'] ?? ''))) {
            $fields['title'] = $entry_id;
        }
        
        // Prepare entry data
        $entry_data = array_merge($fields, [
            'id' => $entry_id,
            'collection' => $collection,
            'created_at' => date('c'),
            'updated_at' => date('c')
        ]);
        
        // Save entry
        $entry_path = "collections/{$collection}/entries/{$entry_id}.json";
        if (!$storage->write($entry_path, $entry_data)) {
            throw new Exception('Failed to create entry');
        }
        
        return ['entry_id' => $entry_id, 'collection' => $collection];
    }
]);

// Call Plugin Action
register_automation_block('action', 'call_plugin_action', [
    'name' => 'Call Plugin Action',
    'description' => 'Call a registered action from a plugin',
    'category' => 'Plugin',
    'schema' => [
        'plugin_id' => [
            'type' => 'string',
            'label' => 'Plugin ID',
            'required' => true
        ],
        'action_name' => [
            'type' => 'string',
            'label' => 'Action Name',
            'required' => true
        ],
        'parameters' => [
            'type' => 'textarea',
            'label' => 'Parameters (JSON)',
            'description' => 'JSON object with action parameters. Use {{variable}} for templates.'
        ]
    ],
    'handler' => function($config, $context) {
        $plugin_id = $config['plugin_id'] ?? '';
        $action_name = $config['action_name'] ?? '';
        $parameters_json = render_automation_template($config['parameters'] ?? '{}', $context);
        
        if (empty($plugin_id) || empty($action_name)) {
            throw new Exception('Plugin ID and action name are required');
        }
        
        // Parse parameters
        $parameters = json_decode($parameters_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $parameters = []; // Use empty array if invalid JSON
        }
        
        // Merge context into parameters
        $parameters = array_merge($parameters, $context);
        
        // Fire plugin action hook
        $result = null;
        do_action("plugin_action_{$plugin_id}_{$action_name}", $parameters, $result);
        
        return ['plugin_id' => $plugin_id, 'action' => $action_name, 'result' => $result];
    }
]);

// Action Priority Support
// Actions can specify priority in their config, and executor will sort by priority
// Higher priority = executes first (default priority is 10)
