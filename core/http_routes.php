<?php
/**
 * Shared HTTP route dispatch for PHP built-in server and nginx index.php fallback.
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';

/**
 * @return array<string, string> Exact path or prefix => handler path under project root.
 */
function birkly_http_route_map(): array
{
    return [
        '/health' => '/api/health.php',
        '/favicon.ico' => '/admin/assets/birkly-icon.svg',
        '/.well-known/oauth-protected-resource' => '/api/oauth_protected_resource.php',
        '/.well-known/oauth-authorization-server' => '/api/oauth_authorization_server.php',
        '/.well-known/brand-context.json' => '/api/well_known_brand_context.php',
        '/.well-known/agent-guide.json' => '/api/well_known_agent_guide.php',
        '/llms.txt' => '/api/llms_txt.php',
        '/sitemap.xml' => '/api/sitemap_xml.php',
        '/for-agents' => '/api/for_agents.php',
        '/for-agents.md' => '/api/for_agents_md.php',
        '/for-agents.json' => '/api/for_agents_json.php',
        '/authorize' => '/api/oauth_authorize.php',
        '/oauth/authorize' => '/api/oauth_authorize.php',
        '/oauth/token' => '/api/oauth_token.php',
        '/oauth/register' => '/api/oauth_register.php',
        '/mcp' => '/api/mcp.php',
    ];
}

/**
 * True when $uri is served by core HTTP route handlers (discovery, OAuth, health, …)
 * before the project not-found fallback in index.php.
 */
function birkly_http_route_is_platform_discovery_path(string $uri): bool
{
    $path = '/' . ltrim(parse_url($uri, PHP_URL_PATH) ?: $uri, '/');
    if ($path === '//') {
        $path = '/';
    }

    foreach (birkly_http_route_map() as $prefix => $_handler) {
        if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
            return true;
        }
    }

    if (preg_match('#^/[a-f0-9]{32}\\.txt$#i', $path)) {
        return true;
    }

    return false;
}

// Bootstrap plugin system at file scope (not inside dispatch) so registries use $GLOBALS.
require_once __DIR__ . '/plugin_public_routes.php';
if (!function_exists('load_plugins')) {
    require_once __DIR__ . '/plugins.php';
}

/**
 * Whether index.php would hand $uri to a core HTTP route handler (without executing it).
 */
function birkly_http_route_uri_has_handler(string $uri): bool
{
    $path = '/' . ltrim(parse_url($uri, PHP_URL_PATH) ?: $uri, '/');
    if ($path === '//') {
        $path = '/';
    }

    require_once __DIR__ . '/ai_presence_discovery.php';
    if (preg_match('#^/([a-f0-9]{32})\\.txt$#i', $path, $m)) {
        $meta = birkly_ai_presence_load_meta();
        $stored = strtolower(trim((string) ($meta['discovery_indexnow_key'] ?? '')));
        if ($stored !== '' && $stored === strtolower($m[1])) {
            return true;
        }
    }

    foreach (birkly_http_route_map() as $prefix => $handler) {
        if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
            $full = __DIR__ . '/..' . $handler;

            return is_file($full);
        }
    }

    return false;
}

function birkly_dispatch_http_route(string $uri): bool
{
    require_once __DIR__ . '/ai_presence_discovery.php';
    if (birkly_ai_presence_discovery_try_serve_indexnow_key($uri)) {
        return true;
    }

    if ($uri === '/favicon.ico') {
        if (!function_exists('birkly_hosting_serve_favicon_if_available')) {
            require_once __DIR__ . '/hosting_routing.php';
        }
        if (birkly_hosting_serve_favicon_if_available()) {
            return true;
        }
    }

    foreach (birkly_http_route_map() as $prefix => $handler) {
        if ($uri === $prefix || str_starts_with($uri, $prefix . '/')) {
            $full = __DIR__ . '/..' . $handler;
            if ($prefix === '/favicon.ico') {
                header('Content-Type: image/svg+xml');
                header('Cache-Control: public, max-age=86400');
                readfile($full);
                return true;
            }
            if (!is_file($full)) {
                http_response_code(503);
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Route handler missing: ' . $handler;
                return true;
            }
            $_SERVER['SCRIPT_NAME'] = birkly_url($handler);
            require $full;
            return true;
        }
    }

    if (function_exists('load_plugins')) {
        load_plugins(dirname(__DIR__) . '/plugins');
    }
    if (function_exists('birkly_dispatch_public_routes') && birkly_dispatch_public_routes($uri)) {
        return true;
    }

    return false;
}
