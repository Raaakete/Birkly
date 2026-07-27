<?php
/**
 * Serves /.well-known/brand-context.json from published Agent Guide snapshot (legacy path).
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/cors.php';
require_once __DIR__ . '/../core/ai_presence.php';

set_cors_headers('public');

$body = birkly_ai_presence_get_public_export_body();
if ($body === null) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Not found']);
    exit;
}

$meta = birkly_ai_presence_load_meta();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');
header('X-AI-Presence-Version: ' . (int) ($meta['published_version'] ?? 0));
echo $body;
