<?php
/**
 * Serves /sitemap.xml for Discovery SEO + Agent Guide (P66/P67).
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/cors.php';
require_once __DIR__ . '/../core/ai_presence_discovery.php';
require_once __DIR__ . '/../core/site_seo.php';

set_cors_headers('public');

$seoLive = birkly_site_seo_is_published() && birkly_site_seo_surface_enabled('sitemap');
$agSettings = birkly_ai_presence_discovery_publication_settings();
$agLive = birkly_ai_presence_is_publicly_live() && !empty($agSettings['discovery_sitemap']);

if (!$seoLive && !$agLive) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not found\n";
    exit;
}

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=300');
echo birkly_ai_presence_discovery_sitemap_xml();
