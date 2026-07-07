<?php
/**
 * Automation API gate — delegates to the automation plugin when installed and active.
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

require_once __DIR__ . '/../core/plugins.php';

header('Content-Type: application/json');

if (!birkly_plugin_is_installed_and_active('automation')
    || !birkly_plugin_file_exists('automation', 'api/automation.php')) {
    http_response_code(404);
    echo json_encode([
        'status' => 'error',
        'message' => 'Automation plugin is not available',
    ]);
    exit;
}

require_once birkly_plugin_dir('automation') . '/api/automation.php';
