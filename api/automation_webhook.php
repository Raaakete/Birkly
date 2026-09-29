<?php
/**
 * Public URL for Automation inbound webhooks.
 * The handler lives in the Automation plugin. This file only boots the plugin and dispatches.
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

header('Content-Type: application/json');

require_once __DIR__ . '/../core/plugin_availability.php';
if (!birkly_bootstrap_active_plugin('automation') || !function_exists('automation_handle_inbound_webhook')) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Automation plugin is not active']);
    exit;
}

automation_handle_inbound_webhook();
