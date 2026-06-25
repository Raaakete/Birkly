<?php
/**
 * Plugin-Automation Integration for Birkly
 * 
 * Provides hooks and utilities for plugins to integrate with automation system.
 */

require_once __DIR__ . '/registry.php';
require_once __DIR__ . '/executor.php';

/**
 * Allow plugins to register automation blocks
 * 
 * This function is called automatically when plugins are loaded.
 * Plugins can call register_automation_block() in their plugin.php file.
 */
function init_plugin_automation_blocks() {
    // This is called automatically when plugins are loaded
    // Plugins can register blocks in their plugin.php file
    do_action('automation_blocks_register');
}

/**
 * Enrich automation context with plugin data
 * 
 * @param array $context Original context
 * @return array Enriched context
 */
function enrich_automation_context($context) {
    // Allow plugins to add context data
    $enriched = apply_filters('automation_context', $context);
    return $enriched;
}

/**
 * Broadcast plugin event to automation system
 * 
 * Plugins can use this to trigger automations from their own events
 * 
 * @param string $event_name Event name (e.g., 'payment_succeeded', 'user_upgraded')
 * @param array $context Event context data
 * @return int Number of automations triggered
 */
function broadcast_plugin_event($event_name, $context = []) {
    if (!function_exists('enqueue_automation')) {
        return 0;
    }
    
    // Enrich context with plugin data
    $enriched_context = enrich_automation_context($context);
    $enriched_context['plugin_event'] = $event_name;
    
    // Enqueue as plugin event trigger
    enqueue_automation('plugin_event', $enriched_context);
    
    return 1;
}

/**
 * Register plugin event trigger block
 * 
 * This is a special trigger type that listens for plugin events
 */
function register_plugin_event_trigger() {
    if (!function_exists('register_automation_block')) {
        return;
    }
    
    register_automation_block('trigger', 'plugin_event', [
        'name' => 'Plugin Event',
        'description' => 'Fires when a plugin broadcasts an event',
        'category' => 'Plugin',
        'schema' => [
            'event_name' => [
                'type' => 'string',
                'label' => 'Event Name',
                'description' => 'The plugin event name to listen for (e.g., payment_succeeded)',
                'required' => true
            ]
        ],
        'handler' => function($config, $context) {
            $event_name = $config['event_name'] ?? '';
            $context_event = $context['plugin_event'] ?? '';
            
            return $event_name === $context_event;
        }
    ]);
}

// Register plugin event trigger on init
if (function_exists('register_automation_block')) {
    register_plugin_event_trigger();
}
