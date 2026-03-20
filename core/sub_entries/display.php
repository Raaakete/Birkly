<?php
/**
 * Sub-Entries Display Helpers for Birkly
 * 
 * Provides template helper functions for rendering sub-entries in different display modes.
 */

require_once __DIR__ . '/../sub_entries.php';

/**
 * Render sub-entries in list mode
 * 
 * @param string $parent_collection Parent collection slug
 * @param string $parent_entry_id Parent entry ID
 * @param string $sub_collection Sub-collection slug
 * @param array $options Display options
 * @return string HTML output
 */
function render_sub_entries_list($parent_collection, $parent_entry_id, $sub_collection, $options = []) {
    $filters = $options['filters'] ?? [];
    $sort = $options['sort'] ?? 'created_at';
    $order = $options['order'] ?? 'desc';
    $limit = $options['limit'] ?? null;
    $template = $options['template'] ?? null;
    
    $entries = getSubEntries($parent_collection, $parent_entry_id, $sub_collection, $filters, [
        'sort' => $sort,
        'order' => $order,
        'limit' => $limit
    ]);
    
    if (empty($entries)) {
        return $options['empty_message'] ?? '<p>No entries found.</p>';
    }
    
    $html = '<div class="sub-entries-list">';
    
    foreach ($entries as $entry) {
        if ($template && is_callable($template)) {
            $html .= call_user_func($template, $entry);
        } else {
            $html .= render_sub_entry_item($entry, $options);
        }
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Render sub-entries in tree mode
 * 
 * @param string $parent_collection Parent collection slug
 * @param string $parent_entry_id Parent entry ID
 * @param string $sub_collection Sub-collection slug
 * @param array $options Display options
 * @return string HTML output
 */
function render_sub_entries_tree($parent_collection, $parent_entry_id, $sub_collection, $options = []) {
    $filters = $options['filters'] ?? [];
    $max_depth = $options['max_depth'] ?? null;
    $template = $options['template'] ?? null;
    
    $entries = getDescendants($parent_collection, $parent_entry_id, $sub_collection, $max_depth, $filters);
    
    if (empty($entries)) {
        return $options['empty_message'] ?? '<p>No entries found.</p>';
    }
    
    // Build tree structure
    $tree = build_sub_entries_tree($entries);
    
    $html = '<div class="sub-entries-tree">';
    $html .= render_tree_node($tree, $template, $options);
    $html .= '</div>';
    
    return $html;
}

/**
 * Render sub-entries in threaded mode (forum-style)
 * 
 * @param string $parent_collection Parent collection slug
 * @param string $parent_entry_id Parent entry ID
 * @param string $sub_collection Sub-collection slug
 * @param array $options Display options
 * @return string HTML output
 */
function render_sub_entries_threaded($parent_collection, $parent_entry_id, $sub_collection, $options = []) {
    $filters = $options['filters'] ?? [];
    $sort = $options['sort'] ?? 'created_at';
    $order = $options['order'] ?? 'asc';
    $template = $options['template'] ?? null;
    
    // Get top-level entries (no parent_sub_entry_id)
    $top_level = getSubEntries($parent_collection, $parent_entry_id, $sub_collection, array_merge($filters, [
        'parent_sub_entry_id' => ['operator' => '=', 'value' => null]
    ]), [
        'sort' => $sort,
        'order' => $order
    ]);
    
    if (empty($top_level)) {
        return $options['empty_message'] ?? '<p>No entries found.</p>';
    }
    
    $html = '<div class="sub-entries-threaded">';
    
    foreach ($top_level as $entry) {
        $html .= render_threaded_entry($entry, $parent_collection, $parent_entry_id, $sub_collection, 0, $template, $options);
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Build tree structure from flat list
 * 
 * @param array $entries Flat list of entries
 * @return array Tree structure
 */
function build_sub_entries_tree($entries) {
    $tree = [];
    $indexed = [];
    
    // Index entries by ID
    foreach ($entries as $entry) {
        $indexed[$entry['id']] = $entry;
        $indexed[$entry['id']]['children'] = [];
    }
    
    // Build tree
    foreach ($indexed as $id => $entry) {
        $parent_id = $entry['parent_sub_entry_id'] ?? null;
        
        if ($parent_id && isset($indexed[$parent_id])) {
            $indexed[$parent_id]['children'][] = &$indexed[$id];
        } else {
            $tree[] = &$indexed[$id];
        }
    }
    
    return $tree;
}

/**
 * Render tree node recursively
 * 
 * @param array $node Tree node
 * @param callable|null $template Custom template function
 * @param array $options Options
 * @param int $depth Current depth
 * @return string HTML
 */
function render_tree_node($node, $template = null, $options = [], $depth = 0) {
    if (is_array($node) && isset($node['id'])) {
        // Single node
        $html = '<div class="tree-node" data-depth="' . $depth . '">';
        
        if ($template && is_callable($template)) {
            $html .= call_user_func($template, $node);
        } else {
            $html .= render_sub_entry_item($node, $options);
        }
        
        if (!empty($node['children'])) {
            $html .= '<div class="tree-children">';
            foreach ($node['children'] as $child) {
                $html .= render_tree_node($child, $template, $options, $depth + 1);
            }
            $html .= '</div>';
        }
        
        $html .= '</div>';
        return $html;
    } else {
        // Array of nodes
        $html = '';
        foreach ($node as $n) {
            $html .= render_tree_node($n, $template, $options, $depth);
        }
        return $html;
    }
}

/**
 * Render threaded entry recursively
 * 
 * @param array $entry Entry data
 * @param string $parent_collection Parent collection
 * @param string $parent_entry_id Parent entry ID
 * @param string $sub_collection Sub-collection
 * @param int $depth Current depth
 * @param callable|null $template Custom template
 * @param array $options Options
 * @return string HTML
 */
function render_threaded_entry($entry, $parent_collection, $parent_entry_id, $sub_collection, $depth = 0, $template = null, $options = []) {
    $html = '<div class="threaded-entry" data-depth="' . $depth . '">';
    
    if ($template && is_callable($template)) {
        $html .= call_user_func($template, $entry);
    } else {
        $html .= render_sub_entry_item($entry, $options);
    }
    
    // Get replies (nested sub-entries)
    $replies = getNestedSubEntries($parent_collection, $parent_entry_id, $sub_collection, $entry['id'], [], [
        'sort' => 'created_at',
        'order' => 'asc'
    ]);
    
    if (!empty($replies)) {
        $html .= '<div class="threaded-replies">';
        foreach ($replies as $reply) {
            $html .= render_threaded_entry($reply, $parent_collection, $parent_entry_id, $sub_collection, $depth + 1, $template, $options);
        }
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Render a single sub-entry item
 * 
 * @param array $entry Entry data
 * @param array $options Options
 * @return string HTML
 */
function render_sub_entry_item($entry, $options = []) {
    $html = '<div class="sub-entry-item" data-id="' . htmlspecialchars($entry['id'] ?? '') . '">';
    
    // Title/name
    $title = $entry['title'] ?? $entry['name'] ?? $entry['id'];
    $html .= '<h4 class="sub-entry-title">' . htmlspecialchars($title) . '</h4>';
    
    // Content
    if (isset($entry['content']) || isset($entry['comment']) || isset($entry['text'])) {
        $content = $entry['content'] ?? $entry['comment'] ?? $entry['text'] ?? '';
        $html .= '<div class="sub-entry-content">' . nl2br(htmlspecialchars($content)) . '</div>';
    }
    
    // Meta
    $html .= '<div class="sub-entry-meta">';
    if (isset($entry['created_at'])) {
        $html .= '<span class="sub-entry-date">' . date('Y-m-d H:i', strtotime($entry['created_at'])) . '</span>';
    }
    if (isset($entry['author_name']) || isset($entry['author'])) {
        $author = $entry['author_name'] ?? $entry['author'] ?? 'Unknown';
        $html .= '<span class="sub-entry-author">' . htmlspecialchars($author) . '</span>';
    }
    $html .= '</div>';
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Get sub-entries with display mode
 * 
 * @param string $parent_collection Parent collection slug
 * @param string $parent_entry_id Parent entry ID
 * @param string $sub_collection Sub-collection slug
 * @param string $display_mode Display mode (list, tree, threaded)
 * @param array $options Options
 * @return string HTML output
 */
function render_sub_entries($parent_collection, $parent_entry_id, $sub_collection, $display_mode = 'list', $options = []) {
    switch ($display_mode) {
        case 'tree':
            return render_sub_entries_tree($parent_collection, $parent_entry_id, $sub_collection, $options);
        case 'threaded':
            return render_sub_entries_threaded($parent_collection, $parent_entry_id, $sub_collection, $options);
        case 'list':
        default:
            return render_sub_entries_list($parent_collection, $parent_entry_id, $sub_collection, $options);
    }
}
