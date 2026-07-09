<?php
/**
 * Studio block registry (P23-M13).
 */

if (!function_exists('register_studio_block')) {
    /**
     * @param array{label?:string,description?:string,category?:string,plugin_id?:string,template?:string,canvas?:array} $meta
     */
    function register_studio_block(string $id, array $meta): void
    {
        if (!isset($GLOBALS['birkly_studio_blocks']) || !is_array($GLOBALS['birkly_studio_blocks'])) {
            $GLOBALS['birkly_studio_blocks'] = [];
        }
        $GLOBALS['birkly_studio_blocks'][$id] = array_merge(['id' => $id], $meta);
    }
}

if (!function_exists('get_studio_blocks')) {
    function get_studio_blocks(): array
    {
        return $GLOBALS['birkly_studio_blocks'] ?? [];
    }
}

if (!function_exists('studio_load_plugin_blocks')) {
    function studio_load_plugin_blocks(): void
    {
        $core = dirname(__DIR__) . '/studio-blocks.php';
        if (is_file($core)) {
            include_once $core;
        }
        if (!function_exists('get_all_plugins')) {
            return;
        }
        foreach (get_all_plugins() as $pid => $plugin) {
            if (empty($plugin['active'])) {
                continue;
            }
            $file = ($plugin['path'] ?? '') . '/studio-blocks.php';
            if (is_file($file)) {
                include_once $file;
            }
        }
    }
}
