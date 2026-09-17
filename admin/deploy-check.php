<?php
/**
 * Deploy verification — build, hosting, and install diagnostics for this server.
 * Requires admin login. Visit: /admin/deploy-check.php
 */

require_once __DIR__ . '/auth_middleware.php';
requireAdminAuth();
requireAdminPermission('read_settings');

require_once __DIR__ . '/../core/birkly_paths.php';
require_once __DIR__ . '/../core/build_info.php';
require_once __DIR__ . '/../core/deploy_diagnostics.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode([
    'status' => 'ok',
    'build' => birkly_build_info(),
    'deploy' => birkly_deploy_verbose_payload(),
    'plugins' => birkly_deploy_plugin_diagnostics()['plugins'],
    'api' => [
        'diagnostics' => birkly_url('/api/admin_deploy_diagnostics.php'),
        'public_health' => birkly_url('/api/deploy_revision.php'),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
