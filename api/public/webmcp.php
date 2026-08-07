<?php
/**
 * WebMCP public config endpoint (P60-M3).
 * ---------------------------------------
 * Secret-free projection the browser runtime (`birkly-webmcp.js`) reads to know
 * which tools are enabled + which public form collections may be submitted.
 *
 * Never returns admin keys or draft data. When WebMCP is off it returns
 * `{enabled:false}` so an off site is indistinguishable from one that never
 * heard of WebMCP.
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');

header('Content-Type: application/json');

require_once __DIR__ . '/../../core/cors.php';
set_cors_headers('public');

require_once __DIR__ . '/../../core/webmcp.php';

// Short cache is fine — config changes are infrequent and the runtime also
// re-reads on birkly:templates-processed.
header('Cache-Control: public, max-age=30');

echo json_encode(['status' => 'ok', 'data' => birkly_webmcp_public_config()]);
