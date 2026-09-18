<?php
// api/updates.php - Birkly Update Management API

require_once __DIR__ . '/session.php';
haru_secure_session_start();

require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/version.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/log.php';

// Clear any output that might have been generated
if (ob_get_level()) {
    ob_clean();
}

header('Content-Type: application/json');

requireAuth('manage_settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $csrf = $input['_csrf'] ?? '';
    
    if (!validate_csrf_token($csrf)) {
        log_security_event('update_failed', ['reason' => 'invalid_csrf', 'action' => $action, 'user' => $_SESSION['user']['email']]);
        echo json_encode(['status' => 'error', 'message' => 'Invalid security token']);
        exit;
    }
    
    switch ($action) {
        case 'check_updates':
            checkForUpdates();
            break;
        case 'download_update':
            downloadUpdate($input);
            break;
        case 'install_update':
            installUpdate($input);
            break;
        case 'get_update_status':
            getUpdateStatus();
            break;
        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}

function checkForUpdates() {
    try {
        $status = BirklyVersion::getUpdateStatus();

        if (($status['status'] ?? '') !== 'ok') {
            $message = (string) ($status['message'] ?? 'Update check failed');
            log_security_event('update_check_failed', ['error' => $message, 'user' => $_SESSION['user']['email']]);
            echo json_encode(['status' => 'error', 'message' => $message]);
            return;
        }

        if (!empty($status['update_check_error'])) {
            log_security_event('update_check_failed', ['error' => $status['update_check_error'], 'user' => $_SESSION['user']['email']]);
        } else {
            log_security_event('update_check', [
                'current_version' => $status['current_version'],
                'latest_version' => $status['latest_version'],
                'update_available' => $status['update_available'],
                'user' => $_SESSION['user']['email'],
            ]);
        }

        $attention = null;
        if (file_exists(__DIR__ . '/../core/cms_update_attention.php')) {
            require_once __DIR__ . '/../core/cms_update_attention.php';
            $sync = birkly_cms_update_attention_sync($status, true);
            $attention = [
                'show_modal' => !empty($sync['show_modal']),
                'emitted' => (int) ($sync['emitted'] ?? 0),
            ];
        }

        echo json_encode([
            'status' => 'ok',
            'data' => array_merge($status, $attention !== null ? ['attention' => $attention] : []),
        ]);
        
    } catch (Exception $e) {
        log_security_event('update_check_error', ['error' => $e->getMessage(), 'user' => $_SESSION['user']['email']]);
        require_once __DIR__ . '/../core/api_response.php';
        echo json_encode(['status' => 'error', 'message' => api_sanitize_error_message($e, 'Update check failed')]);
    }
}

function downloadUpdate($data) {
    $version = $data['version'] ?? '';
    
    if (empty($version)) {
        echo json_encode(['status' => 'error', 'message' => 'Version required']);
        return;
    }
    
    try {
        $result = BirklyVersion::downloadUpdate($version);
        
        if (isset($result['error'])) {
            log_security_event('update_download_failed', ['version' => $version, 'error' => $result['error'], 'user' => $_SESSION['user']['email']]);
            echo json_encode(['status' => 'error', 'message' => $result['error']]);
            return;
        }
        
        log_security_event('update_downloaded', [
            'version' => $version,
            'size' => $result['size'],
            'user' => $_SESSION['user']['email']
        ]);
        
        echo json_encode([
            'status' => 'ok',
            'message' => 'Update downloaded successfully',
            'data' => [
                'version' => $result['version'],
                'size' => $result['size'],
                'temp_file' => basename($result['temp_file'])
            ]
        ]);
        
    } catch (Exception $e) {
        log_security_event('update_download_error', ['version' => $version, 'error' => $e->getMessage(), 'user' => $_SESSION['user']['email']]);
        require_once __DIR__ . '/../core/api_response.php';
        echo json_encode(['status' => 'error', 'message' => api_sanitize_error_message($e, 'Download failed')]);
    }
}

function installUpdate($data) {
    $version = $data['version'] ?? '';
    $tempFile = $data['temp_file'] ?? '';
    
    if (empty($version) || empty($tempFile)) {
        echo json_encode(['status' => 'error', 'message' => 'Version and temp file required']);
        return;
    }
    
    // Security check: ensure temp file is in temp directory and has expected name pattern
    $expectedPattern = '/^birkly-update-' . preg_quote($version, '/') . '\.zip$/';
    if (!preg_match($expectedPattern, $tempFile)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid temp file']);
        return;
    }
    
    $fullTempPath = sys_get_temp_dir() . '/' . $tempFile;
    if (!file_exists($fullTempPath)) {
        echo json_encode(['status' => 'error', 'message' => 'Temp file not found']);
        return;
    }
    
    try {
        $result = BirklyVersion::installUpdate($fullTempPath, $version);
        
        if (isset($result['error'])) {
            log_security_event('update_install_failed', ['version' => $version, 'error' => $result['error'], 'user' => $_SESSION['user']['email']]);
            echo json_encode(['status' => 'error', 'message' => $result['error']]);
            return;
        }
        
        log_security_event('update_installed', [
            'version' => $version,
            'backup_location' => $result['backup_location'],
            'user' => $_SESSION['user']['email']
        ]);
        
        echo json_encode([
            'status' => 'ok',
            'message' => 'Update installed successfully',
            'data' => [
                'version' => $result['version'],
                'backup_location' => $result['backup_location']
            ]
        ]);
        
    } catch (Exception $e) {
        log_security_event('update_install_error', ['version' => $version, 'error' => $e->getMessage(), 'user' => $_SESSION['user']['email']]);
        require_once __DIR__ . '/../core/api_response.php';
        echo json_encode(['status' => 'error', 'message' => api_sanitize_error_message($e, 'Installation failed')]);
    }
}

function getUpdateStatus() {
    try {
        $status = BirklyVersion::getUpdateStatus();
        echo json_encode($status);
        
    } catch (Exception $e) {
        log_security_event('update_status_error', ['error' => $e->getMessage(), 'user' => $_SESSION['user']['email']]);
        require_once __DIR__ . '/../core/api_response.php';
        echo json_encode(['status' => 'error', 'message' => api_sanitize_error_message($e, 'Failed to get update status')]);
    }
}
?>
