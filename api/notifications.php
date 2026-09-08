<?php
/**
 * Notifications API
 * Provides notification data from security.log filtered by user preferences
 */

$__cmsMcpNotificationsFunctionsOnly = !empty($GLOBALS['__CMS_MCP_LOAD_FUNCTIONS_ONLY']);

if (!$__cmsMcpNotificationsFunctionsOnly) {
    ob_start();
}
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

require_once 'session.php';
require_once __DIR__ . '/../core/birkly_paths.php';
haru_secure_session_start();

if (!$__cmsMcpNotificationsFunctionsOnly && !headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    header('Content-Type: application/json');
}

require_once 'auth.php';
require_once 'storage.php';
require_once '../core/csrf.php';
require_once __DIR__ . '/../core/notification_broadcast.php';
require_once __DIR__ . '/../core/plugins/notification_shelf.php';

if (!$__cmsMcpNotificationsFunctionsOnly) {
    // Check authentication
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    // Get action from GET, POST, or input
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $_GET['action'] ?? $_POST['action'] ?? $input['action'] ?? 'list_recent';
}

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

// Get read / seen notification IDs for user (P69)
function getNotificationUserState($userId): array
{
    $readFile = __DIR__ . '/../users/' . $userId . '/notifications_read.json';
    if (!file_exists($readFile)) {
        return ['read_ids' => [], 'seen_ids' => []];
    }
    $data = json_decode((string) file_get_contents($readFile), true);
    if (!is_array($data)) {
        return ['read_ids' => [], 'seen_ids' => []];
    }
    $readIds = is_array($data['read_ids'] ?? null) ? $data['read_ids'] : [];
    $seenIds = is_array($data['seen_ids'] ?? null) ? $data['seen_ids'] : [];
    return ['read_ids' => $readIds, 'seen_ids' => $seenIds];
}

function getReadNotifications($userId) {
    return getNotificationUserState($userId)['read_ids'];
}

function getSeenNotifications($userId): array
{
    return getNotificationUserState($userId)['seen_ids'];
}

function writeNotificationUserState($userId, array $readIds, array $seenIds): void
{
    $readDir = __DIR__ . '/../users/' . $userId;
    if (!is_dir($readDir)) {
        mkdir($readDir, 0755, true);
    }
    $readFile = $readDir . '/notifications_read.json';
    file_put_contents($readFile, json_encode([
        'read_ids' => array_values(array_unique($readIds)),
        'seen_ids' => array_values(array_unique($seenIds)),
    ], JSON_PRETTY_PRINT));
}

/**
 * @param list<array<string, mixed>> $notifications
 * @return array{badge_state: string, pending_count: int, unread_count: int}
 */
function birkly_notifications_badge_metrics(array $notifications, array $readIds, array $seenIds): array
{
    $pending = 0;
    $fresh = 0;
    foreach ($notifications as $notification) {
        $id = (string) ($notification['id'] ?? '');
        $read = !empty($notification['read']) || ($id !== '' && in_array($id, $readIds, true));
        if ($read) {
            continue;
        }
        $pending++;
        $seen = $id !== '' && in_array($id, $seenIds, true);
        if (!$seen) {
            $fresh++;
        }
    }
    if ($pending === 0) {
        return ['badge_state' => 'none', 'pending_count' => 0, 'unread_count' => 0];
    }
    if ($fresh > 0) {
        return ['badge_state' => 'unread', 'pending_count' => $pending, 'unread_count' => $fresh];
    }
    return ['badge_state' => 'seen', 'pending_count' => $pending, 'unread_count' => 0];
}

// Mark notification as read
function markNotificationRead($userId, $notificationId) {
    $state = getNotificationUserState($userId);
    $readIds = $state['read_ids'];
    $seenIds = $state['seen_ids'];
    
    if (!in_array($notificationId, $readIds, true)) {
        $readIds[] = $notificationId;
    }
    if (!in_array($notificationId, $seenIds, true)) {
        $seenIds[] = $notificationId;
    }
    writeNotificationUserState($userId, $readIds, $seenIds);
}

function markNotificationsSeen($userId, array $notificationIds): void
{
    $state = getNotificationUserState($userId);
    $readIds = $state['read_ids'];
    $seenIds = $state['seen_ids'];
    foreach ($notificationIds as $id) {
        $id = (string) $id;
        if ($id === '' || in_array($id, $seenIds, true)) {
            continue;
        }
        $seenIds[] = $id;
    }
    writeNotificationUserState($userId, $readIds, $seenIds);
}

// Mark all notifications as read
function markAllNotificationsRead($userId, $notificationIds) {
    $state = getNotificationUserState($userId);
    $readIds = $state['read_ids'];
    $seenIds = $state['seen_ids'];
    
    foreach ($notificationIds as $id) {
        $id = (string) $id;
        if ($id === '') {
            continue;
        }
        if (!in_array($id, $readIds, true)) {
            $readIds[] = $id;
        }
        if (!in_array($id, $seenIds, true)) {
            $seenIds[] = $id;
        }
    }
    
    writeNotificationUserState($userId, $readIds, $seenIds);
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
        'permission_denied' => 'security_events',
        // Commerce events intentionally unmapped → shown by default
        // (see shouldShowNotification fallback).
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

    if (birkly_notification_apply_formatter($notification, $entry, $currentUserEmail)) {
        if (!isset($notification['message'])) {
            return null;
        }
        return $notification;
    }
    
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
            $notification['message'] = $details['message']
                ?? ucfirst(str_replace('_', ' ', $event));
            $notification['icon'] = '📢';
            $notification['color'] = 'gray';
            $notification['category'] = 'other';
            if (!empty($details['link'])) {
                $notification['link'] = $details['link'];
            }
            break;
    }
    
    return $notification;
}

/**
 * @return array{notifications: list<array<string, mixed>>, unread_count: int, pending_count: int, badge_state: string}
 */
function birkly_notifications_list_recent(int $limit = 20): array
{
    $userId = (string) ($_SESSION['user']['id'] ?? '');
    $userEmail = (string) ($_SESSION['user']['email'] ?? '');
    $preferences = getUserNotificationPreferences($userId);
    $readIds = getReadNotifications($userId);
    $seenIds = getSeenNotifications($userId);
    $notifications = [];
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
            $details = $entry['details'] ?? [];
            $entryUser = $entry['user'] ?? '';

            if (birkly_notifications_skip_security_log_for_bus($event)) {
                continue;
            }

            $isTestNotification = isset($details['test']) && $details['test'] === true;
            $isReviewAction = ($event === 'review_action');
            $normalizedUserEmail = strtolower(trim($userEmail));
            $normalizedEntryUser = strtolower(trim($entryUser));

            if ($isReviewAction && !$isTestNotification) {
                $reviewerEmail = strtolower(trim($details['reviewer'] ?? $entryUser));
                if ($reviewerEmail !== $normalizedUserEmail && $normalizedEntryUser !== $normalizedUserEmail) {
                    continue;
                }
            } elseif (
                !$isTestNotification
                && $normalizedEntryUser !== $normalizedUserEmail
                && !isBroadcastNotificationActor($entryUser, $event)
            ) {
                continue;
            }

            if (!$isTestNotification && !shouldShowNotification($event, $preferences)) {
                continue;
            }

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
                    'read' => in_array($notificationId, $readIds, true),
                ];
            } else {
                $notification = formatNotification($entry, $userEmail);
                if (!$notification) {
                    continue;
                }
                $notificationId = $notification['id'];
                $notification['read'] = in_array($notificationId, $readIds, true);
            }

            $notifications[] = $notification;
            if (count($notifications) >= $limit) {
                break;
            }
        }
    }

    birkly_notifications_load_plugin_activity();
    if (function_exists('birkly_plugin_activity_list')) {
        foreach (birkly_plugin_activity_list(50) as $item) {
            if (empty($item['notify'])) {
                continue;
            }
            $id = (string) ($item['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $notifications[] = [
                'id' => $id,
                'event' => 'plugin_activity',
                'timestamp' => $item['created_at'] ?? date('c'),
                'user' => (string) ($item['plugin'] ?? 'plugin'),
                'details' => $item['meta'] ?? [],
                'message' => trim((string) (($item['title'] ?? '') . (($item['body'] ?? '') !== '' ? ' — ' . $item['body'] : ''))),
                'icon' => '🔔',
                'color' => 'mint',
                'category' => 'plugin',
                'link' => $item['link'] ?? '',
                'plugin' => $item['plugin'] ?? '',
                'read' => !empty($item['read_at']) || in_array($id, $readIds, true),
            ];
        }
        usort($notifications, static function ($a, $b) {
            return strcmp((string) ($b['timestamp'] ?? ''), (string) ($a['timestamp'] ?? ''));
        });
        $notifications = array_slice($notifications, 0, $limit);
    }

    foreach ($notifications as &$notificationRow) {
        $nid = (string) ($notificationRow['id'] ?? '');
        $isRead = !empty($notificationRow['read']);
        $notificationRow['seen'] = $isRead || ($nid !== '' && in_array($nid, $seenIds, true));
    }
    unset($notificationRow);

    $badgeMetrics = birkly_notifications_badge_metrics($notifications, $readIds, $seenIds);

    return [
        'notifications' => $notifications,
        'unread_count' => (int) ($badgeMetrics['unread_count'] ?? 0),
        'pending_count' => (int) ($badgeMetrics['pending_count'] ?? 0),
        'badge_state' => (string) ($badgeMetrics['badge_state'] ?? 'none'),
    ];
}

/** @return array{unread_count: int} */
function birkly_notifications_unread_count_data(): array
{
    $userId = (string) ($_SESSION['user']['id'] ?? '');
    $userEmail = (string) ($_SESSION['user']['email'] ?? '');
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

            if (birkly_notifications_skip_security_log_for_bus($event)) {
                continue;
            }

            $isTestNotification = isset($details['test']) && $details['test'] === true;
            $isReviewAction = ($event === 'review_action');
            $normalizedUserEmail = strtolower(trim($userEmail));
            $normalizedEntryUser = strtolower(trim($entryUser));

            if ($isReviewAction && !$isTestNotification) {
                $reviewerEmail = strtolower(trim($details['reviewer'] ?? $entryUser));
                if ($reviewerEmail !== $normalizedUserEmail && $normalizedEntryUser !== $normalizedUserEmail) {
                    continue;
                }
            } elseif (
                !$isTestNotification
                && $normalizedEntryUser !== $normalizedUserEmail
                && !isBroadcastNotificationActor($entryUser, $event)
            ) {
                continue;
            }

            if (!$isTestNotification && !shouldShowNotification($event, $preferences)) {
                continue;
            }

            $notification = formatNotification($entry, $userEmail);
            if (!$notification) {
                continue;
            }

            $notificationId = $notification['id'];
            if (in_array($notificationId, $seenIds, true)) {
                continue;
            }
            $seenIds[] = $notificationId;

            if (!in_array($notificationId, $readIds, true)) {
                $unreadCount++;
            }
        }
    }

    birkly_notifications_load_plugin_activity();
    if (function_exists('birkly_plugin_activity_unread_notify_count')) {
        $unreadCount += birkly_plugin_activity_unread_notify_count($readIds);
    }

    return ['unread_count' => $unreadCount];
}

if (!$__cmsMcpNotificationsFunctionsOnly) {

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
        $data = birkly_notifications_list_recent($limit);

        ob_end_clean();
        echo json_encode(array_merge(['status' => 'ok'], $data));
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
        $data = birkly_notifications_unread_count_data();

        ob_end_clean();
        echo json_encode(array_merge(['status' => 'ok'], $data));
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

// Mark notifications as seen (dropdown opened — P69)
if ($action === 'mark_seen') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }

    $userId = $_SESSION['user']['id'];
    $ids = $input['ids'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }
    markNotificationsSeen($userId, $ids);

    ob_end_clean();
    echo json_encode(['status' => 'ok']);
    exit;
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

    birkly_notifications_load_plugin_activity();
    if (function_exists('birkly_plugin_activity_mark_read')) {
        birkly_plugin_activity_mark_read([$notificationId]);
    }
    
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
    
    birkly_notifications_load_plugin_activity();
    if (function_exists('birkly_plugin_activity_notify_ids')) {
        $notificationIds = array_values(array_unique(array_merge(
            $notificationIds,
            birkly_plugin_activity_notify_ids(false)
        )));
        if (function_exists('birkly_plugin_activity_mark_read')) {
            birkly_plugin_activity_mark_read($notificationIds);
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

}
