<?php
/**
 * Admin page data providers.
 *
 * Core routes a page slug to a callable a plugin registered. It does not
 * know that plugin's records.
 */

$birkly_admin_page_data_providers = [];

/**
 * @param callable(): array $provider
 */
function register_admin_page_data_provider(string $page, callable $provider, string $plugin_id = ''): bool
{
    global $birkly_admin_page_data_providers;

    $page = trim($page);
    if ($page === '' || !preg_match('/^[a-z0-9_-]+$/', $page)) {
        return false;
    }

    $birkly_admin_page_data_providers[$page] = [
        'plugin_id' => $plugin_id,
        'provider' => $provider,
    ];

    return true;
}

/**
 * @return array<string, mixed>|null Null when nobody registered this page.
 */
function birkly_admin_page_data(string $page): ?array
{
    global $birkly_admin_page_data_providers;

    $entry = $birkly_admin_page_data_providers[$page] ?? null;
    if (!is_array($entry) || !is_callable($entry['provider'] ?? null)) {
        return null;
    }

    $pluginId = (string) ($entry['plugin_id'] ?? '');
    if ($pluginId !== '' && function_exists('birkly_is_plugin_active') && !birkly_is_plugin_active($pluginId)) {
        return null;
    }

    $data = ($entry['provider'])();

    return is_array($data) ? $data : null;
}
