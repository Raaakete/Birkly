<?php
// api/activity.php - Recent activity tracking for dashboard

header('Content-Type: application/json');
require_once 'session.php';
haru_secure_session_start();

require_once 'storage.php';
require_once 'auth.php';

// Check authentication
requireAuth('view_dashboard');

$action = $_GET['action'] ?? '';

if ($action === 'recent') {
    $activity = [];
    
    // Read from security log for recent activity
    $logFile = __DIR__ . '/../logs/security.log';
    if (file_exists($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $recentLines = array_slice($lines, -10); // Get last 10 lines
        
        foreach ($recentLines as $line) {
            $parts = explode(' | ', $line);
            if (count($parts) >= 3) {
                $timestamp = $parts[0] ?? '';
                $action = $parts[1] ?? '';
                $details = $parts[2] ?? '';
                
                // Format the activity message
                $message = '';
                switch ($action) {
                    case 'login_success':
                        $message = 'User logged in successfully';
                        break;
                    case 'create_entry':
                        $message = 'New entry created';
                        break;
                    case 'update_entry':
                        $message = 'Entry updated';
                        break;
                    case 'delete_entry':
                        $message = 'Entry deleted';
                        break;
                    case 'create_collection':
                        $message = 'New collection created';
                        break;
                    case 'upload_media':
                        $message = 'Media file uploaded';
                        break;
                    case 'create_user':
                        $message = 'New user created';
                        break;
                    default:
                        $message = ucfirst(str_replace('_', ' ', $action));
                }
                
                if ($message) {
                    $activity[] = $message;
                }
            }
        }
    }
    
    // If no activity found, show default messages
    if (empty($activity)) {
        $activity = [
            'Dashboard loaded',
            'System is running normally',
            'All services operational'
        ];
    }
    
    // Limit to 5 most recent activities
    $activity = array_slice(array_reverse($activity), 0, 5);
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'activity' => $activity
        ]
    ]);
    exit;
}

// Default response
echo json_encode([
    'status' => 'error',
    'message' => 'Invalid action'
]); 