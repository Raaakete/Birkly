<?php
/**
 * Serves RSS feeds for SEO detail bindings with feed_enabled.
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/cors.php';
require_once __DIR__ . '/../core/site_seo_feed.php';

set_cors_headers('public');

if (!birkly_site_seo_is_published() || !birkly_site_seo_surface_enabled('rss')) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not found\n";
    exit;
}

$uri = (string) ($_SERVER['REQUEST_URI'] ?? '/feed.xml');
$feedDef = birkly_seo_feed_for_path($uri);
if ($feedDef === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not found\n";
    exit;
}

header('Content-Type: application/rss+xml; charset=utf-8');
header('Cache-Control: public, max-age=300');
echo birkly_seo_feed_rss_xml($feedDef);