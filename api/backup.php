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
require_once 'log.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$backupDirs = [
    '../content',
    '../users',
    '../media',
    '../settings'
];
$backupFile = '../backup/harucms-backup-' . date('Ymd-His') . '.zip';

if (!is_dir('../backup')) mkdir('../backup', 0755, true);

// Create backup zip
if ($action === 'backup') {
    $zip = new ZipArchive();
    if ($zip->open($backupFile, ZipArchive::CREATE) !== TRUE) {
        echo json_encode(['status' => 'error', 'message' => 'Could not create backup file']);
        exit;
    }
    foreach ($backupDirs as $dir) {
        $dir = rtrim($dir, '/');
        $base = basename($dir);
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $localPath = $base . '/' . substr($filePath, strlen($dir) + 1);
            if ($file->isDir()) {
                $zip->addEmptyDir($localPath);
            } else {
                $zip->addFile($filePath, $localPath);
            }
        }
    }
    $zip->close();
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
    $zip = new ZipArchive();
    if ($zip->open($file) !== TRUE) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid backup file']);
        exit;
    }
    $zip->extractTo('..');
    $zip->close();
    log_security_event('backup_restored', ['user' => $_SESSION['user']['email'] ?? 'unknown']);
    echo json_encode(['status' => 'ok', 'message' => 'Backup restored']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']); 