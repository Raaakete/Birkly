<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
// Security logging helper for Birkly

require_once 'auth.php';

function log_security_event($event, $details = [], $user = null) {
    $logDir = dirname(__DIR__) . '/logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0700, true);
    }
    $logFile = $logDir . '/security.log';
    $actor = $user;
    if ($actor === null || $actor === '') {
        $actor = $_SESSION['user']['email'] ?? 'guest';
    }
    $entry = [
        'timestamp' => date('c'),
        'user' => $actor,
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'details' => $details
    ];
    file_put_contents($logFile, json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);
}

// API: Get recent log entries
// Only execute if this file is called directly, not when included
if (basename($_SERVER['SCRIPT_NAME']) === 'log.php') {
    if (isset($_GET['action']) && $_GET['action'] === 'recent') {
        requireAuth(['admin', 'manage_settings']);
        $logFile = dirname(__DIR__) . '/logs/security.log';
        $entries = [];
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_reverse($lines);
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if ($entry) {
                    $entries[] = $entry;
                }
                if (count($entries) >= 10) break;
            }
        }
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'ok',
            'data' => [ 'logs' => $entries ]
        ]);
        exit;
    }

    // API: Get all log entries (for audit log viewer)
    if (isset($_GET['action']) && $_GET['action'] === 'list') {
        header('Content-Type: application/json');
        require_once 'auth.php';
        requireAuth(['admin']);
        
        $logFile = dirname(__DIR__) . '/logs/security.log';
        $entries = [];
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_reverse($lines); // Newest first
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if ($entry) {
                    $entries[] = $entry;
                }
            }
        }
        echo json_encode([
            'status' => 'ok',
            'logs' => $entries
        ]);
        exit;
    }
} 