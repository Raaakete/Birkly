<?php
/**
 * Serves /for-agents.md — markdown alias for the published Agent Guide.
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/cors.php';
require_once __DIR__ . '/../core/ai_presence.php';

set_cors_headers('public');

$body = birkly_ai_presence_for_agents_markdown();
if ($body === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not found\n";
    exit;
}

$meta = birkly_ai_presence_load_meta();
header('Content-Type: text/markdown; charset=utf-8');
header('Cache-Control: public, max-age=300');
header('X-Agent-Guide-Version: ' . (int) ($meta['published_version'] ?? 0));
echo $body;
