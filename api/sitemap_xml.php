<?php
/**
 * Serves /sitemap.xml for Agent Guide discovery (P66).
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/cors.php';
require_once __DIR__ . '/../core/ai_presence_discovery.php';

set_cors_headers('public');

if (!birkly_ai_presence_is_publicly_live()) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not found\n";
    exit;
}

$settings = birkly_ai_presence_discovery_publication_settings();
if (!$settings['discovery_sitemap']) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not found\n";
    exit;
}

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=300');
echo birkly_ai_presence_discovery_sitemap_xml();
