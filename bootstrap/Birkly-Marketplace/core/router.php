<?php
/**
 * Birkly Marketplace — simple request router.
 */

/**
 * Registered routes: "METHOD /path" => handler callable.
 */
function marketplace_routes(): array
{
    return [
        'GET /' => 'marketplace_handle_landing',
        'GET /index.php' => 'marketplace_handle_landing',
        'GET /api/v1/health' => 'marketplace_handle_health',
        'GET /api/v1/catalog' => 'marketplace_handle_catalog',
    ];
}

/**
 * Normalize a request path for routing.
 */
function marketplace_normalize_path(string $path): string
{
    $path = parse_url($path, PHP_URL_PATH) ?? '/';
    if ($path === '') {
        $path = '/';
    }

    $path = '/' . ltrim($path, '/');
    if ($path !== '/' && str_ends_with($path, '/')) {
        $path = rtrim($path, '/');
    }

    return $path;
}

/**
 * Match a method + path to a route key.
 */
function marketplace_route_key(string $method, string $path): ?string
{
    $method = strtoupper(trim($method));
    $path = marketplace_normalize_path($path);
    $key = $method . ' ' . $path;

    return array_key_exists($key, marketplace_routes()) ? $key : null;
}

/**
 * Dispatch a request to the matching handler.
 */
function marketplace_route(string $method, string $path): bool
{
    $key = marketplace_route_key($method, $path);
    if ($key === null) {
        return false;
    }

    $handler = marketplace_routes()[$key];
    if (!is_callable($handler)) {
        return false;
    }

    $handler();
    return true;
}

/**
 * Landing page handler.
 */
function marketplace_handle_landing(): void
{
    require MARKETPLACE_ROOT . '/index.php';
}

/**
 * Health endpoint handler.
 */
function marketplace_handle_health(): void
{
    require MARKETPLACE_ROOT . '/api/v1/health.php';
}

/**
 * Catalog endpoint handler.
 */
function marketplace_handle_catalog(): void
{
    require MARKETPLACE_ROOT . '/api/v1/catalog.php';
}
