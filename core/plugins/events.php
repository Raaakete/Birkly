<?php
/**
 * Event Broadcasting System for Birkly
 * 
 * Enables plugins to broadcast and subscribe to events for plugin-to-plugin communication.
 */

require_once __DIR__ . '/capabilities.php';

// Global event subscribers registry
$birkly_event_subscribers = [];

/**
 * Subscribe to an event
 * 
 * @param string $event_name Event name (e.g., 'payment_succeeded', 'user_created')
 * @param callable $callback Callback function to execute when event is broadcast
 * @param array $options Options array:
 *   - 'priority' (int): Priority for execution order (default: 10)
 *   - 'capability' (string): Required capability to receive this event
 *   - 'scope' (string): Scope for capability check
 * 
 * @return bool True on success
 */
function subscribe_to_event($event_name, $callback, $options = []) {
    global $birkly_event_subscribers;
    
    // Get plugin ID from backtrace
    $plugin_id = null;
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    if (isset($backtrace[1]['file'])) {
        $file = $backtrace[1]['file'];
        global $birkly_plugins;
        if (isset($birkly_plugins)) {
            foreach ($birkly_plugins as $pid => $plugin) {
                if (strpos($file, $plugin['path'] ?? '') !== false) {
                    $plugin_id = $pid;
                    break;
                }
            }
        }
    }
    
    if (!isset($birkly_event_subscribers[$event_name])) {
        $birkly_event_subscribers[$event_name] = [];
    }
    
    $subscriber = [
        'callback' => $callback,
        'plugin_id' => $plugin_id,
        'priority' => $options['priority'] ?? 10,
        'capability' => $options['capability'] ?? null,
        'scope' => $options['scope'] ?? 'global'
    ];
    
    $birkly_event_subscribers[$event_name][] = $subscriber;
    
    // Sort by priority
    usort($birkly_event_subscribers[$event_name], function($a, $b) {
        return $a['priority'] <=> $b['priority'];
    });
    
    return true;
}

/**
 * Broadcast an event
 * 
 * @param string $event_name Event name
 * @param array $data Event data payload
 * @param string|null $broadcasting_plugin_id Plugin ID broadcasting the event
 * @return int Number of subscribers that received the event
 */
function broadcast_event($event_name, $data = [], $broadcasting_plugin_id = null) {
    global $birkly_event_subscribers;
    
    if (!isset($birkly_event_subscribers[$event_name])) {
        return 0;
    }
    
    $notified_count = 0;
    
    foreach ($birkly_event_subscribers[$event_name] as $subscriber) {
        $plugin_id = $subscriber['plugin_id'];
        $callback = $subscriber['callback'];
        $capability = $subscriber['capability'];
        $scope = $subscriber['scope'];
        
        // Skip if subscriber is the broadcaster (prevent self-notification loops)
        if ($plugin_id === $broadcasting_plugin_id) {
            continue;
        }
        
        // Check capability if required
        if ($capability && $plugin_id) {
            if (!has_plugin_capability($plugin_id, $capability, $scope)) {
                continue; // Skip subscriber without permission
            }
        }
        
        // Check if plugin is active
        if ($plugin_id) {
            global $birkly_plugins;
            if (isset($birkly_plugins[$plugin_id]) && !($birkly_plugins[$plugin_id]['active'] ?? false)) {
                continue; // Skip inactive plugins
            }
        }
        
        // Execute callback
        try {
            call_user_func($callback, $data, $event_name);
            $notified_count++;
        } catch (Exception $e) {
            // Log error but continue with other subscribers
            error_log("Event subscriber error for '$event_name' from plugin '$plugin_id': " . $e->getMessage());
        } catch (Error $e) {
            error_log("Fatal event subscriber error for '$event_name' from plugin '$plugin_id': " . $e->getMessage());
        }
    }
    
    return $notified_count;
}

/**
 * Get list of subscribed events
 * 
 * @return array List of event names with subscriber counts
 */
function get_subscribed_events() {
    global $birkly_event_subscribers;
    
    $events = [];
    foreach ($birkly_event_subscribers as $event_name => $subscribers) {
        $events[$event_name] = count($subscribers);
    }
    
    return $events;
}
