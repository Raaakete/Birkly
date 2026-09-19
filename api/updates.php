<?php
// api/updates.php - Birkly Update Management API

require_once __DIR__ . '/session.php';
haru_secure_session_start();

require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/api_response.php';
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
        api_json_echo(['status' => 'error', 'message' => 'Invalid security token']);
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
            api_json_echo(['status' => 'error', 'message' => 'Invalid action']);
    }
} else {
    http_response_code(405);
    api_json_echo(['status' => 'error', 'message' => 'Method not allowed']);
}

function checkForUpdates() {
    try {
        $status = BirklyVersion::getUpdateStatus();

        if (($status['status'] ?? '') !== 'ok') {
            $message = (string) ($status['message'] ?? 'Update check failed');
            log_security_event('update_check_failed', ['error' => $message, 'user' => $_SESSION['user']['email']]);
            api_json_echo(['status' => 'error', 'message' => $message]);
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
            try {
                require_once __DIR__ . '/../core/cms_update_attention.php';
                $sync = birkly_cms_update_attention_sync($status, true);
                $attention = [
                    'show_modal' => !empty($sync['show_modal']),
                    'emitted' => (int) ($sync['emitted'] ?? 0),
                ];
            } catch (Throwable $attentionError) {
                error_log('cms_update_attention_sync failed: ' . $attentionError->getMessage());
            }
        }

        api_json_echo([
            'status' => 'ok',
            'data' => array_merge($status, $attention !== null ? ['attention' => $attention] : []),
        ]);
        
    } catch (Throwable $e) {
        log_security_event('update_check_error', ['error' => $e->getMessage(), 'user' => $_SESSION['user']['email']]);
        api_json_echo(['status' => 'error', 'message' => api_sanitize_error_message($e, 'Update check failed')]);
    }
}

function downloadUpdate($data) {
    $version = $data['version'] ?? '';
    
    if (empty($version)) {
        api_json_echo(['status' => 'error', 'message' => 'Version required']);
        return;
    }
    
    try {
        $result = BirklyVersion::downloadUpdate($version);
        
        if (isset($result['error'])) {
            log_security_event('update_download_failed', ['version' => $version, 'error' => $result['error'], 'user' => $_SESSION['user']['email']]);
            api_json_echo(['status' => 'error', 'message' => $result['error']]);
            return;
        }
        
        log_security_event('update_downloaded', [
            'version' => $version,
            'size' => $result['size'],
            'user' => $_SESSION['user']['email']
        ]);
        
        api_json_echo([
            'status' => 'ok',
            'message' => 'Update downloaded successfully',
            'data' => [
                'version' => $result['version'],
                'size' => $result['size'],
                'temp_file' => basename($result['temp_file'])
            ]
        ]);
        
    } catch (Throwable $e) {
        log_security_event('update_download_error', ['version' => $version, 'error' => $e->getMessage(), 'user' => $_SESSION['user']['email']]);
        api_json_echo(['status' => 'error', 'message' => api_sanitize_error_message($e, 'Download failed')]);
    }
}

function installUpdate($data) {
    $version = $data['version'] ?? '';
    $tempFile = $data['temp_file'] ?? '';
    
    if (empty($version) || empty($tempFile)) {
        api_json_echo(['status' => 'error', 'message' => 'Version and temp file required']);
        return;
    }
    
    $expectedBasename = BirklyVersion::updatePackageBasename($version);
    if ($tempFile !== $expectedBasename) {
        api_json_echo(['status' => 'error', 'message' => 'Invalid temp file']);
        return;
    }

    $fullTempPath = BirklyVersion::resolveDownloadedUpdatePath($version, $tempFile);
    if ($fullTempPath === null) {
        api_json_echo(['status' => 'error', 'message' => 'Update package not found. Download the update again before installing.']);
        return;
    }
    
    try {
        $result = BirklyVersion::installUpdate($fullTempPath, $version);
        
        if (isset($result['error'])) {
            log_security_event('update_install_failed', ['version' => $version, 'error' => $result['error'], 'user' => $_SESSION['user']['email']]);
            api_json_echo(['status' => 'error', 'message' => $result['error']]);
            return;
        }
        
        log_security_event('update_installed', [
            'version' => $version,
            'backup_location' => $result['backup_location'],
            'user' => $_SESSION['user']['email']
        ]);
        
        api_json_echo([
            'status' => 'ok',
            'message' => 'Update installed successfully',
            'data' => [
                'version' => $result['version'],
                'backup_location' => $result['backup_location']
            ]
        ]);
        
    } catch (Throwable $e) {
        log_security_event('update_install_error', ['version' => $version, 'error' => $e->getMessage(), 'user' => $_SESSION['user']['email']]);
        api_json_echo(['status' => 'error', 'message' => api_sanitize_error_message($e, 'Installation failed')]);
    }
}

function getUpdateStatus() {
    try {
        $status = BirklyVersion::getUpdateStatus();
        api_json_echo($status);
        
    } catch (Throwable $e) {
        log_security_event('update_status_error', ['error' => $e->getMessage(), 'user' => $_SESSION['user']['email']]);
        api_json_echo(['status' => 'error', 'message' => api_sanitize_error_message($e, 'Failed to get update status')]);
    }
}
