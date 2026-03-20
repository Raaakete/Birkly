<?php
/**
 * Sub-Entry Activity API for Birkly
 * 
 * Provides API endpoints for retrieving sub-entry activity streams.
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../core/sub_entries/activity.php';

// Require authentication
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

// Get activities
if ($action === 'list' || $action === '') {
    $filters = [];
    
    if (isset($_GET['parent_collection'])) {
        $filters['parent_collection'] = $_GET['parent_collection'];
    }
    if (isset($_GET['parent_entry_id'])) {
        $filters['parent_entry_id'] = $_GET['parent_entry_id'];
    }
    if (isset($_GET['sub_collection'])) {
        $filters['sub_collection'] = $_GET['sub_collection'];
    }
    if (isset($_GET['sub_entry_id'])) {
        $filters['sub_entry_id'] = $_GET['sub_entry_id'];
    }
    if (isset($_GET['action'])) {
        $filters['action'] = $_GET['action'];
    }
    if (isset($_GET['user_id'])) {
        $filters['user_id'] = $_GET['user_id'];
    }
    if (isset($_GET['date_from'])) {
        $filters['date_from'] = $_GET['date_from'];
    }
    if (isset($_GET['date_to'])) {
        $filters['date_to'] = $_GET['date_to'];
    }
    
    $options = [
        'sort' => $_GET['sort'] ?? 'timestamp',
        'order' => $_GET['order'] ?? 'desc',
        'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : 50,
        'offset' => isset($_GET['offset']) ? (int)$_GET['offset'] : 0
    ];
    
    $activities = get_sub_entry_activities($filters, $options);
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'activities' => $activities,
            'count' => count($activities)
        ]
    ]);
    exit;
}

// Get activity stream for entry
if ($action === 'entry_stream') {
    $parent_collection = $_GET['parent_collection'] ?? '';
    $parent_entry_id = $_GET['parent_entry_id'] ?? '';
    
    if (empty($parent_collection) || empty($parent_entry_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Parent collection and entry ID required']);
        exit;
    }
    
    $options = [
        'sort' => $_GET['sort'] ?? 'timestamp',
        'order' => $_GET['order'] ?? 'desc',
        'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : 50,
        'offset' => isset($_GET['offset']) ? (int)$_GET['offset'] : 0
    ];
    
    $activities = get_entry_activity_stream($parent_collection, $parent_entry_id, $options);
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'activities' => $activities,
            'count' => count($activities)
        ]
    ]);
    exit;
}

// Get activity stream for sub-entry
if ($action === 'sub_entry_stream') {
    $sub_collection = $_GET['sub_collection'] ?? '';
    $sub_entry_id = $_GET['sub_entry_id'] ?? '';
    
    if (empty($sub_collection) || empty($sub_entry_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Sub-collection and sub-entry ID required']);
        exit;
    }
    
    $options = [
        'sort' => $_GET['sort'] ?? 'timestamp',
        'order' => $_GET['order'] ?? 'desc',
        'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : 50,
        'offset' => isset($_GET['offset']) ? (int)$_GET['offset'] : 0
    ];
    
    $activities = get_sub_entry_activity_stream($sub_collection, $sub_entry_id, $options);
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'activities' => $activities,
            'count' => count($activities)
        ]
    ]);
    exit;
}

// Get activity summary
if ($action === 'summary') {
    $filters = [];
    
    if (isset($_GET['parent_collection'])) {
        $filters['parent_collection'] = $_GET['parent_collection'];
    }
    if (isset($_GET['parent_entry_id'])) {
        $filters['parent_entry_id'] = $_GET['parent_entry_id'];
    }
    if (isset($_GET['sub_collection'])) {
        $filters['sub_collection'] = $_GET['sub_collection'];
    }
    if (isset($_GET['date_from'])) {
        $filters['date_from'] = $_GET['date_from'];
    }
    if (isset($_GET['date_to'])) {
        $filters['date_to'] = $_GET['date_to'];
    }
    
    $summary = get_sub_entry_activity_summary($filters);
    
    echo json_encode([
        'status' => 'ok',
        'data' => $summary
    ]);
    exit;
}

// Clear old activities (admin only)
if ($action === 'clear_old') {
    // Check admin permission
    if (!has_permission('admin')) {
        echo json_encode(['status' => 'error', 'message' => 'Admin permission required']);
        exit;
    }
    
    $days_to_keep = isset($_GET['days']) ? (int)$_GET['days'] : 90;
    $removed = clear_old_sub_entry_activities($days_to_keep);
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'removed' => $removed,
            'days_kept' => $days_to_keep
        ]
    ]);
    exit;
}

// Unknown action
echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
exit;
