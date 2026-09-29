<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
header('Content-Type: application/json');
require_once 'auth.php';
requireAuth('admin');
require_once 'validate.php';
require_once '../core/csrf.php';
require_once '../core/backup.php';
require_once 'log.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$backupFile = '../backup/birkly-backup-' . date('Ymd-His') . '.zip';

if (!is_dir('../backup')) mkdir('../backup', 0755, true);

// Create backup zip
if ($action === 'backup') {
    $created = birkly_backup_create_zip(dirname(__DIR__), $backupFile);
    if (empty($created['ok'])) {
        echo json_encode(['status' => 'error', 'message' => $created['error'] ?? 'Could not create backup file']);
        exit;
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($backupFile) . '"');
    readfile($backupFile);
    unlink($backupFile);
    log_security_event('backup_created', ['user' => $_SESSION['user']['email'] ?? 'unknown']);
    exit;
}

// Restore from backup zip
if ($action === 'restore' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        log_security_event('permission_denied', ['action' => 'backup_restore', 'reason' => 'invalid CSRF']);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    if (!isset($_FILES['backup'])) {
        echo json_encode(['status' => 'error', 'message' => 'No backup file uploaded']);
        exit;
    }
    $file = $_FILES['backup']['tmp_name'];
    $restored = birkly_backup_restore_zip($file, dirname(__DIR__));
    if (empty($restored['ok'])) {
        echo json_encode(['status' => 'error', 'message' => $restored['error'] ?? 'Invalid backup file']);
        exit;
    }
    log_security_event('backup_restored', ['user' => $_SESSION['user']['email'] ?? 'unknown']);
    echo json_encode(['status' => 'ok', 'message' => 'Backup restored']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']); 