<?php
/**
 * Notifications API
 * Provides notification data from security.log filtered by user preferences
 */

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

require_once 'session.php';
require_once __DIR__ . '/../core/birkly_paths.php';
haru_secure_session_start();

if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    header('Content-Type: application/json');
}

require_once 'auth.php';
require_once 'storage.php';
require_once '../core/csrf.php';

// Check authentication
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Get action from GET, POST, or input
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $_POST['action'] ?? $input['action'] ?? 'list_recent';

// Get user notification preferences
function getUserNotificationPreferences($userId) {
    $storage = new Storage(__DIR__ . '/../users');
    $user = $storage->read($userId . '.json');
    if (!$user) {
        return getDefaultPreferences();
    }
    return $user['notification_preferences'] ?? getDefaultPreferences();
}

function getDefaultPreferences() {
    return [
        'entry_published' => true,
        'entry_review' => true,
        'entry_commented' => true,
        'invitation_accepted' => true,
        'role_changed' => true,
        'system_updates' => true,
        'security_events' => false
    ];
}

// Get read notification IDs for user
function getReadNotifications($userId) {
    $readFile = __DIR__ . '/../users/' . $userId . '/notifications_read.json';
    if (file_exists($readFile)) {
        $data = json_decode(file_get_contents($readFile), true);
        return $data['read_ids'] ?? [];
    }
    return [];
}

// Mark notification as read
function markNotificationRead($userId, $notificationId) {
    $readDir = __DIR__ . '/../users/' . $userId;
    if (!is_dir($readDir)) {
        mkdir($readDir, 0755, true);
    }
    
    $readFile = $readDir . '/notifications_read.json';
    $readIds = getReadNotifications($userId);
    
    if (!in_array($notificationId, $readIds)) {
        $readIds[] = $notificationId;
        file_put_contents($readFile, json_encode(['read_ids' => $readIds], JSON_PRETTY_PRINT));
    }
}

// Mark all notifications as read
function markAllNotificationsRead($userId, $notificationIds) {
    $readDir = __DIR__ . '/../users/' . $userId;
    if (!is_dir($readDir)) {
        mkdir($readDir, 0755, true);
    }
    
    $readFile = $readDir . '/notifications_read.json';
    $readIds = getReadNotifications($userId);
    
    foreach ($notificationIds as $id) {
        if (!in_array($id, $readIds)) {
            $readIds[] = $id;
        }
    }
    
    file_put_contents($readFile, json_encode(['read_ids' => $readIds], JSON_PRETTY_PRINT));
}

// Map event to notification preference key
function getPreferenceKeyForEvent($event) {
    $mapping = [
        'publish_entry' => 'entry_published',
        'unpublish_entry' => 'entry_published',
        'review_action' => 'entry_review',
        'invitation_accepted' => 'invitation_accepted',
        'role_changed' => 'role_changed',
        'login_failed' => 'security_events',
        'rate_limited' => 'security_events',
        'permission_denied' => 'security_events'
    ];
    return $mapping[$event] ?? null;
}

// Check if event should be shown based on preferences
function shouldShowNotification($event, $preferences) {
    $prefKey = getPreferenceKeyForEvent($event);
    if ($prefKey) {
        return $preferences[$prefKey] ?? false;
    }
    
    // Default: show content events if entry_published is enabled
    $contentEvents = ['create_entry', 'update_entry', 'delete_entry', 'create_collection', 'update_collection'];
    if (in_array($event, $contentEvents)) {
        return $preferences['entry_published'] ?? true;
    }
    
    // Show by default if no specific preference
    return true;
}

// Format notification from log entry
function formatNotification($entry, $currentUserEmail) {
    $event = $entry['event'] ?? '';
    $user = $entry['user'] ?? 'Unknown';
    $details = $entry['details'] ?? [];
    $timestamp = $entry['timestamp'] ?? date('c');
    
    // Normalize emails for comparison (case-insensitive)
    $normalizedUser = strtolower(trim($user));
    $normalizedCurrentUser = strtolower(trim($currentUserEmail));
    
    // Skip own actions UNLESS it's a test notification or a review_action
    // Test notifications have 'test' => true in details
    // Review actions should always show because they're actionable notifications
    if ($normalizedUser === $normalizedCurrentUser && !isset($details['test']) && $event !== 'review_action') {
        return null;
    }
    
    $notification = [
        'id' => md5($event . $timestamp . $user . json_encode($details)),
        'event' => $event,
        'timestamp' => $timestamp,
        'user' => $user,
        'details' => $details
    ];
    
    // Generate message and metadata
    switch ($event) {
        case 'publish_entry':
            // Check if this is a test notification
            if (isset($details['test']) && $details['test'] === true) {
                $notification['message'] = sprintf(
                    'Test: Entry "%s" was published in %s',
                    $details['title'] ?? 'Untitled',
                    $details['collection'] ?? 'collection'
                );
            } else {
                $notification['message'] = sprintf(
                    'Entry "%s" was published in %s',
                    $details['title'] ?? 'Untitled',
                    $details['collection'] ?? 'collection'
                );
            }
            $notification['icon'] = '📝';
            $notification['color'] = 'blue';
            $notification['category'] = 'content';
            if (isset($details['collection']) && isset($details['id'])) {
                $notification['link'] = birkly_admin_url('content', ['collection' => $details['collection'], 'entry' => $details['id']]);
            }
            break;
            
        case 'create_entry':
            $notification['message'] = sprintf(
                'New entry "%s" created in %s',
                $details['title'] ?? 'Untitled',
                $details['collection'] ?? 'collection'
            );
            $notification['icon'] = '✨';
            $notification['color'] = 'green';
            $notification['category'] = 'content';
            if (isset($details['collection']) && isset($details['id'])) {
                $notification['link'] = birkly_admin_url('content', ['collection' => $details['collection'], 'entry' => $details['id']]);
            }
            break;
            
        case 'review_action':
            $notification['message'] = sprintf(
                'Entry "%s" needs review in %s',
                $details['title'] ?? 'Untitled',
                $details['collection'] ?? 'collection'
            );
            $notification['icon'] = '👀';
            $notification['color'] = 'orange';
            $notification['category'] = 'content';
            if (isset($details['collection']) && isset($details['id'])) {
                // Link directly to the entry editor
                $notification['link'] = birkly_admin_url('entry-editor', ['collection' => $details['collection'], 'entry' => $details['id']]);
            }
            break;
            
        case 'invitation_accepted':
            $notification['message'] = sprintf(
                '%s accepted your invitation',
                $details['email'] ?? 'User'
            );
            $notification['icon'] = '👋';
            $notification['color'] = 'green';
            $notification['category'] = 'users';
            $notification['link'] = birkly_admin_url('users');
            break;
            
        case 'role_changed':
            $notification['message'] = sprintf(
                'Your role was changed to %s',
                $details['new_role'] ?? 'unknown'
            );
            $notification['icon'] = '🔑';
            $notification['color'] = 'purple';
            $notification['category'] = 'users';
            $notification['link'] = birkly_admin_url('account');
            break;
            
        case 'login_failed':
            if (isset($details['email']) && $details['email'] === $currentUserEmail) {
                $notification['message'] = 'Failed login attempt detected';
                $notification['icon'] = '⚠️';
                $notification['color'] = 'red';
                $notification['category'] = 'security';
                $notification['link'] = birkly_admin_url('account');
            } else {
                return null; // Don't show other users' failed logins
            }
            break;
            
        case 'rate_limited':
            if (isset($details['ip']) && $details['ip'] === ($_SERVER['REMOTE_ADDR'] ?? '')) {
                $notification['message'] = 'Rate limit exceeded. Please slow down.';
                $notification['icon'] = '⏱️';
                $notification['color'] = 'orange';
                $notification['category'] = 'security';
            } else {
                return null;
            }
            break;
            
        default:
            // Generic notification for other events
            $notification['message'] = ucfirst(str_replace('_', ' ', $event));
            $notification['icon'] = '📢';
            $notification['color'] = 'gray';
            $notification['category'] = 'other';
            break;
    }
    
    return $notification;
}

// Create test notification (for testing purposes) - MUST be checked BEFORE list_recent
if ($action === 'create_test_notification') {
    // Log that we're in the create_test_notification handler
    error_log('create_test_notification handler called, action=' . $action . ', GET=' . ($_GET['action'] ?? 'none') . ', POST=' . ($_POST['action'] ?? 'none'));
    
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $userId = $_SESSION['user']['id'] ?? '';
    $userEmail = $_SESSION['user']['email'] ?? '';
    
    if (empty($userEmail)) {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'User email not found']);
        exit;
    }
    
    // Force immediate write by creating the entry directly
    $logDir = dirname(__DIR__) . '/logs';
    if (!file_exists($logDir)) {
        if (!mkdir($logDir, 0700, true)) {
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Failed to create logs directory']);
            exit;
        }
    }
    $logFile = $logDir . '/security.log';
    
    $testEntry = [
        'timestamp' => date('c'),
        'user' => $userEmail,
        'event' => 'publish_entry',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'details' => [
            'collection' => 'test',
            'id' => 'test-' . time(),
            'title' => 'Test Notification - ' . date('H:i:s'),
            'test' => true
        ]
    ];
    
    $entryJson = json_encode($testEntry) . "\n";
    
    // Write directly to log file - this is the most reliable method
    $result = @file_put_contents($logFile, $entryJson, FILE_APPEND | LOCK_EX);
    $entryWritten = ($result !== false && $result > 0);
    
    // Also use log_security_event as backup (but don't rely on it)
    if (!$entryWritten && file_exists(__DIR__ . '/log.php')) {
        try {
            require_once __DIR__ . '/log.php';
            log_security_event('publish_entry', $testEntry['details']);
            $entryWritten = true;
        } catch (Exception $e) {
            // Ignore errors from log_security_event
        }
    }
    
    // Force flush and verify
    if ($entryWritten) {
        clearstatcache(false, $logFile);
        // Small delay to ensure file system has written
        usleep(200000); // 0.2 seconds
        
        // Verify by reading the file
        $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines) {
            $lastLine = end($lines);
            $entryWritten = $lastLine && (
                strpos($lastLine, '"test":true') !== false || 
                strpos($lastLine, $testEntry['details']['title']) !== false ||
                strpos($lastLine, 'publish_entry') !== false
            );
        }
    }
    
    // Log to error log for debugging
    error_log('Test notification creation: entryWritten=' . ($entryWritten ? 'true' : 'false') . ', bytes=' . $result . ', file=' . $logFile);
    if ($entryWritten) {
        error_log('Test entry written: ' . substr($entryJson, 0, 200));
    } else {
        error_log('Test entry FAILED to write. Result: ' . var_export($result, true) . ', file writable: ' . (is_writable($logFile) ? 'yes' : 'no') . ', dir writable: ' . (is_writable($logDir) ? 'yes' : 'no'));
    }
    
    ob_end_clean();
    $response = [
        'status' => $entryWritten ? 'ok' : 'error',
        'message' => $entryWritten
            ? 'Test notification created. Check the bell icon in the top-right corner.'
            : 'Failed to create test notification.',
        'debug' => [
            'entry_created' => $entryWritten,
            'bytes_written' => $result,
            'timestamp' => $testEntry['timestamp'],
            'log_file' => $logFile,
            'log_exists' => file_exists($logFile),
            'log_writable' => is_writable($logFile),
            'log_readable' => is_readable($logFile),
            'user_email' => $userEmail,
            'entry_preview' => substr($entryJson, 0, 100)
        ]
    ];
    error_log('Test notification API response: ' . json_encode($response));
    echo json_encode($response);
    exit;
}

// List recent notifications
if ($action === 'list_recent') {
    try {
        $limit = intval($_GET['limit'] ?? 20);
        $userId = $_SESSION['user']['id'];
        $userEmail = $_SESSION['user']['email'] ?? '';
        $preferences = getUserNotificationPreferences($userId);
        $readIds = getReadNotifications($userId);
        
        $notifications = [];
        $logFile = __DIR__ . '/../logs/security.log';
        
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_reverse($lines); // Most recent first
            
            $cutoffTime = strtotime('-7 days'); // Only show last 7 days
            
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if (!$entry || !is_array($entry)) {
                    continue;
                }
                
                $timestamp = $entry['timestamp'] ?? '';
                $entryTime = strtotime($timestamp);
                if ($entryTime < $cutoffTime) {
                    continue; // Too old
                }
                
                $event = $entry['event'] ?? '';
                $details = $entry['details'] ?? [];
                $entryUser = $entry['user'] ?? '';
                
                // Filter: Only show notifications where the log entry's user matches current user
                // OR it's a review_action event (which should show to the reviewer)
                // OR it's a test notification
                $isTestNotification = isset($details['test']) && $details['test'] === true;
                $isReviewAction = ($event === 'review_action');
                
                // For review_action, check if current user is the reviewer
                // Use case-insensitive comparison for emails
                $normalizedUserEmail = strtolower(trim($userEmail));
                $normalizedEntryUser = strtolower(trim($entryUser));
                
                if ($isReviewAction && !$isTestNotification) {
                    $reviewerEmail = strtolower(trim($details['reviewer'] ?? $entryUser));
                    error_log("DEBUG NOTIFICATION: review_action found - reviewer=" . $reviewerEmail . ", currentUser=" . $normalizedUserEmail . ", entryUser=" . $normalizedEntryUser);
                    if ($reviewerEmail !== $normalizedUserEmail && $normalizedEntryUser !== $normalizedUserEmail) {
                        error_log("DEBUG NOTIFICATION: Skipping - not for this user");
                        continue; // Not for this user
                    }
                    error_log("DEBUG NOTIFICATION: Matched - showing notification");
                } elseif (!$isTestNotification && $normalizedEntryUser !== $normalizedUserEmail) {
                    // For other events, only show if user matches
                    continue; // Not for this user
                }
                
                // Test notifications always show (bypass preference check)
                if (!$isTestNotification && !shouldShowNotification($event, $preferences)) {
                    continue; // Filtered by preferences
                }
                
                // For test notifications, create notification directly
                if ($isTestNotification) {
                    $notificationId = md5($event . $timestamp . $userEmail . json_encode($details));
                    $notification = [
                        'id' => $notificationId,
                        'event' => $event,
                        'timestamp' => $timestamp,
                        'user' => $entry['user'] ?? $userEmail,
                        'details' => $details,
                        'message' => sprintf(
                            'Test: Entry "%s" was published in %s',
                            $details['title'] ?? 'Untitled',
                            $details['collection'] ?? 'collection'
                        ),
                        'icon' => '📝',
                        'color' => 'blue',
                        'category' => 'content',
                        'read' => in_array($notificationId, $readIds)
                    ];
                } else {
                    $notification = formatNotification($entry, $userEmail);
                    if (!$notification) {
                        continue; // Filtered out
                    }
                    
                    // Set read status based on readIds
                    $notificationId = $notification['id'];
                    $notification['read'] = in_array($notificationId, $readIds);
                }
                
                $notifications[] = $notification;
                
                if (count($notifications) >= $limit) {
                    break;
                }
            }
        }
        
        $unreadCount = count(array_filter($notifications, function($n) {
            return !$n['read'];
        }));
        
        ob_end_clean();
        echo json_encode([
            'status' => 'ok',
            'notifications' => $notifications,
            'unread_count' => $unreadCount
        ]);
        exit;
        
    } catch (Exception $e) {
        ob_end_clean();
        error_log('Notifications API error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to load notifications'
        ]);
        exit;
    }
}

// Get unread count only
if ($action === 'unread_count') {
    try {
        $userId = $_SESSION['user']['id'];
        $userEmail = $_SESSION['user']['email'] ?? '';
        $preferences = getUserNotificationPreferences($userId);
        $readIds = getReadNotifications($userId);
        
        $unreadCount = 0;
        $logFile = __DIR__ . '/../logs/security.log';
        
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_reverse($lines);
            
            $cutoffTime = strtotime('-7 days');
            $seenIds = [];
            
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if (!$entry || !is_array($entry)) {
                    continue;
                }
                
                $timestamp = $entry['timestamp'] ?? '';
                $entryTime = strtotime($timestamp);
                if ($entryTime < $cutoffTime) {
                    continue;
                }
                
                $event = $entry['event'] ?? '';
                $details = $entry['details'] ?? [];
                $entryUser = $entry['user'] ?? '';
                
                // Filter: Only show notifications where the log entry's user matches current user
                // OR it's a review_action event (which should show to the reviewer)
                // OR it's a test notification
                $isTestNotification = isset($details['test']) && $details['test'] === true;
                $isReviewAction = ($event === 'review_action');
                
                // For review_action, check if current user is the reviewer
                // Use case-insensitive comparison for emails
                $normalizedUserEmail = strtolower(trim($userEmail));
                $normalizedEntryUser = strtolower(trim($entryUser));
                
                if ($isReviewAction && !$isTestNotification) {
                    $reviewerEmail = strtolower(trim($details['reviewer'] ?? $entryUser));
                    if ($reviewerEmail !== $normalizedUserEmail && $normalizedEntryUser !== $normalizedUserEmail) {
                        continue; // Not for this user
                    }
                } elseif (!$isTestNotification && $normalizedEntryUser !== $normalizedUserEmail) {
                    // For other events, only show if user matches
                    continue; // Not for this user
                }
                
                // Test notifications always show (bypass preference check)
                // Note: $isTestNotification already set above
                
                if (!$isTestNotification && !shouldShowNotification($event, $preferences)) {
                    continue;
                }
                
                $notification = formatNotification($entry, $userEmail);
                if (!$notification) {
                    continue;
                }
                
                $notificationId = $notification['id'];
                if (in_array($notificationId, $seenIds)) {
                    continue; // Deduplicate
                }
                $seenIds[] = $notificationId;
                
                if (!in_array($notificationId, $readIds)) {
                    $unreadCount++;
                }
            }
        }
        
        ob_end_clean();
        echo json_encode([
            'status' => 'ok',
            'unread_count' => $unreadCount
        ]);
        exit;
        
    } catch (Exception $e) {
        ob_end_clean();
        error_log('Notifications API error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to get unread count'
        ]);
        exit;
    }
}

// Mark notification as read
if ($action === 'mark_read') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $userId = $_SESSION['user']['id'];
    $notificationId = $input['id'] ?? '';
    
    if (empty($notificationId)) {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Notification ID required']);
        exit;
    }
    
    markNotificationRead($userId, $notificationId);
    
    ob_end_clean();
    echo json_encode(['status' => 'ok', 'message' => 'Notification marked as read']);
    exit;
}

// Mark all as read
if ($action === 'mark_all_read') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $userId = $_SESSION['user']['id'];
    $userEmail = $_SESSION['user']['email'] ?? '';
    $preferences = getUserNotificationPreferences($userId);
    
    // Get all recent notification IDs
    $notificationIds = [];
    $logFile = __DIR__ . '/../logs/security.log';
    
    if (file_exists($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $lines = array_reverse($lines);
        
        $cutoffTime = strtotime('-7 days');
        
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if (!$entry || !is_array($entry)) {
                continue;
            }
            
            $timestamp = $entry['timestamp'] ?? '';
            $entryTime = strtotime($timestamp);
            if ($entryTime < $cutoffTime) {
                continue;
            }
            
            $event = $entry['event'] ?? '';
            if (!shouldShowNotification($event, $preferences)) {
                continue;
            }
            
            $notification = formatNotification($entry, $userEmail);
            if (!$notification) {
                continue;
            }
            
            $notificationIds[] = $notification['id'];
        }
    }
    
    markAllNotificationsRead($userId, $notificationIds);
    
    ob_end_clean();
    echo json_encode(['status' => 'ok', 'message' => 'All notifications marked as read']);
    exit;
}

// Default response
ob_end_clean();
echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
exit;

