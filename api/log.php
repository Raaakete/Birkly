<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
// Security logging helper for HaruCMS

require_once 'auth.php';

function log_security_event($event, $details = []) {
    $logDir = dirname(__DIR__) . '/logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0700, true);
    }
    $logFile = $logDir . '/security.log';
    $entry = [
        'timestamp' => date('c'),
        'user' => $_SESSION['user']['email'] ?? 'guest',
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'details' => $details
    ];
    file_put_contents($logFile, json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);
}

// API: Get recent log entries
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
    echo json_encode([
        'status' => 'ok',
        'data' => [ 'logs' => $entries ]
    ]);
    exit;
} 