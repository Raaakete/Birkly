<?php
/**
 * Template function helpers for page_bundle v2 (P21-M1).
 */

require_once __DIR__ . '/plugins/template_functions.php';

function birkly_page_bundle_bootstrap_template_functions(): void {
    static $booted = false;
    if ($booted) {
        return;
    }
    require_once __DIR__ . '/../api/session.php';
    if (function_exists('birkly_site_session_start')) {
        birkly_site_session_start();
    } elseif (function_exists('haru_secure_session_start')) {
        haru_secure_session_start();
    } elseif (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    require_once __DIR__ . '/public_bootstrap.php';
    birkly_public_load_active_plugins();
    $booted = true;
}

function birkly_page_bundle_template_functions_meta_list(): array {
    try {
        birkly_page_bundle_bootstrap_template_functions();
        if (!function_exists('get_all_template_functions')) {
            return [];
        }
        $all = get_all_template_functions();
        if (!is_array($all)) {
            return [];
        }
        $out = [];
        foreach ($all as $name => $meta) {
            if (!is_string($name) || !is_array($meta)) {
                continue;
            }
            $out[] = [
                'name' => $name,
                'description' => (string) ($meta['description'] ?? ''),
                'arguments' => is_array($meta['arguments'] ?? null) ? $meta['arguments'] : [],
                'plugin_id' => isset($meta['plugin_id']) ? (string) $meta['plugin_id'] : null,
            ];
        }
        return $out;
    } catch (Throwable $e) {
        error_log('page_bundle template_functions_meta error: ' . $e->getMessage());
        return [];
    }
}

function birkly_page_bundle_call_template_function(string $name, array $args = []) {
    if ($name === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
        return null;
    }
    birkly_page_bundle_bootstrap_template_functions();
    birkly_public_load_plugins_for_template_function($name);
    if (!function_exists('get_template_function')) {
        return null;
    }
    $fn = get_template_function($name);
    if (!$fn) {
        return null;
    }
    try {
        return call_user_func_array($fn, $args);
    } catch (Throwable $e) {
        error_log('page_bundle template_function error: ' . $e->getMessage());
        return null;
    }
}
