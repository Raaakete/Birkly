<?php
/**
 * Serves /llms-full.txt — optional publish-time snapshot with fetched source text.
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/cors.php';
require_once __DIR__ . '/../core/ai_presence.php';
require_once __DIR__ . '/../core/agent_guide_public_export.php';
require_once __DIR__ . '/../core/ai_presence_discovery.php';

set_cors_headers('public');

$body = birkly_agent_guide_public_export_llms_full_body();
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
birkly_ai_presence_discovery_send_http_link_headers();
echo $body;