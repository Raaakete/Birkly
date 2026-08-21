<?php
/**
 * Serves /for-agents — indexable HTML summary of the published Agent Guide.
 *
 * Website-visiting chat/search AIs read page body text, not JSON endpoints.
 * This page exposes supported claims and products in plain HTML.
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/cors.php';
require_once __DIR__ . '/../core/ai_presence.php';

set_cors_headers('public');

$html = birkly_ai_presence_for_agents_html();
if ($html === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not found\n";
    exit;
}

$meta = birkly_ai_presence_load_meta();
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=300');
header('X-Agent-Guide-Version: ' . (int) ($meta['published_version'] ?? 0));
echo $html;
