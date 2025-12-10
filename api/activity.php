<?php
// api/activity.php - Recent activity tracking for dashboard

// Only execute API code if called directly, not when included
if (basename($_SERVER['SCRIPT_NAME']) === 'activity.php') {
    header('Content-Type: application/json');
    require_once 'session.php';
    haru_secure_session_start();
    
    require_once 'storage.php';
    require_once 'auth.php';
    
    // Check authentication
    requireAuth('view_dashboard');
    
    $action = $_GET['action'] ?? '';
    
    if ($action === 'recent') {
        $limit = intval($_GET['limit'] ?? 10);
        $offset = intval($_GET['offset'] ?? 0);
        $filter = $_GET['filter'] ?? ''; // 'all', 'content', 'users', 'system'
        
        $activity = [];
        $lines = [];
        
        // Read from security log for recent activity
        $logFile = __DIR__ . '/../logs/security.log';
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_reverse($lines); // Newest first
            $totalLines = count($lines);
            
            $processed = 0;
            foreach ($lines as $line) {
                if ($processed >= ($offset + $limit)) break;
                
                $entry = json_decode($line, true);
                if (!$entry || !is_array($entry)) {
                    // Try old format (pipe-separated)
                    $parts = explode(' | ', $line);
                    if (count($parts) >= 3) {
                        $entry = [
                            'timestamp' => $parts[0] ?? '',
                            'event' => $parts[1] ?? '',
                            'details' => json_decode($parts[2] ?? '{}', true) ?: []
                        ];
                    } else {
                        continue;
                    }
                }
                
                $event = $entry['event'] ?? '';
                $timestamp = $entry['timestamp'] ?? '';
                $user = $entry['user'] ?? 'Unknown';
                $details = $entry['details'] ?? [];
                
                // Apply filter
                if ($filter && $filter !== 'all') {
                    $category = getEventCategory($event);
                    if ($category !== $filter) {
                        continue;
                    }
                }
                
                // Format activity item
                $activityItem = formatActivityItem($event, $user, $details, $timestamp);
                if ($activityItem) {
                    if ($processed >= $offset) {
                        $activity[] = $activityItem;
                    }
                    $processed++;
                }
            }
        }
        
        echo json_encode([
            'status' => 'ok',
            'data' => [
                'activity' => $activity,
                'total' => count($lines),
                'limit' => $limit,
                'offset' => $offset
            ]
        ]);
        exit;
    }
    
    if ($action === 'list') {
        $limit = intval($_GET['limit'] ?? 50);
        $offset = intval($_GET['offset'] ?? 0);
        $filter = $_GET['filter'] ?? '';
        
        $activity = [];
        $logFile = __DIR__ . '/../logs/security.log';
        
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_reverse($lines);
            
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if (!$entry || !is_array($entry)) {
                    continue;
                }
                
                $event = $entry['event'] ?? '';
                $timestamp = $entry['timestamp'] ?? '';
                $user = $entry['user'] ?? 'Unknown';
                $details = $entry['details'] ?? [];
                
                if ($filter && $filter !== 'all') {
                    $category = getEventCategory($event);
                    if ($category !== $filter) {
                        continue;
                    }
                }
                
                $activityItem = formatActivityItem($event, $user, $details, $timestamp);
                if ($activityItem) {
                    $activity[] = $activityItem;
                    if (count($activity) >= $limit) break;
                }
            }
        }
        
        echo json_encode([
            'status' => 'ok',
            'activity' => $activity,
            'total' => count($activity)
        ]);
        exit;
    }
    
    // Default response
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid action'
    ]);
    exit;
}

// Helper functions are always available (for use when included)
function getEventCategory($event) {
    $contentEvents = ['create_entry', 'update_entry', 'delete_entry', 'create_collection', 'update_collection', 'delete_collection', 'publish_entry', 'unpublish_entry', 'scheduled_publish', 'review_action'];
    $userEvents = ['create_user', 'update_user', 'delete_user', 'invitation_created', 'invitation_accepted'];
    $systemEvents = ['login_success', 'login_failed', 'logout', 'rate_limited'];
    
    if (in_array($event, $contentEvents)) return 'content';
    if (in_array($event, $userEvents)) return 'users';
    if (in_array($event, $systemEvents)) return 'system';
    
    return 'other';
}

function formatActivityItem($event, $user, $details, $timestamp) {
    $icon = getEventIcon($event);
    $message = getEventMessage($event, $details);
    $color = getEventColor($event);
    
    return [
        'event' => $event,
        'user' => $user,
        'message' => $message,
        'icon' => $icon,
        'color' => $color,
        'timestamp' => $timestamp,
        'details' => $details,
        'category' => getEventCategory($event)
    ];
}

function getEventIcon($event) {
    $icons = [
        'create_entry' => '📝',
        'update_entry' => '✏️',
        'delete_entry' => '🗑️',
        'create_collection' => '📁',
        'publish_entry' => '✅',
        'unpublish_entry' => '❌',
        'scheduled_publish' => '⏰',
        'review_action' => '👁️',
        'create_user' => '👤',
        'invitation_created' => '✉️',
        'login_success' => '🔓',
        'login_failed' => '🔒',
        'logout' => '🚪',
        'upload_media' => '📷'
    ];
    
    return $icons[$event] ?? '📌';
}

function getEventMessage($event, $details) {
    switch ($event) {
        case 'create_entry':
            $collection = $details['collection'] ?? 'collection';
            $title = $details['title'] ?? $details['id'] ?? 'entry';
            return "Created entry \"{$title}\" in {$collection}";
            
        case 'update_entry':
            $collection = $details['collection'] ?? 'collection';
            $title = $details['title'] ?? $details['id'] ?? 'entry';
            return "Updated entry \"{$title}\" in {$collection}";
            
        case 'delete_entry':
            $collection = $details['collection'] ?? 'collection';
            return "Deleted entry from {$collection}";
            
        case 'create_collection':
            $name = $details['name'] ?? 'collection';
            return "Created collection \"{$name}\"";
            
        case 'publish_entry':
            $collection = $details['collection'] ?? 'collection';
            return "Published entry in {$collection}";
            
        case 'scheduled_publish':
            $collection = $details['collection'] ?? 'collection';
            return "Scheduled entry in {$collection}";
            
        case 'review_action':
            $action = $details['action'] ?? 'reviewed';
            $collection = $details['collection'] ?? 'collection';
            return "{$action} entry in {$collection}";
            
        case 'create_user':
            $email = $details['email'] ?? 'user';
            return "Created user {$email}";
            
        case 'invitation_created':
            $email = $details['email'] ?? 'user';
            return "Sent invitation to {$email}";
            
        case 'invitation_accepted':
            $email = $details['email'] ?? 'user';
            return "{$email} accepted invitation";
            
        case 'login_success':
            return "User logged in";
            
        case 'login_failed':
            $email = $details['email'] ?? 'unknown';
            return "Failed login attempt for {$email}";
            
        case 'logout':
            return "User logged out";
            
        case 'upload_media':
            $name = $details['name'] ?? 'file';
            return "Uploaded media: {$name}";
            
        default:
            return ucfirst(str_replace('_', ' ', $event));
    }
}

function getEventColor($event) {
    $colors = [
        'create_entry' => '#28a745',
        'update_entry' => '#17a2b8',
        'delete_entry' => '#dc3545',
        'publish_entry' => '#28a745',
        'review_action' => '#ffc107',
        'login_success' => '#28a745',
        'login_failed' => '#dc3545',
        'upload_media' => '#6f42c1'
    ];
    
    return $colors[$event] ?? '#6c757d';
} 