<?php
/**
 * Plugin public route shelf (P22-D8).
 *
 * Thin core hook: plugins register URL patterns; server-router dispatches before static fallback.
 */

if (!function_exists('register_public_route')) {
    /**
     * @param string $pattern Route prefix e.g. /site/
     * @param callable $handler function(string $uri): bool — return true when handled
     * @param array $options plugin_id, priority
     */
    function register_public_route(string $pattern, callable $handler, array $options = []): void
    {
        if (!isset($GLOBALS['birkly_public_routes']) || !is_array($GLOBALS['birkly_public_routes'])) {
            $GLOBALS['birkly_public_routes'] = [];
        }
        $GLOBALS['birkly_public_routes'][] = [
            'pattern' => $pattern,
            'handler' => $handler,
            'plugin_id' => $options['plugin_id'] ?? '',
            'priority' => (int) ($options['priority'] ?? 100),
        ];
    }
}

if (!function_exists('birkly_dispatch_public_routes')) {
  /**
     * Try registered public routes. Returns true if a handler served the request.
     */
    function birkly_dispatch_public_routes(string $uri): bool
    {
        $routes = $GLOBALS['birkly_public_routes'] ?? [];
        if ($routes === []) {
            return false;
        }

        usort($routes, static function ($a, $b) {
            $pa = strlen($a['pattern'] ?? '');
            $pb = strlen($b['pattern'] ?? '');
            if ($pa !== $pb) {
                return $pb <=> $pa;
            }
            return ($a['priority'] ?? 100) <=> ($b['priority'] ?? 100);
        });

        foreach ($routes as $route) {
            $pattern = $route['pattern'] ?? '';
            if ($pattern === '') {
                continue;
            }
            if ($uri === $pattern || str_starts_with($uri, rtrim($pattern, '/') . '/')) {
                $handler = $route['handler'] ?? null;
                if (is_callable($handler)) {
                    try {
                        if ($handler($uri) === true) {
                            return true;
                        }
                    } catch (Throwable $e) {
                        error_log('public_route error: ' . $e->getMessage());
                    }
                }
            }
        }

        return false;
    }
}
