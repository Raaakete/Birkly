<?php
/**
 * Sub-Entries System Core Functions for Birkly
 * 
 * Handles sub-entry storage, queries, and parent-child relationships.
 */

require_once __DIR__ . '/storage/StorageFactory.php';

// Ensure get_storage function is available
if (!function_exists('get_storage')) {
    require_once __DIR__ . '/storage/StorageFactory.php';
}

// Load display helpers if needed
if (file_exists(__DIR__ . '/sub_entries/display.php')) {
    require_once __DIR__ . '/sub_entries/display.php';
}

// Load activity tracking if needed
if (file_exists(__DIR__ . '/sub_entries/activity.php')) {
    require_once __DIR__ . '/sub_entries/activity.php';
}

/**
 * Get sub-entries for a parent entry
 * 
 * @param string $parent_collection Parent collection slug
 * @param string $parent_entry_id Parent entry ID
 * @param string $sub_collection Sub-collection slug
 * @param array $filters Optional filters
 * @param array $options Optional query options (limit, offset, sort, order)
 * @return array List of sub-entries
 */
function getSubEntries($parent_collection, $parent_entry_id, $sub_collection, $filters = [], $options = []) {
    // DISABLE CACHE for now - it prevents reordering from working
    // The cache doesn't know when .order.json changes
    /*
    if (file_exists(__DIR__ . '/sub_entries/cache.php')) {
        require_once __DIR__ . '/sub_entries/cache.php';
        $cache_key = get_sub_entries_cache_key($parent_collection, $parent_entry_id, $sub_collection, $filters, $options);
        $cached = get_cached_sub_entries($cache_key);
        if ($cached !== null) {
            return $cached;
        }
    }
    */
    
    // Ensure storage is available
    if (!function_exists('get_storage')) {
        require_once __DIR__ . '/storage/StorageFactory.php';
    }
    
    try {
        $storage = get_storage();
    } catch (Exception $e) {
        error_log("Error getting storage in getSubEntries: " . $e->getMessage());
        return [];
    }
    
    // Build sub-entry path
    $subEntryPath = "collections/{$parent_collection}/entries/{$parent_entry_id}/sub_entries/{$sub_collection}";
    
    // Get all sub-entry files
    $files = $storage->list($subEntryPath);
    $entries = [];
    
    foreach ($files as $file) {
        if (substr($file, -5) !== '.json') {
            continue;
        }
        
        // Skip dotfiles (like .order.json)
        if (strpos($file, '.') === 0) {
            continue;
        }
        
        $entryPath = "{$subEntryPath}/{$file}";
        $entry = $storage->read($entryPath);
        
        if ($entry === null) {
            continue;
        }
        
        // Ensure ID is set from filename (in case it's missing or different in the file)
        $entryId = basename($file, '.json');
        $entry['id'] = $entryId;
        
        // Apply filters
        $matches = true;
        foreach ($filters as $field => $filter) {
            $operator = '=';
            $value = $filter;
            
            // Support advanced filter format
            if (is_array($filter) && isset($filter['operator']) && isset($filter['value'])) {
                $operator = $filter['operator'];
                $value = $filter['value'];
            }
            
            $fieldValue = $entry[$field] ?? null;
            
            switch ($operator) {
                case '=':
                    $matches = ($fieldValue === $value);
                    break;
                case '!=':
                    $matches = ($fieldValue !== $value);
                    break;
                case '>':
                    $matches = ($fieldValue > $value);
                    break;
                case '>=':
                    $matches = ($fieldValue >= $value);
                    break;
                case '<':
                    $matches = ($fieldValue < $value);
                    break;
                case '<=':
                    $matches = ($fieldValue <= $value);
                    break;
                case 'contains':
                    $matches = (is_string($fieldValue) && strpos($fieldValue, $value) !== false);
                    break;
                case 'in':
                    $matches = (is_array($value) && in_array($fieldValue, $value));
                    break;
                default:
                    $matches = ($fieldValue === $value);
            }
            
            if (!$matches) {
                break;
            }
        }
        
        if ($matches) {
            $entries[] = $entry;
        }
    }
    
    // Apply custom order from .order.json file if it exists (for drag and drop reordering)
    $orderFilePath = "{$subEntryPath}/.order.json";
    try {
        $orderData = $storage->read($orderFilePath);
        if ($orderData && isset($orderData['order']) && is_array($orderData['order'])) {
            $customOrder = $orderData['order'];
            
            // Create a map of entry IDs to entries
            $entryMap = [];
            foreach ($entries as $entry) {
                $entryMap[$entry['id']] = $entry;
            }
            
            // Rebuild entries array in the custom order
            $orderedEntries = [];
            foreach ($customOrder as $entryId) {
                if (isset($entryMap[$entryId])) {
                    $orderedEntries[] = $entryMap[$entryId];
                    unset($entryMap[$entryId]); // Remove from map
                }
            }
            
            // Add any entries that weren't in the order file (new entries)
            foreach ($entryMap as $entry) {
                $orderedEntries[] = $entry;
            }
            
            $entries = $orderedEntries;
        }
    } catch (Exception $e) {
        // If .order.json doesn't exist or can't be read, just use the default order
        // This is normal for collections that haven't been reordered yet
    }
    
    // Apply sorting
    if (isset($options['sort'])) {
        $sortField = $options['sort'];
        $sortOrder = $options['order'] ?? 'asc';
        usort($entries, function($a, $b) use ($sortField, $sortOrder) {
            $aVal = $a[$sortField] ?? '';
            $bVal = $b[$sortField] ?? '';
            $result = $aVal <=> $bVal;
            return $sortOrder === 'desc' ? -$result : $result;
        });
    }
    
    // Apply pagination
    if (isset($options['offset'])) {
        $entries = array_slice($entries, $options['offset']);
    }
    if (isset($options['limit'])) {
        $entries = array_slice($entries, 0, $options['limit']);
    }
    
    return $entries;
}

/**
 * Get sibling sub-entries (same parent, same sub-collection)
 * 
 * @param string $parent_collection Parent collection slug
 * @param string $parent_entry_id Parent entry ID
 * @param string $sub_collection Sub-collection slug
 * @param string $sub_entry_id Sub-entry ID to exclude
 * @param array $options Optional query options
 * @return array List of sibling sub-entries
 */
function getSubEntrySiblings($parent_collection, $parent_entry_id, $sub_collection, $sub_entry_id, $options = []) {
    $all_entries = getSubEntries($parent_collection, $parent_entry_id, $sub_collection, [], $options);
    
    // Filter out the current entry
    return array_filter($all_entries, function($entry) use ($sub_entry_id) {
        return ($entry['id'] ?? '') !== $sub_entry_id;
    });
}

/**
 * Get sub-entries with aggregations
 * 
 * @param string $parent_collection Parent collection slug
 * @param string $parent_entry_id Parent entry ID
 * @param string $sub_collection Sub-collection slug
 * @param array $filters Optional filters
 * @param array $aggregations Aggregation operations (e.g., ['count' => true, 'sum' => 'price', 'avg' => 'rating'])
 * @return array Results with aggregations
 */
function getSubEntriesWithAggregations($parent_collection, $parent_entry_id, $sub_collection, $filters = [], $aggregations = []) {
    $entries = getSubEntries($parent_collection, $parent_entry_id, $sub_collection, $filters);
    
    $result = [
        'entries' => $entries,
        'aggregations' => []
    ];
    
    foreach ($aggregations as $operation => $field) {
        if ($operation === 'count') {
            $result['aggregations']['count'] = count($entries);
        } elseif ($operation === 'sum' && $field) {
            $sum = 0;
            foreach ($entries as $entry) {
                $value = $entry[$field] ?? 0;
                if (is_numeric($value)) {
                    $sum += $value;
                }
            }
            $result['aggregations']['sum_' . $field] = $sum;
        } elseif ($operation === 'avg' && $field) {
            $sum = 0;
            $count = 0;
            foreach ($entries as $entry) {
                $value = $entry[$field] ?? 0;
                if (is_numeric($value)) {
                    $sum += $value;
                    $count++;
                }
            }
            $result['aggregations']['avg_' . $field] = $count > 0 ? $sum / $count : 0;
        } elseif ($operation === 'min' && $field) {
            $values = array_filter(array_map(function($entry) use ($field) {
                return isset($entry[$field]) && is_numeric($entry[$field]) ? $entry[$field] : null;
            }, $entries));
            $result['aggregations']['min_' . $field] = !empty($values) ? min($values) : null;
        } elseif ($operation === 'max' && $field) {
            $values = array_filter(array_map(function($entry) use ($field) {
                return isset($entry[$field]) && is_numeric($entry[$field]) ? $entry[$field] : null;
            }, $entries));
            $result['aggregations']['max_' . $field] = !empty($values) ? max($values) : null;
        } elseif ($operation === 'group_by' && $field) {
            // Group by field value
            $groups = [];
            foreach ($entries as $entry) {
                $group_key = $entry[$field] ?? 'null';
                if (!isset($groups[$group_key])) {
                    $groups[$group_key] = [];
                }
                $groups[$group_key][] = $entry;
            }
            $result['aggregations']['group_by_' . $field] = $groups;
        }
    }
    
    return $result;
}

/**
 * Get nested sub-entries (sub-entries of sub-entries)
 * 
 * @param string $parent_collection Parent collection slug
 * @param string $parent_entry_id Parent entry ID
 * @param string $sub_collection Sub-collection slug
 * @param string $parent_sub_entry_id Parent sub-entry ID
 * @param array $filters Optional filters
 * @param array $options Optional query options
 * @return array List of nested sub-entries
 */
function getNestedSubEntries($parent_collection, $parent_entry_id, $sub_collection, $parent_sub_entry_id, $filters = [], $options = []) {
    if (!function_exists('get_storage')) {
        require_once __DIR__ . '/storage/StorageFactory.php';
    }
    try {
        $storage = get_storage();
    } catch (Exception $e) {
        error_log("Error getting storage in getNestedSubEntries: " . $e->getMessage());
        return [];
    }
    
    // Build nested sub-entry path
    $nestedPath = "collections/{$parent_collection}/entries/{$parent_entry_id}/sub_entries/{$sub_collection}/{$parent_sub_entry_id}/sub_entries/{$sub_collection}";
    
    // Get all nested sub-entry files
    $files = $storage->list($nestedPath);
    $entries = [];
    
    foreach ($files as $file) {
        if (substr($file, -5) !== '.json') {
            continue;
        }
        
        $entryPath = "{$nestedPath}/{$file}";
        $entry = $storage->read($entryPath);
        
        if ($entry === null) {
            continue;
        }
        
        // Apply filters (same logic as getSubEntries)
        $matches = true;
        foreach ($filters as $field => $filter) {
            $operator = '=';
            $value = $filter;
            
            if (is_array($filter) && isset($filter['operator']) && isset($filter['value'])) {
                $operator = $filter['operator'];
                $value = $filter['value'];
            }
            
            $fieldValue = $entry[$field] ?? null;
            
            switch ($operator) {
                case '=': $matches = ($fieldValue === $value); break;
                case '!=': $matches = ($fieldValue !== $value); break;
                case '>': $matches = ($fieldValue > $value); break;
                case '>=': $matches = ($fieldValue >= $value); break;
                case '<': $matches = ($fieldValue < $value); break;
                case '<=': $matches = ($fieldValue <= $value); break;
                case 'contains': $matches = (is_string($fieldValue) && strpos($fieldValue, $value) !== false); break;
                case 'in': $matches = (is_array($value) && in_array($fieldValue, $value)); break;
                case 'like': 
                    // SQL LIKE pattern matching
                    $pattern = str_replace(['%', '_'], ['.*', '.'], preg_quote($value, '/'));
                    $matches = preg_match('/^' . $pattern . '$/i', (string)$fieldValue);
                    break;
                case 'between':
                    // Value should be array with 'min' and 'max' or [min, max]
                    $min = is_array($value) ? ($value['min'] ?? $value[0] ?? null) : null;
                    $max = is_array($value) ? ($value['max'] ?? $value[1] ?? null) : null;
                    if ($min !== null && $max !== null) {
                        $matches = is_numeric($fieldValue) && $fieldValue >= $min && $fieldValue <= $max;
                    } else {
                        $matches = false;
                    }
                    break;
                default: $matches = ($fieldValue === $value);
            }
            
            if (!$matches) break;
        }
        
        if ($matches) {
            $entries[] = $entry;
        }
    }
    
    // Apply sorting
    if (isset($options['sort'])) {
        $sortField = $options['sort'];
        $sortOrder = $options['order'] ?? 'asc';
        usort($entries, function($a, $b) use ($sortField, $sortOrder) {
            $aVal = $a[$sortField] ?? '';
            $bVal = $b[$sortField] ?? '';
            $result = $aVal <=> $bVal;
            return $sortOrder === 'desc' ? -$result : $result;
        });
    }
    
    // Apply pagination
    if (isset($options['offset'])) {
        $entries = array_slice($entries, $options['offset']);
    }
    if (isset($options['limit'])) {
        $entries = array_slice($entries, 0, $options['limit']);
    }
    
    return $entries;
}

/**
 * Get a single sub-entry by ID
 * 
 * @param string $parent_collection Parent collection slug
 * @param string $parent_entry_id Parent entry ID
 * @param string $sub_collection Sub-collection slug
 * @param string $sub_entry_id Sub-entry ID
 * @return array|null Sub-entry data or null if not found
 */
function getSubEntry($parent_collection, $parent_entry_id, $sub_collection, $sub_entry_id) {
    if (!function_exists('get_storage')) {
        require_once __DIR__ . '/storage/StorageFactory.php';
    }
    try {
        $storage = get_storage();
    } catch (Exception $e) {
        error_log("Error getting storage in getSubEntry: " . $e->getMessage());
        return null;
    }
    $entryPath = "collections/{$parent_collection}/entries/{$parent_entry_id}/sub_entries/{$sub_collection}/{$sub_entry_id}.json";
    error_log("getSubEntry looking for: {$entryPath}");
    $result = $storage->read($entryPath);
    if (!$result) {
        error_log("getSubEntry: Entry not found at {$entryPath}");
        // Try to list the directory to see what's there
        $parentDir = "collections/{$parent_collection}/entries/{$parent_entry_id}/sub_entries/{$sub_collection}";
        try {
            $files = $storage->list($parentDir);
            error_log("getSubEntry: Files in directory: " . json_encode($files));
        } catch (Exception $e) {
            error_log("getSubEntry: Error listing directory: " . $e->getMessage());
        }
    } else {
        error_log("getSubEntry: Entry found successfully");
    }
    return $result;
}

/**
 * Create a new sub-entry
 * 
 * @param string $parent_collection Parent collection slug
 * @param string $parent_entry_id Parent entry ID
 * @param string $sub_collection Sub-collection slug
 * @param array $data Sub-entry data
 * @return array|false Created sub-entry with ID, or false on failure
 */
function createSubEntry($parent_collection, $parent_entry_id, $sub_collection, $data) {
    if (!function_exists('get_storage')) {
        require_once __DIR__ . '/storage/StorageFactory.php';
    }
    try {
        $storage = get_storage();
    } catch (Exception $e) {
        error_log("Error getting storage in createSubEntry: " . $e->getMessage());
        return false;
    }
    
    // Check if this is a nested sub-entry (sub-entry of a sub-entry)
    $parent_sub_collection = $data['parent_sub_collection'] ?? null;
    $parent_sub_entry_id = $data['parent_sub_entry_id'] ?? null;
    $isNested = !empty($parent_sub_collection) && !empty($parent_sub_entry_id);
    
    error_log("createSubEntry called with: parent_collection={$parent_collection}, parent_entry_id={$parent_entry_id}, sub_collection={$sub_collection}, isNested=" . ($isNested ? 'true' : 'false'));
    if ($isNested) {
        error_log("Nested sub-entry: parent_sub_collection={$parent_sub_collection}, parent_sub_entry_id={$parent_sub_entry_id}");
    }
    
    if ($isNested) {
        // Validate parent sub-entry exists
        $parentSubEntryPath = "collections/{$parent_collection}/entries/{$parent_entry_id}/sub_entries/{$parent_sub_collection}/{$parent_sub_entry_id}.json";
        error_log("Looking for parent sub-entry at: {$parentSubEntryPath}");
        $parentSubEntry = $storage->read($parentSubEntryPath);
        if (!$parentSubEntry) {
            error_log("Parent sub-entry not found: {$parentSubEntryPath}");
            // Try to list the directory to see what's there
            $parentDir = "collections/{$parent_collection}/entries/{$parent_entry_id}/sub_entries/{$parent_sub_collection}";
            try {
                $files = $storage->list($parentDir);
                error_log("Files in parent directory: " . json_encode($files));
            } catch (Exception $e) {
                error_log("Error listing parent directory: " . $e->getMessage());
            }
            return false; // Parent sub-entry doesn't exist
        }
        error_log("Parent sub-entry found successfully");
        
        // Build nested path: collections/{collection}/entries/{entry}/sub_entries/{parent_sub_collection}/{parent_sub_entry}/sub_entries/{sub_collection}/{sub_entry}.json
        $subEntryPath = "collections/{$parent_collection}/entries/{$parent_entry_id}/sub_entries/{$parent_sub_collection}/{$parent_sub_entry_id}/sub_entries/{$sub_collection}";
        error_log("Nested sub-entry path: {$subEntryPath}");
    } else {
        // Validate parent entry exists
        $parentPath = "collections/{$parent_collection}/entries/{$parent_entry_id}.json";
        error_log("Looking for parent entry at: {$parentPath}");
        $parent = $storage->read($parentPath);
        if (!$parent) {
            error_log("Parent entry not found: {$parentPath}");
            return false; // Parent doesn't exist
        }
        error_log("Parent entry found successfully");
        
        // Build first-level path: collections/{collection}/entries/{entry}/sub_entries/{sub_collection}
        $subEntryPath = "collections/{$parent_collection}/entries/{$parent_entry_id}/sub_entries/{$sub_collection}";
        error_log("First-level sub-entry path: {$subEntryPath}");
    }
    
    // Generate sub-entry ID
    $sub_entry_id = $data['id'] ?? uniqid('sub_', true);
    
    // Auto-fill title with sub-entry ID if not provided
    if (empty(trim($data['title'] ?? ''))) {
        $data['title'] = $sub_entry_id;
    }
    
    // Auto-populate parent reference
    $data['parent_collection'] = $parent_collection;
    $data['parent_entry_id'] = $parent_entry_id;
    $data['sub_collection'] = $sub_collection;
    $data['id'] = $sub_entry_id;
    
    // Build ancestry path (for nested sub-entries support)
    $ancestry = [];
    if ($isNested) {
        // This is a nested sub-entry - get parent's ancestry
        if (isset($parentSubEntry['ancestry'])) {
            $ancestry = $parentSubEntry['ancestry'];
        }
        $ancestry[] = [
            'collection' => $parent_sub_collection,
            'entry_id' => $parent_sub_entry_id
        ];
        $ancestry[] = [
            'collection' => $sub_collection,
            'entry_id' => $sub_entry_id
        ];
    } else {
        // First-level sub-entry
        $ancestry[] = [
            'collection' => $parent_collection,
            'entry_id' => $parent_entry_id
        ];
        $ancestry[] = [
            'collection' => $sub_collection,
            'entry_id' => $sub_entry_id
        ];
    }
    $data['ancestry'] = $ancestry;
    
    // Set timestamps
    $now = date('c');
    $data['created_at'] = $data['created_at'] ?? $now;
    $data['updated_at'] = $now;
    
    // Set default status if not provided
    if (!isset($data['status'])) {
        $data['status'] = 'draft';
    }
    
    // Save sub-entry
    $fullSubEntryPath = "{$subEntryPath}/{$sub_entry_id}.json";
    error_log("Attempting to save sub-entry to: {$fullSubEntryPath}");
    error_log("Sub-entry data keys: " . implode(', ', array_keys($data)));
    error_log("Sub-entry ID being saved: {$sub_entry_id}");
    $result = $storage->write($fullSubEntryPath, $data);
    
    if ($result) {
        error_log("Sub-entry saved successfully to: {$fullSubEntryPath}");
        // Invalidate cache for both first-level and nested sub-entries
        if (function_exists('invalidate_sub_entries_cache')) {
            invalidate_sub_entries_cache($parent_collection, $parent_entry_id, $sub_collection);
            // If nested, also invalidate parent sub-entry cache
            if ($isNested) {
                invalidate_sub_entries_cache($parent_collection, $parent_entry_id, $parent_sub_collection);
            }
        }
        
        // Fire hook (only if hooks system is loaded)
        if (function_exists('do_action')) {
            do_action('sub_entry_created', $sub_entry_id, $data, $parent_collection, $parent_entry_id, $sub_collection);
        }
        
        // Log activity
        if (function_exists('log_sub_entry_activity')) {
            log_sub_entry_activity('created', $parent_collection, $parent_entry_id, $sub_collection, $sub_entry_id, [
                'title' => $data['title'] ?? $data['name'] ?? '',
                'status' => $data['status'] ?? 'draft'
            ]);
        }
        
        // Trigger automation (if automation system is available)
        if (function_exists('enqueue_automation')) {
            enqueue_automation('entry_created', [
                'entry' => $data,
                'collection' => $sub_collection,
                'parent_collection' => $parent_collection,
                'parent_entry_id' => $parent_entry_id,
                'is_sub_entry' => true
            ]);
        }
        
        return $data;
    } else {
        error_log("Failed to write sub-entry to: {$fullSubEntryPath}");
        error_log("Storage write returned: " . ($result === false ? 'false' : var_export($result, true)));
        return false;
    }
}

/**
 * Update a sub-entry
 * 
 * @param string $parent_collection Parent collection slug
 * @param string $parent_entry_id Parent entry ID
 * @param string $sub_collection Sub-collection slug
 * @param string $sub_entry_id Sub-entry ID
 * @param array $data Updated data
 * @return array|false Updated sub-entry or false on failure
 */
function updateSubEntry($parent_collection, $parent_entry_id, $sub_collection, $sub_entry_id, $data) {
    if (!function_exists('get_storage')) {
        require_once __DIR__ . '/storage/StorageFactory.php';
    }
    try {
        $storage = get_storage();
    } catch (Exception $e) {
        error_log("Error getting storage in updateSubEntry: " . $e->getMessage());
        return false;
    }
    
    // Get existing entry
    $entryPath = "collections/{$parent_collection}/entries/{$parent_entry_id}/sub_entries/{$sub_collection}/{$sub_entry_id}.json";
    $existing = $storage->read($entryPath);
    
    if (!$existing) {
        return false; // Entry doesn't exist
    }
    
    // Auto-fill title with sub-entry ID if not provided
    if (empty(trim($data['title'] ?? ''))) {
        $data['title'] = $sub_entry_id;
    }
    
    // Merge data (preserve system fields)
    $updated = array_merge($existing, $data);
    $updated['updated_at'] = date('c');
    
    // Preserve parent references and ancestry
    $updated['parent_collection'] = $parent_collection;
    $updated['parent_entry_id'] = $parent_entry_id;
    $updated['sub_collection'] = $sub_collection;
    $updated['id'] = $sub_entry_id;
    
    // Preserve ancestry if it exists
    if (isset($existing['ancestry'])) {
        $updated['ancestry'] = $existing['ancestry'];
    }
    
    // Save
    $result = $storage->write($entryPath, $updated);
    
    if ($result) {
        // Invalidate cache
        if (function_exists('invalidate_sub_entries_cache')) {
            invalidate_sub_entries_cache($parent_collection, $parent_entry_id, $sub_collection);
        }
        
        // Fire hook
        if (function_exists('do_action')) {
            do_action('sub_entry_updated', $sub_entry_id, $updated, $existing, $parent_collection, $parent_entry_id, $sub_collection);
        }
        
        // Calculate changes
        $changes = [];
        foreach ($updated as $key => $value) {
            if (!isset($existing[$key]) || $existing[$key] !== $value) {
                $changes[$key] = ['old' => $existing[$key] ?? null, 'new' => $value];
            }
        }
        
        // Log activity
        if (function_exists('log_sub_entry_activity')) {
            log_sub_entry_activity('updated', $parent_collection, $parent_entry_id, $sub_collection, $sub_entry_id, [
                'changes' => $changes,
                'old_status' => $existing['status'] ?? null,
                'new_status' => $updated['status'] ?? null
            ]);
        }
        
        // Trigger automation (if automation system is available)
        if (function_exists('enqueue_automation')) {
            $oldStatus = $existing['status'] ?? '';
            $newStatus = $updated['status'] ?? '';
            
            enqueue_automation('entry_updated', [
                'entry' => $updated,
                'collection' => $sub_collection,
                'old_entry' => $existing,
                'parent_collection' => $parent_collection,
                'parent_entry_id' => $parent_entry_id,
                'is_sub_entry' => true
            ]);
            
            // Status changed trigger
            if ($oldStatus !== $newStatus) {
                enqueue_automation('status_changed', [
                    'entry' => $updated,
                    'collection' => $sub_collection,
                    'from_status' => $oldStatus,
                    'to_status' => $newStatus,
                    'parent_collection' => $parent_collection,
                    'parent_entry_id' => $parent_entry_id,
                    'is_sub_entry' => true
                ]);
                
                // Log status change activity
                if (function_exists('log_sub_entry_activity')) {
                    log_sub_entry_activity('status_changed', $parent_collection, $parent_entry_id, $sub_collection, $sub_entry_id, [
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus
                    ]);
                }
            }
        }
        
        return $updated;
    }
    
    return false;
}

/**
 * Delete a sub-entry
 * 
 * @param string $parent_collection Parent collection slug
 * @param string $parent_entry_id Parent entry ID
 * @param string $sub_collection Sub-collection slug
 * @param string $sub_entry_id Sub-entry ID
 * @return bool True on success
 */
function deleteSubEntry($parent_collection, $parent_entry_id, $sub_collection, $sub_entry_id, $parent_sub_collection = null, $parent_sub_entry_id = null) {
    if (!function_exists('get_storage')) {
        require_once __DIR__ . '/storage/StorageFactory.php';
    }
    try {
        $storage = get_storage();
    } catch (Exception $e) {
        error_log("Error getting storage in deleteSubEntry: " . $e->getMessage());
        return false;
    }
    
    // Determine if this is a nested sub-entry
    $isNested = !empty($parent_sub_collection) && !empty($parent_sub_entry_id);
    
    // Build the correct path based on whether it's nested or first-level
    if ($isNested) {
        // Nested sub-entry path: collections/{collection}/entries/{entry}/sub_entries/{parent_sub_collection}/{parent_sub_entry}/sub_entries/{sub_collection}/{sub_entry}.json
        $entryPath = "collections/{$parent_collection}/entries/{$parent_entry_id}/sub_entries/{$parent_sub_collection}/{$parent_sub_entry_id}/sub_entries/{$sub_collection}/{$sub_entry_id}.json";
        error_log("deleteSubEntry: Deleting nested sub-entry at: {$entryPath}");
    } else {
        // First-level sub-entry path: collections/{collection}/entries/{entry}/sub_entries/{sub_collection}/{sub_entry}.json
        $entryPath = "collections/{$parent_collection}/entries/{$parent_entry_id}/sub_entries/{$sub_collection}/{$sub_entry_id}.json";
        error_log("deleteSubEntry: Deleting first-level sub-entry at: {$entryPath}");
    }
    
    // Get entry before deletion for hook
    $entry = $storage->read($entryPath);
    
    if (!$entry) {
        error_log("deleteSubEntry: Entry not found at: {$entryPath}");
        return false;
    }
    
    $result = $storage->delete($entryPath);
    
    if (!$result) {
        error_log("deleteSubEntry: Storage delete() returned false for path: {$entryPath}");
        return false;
    }
    
    if ($result && $entry) {
        // Invalidate cache
        if (function_exists('invalidate_sub_entries_cache')) {
            invalidate_sub_entries_cache($parent_collection, $parent_entry_id, $sub_collection);
        }
        
        // Fire hook
        if (function_exists('do_action')) {
            do_action('sub_entry_deleted', $sub_entry_id, $entry, $parent_collection, $parent_entry_id, $sub_collection);
        }
        
        // Log activity
        if (function_exists('log_sub_entry_activity')) {
            log_sub_entry_activity('deleted', $parent_collection, $parent_entry_id, $sub_collection, $sub_entry_id, [
                'title' => $entry['title'] ?? $entry['name'] ?? '',
                'status' => $entry['status'] ?? 'draft'
            ]);
        }
        
        // Trigger automation (if automation system is available)
        if (function_exists('enqueue_automation')) {
            enqueue_automation('entry_deleted', [
                'entry' => $entry,
                'collection' => $sub_collection,
                'parent_collection' => $parent_collection,
                'parent_entry_id' => $parent_entry_id,
                'is_sub_entry' => true
            ]);
        }
    }
    
    return $result;
}

/**
 * Count sub-entries
 * 
 * @param string $parent_collection Parent collection slug
 * @param string $parent_entry_id Parent entry ID
 * @param string $sub_collection Sub-collection slug
 * @param array $filters Optional filters
 * @return int Count of sub-entries
 */
function countSubEntries($parent_collection, $parent_entry_id, $sub_collection, $filters = []) {
    $entries = getSubEntries($parent_collection, $parent_entry_id, $sub_collection, $filters);
    return count($entries);
}

/**
 * Check if a collection has sub-collections configured
 * 
 * @param string $collection_slug Collection slug
 * @return array List of sub-collection configurations
 */
function getSubCollections($collection_slug) {
    if (!function_exists('get_storage')) {
        require_once __DIR__ . '/storage/StorageFactory.php';
    }
    try {
        $storage = get_storage();
    } catch (Exception $e) {
        error_log("Error getting storage in getSubCollections: " . $e->getMessage());
        return [];
    }
    $collectionPath = "collections/{$collection_slug}/collection.json";
    $collection = $storage->read($collectionPath);
    
    if (!$collection) {
        return [];
    }
    
    return $collection['sub_collections'] ?? [];
}

/**
 * Get sub-collection configuration
 * 
 * @param string $collection_slug Parent collection slug
 * @param string $sub_collection_slug Sub-collection slug
 * @return array|null Sub-collection configuration or null
 */
function getSubCollectionConfig($collection_slug, $sub_collection_slug, $parent_sub_collection_slug = null) {
    $subCollections = getSubCollections($collection_slug);
    
    // If parent_sub_collection_slug is provided, search in nested sub-collections
    if ($parent_sub_collection_slug) {
        // Find the parent sub-collection first
        foreach ($subCollections as $subCollection) {
            if (($subCollection['slug'] ?? '') === $parent_sub_collection_slug) {
                // Search in nested sub-collections
                $nestedSubCollections = $subCollection['sub_collections'] ?? [];
                foreach ($nestedSubCollections as $nestedSubCollection) {
                    if (($nestedSubCollection['slug'] ?? '') === $sub_collection_slug) {
                        return $nestedSubCollection;
                    }
                }
                return null;
            }
        }
        return null;
    }
    
    // Search in top-level sub-collections
    foreach ($subCollections as $subCollection) {
        if (($subCollection['slug'] ?? '') === $sub_collection_slug) {
            return $subCollection;
        }
        
        // Also search recursively in nested sub-collections
        $nestedSubCollections = $subCollection['sub_collections'] ?? [];
        foreach ($nestedSubCollections as $nestedSubCollection) {
            if (($nestedSubCollection['slug'] ?? '') === $sub_collection_slug) {
                return $nestedSubCollection;
            }
        }
    }
    
    return null;
}
