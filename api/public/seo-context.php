<?php
/**
 * Public SEO context for CSR project sites (P68-M7).
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');

header('Content-Type: application/json');

require_once __DIR__ . '/../../core/cors.php';
set_cors_headers('public');

require_once __DIR__ . '/../../core/site_seo.php';
require_once __DIR__ . '/../../core/site_seo_url_map.php';

if (!birkly_site_seo_is_published() || !birkly_site_seo_should_auto_inject()) {
    echo json_encode(['status' => 'ok', 'data' => ['enabled' => false]]);
    exit;
}

$path = trim((string) ($_GET['path'] ?? '/'));
$query = $_GET;
unset($query['path']);

$config = birkly_site_seo_load();
$etag = substr(hash('sha256', json_encode([
    $config['published_at'] ?? '',
    $path,
    $query,
]) ?: ''), 0, 16);
header('Cache-Control: public, max-age=120');
header('ETag: "' . $etag . '"');
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH'], '"') === $etag) {
    http_response_code(304);
    exit;
}

$context = birkly_seo_public_context($path, $query);
echo json_encode([
    'status' => 'ok',
    'data' => array_merge(['enabled' => true], $context),
]);
