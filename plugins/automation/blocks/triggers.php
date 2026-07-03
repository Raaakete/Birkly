<?php
/**
 * Core Automation Triggers for Birkly
 * 
 * Defines built-in trigger blocks.
 */

require_once __DIR__ . '/../registry.php';

// Entry Created Trigger
register_automation_block('trigger', 'entry_created', [
    'name' => 'Entry Created',
    'description' => 'Fires when a new entry is created',
    'category' => 'Content',
    'schema' => [
        'collection' => [
            'type' => 'collection_picker',
            'label' => 'Collection (optional)',
            'description' => 'Filter by specific collection, or leave empty for all collections'
        ]
    ],
    'handler' => function($config, $context) {
        return true;
    }
]);

// Entry Updated Trigger
register_automation_block('trigger', 'entry_updated', [
    'name' => 'Entry Updated',
    'description' => 'Fires when an entry is updated',
    'category' => 'Content',
    'schema' => [
        'collection' => [
            'type' => 'collection_picker',
            'label' => 'Collection (optional)',
            'description' => 'Filter by specific collection, or leave empty for all'
        ]
    ],
    'handler' => function($config, $context) {
        return true;
    }
]);

// Entry Deleted Trigger
register_automation_block('trigger', 'entry_deleted', [
    'name' => 'Entry Deleted',
    'description' => 'Fires when an entry is deleted',
    'category' => 'Content',
    'schema' => [
        'collection' => [
            'type' => 'collection_picker',
            'label' => 'Collection (optional)',
            'description' => 'Filter by specific collection, or leave empty for all'
        ]
    ],
    'handler' => function($config, $context) {
        return true;
    }
]);

// Sub-Entry Created Trigger
register_automation_block('trigger', 'sub_entry_created', [
    'name' => 'Sub-Entry Created',
    'description' => 'Fires when a new sub-entry is created in a sub-collection',
    'category' => 'Sub-Content',
    'schema' => [
        'collection' => [
            'type' => 'collection_picker',
            'label' => 'Parent Collection (optional)',
            'description' => 'Filter by parent collection, or leave empty for all'
        ],
        'sub_collection' => [
            'type' => 'sub_collection_picker',
            'label' => 'Sub-Collection (optional)',
            'description' => 'Filter by sub-collection slug, or leave empty for all'
        ]
    ],
    'handler' => function($config, $context) {
        return true;
    }
]);

// Sub-Entry Updated Trigger
register_automation_block('trigger', 'sub_entry_updated', [
    'name' => 'Sub-Entry Updated',
    'description' => 'Fires when a sub-entry is updated',
    'category' => 'Sub-Content',
    'schema' => [
        'collection' => [
            'type' => 'collection_picker',
            'label' => 'Parent Collection (optional)',
            'description' => 'Filter by parent collection, or leave empty for all'
        ],
        'sub_collection' => [
            'type' => 'sub_collection_picker',
            'label' => 'Sub-Collection (optional)',
            'description' => 'Filter by sub-collection slug, or leave empty for all'
        ]
    ],
    'handler' => function($config, $context) {
        return true;
    }
]);

// Sub-Entry Deleted Trigger
register_automation_block('trigger', 'sub_entry_deleted', [
    'name' => 'Sub-Entry Deleted',
    'description' => 'Fires when a sub-entry is deleted',
    'category' => 'Sub-Content',
    'schema' => [
        'collection' => [
            'type' => 'collection_picker',
            'label' => 'Parent Collection (optional)',
            'description' => 'Filter by parent collection, or leave empty for all'
        ],
        'sub_collection' => [
            'type' => 'sub_collection_picker',
            'label' => 'Sub-Collection (optional)',
            'description' => 'Filter by sub-collection slug, or leave empty for all'
        ]
    ],
    'handler' => function($config, $context) {
        return true;
    }
]);

// Status Changed Trigger
register_automation_block('trigger', 'status_changed', [
    'name' => 'Status Changed',
    'description' => 'Fires when an entry status changes',
    'category' => 'Content',
    'schema' => [
        'collection' => [
            'type' => 'collection_picker',
            'label' => 'Collection (optional)',
            'description' => 'Filter by specific collection, or leave empty for all'
        ],
        'from' => [
            'type' => 'string',
            'label' => 'From Status (optional)',
            'description' => 'Only trigger when changing from this status'
        ],
        'to' => [
            'type' => 'string',
            'label' => 'To Status (optional)',
            'description' => 'Only trigger when changing to this status'
        ]
    ],
    'handler' => function($config, $context) {
        $from = $config['from'] ?? null;
        $to = $config['to'] ?? null;
        $context_from = $context['from_status'] ?? null;
        $context_to = $context['to_status'] ?? null;
        
        if ($from && $context_from !== $from) {
            return false;
        }
        if ($to && $context_to !== $to) {
            return false;
        }
        
        return true;
    }
]);
