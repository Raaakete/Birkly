<?php
/**
 * Deploy verification — confirms which CMS build is running on this server.
 * Requires admin login. Visit: /admin/deploy-check.php
 */

require_once __DIR__ . '/auth_middleware.php';
requireAdminAuth();

require_once __DIR__ . '/../core/birkly_paths.php';
require_once __DIR__ . '/../core/build_info.php';

header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'build' => birkly_build_info(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
