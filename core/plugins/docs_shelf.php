<?php
/**
 * Plugin Documentation Shelf for Birkly
 *
 * Allows plugins to register documentation for AI, Admin Help, and public docs.
 * Follows the "Rooms and Shelves" model — plugins place docs on the Docs Shelf.
 *
 * Inactive plugins are excluded from Help, AI context, and MCP resources.
 */

$birkly_plugin_docs = [];

/**
 * Ensure plugins are loaded so shelf registrations from plugin.php are available.
 */
function birkly_bootstrap_plugins_for_docs(): void
{
    if (!function_exists('load_plugins')) {
        require_once dirname(__DIR__) . '/plugins.php';
    }
    if (!isset($GLOBALS['haru_plugins_loaded'])) {
        load_plugins();
        $GLOBALS['haru_plugins_loaded'] = true;
    }
}

/**
 * Register plugin documentation (place docs on Docs Shelf).
 *
 * @param string $plugin_id Plugin identifier (must match calling plugin)
 * @param array $config Configuration:
 *   - title (string): Display title in Help nav
 *   - summary (string): Short summary for AI L1 context
 *   - badge (string): Help UI badge, default 'Extension'
 *   - docs (array): Document paths:
 *       - ai (string): AI/MCP full context markdown
 *       - help (string): Admin Help overview markdown
 *       - help_topics (array): Optional sub-topics [{id, title, file, keywords?}]
 *       - public_path (string): Birkly-Docs HTML path for "View full docs" link
 *   - mcp_topic_slug (string): Slug for read_birkly_docs / MCP (default: plugin_id)
 *   - keywords (array): Search keywords for Help
 *
 * @return bool True on success
 */
function birkly_docs_resolve_label(string $label): string
{
    if ($label !== '' && function_exists('birkly_i18n_resolve_string')) {
        return birkly_i18n_resolve_string($label);
    }

    return $label;
}

function register_plugin_docs(string $plugin_id, array $config = []): bool
{
    global $birkly_plugin_docs;

    if ($plugin_id === '' || !preg_match('/^[a-z0-9_-]+$/', $plugin_id)) {
        error_log("Invalid plugin docs id: " . $plugin_id);
        return false;
    }

    if (!birkly_is_plugin_docs_plugin_active($plugin_id)) {
        error_log("Cannot register docs for inactive or unknown plugin: " . $plugin_id);
        return false;
    }

    $docs = $config['docs'] ?? [];
    if (!is_array($docs)) {
        error_log("Plugin docs config for '$plugin_id' must include docs array");
        return false;
    }

    $birkly_plugin_docs[$plugin_id] = [
        'plugin_id' => $plugin_id,
        'title' => (string) ($config['title'] ?? ucfirst(str_replace('_', ' ', $plugin_id))),
        'summary' => (string) ($config['summary'] ?? ''),
        'badge' => (string) ($config['badge'] ?? 'Extension'),
        'docs' => $docs,
        'mcp_topic_slug' => (string) ($config['mcp_topic_slug'] ?? $plugin_id),
        'keywords' => is_array($config['keywords'] ?? null) ? $config['keywords'] : [],
        'registered_at' => date('c'),
    ];

    return true;
}

/**
 * Check whether a plugin is active for docs shelf purposes.
 */
function birkly_is_plugin_docs_plugin_active(string $plugin_id): bool
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
 * Get registered docs for one plugin (null if inactive or not registered).
 */
function get_plugin_docs(string $plugin_id): ?array
{
    global $birkly_plugin_docs;

    if (!isset($birkly_plugin_docs[$plugin_id])) {
        return null;
    }

    if (!birkly_is_plugin_docs_plugin_active($plugin_id)) {
        return null;
    }

    return $birkly_plugin_docs[$plugin_id];
}

/**
 * Get all registered plugin docs (includes inactive plugins).
 *
 * @return array<string, array>
 */
function get_all_plugin_docs(): array
{
    global $birkly_plugin_docs;

    if (!isset($birkly_plugin_docs) || !is_array($birkly_plugin_docs)) {
        return [];
    }

    return $birkly_plugin_docs;
}

/**
 * Get docs for active plugins only.
 *
 * @return array<string, array>
 */
function get_active_plugin_docs(): array
{
    birkly_bootstrap_plugins_for_docs();

    $active = [];
    foreach (get_all_plugin_docs() as $plugin_id => $config) {
        if (birkly_is_plugin_docs_plugin_active($plugin_id)) {
            $active[$plugin_id] = $config;
        }
    }

    return $active;
}

/**
 * Resolve plugin docs config by MCP/read_birkly_docs topic slug.
 */
function get_plugin_docs_by_topic_slug(string $slug): ?array
{
    $slug = preg_replace('/[^a-z0-9\-_]/', '', strtolower(trim($slug)));
    if ($slug === '') {
        return null;
    }

    foreach (get_active_plugin_docs() as $plugin_id => $config) {
        $topicSlug = (string) ($config['mcp_topic_slug'] ?? $plugin_id);
        if ($topicSlug === $slug || $plugin_id === $slug) {
            return $config;
        }
    }

    return null;
}

/**
 * Plugin AI playbooks. Core discovers these; it does not name the plugin topics.
 *
 * @var array<string, array<string, mixed>>
 */
$birkly_plugin_playbooks = [];

/**
 * Register one playbook topic for an active plugin.
 *
 * @param array{
 *   title?: string,
 *   summary?: string,
 *   triggers?: string,
 *   category?: string,
 *   file?: string,
 *   aliases?: list<string>
 * } $config
 */
function register_plugin_playbook(string $plugin_id, string $topic, array $config = []): bool
{
    global $birkly_plugin_playbooks;

    $plugin_id = strtolower(trim($plugin_id));
    $topic = strtolower(trim($topic));
    if ($plugin_id === '' || !preg_match('/^[a-z0-9_-]+$/', $plugin_id)) {
        return false;
    }
    if ($topic === '' || !preg_match('/^[a-z0-9_-]+$/', $topic)) {
        return false;
    }
    if (!birkly_is_plugin_docs_plugin_active($plugin_id)) {
        return false;
    }

    $file = (string) ($config['file'] ?? '');
    if ($file === '' || !is_file($file)) {
        return false;
    }

    $aliases = [];
    foreach (($config['aliases'] ?? []) as $alias) {
        $alias = strtolower(trim((string) $alias));
        if ($alias === '' || $alias === $topic || !preg_match('/^[a-z0-9_-]+$/', $alias)) {
            continue;
        }
        $aliases[] = $alias;
    }

    $entry = [
        'plugin_id' => $plugin_id,
        'topic' => $topic,
        'title' => (string) ($config['title'] ?? $topic),
        'summary' => (string) ($config['summary'] ?? ''),
        'triggers' => (string) ($config['triggers'] ?? ''),
        'category' => (string) ($config['category'] ?? 'Plugins'),
        'file' => $file,
        'aliases' => array_values(array_unique($aliases)),
    ];

    if (!isset($birkly_plugin_playbooks) || !is_array($birkly_plugin_playbooks)) {
        $birkly_plugin_playbooks = [];
    }
    $birkly_plugin_playbooks[$topic] = $entry;
    foreach ($entry['aliases'] as $alias) {
        $birkly_plugin_playbooks[$alias] = $entry;
    }

    return true;
}

/**
 * Catalog rows for active plugin playbooks, keyed by the primary topic id.
 *
 * @return array<string, array{title: string, topic: string, summary: string, triggers: string, category: string}>
 */
function birkly_plugin_playbook_catalog(): array
{
    global $birkly_plugin_playbooks;

    birkly_bootstrap_plugins_for_docs();

    if (!isset($birkly_plugin_playbooks) || !is_array($birkly_plugin_playbooks)) {
        return [];
    }

    $out = [];
    foreach ($birkly_plugin_playbooks as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $topic = (string) ($entry['topic'] ?? '');
        $pluginId = (string) ($entry['plugin_id'] ?? '');
        if ($topic === '' || isset($out[$topic]) || !birkly_is_plugin_docs_plugin_active($pluginId)) {
            continue;
        }
        $out[$topic] = [
            'title' => (string) ($entry['title'] ?? $topic),
            'topic' => $topic,
            'summary' => (string) ($entry['summary'] ?? ''),
            'triggers' => (string) ($entry['triggers'] ?? ''),
            'category' => (string) ($entry['category'] ?? 'Plugins'),
        ];
    }

    return $out;
}

/**
 * Active plugin playbook registration for a topic id or alias.
 *
 * @return array<string, mixed>|null
 */
function birkly_plugin_playbook_for_topic(string $topic): ?array
{
    global $birkly_plugin_playbooks;

    birkly_bootstrap_plugins_for_docs();

    $topic = preg_replace('/[^a-z0-9\-_]/', '', strtolower(trim($topic)));
    if ($topic === '' || !isset($birkly_plugin_playbooks[$topic]) || !is_array($birkly_plugin_playbooks[$topic])) {
        return null;
    }

    $entry = $birkly_plugin_playbooks[$topic];
    if (!birkly_is_plugin_docs_plugin_active((string) ($entry['plugin_id'] ?? ''))) {
        return null;
    }

    return $entry;
}

/**
 * Read a registered playbook file. The file must sit inside that plugin's directory.
 */
function birkly_read_plugin_playbook_markdown(array $entry): ?string
{
    $pluginId = (string) ($entry['plugin_id'] ?? '');
    $path = (string) ($entry['file'] ?? '');
    if ($pluginId === '' || $path === '' || !is_file($path)) {
        return null;
    }

    $real = realpath($path);
    if ($real === false) {
        return null;
    }

    global $birkly_plugins;
    $pluginPath = $birkly_plugins[$pluginId]['path'] ?? null;
    if (is_string($pluginPath) && $pluginPath !== '') {
        $pluginReal = realpath($pluginPath);
        if ($pluginReal === false) {
            return null;
        }
        $prefix = rtrim($pluginReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($real, $prefix) && $real !== $pluginReal) {
            return null;
        }
    }

    $text = trim((string) file_get_contents($real));
    if ($text === '') {
        return null;
    }
    if (strlen($text) > 120000) {
        $text = substr($text, 0, 120000) . "\n\n[truncated]";
    }

    return $text;
}

/**
 * Read a plugin markdown doc file by logical key (overview or help_topic id).
 */
function read_plugin_doc_markdown(string $plugin_id, string $doc_key): ?string
{
    $config = get_plugin_docs($plugin_id);
    if ($config === null) {
        return null;
    }

    $docs = $config['docs'] ?? [];
    $path = null;

    if ($doc_key === '' || $doc_key === 'overview' || $doc_key === 'help') {
        $path = $docs['help'] ?? null;
    } elseif ($doc_key === 'ai') {
        $path = $docs['ai'] ?? null;
    } else {
        foreach ($docs['help_topics'] ?? [] as $topic) {
            if (($topic['id'] ?? '') === $doc_key) {
                $path = $topic['file'] ?? null;
                break;
            }
        }
    }

    if (!$path || !is_string($path) || !is_file($path)) {
        return null;
    }

    $real = realpath($path);
    if ($real === false) {
        return null;
    }

    // Ensure file lives under the plugin directory
    $pluginPath = null;
    if (function_exists('get_all_plugins')) {
        $pluginPath = get_all_plugins()[$plugin_id]['path'] ?? null;
    }
    if (!$pluginPath) {
        global $birkly_plugins;
        $pluginPath = $birkly_plugins[$plugin_id]['path'] ?? null;
    }
    if ($pluginPath) {
        $pluginReal = realpath($pluginPath);
        if ($pluginReal !== false && !str_starts_with($real, $pluginReal)) {
            return null;
        }
    }

    $text = trim((string) file_get_contents($real));
    if (strlen($text) > 120000) {
        $text = substr($text, 0, 120000) . "\n\n[truncated]";
    }

    return $text;
}

/**
 * Build merged Help manifest: core platform topics + active plugin topics.
 *
 * @return array<string, mixed>
 */
function birkly_build_help_manifest(): array
{
    birkly_bootstrap_plugins_for_docs();

    if (!function_exists('birkly_build_platform_help_manifest')) {
        require_once dirname(__DIR__) . '/help_docs.php';
    }

    $manifest = birkly_build_platform_help_manifest();

    $manifest['sections'] = [
        ['id' => 'platform', 'title' => function_exists('__') ? __('admin.help_section_platform') : 'Birkly platform'],
        ['id' => 'plugins', 'title' => function_exists('__') ? __('admin.help_section_plugins') : 'Plugin extensions'],
    ];

    $topics = [];
    foreach ($manifest['topics'] ?? [] as $topic) {
        if (!is_array($topic)) {
            continue;
        }
        // Core manifest may still list plugin-adjacent topics; keep under platform for now
        $topic['section'] = 'platform';
        $topic['source'] = 'core';
        $topics[] = $topic;
    }

    foreach (get_active_plugin_docs() as $plugin_id => $doc) {
        $publicPath = (string) ($doc['docs']['public_path'] ?? '');
        $keywords = is_array($doc['keywords'] ?? null) ? $doc['keywords'] : [];

        $topics[] = [
            'id' => $plugin_id,
            'title' => birkly_docs_resolve_label((string) ($doc['title'] ?? $plugin_id)),
            'keywords' => $keywords,
            'section' => 'plugins',
            'source' => 'plugin',
            'plugin_id' => $plugin_id,
            'badge' => (string) ($doc['badge'] ?? 'Extension'),
            'snapshot' => '/api/plugin_doc.php?plugin=' . rawurlencode($plugin_id) . '&doc=overview',
            'docs_path' => $publicPath,
        ];

        foreach ($doc['docs']['help_topics'] ?? [] as $sub) {
            if (!is_array($sub) || empty($sub['id'])) {
                continue;
            }
            $subKeywords = is_array($sub['keywords'] ?? null) ? $sub['keywords'] : $keywords;
            $topicRow = [
                'id' => (string) $sub['id'],
                'title' => birkly_docs_resolve_label((string) ($sub['title'] ?? $sub['id'])),
                'keywords' => $subKeywords,
                'section' => 'plugins',
                'source' => 'plugin',
                'plugin_id' => $plugin_id,
                'parent' => $plugin_id,
                'badge' => (string) ($doc['badge'] ?? 'Extension'),
                'snapshot' => '/api/plugin_doc.php?plugin=' . rawurlencode($plugin_id) . '&doc=' . rawurlencode((string) $sub['id']),
                'docs_path' => $publicPath,
            ];
            $group = trim((string) ($sub['group'] ?? ''));
            if ($group !== '') {
                $topicRow['group'] = birkly_docs_resolve_label($group);
            }
            $topics[] = $topicRow;
        }
    }

    $manifest['topics'] = $topics;

    return $manifest;
}

/**
 * Summaries from active plugins for AI L1 context merge.
 *
 * @return string Markdown block (empty if none)
 */
function birkly_plugin_docs_ai_summaries(): string
{
    birkly_bootstrap_plugins_for_docs();

    $blocks = [];
    foreach (get_active_plugin_docs() as $plugin_id => $doc) {
        $title = birkly_docs_resolve_label((string) ($doc['title'] ?? $plugin_id));
        $summary = trim((string) ($doc['summary'] ?? ''));
        if ($summary === '') {
            continue;
        }
        $blocks[] = "### Plugin: {$title} ({$plugin_id})\n{$summary}";
    }

    if ($blocks === []) {
        return '';
    }

    return "## Active plugin extensions\n\n" . implode("\n\n", $blocks);
}
