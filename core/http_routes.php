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
        '/for-agents' => '/api/for_agents.php',
        '/authorize' => '/api/oauth_authorize.php',
        '/oauth/authorize' => '/api/oauth_authorize.php',
        '/oauth/token' => '/api/oauth_token.php',
        '/oauth/register' => '/api/oauth_register.php',
        '/mcp' => '/api/mcp.php',
    ];
}

// Bootstrap plugin system at file scope (not inside dispatch) so registries use $GLOBALS.
require_once __DIR__ . '/plugin_public_routes.php';
if (!function_exists('load_plugins')) {
    require_once __DIR__ . '/plugins.php';
}

function birkly_dispatch_http_route(string $uri): bool
{
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

    if (str_starts_with($uri, '/site') && ($uri === '/site' || str_starts_with($uri, '/site/'))) {
        require __DIR__ . '/../api/studio_public.php';
        return true;
    }

    if (function_exists('load_plugins')) {
        load_plugins(dirname(__DIR__) . '/plugins');
    }
    if (function_exists('birkly_dispatch_public_routes') && birkly_dispatch_public_routes($uri)) {
        return true;
    }

    return false;
}
