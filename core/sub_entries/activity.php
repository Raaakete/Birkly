<?php
/**
 * Sub-Entries Activity Tracking for Birkly
 * 
 * Tracks and logs activity for sub-entries (create, update, delete, status changes).
 */

require_once __DIR__ . '/../storage/StorageFactory.php';

/**
 * Log sub-entry activity
 * 
 * @param string $action Action type (created, updated, deleted, status_changed)
 * @param string $parent_collection Parent collection slug
 * @param string $parent_entry_id Parent entry ID
 * @param string $sub_collection Sub-collection slug
 * @param string $sub_entry_id Sub-entry ID
 * @param array $data Additional data (changes, old_value, new_value, etc.)
 * @return bool True on success
 */
function log_sub_entry_activity($action, $parent_collection, $parent_entry_id, $sub_collection, $sub_entry_id, $data = []) {
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    
    $activity = [
        'id' => uniqid('act_', true),
        'action' => $action,
        'parent_collection' => $parent_collection,
        'parent_entry_id' => $parent_entry_id,
        'sub_collection' => $sub_collection,
        'sub_entry_id' => $sub_entry_id,
        'timestamp' => date('c'),
        'user_id' => $_SESSION['user']['id'] ?? null,
        'user_email' => $_SESSION['user']['email'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'data' => $data
    ];
    
    // Load existing activities
    $activities = $storage->read('sub_entry_activities.json') ?? [];
    
    // Add new activity
    $activities[] = $activity;
    
    // Keep only last 10,000 activities (prevent file from growing too large)
    if (count($activities) > 10000) {
        $activities = array_slice($activities, -10000);
    }
    
    return $storage->write('sub_entry_activities.json', $activities);
}

/**
 * Get sub-entry activities
 * 
 * @param array $filters Filters (parent_collection, parent_entry_id, sub_collection, sub_entry_id, action, user_id, date_from, date_to)
 * @param array $options Options (sort, order, limit, offset)
 * @return array Activities
 */
function get_sub_entry_activities($filters = [], $options = []) {
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $activities = $storage->read('sub_entry_activities.json') ?? [];
    
    // Apply filters
    if (!empty($filters)) {
        $filtered = [];
        foreach ($activities as $activity) {
            $match = true;
            
            if (isset($filters['parent_collection']) && $activity['parent_collection'] !== $filters['parent_collection']) {
                $match = false;
            }
            if (isset($filters['parent_entry_id']) && $activity['parent_entry_id'] !== $filters['parent_entry_id']) {
                $match = false;
            }
            if (isset($filters['sub_collection']) && $activity['sub_collection'] !== $filters['sub_collection']) {
                $match = false;
            }
            if (isset($filters['sub_entry_id']) && $activity['sub_entry_id'] !== $filters['sub_entry_id']) {
                $match = false;
            }
            if (isset($filters['action']) && $activity['action'] !== $filters['action']) {
                $match = false;
            }
            if (isset($filters['user_id']) && $activity['user_id'] !== $filters['user_id']) {
                $match = false;
            }
            if (isset($filters['date_from'])) {
                $date_from = is_numeric($filters['date_from']) ? $filters['date_from'] : strtotime($filters['date_from']);
                $activity_time = strtotime($activity['timestamp']);
                if ($activity_time < $date_from) {
                    $match = false;
                }
            }
            if (isset($filters['date_to'])) {
                $date_to = is_numeric($filters['date_to']) ? $filters['date_to'] : strtotime($filters['date_to']);
                $activity_time = strtotime($activity['timestamp']);
                if ($activity_time > $date_to) {
                    $match = false;
                }
            }
            
            if ($match) {
                $filtered[] = $activity;
            }
        }
        $activities = $filtered;
    }
    
    // Sort
    $sort = $options['sort'] ?? 'timestamp';
    $order = $options['order'] ?? 'desc';
    
    usort($activities, function($a, $b) use ($sort, $order) {
        $a_val = $a[$sort] ?? '';
        $b_val = $b[$sort] ?? '';
        
        if ($order === 'asc') {
            return $a_val <=> $b_val;
        } else {
            return $b_val <=> $a_val;
        }
    });
    
    // Limit and offset
    $limit = $options['limit'] ?? null;
    $offset = $options['offset'] ?? 0;
    
    if ($offset > 0) {
        $activities = array_slice($activities, $offset);
    }
    if ($limit !== null) {
        $activities = array_slice($activities, 0, $limit);
    }
    
    return $activities;
}

/**
 * Get activity stream for a parent entry
 * 
 * @param string $parent_collection Parent collection slug
 * @param string $parent_entry_id Parent entry ID
 * @param array $options Options
 * @return array Activities
 */
function get_entry_activity_stream($parent_collection, $parent_entry_id, $options = []) {
    return get_sub_entry_activities([
        'parent_collection' => $parent_collection,
        'parent_entry_id' => $parent_entry_id
    ], $options);
}

/**
 * Get activity stream for a sub-entry
 * 
 * @param string $sub_collection Sub-collection slug
 * @param string $sub_entry_id Sub-entry ID
 * @param array $options Options
 * @return array Activities
 */
function get_sub_entry_activity_stream($sub_collection, $sub_entry_id, $options = []) {
    return get_sub_entry_activities([
        'sub_collection' => $sub_collection,
        'sub_entry_id' => $sub_entry_id
    ], $options);
}

/**
 * Get activity summary (counts by action)
 * 
 * @param array $filters Filters
 * @return array Summary with counts per action
 */
function get_sub_entry_activity_summary($filters = []) {
    $activities = get_sub_entry_activities($filters);
    
    $summary = [
        'total' => count($activities),
        'by_action' => []
    ];
    
    foreach ($activities as $activity) {
        $action = $activity['action'] ?? 'unknown';
        if (!isset($summary['by_action'][$action])) {
            $summary['by_action'][$action] = 0;
        }
        $summary['by_action'][$action]++;
    }
    
    return $summary;
}

/**
 * Clear old activities
 * 
 * @param int $days_to_keep Number of days to keep (default: 90)
 * @return int Number of activities removed
 */
function clear_old_sub_entry_activities($days_to_keep = 90) {
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $activities = $storage->read('sub_entry_activities.json') ?? [];
    
    $cutoff = time() - ($days_to_keep * 24 * 60 * 60);
    $kept = [];
    $removed = 0;
    
    foreach ($activities as $activity) {
        $timestamp = strtotime($activity['timestamp'] ?? '');
        if ($timestamp >= $cutoff) {
            $kept[] = $activity;
        } else {
            $removed++;
        }
    }
    
    $storage->write('sub_entry_activities.json', $kept);
    
    return $removed;
}
