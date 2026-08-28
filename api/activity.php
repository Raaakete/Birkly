<?php
// api/activity.php - Recent activity tracking for dashboard

require_once __DIR__ . '/../core/activity_format.php';

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
        $filter = $_GET['filter'] ?? 'all';
        $severity = $_GET['severity'] ?? 'all';

        $security = birkly_activity_read_security_log($limit + $offset, 0, $filter, $severity);
        $plugin = birkly_activity_read_plugin_bus(50);
        $merged = birkly_activity_merge_sorted([$security, $plugin], $limit + $offset);
        $activity = array_slice($merged, $offset, $limit);

        $logFile = __DIR__ . '/../logs/security.log';
        $totalLines = is_file($logFile) ? count(file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : 0;

        echo json_encode([
            'status' => 'ok',
            'data' => [
                'activity' => $activity,
                'total' => max($totalLines, count($merged)),
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);
        exit;
    }

    if ($action === 'list') {
        $limit = intval($_GET['limit'] ?? 50);
        $offset = intval($_GET['offset'] ?? 0);
        $filter = $_GET['filter'] ?? 'all';
        $severity = $_GET['severity'] ?? 'all';

        $security = birkly_activity_read_security_log($limit + $offset + 200, 0, $filter, $severity);
        $plugin = birkly_activity_read_plugin_bus(100);
        $merged = birkly_activity_merge_sorted([$security, $plugin], $limit + $offset + 200);
        $activity = array_slice($merged, $offset, $limit);

        echo json_encode([
            'status' => 'ok',
            'activity' => $activity,
            'total' => count($merged),
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid action',
    ]);
    exit;
}
