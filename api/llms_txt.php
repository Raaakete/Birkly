<?php
/**
 * Serves /llms.txt for Lighthouse Agentic Browsing (P60).
 *
 * Built from the published Agent Guide so agents get a short Markdown map of
 * the brand plus links to the guide JSON and key site URLs. Returns 404 when
 * the guide is not published (Lighthouse then marks the audit N/A).
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/cors.php';
require_once __DIR__ . '/../core/ai_presence.php';

set_cors_headers('public');

$body = birkly_ai_presence_llms_txt_body();
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
