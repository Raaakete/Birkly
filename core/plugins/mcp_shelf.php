<?php
/**
 * Plugin MCP tool shelf — active plugins inject tools into the AI/MCP registry.
 *
 * Tools live in plugin repos (e.g. docs/mcp_tools.php) and register via
 * register_plugin_mcp_tool() / register_plugin_mcp_tools() during plugin bootstrap.
 */

$GLOBALS['birkly_plugin_mcp_tools'] = [];

/**
 * Ensure plugins are loaded so MCP tool registrations from plugin.php are available.
 */
function birkly_bootstrap_plugins_for_mcp_tools(): void
{
    if (!function_exists('load_plugins')) {
        require_once dirname(__DIR__) . '/plugins.php';
    }
    if (!isset($GLOBALS['birkly_plugins_mcp_bootstrapped'])) {
        load_plugins();
        $GLOBALS['birkly_plugins_mcp_bootstrapped'] = true;
    }
}

function birkly_is_plugin_mcp_plugin_active(string $plugin_id): bool
{
    if (function_exists('birkly_is_plugin_active')) {
        return birkly_is_plugin_active($plugin_id);
    }

    global $birkly_plugins;
    if (isset($birkly_plugins[$plugin_id])) {
        return (bool) ($birkly_plugins[$plugin_id]['active'] ?? false);
    }

    if (function_exists('get_all_plugins')) {
        $all = get_all_plugins();
        if (isset($all[$plugin_id])) {
            return (bool) ($all[$plugin_id]['active'] ?? false);
        }
    }

    return false;
}

/**
 * Register one MCP tool from an active plugin.
 *
 * @param array{
 *   description: string,
 *   category: string,
 *   inputSchema: array,
 *   permissions: string[],
 *   handler: callable
 * } $config
 */
function register_plugin_mcp_tool(string $plugin_id, string $tool_name, array $config): bool
{
    global $birkly_plugin_mcp_tools;

    if ($plugin_id === '' || $tool_name === '' || !preg_match('/^[a-z][a-z0-9_]*$/', $tool_name)) {
        error_log('Invalid plugin MCP tool registration: ' . $plugin_id . ' / ' . $tool_name);
        return false;
    }

    if (!birkly_is_plugin_mcp_plugin_active($plugin_id)) {
        return false;
    }

    if (!is_callable($config['handler'] ?? null)) {
        error_log("Plugin MCP tool '$tool_name' missing callable handler");
        return false;
    }

    $category = (string) ($config['category'] ?? 'read');
    if (!in_array($category, ['read', 'write'], true)) {
        $category = 'read';
    }

    $permissions = $config['permissions'] ?? ['read'];
    if (!is_array($permissions) || $permissions === []) {
        $permissions = ['read'];
    }

    $birkly_plugin_mcp_tools[$tool_name] = [
        'plugin_id' => $plugin_id,
        'name' => $tool_name,
        'description' => (string) ($config['description'] ?? ''),
        'category' => $category,
        'inputSchema' => is_array($config['inputSchema'] ?? null) ? $config['inputSchema'] : ['type' => 'object', 'properties' => []],
        'permissions' => array_values(array_map('strval', $permissions)),
        'handler' => $config['handler'],
        'registered_at' => date('c'),
    ];

    return true;
}

/**
 * @param array<string, array> $tools Map of tool_name => config
 */
function register_plugin_mcp_tools(string $plugin_id, array $tools): bool
{
    $ok = true;
    foreach ($tools as $tool_name => $config) {
        if (!is_array($config)) {
            $ok = false;
            continue;
        }
        if (!register_plugin_mcp_tool($plugin_id, (string) $tool_name, $config)) {
            $ok = false;
        }
    }
    return $ok;
}

/** @return array<string, array{name: string, description: string, category: string, inputSchema: array, plugin_id: string}> */
function birkly_plugin_mcp_tool_definitions(): array
{
    birkly_bootstrap_plugins_for_mcp_tools();

    $definitions = [];
    foreach ($GLOBALS['birkly_plugin_mcp_tools'] as $tool_name => $meta) {
        if (!is_array($meta) || !birkly_is_plugin_mcp_plugin_active((string) ($meta['plugin_id'] ?? ''))) {
            continue;
        }
        $definitions[$tool_name] = [
            'name' => $tool_name,
            'description' => (string) ($meta['description'] ?? ''),
            'category' => (string) ($meta['category'] ?? 'read'),
            'inputSchema' => is_array($meta['inputSchema'] ?? null) ? $meta['inputSchema'] : ['type' => 'object', 'properties' => []],
            'plugin_id' => (string) ($meta['plugin_id'] ?? ''),
        ];
    }

    return $definitions;
}

/** @return array<string, string[]> */
function birkly_plugin_mcp_permission_map(): array
{
    birkly_bootstrap_plugins_for_mcp_tools();

    $map = [];
    foreach ($GLOBALS['birkly_plugin_mcp_tools'] as $tool_name => $meta) {
        if (!is_array($meta) || !birkly_is_plugin_mcp_plugin_active((string) ($meta['plugin_id'] ?? ''))) {
            continue;
        }
        $map[$tool_name] = is_array($meta['permissions'] ?? null) ? $meta['permissions'] : ['read'];
    }

    return $map;
}

function birkly_plugin_mcp_tool_is_registered(string $tool_name): bool
{
    birkly_bootstrap_plugins_for_mcp_tools();
    return isset($GLOBALS['birkly_plugin_mcp_tools'][$tool_name])
        && birkly_is_plugin_mcp_plugin_active((string) ($GLOBALS['birkly_plugin_mcp_tools'][$tool_name]['plugin_id'] ?? ''));
}

/**
 * Dispatch a plugin MCP tool. Returns null when the tool is not a registered plugin tool.
 *
 * @return array{isError?: bool, content: list<array{type: string, text: string}>}|null
 */
function birkly_plugin_mcp_dispatch(string $tool_name, array $arguments, array $aiUser): ?array
{
    birkly_bootstrap_plugins_for_mcp_tools();

    if (!isset($GLOBALS['birkly_plugin_mcp_tools'][$tool_name])) {
        return null;
    }

    $meta = $GLOBALS['birkly_plugin_mcp_tools'][$tool_name];
    if (!is_array($meta) || !birkly_is_plugin_mcp_plugin_active((string) ($meta['plugin_id'] ?? ''))) {
        return null;
    }

    $handler = $meta['handler'] ?? null;
    if (!is_callable($handler)) {
        return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Plugin MCP tool handler is not callable: ' . $tool_name]]];
    }

    try {
        $result = $handler($arguments, $aiUser);
    } catch (Throwable $e) {
        error_log('Plugin MCP tool failed (' . $tool_name . '): ' . $e->getMessage());
        return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Plugin tool error: ' . $e->getMessage()]]];
    }

    if (!is_array($result)) {
        return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Plugin tool returned invalid result']]];
    }

    if (($result['status'] ?? '') !== 'ok') {
        $message = (string) ($result['message'] ?? $result['error'] ?? 'Plugin tool failed');
        return ['isError' => true, 'content' => [['type' => 'text', 'text' => $message]]];
    }

    $payload = $result;
    unset($payload['status']);

    return ['content' => [['type' => 'text', 'text' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)]]];
}

/**
 * Summaries of active plugin MCP tools for AI L1 context.
 */
function birkly_plugin_mcp_tools_ai_summary(): string
{
    $definitions = birkly_plugin_mcp_tool_definitions();
    if ($definitions === []) {
        return '';
    }

    $byPlugin = [];
    foreach ($definitions as $name => $def) {
        $pluginId = (string) ($def['plugin_id'] ?? 'plugin');
        $byPlugin[$pluginId][] = '- `' . $name . '` [' . ($def['category'] ?? 'read') . ']: ' . ($def['description'] ?? '');
    }

    $blocks = ["## Active plugin MCP tools\n"];
    foreach ($byPlugin as $pluginId => $lines) {
        $blocks[] = '### ' . $pluginId;
        $blocks[] = implode("\n", $lines);
        $blocks[] = 'Read `read_birkly_docs` topic `' . $pluginId . '` or `birkly://docs/plugin/' . $pluginId . '` for plugin docs.';
        $blocks[] = '';
    }

    return implode("\n", $blocks);
}
