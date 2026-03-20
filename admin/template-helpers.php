<?php
/**
 * Template Helper Functions
 * These functions are called from templates to perform complex operations
 */

/**
 * Build breadcrumbs for entry editor/detail pages
 * Returns array of breadcrumb items with label and url
 */
function build_entry_breadcrumbs($data) {
    $breadcrumbs = [];
    
    // Add "Content" link
    $breadcrumbs[] = [
        'label' => __('admin.content'),
        'url' => '/admin/index.php?page=content',
        'is_current' => false
    ];
    
    // Add collection link
    $collectionSlug = $data['collection']['slug'] ?? '';
    $collectionName = $data['collection']['name'] ?? $data['collection']['title'] ?? $collectionSlug ?? 'Collection';
    $breadcrumbs[] = [
        'label' => $collectionName,
        'url' => '/admin/index.php?page=entries&collection=' . urlencode($collectionSlug),
        'is_current' => false
    ];
    
    // If this is a sub-entry, add parent entry link
    if (!empty($data['is_sub_entry'])) {
        $mainEntryId = $data['parent_entry_id'] ?? $data['entry']['_parent_entry_id'] ?? '';
        $mainEntryTitle = $data['entry']['_parent_entry_title'] ?? 'Parent Entry';
        
        $breadcrumbs[] = [
            'label' => $mainEntryTitle,
            'url' => '/admin/index.php?page=entry-detail&collection=' . urlencode($collectionSlug) . '&entry=' . urlencode($mainEntryId),
            'is_current' => false
        ];
        
        // If this is a nested sub-entry, add parent sub-entry link
        if (!empty($data['is_nested_sub_entry']) && !empty($data['parent_sub_collection']) && !empty($data['parent_sub_entry_id'])) {
            $parentSubCollectionSlug = $data['parent_sub_collection'];
            $parentSubEntryId = $data['parent_sub_entry_id'];
            $parentSubEntryTitle = $data['entry']['_parent_sub_entry_title'] ?? 'Parent Sub-Entry';
            
            $breadcrumbs[] = [
                'label' => $parentSubCollectionSlug . ': ' . $parentSubEntryTitle,
                'url' => '/admin/index.php?page=entry-detail&collection=' . urlencode($collectionSlug) . 
                        '&entry=' . urlencode($mainEntryId) . 
                        '&sub_collection=' . urlencode($parentSubCollectionSlug) . 
                        '&sub_entry=' . urlencode($parentSubEntryId),
                'is_current' => false
            ];
        }
        
        // Add current entry/sub-entry
        $subCollectionSlug = $data['sub_collection'] ?? 'Sub-Entry';
        $entryTitle = !empty($data['entry']['title']) ? $data['entry']['title'] : __('admin.new_entry');
        $breadcrumbs[] = [
            'label' => $subCollectionSlug . ': ' . $entryTitle,
            'url' => '',
            'is_current' => true
        ];
    } else {
        // Regular entry - add current entry
        $entryTitle = !empty($data['entry']['title']) ? $data['entry']['title'] : __('admin.new_entry');
        $breadcrumbs[] = [
            'label' => $entryTitle,
            'url' => '',
            'is_current' => true
        ];
    }
    
    return $breadcrumbs;
}

/**
 * Build cancel URL for entry editor
 * Returns the appropriate URL to return to based on context
 */
function build_cancel_url($data) {
    $collectionSlug = $data['collection']['slug'] ?? '';
    
    // Check for explicit return_to parameter
    if (!empty($_GET['return_to'])) {
        return urldecode($_GET['return_to']);
    }
    
    // For sub-entries
    if (!empty($data['is_sub_entry'])) {
        $mainEntryId = $data['parent_entry_id'] ?? $data['entry']['_parent_entry_id'] ?? '';
        
        // Check for cancel_to parameters (explicitly passed)
        if (!empty($_GET['cancel_to_sub_collection']) && !empty($_GET['cancel_to_sub_entry'])) {
            return '/admin/index.php?page=entry-detail&collection=' . urlencode($collectionSlug) . 
                   '&entry=' . urlencode($mainEntryId) . 
                   '&sub_collection=' . urlencode($_GET['cancel_to_sub_collection']) . 
                   '&sub_entry=' . urlencode($_GET['cancel_to_sub_entry']);
        }
        
        // If editing existing sub-entry with nested sub-collections, go to sub-entry detail
        if (!empty($data['sub_entry_id']) && !empty($data['collection']['sub_collections'])) {
            $currentSubCollectionSlug = $data['sub_collection'] ?? '';
            $hasNestedSubCollections = false;
            
            foreach ($data['collection']['sub_collections'] as $subCol) {
                if (($subCol['slug'] ?? '') === $currentSubCollectionSlug && !empty($subCol['sub_collections'])) {
                    $hasNestedSubCollections = true;
                    break;
                }
            }
            
            if ($hasNestedSubCollections) {
                $url = '/admin/index.php?page=entry-detail&collection=' . urlencode($collectionSlug) . 
                       '&entry=' . urlencode($mainEntryId) . 
                       '&sub_collection=' . urlencode($currentSubCollectionSlug) . 
                       '&sub_entry=' . urlencode($data['sub_entry_id']);
                
                // Include parent sub-entry info if this is nested
                if (!empty($data['parent_sub_collection']) && !empty($data['parent_sub_entry_id'])) {
                    $url .= '&parent_sub_collection=' . urlencode($data['parent_sub_collection']) . 
                           '&parent_sub_entry=' . urlencode($data['parent_sub_entry_id']);
                }
                
                return $url;
            }
        }
        
        // If creating nested sub-entry, go back to parent sub-entry detail
        if (!empty($data['parent_sub_collection']) && !empty($data['parent_sub_entry_id'])) {
            return '/admin/index.php?page=entry-detail&collection=' . urlencode($collectionSlug) . 
                   '&entry=' . urlencode($mainEntryId) . 
                   '&sub_collection=' . urlencode($data['parent_sub_collection']) . 
                   '&sub_entry=' . urlencode($data['parent_sub_entry_id']);
        }
        
        // For first-level sub-entries: Always return to parent entry detail page
        // The presence of parent_entry_id means we came from an entry detail page
        if (!empty($mainEntryId)) {
            return '/admin/index.php?page=entry-detail&collection=' . urlencode($collectionSlug) . '&entry=' . urlencode($mainEntryId);
        }
        
        // Fallback to entries list (shouldn't normally reach here for sub-entries)
        return '/admin/index.php?page=entries&collection=' . urlencode($collectionSlug);
    }
    
    // For regular entries
    $entryId = $data['entry']['id'] ?? $_GET['entry'] ?? null;
    $isEditingMainEntry = !empty($entryId);
    
    // Check HTTP_REFERER for better navigation
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (!empty($referer)) {
        $refererParts = parse_url($referer);
        if (!empty($refererParts['query'])) {
            parse_str($refererParts['query'], $refererParams);
            
            // If came from entry-detail page, go back there
            if (!empty($refererParams['page']) && $refererParams['page'] === 'entry-detail' &&
                !empty($refererParams['entry']) && !empty($refererParams['collection'])) {
                return '/admin/index.php?page=entry-detail&collection=' . urlencode($refererParams['collection']) . 
                       '&entry=' . urlencode($refererParams['entry']);
            }
        }
    }
    
    // Default to entries list
    return '/admin/index.php?page=entries&collection=' . urlencode($collectionSlug);
}

/**
 * Build save button text based on context
 */
function get_save_button_text($data) {
    if (!empty($data['entry']['id'])) {
        return __('admin.update_entry');
    }
    return __('admin.create_entry');
}

/**
 * Check if cancel button should be shown
 */
function should_show_cancel_button($data) {
    // Always show cancel button for now
    // Could add more complex logic here if needed
    return true;
}
