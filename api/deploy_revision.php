<?php
/**
 * Public deploy health check only (P88).
 *
 * Load balancers and Dokploy use this URL with no configuration.
 * Admin diagnostics: api/admin_deploy_diagnostics.php (session + permissions).
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../core/birkly_paths.php';
require_once __DIR__ . '/../core/deploy_health.php';

$action = trim((string) ($_GET['action'] ?? ''));
if ($action !== '') {
    http_response_code(410);
    echo json_encode([
        'status' => 'error',
        'message' => 'Deploy diagnostics moved to the admin API',
        'use' => '/api/admin_deploy_diagnostics.php',
        'docs' => '/admin/deploy-check.php',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(birkly_public_deploy_health_payload(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
