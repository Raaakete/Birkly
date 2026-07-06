<?php
/**
 * Plugin admin page shell — breadcrumb header only (no duplicate h1).
 *
 * Expects $plugin_admin (meta array) and $page (current slug) in scope,
 * or pass via birkly_render_plugin_page_breadcrumbs().
 */
if (!isset($plugin_admin) || !is_array($plugin_admin)) {
    return;
}
$currentPage = isset($page) ? (string) $page : (string) ($plugin_admin['root_page'] ?? '');
if (function_exists('birkly_render_plugin_page_breadcrumbs')) {
    echo birkly_render_plugin_page_breadcrumbs($plugin_admin, $currentPage);
}
