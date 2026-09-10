#!/usr/bin/env php
<?php
/**
 * Scaffold a minimal Birkly plugin tree (P15-M5).
 *
 *   php scripts/scaffold-plugin.php --id=my-plugin --title="My Plugin" [--privileged] [--out=plugins]
 */
declare(strict_types=1);

$options = getopt('', ['id:', 'title:', 'privileged', 'out::', 'help']);

if (isset($options['help']) || empty($options['id']) || empty($options['title'])) {
    fwrite(STDOUT, "Usage: php scripts/scaffold-plugin.php --id=my-plugin --title=\"My Plugin\" [--privileged] [--out=plugins]\n");
    exit(isset($options['help']) ? 0 : 1);
}

$pluginId = (string) $options['id'];
$title = (string) $options['title'];
$privileged = array_key_exists('privileged', $options);
if (!preg_match('/^[a-z][a-z0-9_-]*$/', $pluginId)) {
    fwrite(STDERR, "Invalid --id: use lowercase letters, digits, hyphens (e.g. my-plugin).\n");
    exit(1);
}

$root = dirname(__DIR__);
$outRoot = isset($options['out']) ? (string) $options['out'] : 'plugins';
$baseDir = ($outRoot !== '' && $outRoot[0] === '/') ? $outRoot : rtrim($root . '/' . ltrim($outRoot, '/'), '/');
$target = $baseDir . '/' . $pluginId;

if (is_dir($target)) {
    fwrite(STDERR, "Target already exists: {$target}\n");
    exit(1);
}

$pageSlug = str_replace('-', '_', $pluginId);
$adminHtml = $pageSlug . '.html';
$adminJs = $pageSlug . '.js';

    $dirs = [
    $target,
    $target . '/admin',
    $target . '/docs',
    $target . '/lang',
];

foreach ($dirs as $dir) {
    if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
        fwrite(STDERR, "Failed to create directory: {$dir}\n");
        exit(1);
    }
}

$i18nNamespace = 'plugins.' . str_replace('-', '_', $pluginId);
$langEn = [
    '_meta' => ['name' => 'English', 'native_name' => 'English', 'flag' => '🇺🇸'],
    'plugins' => [
        str_replace('-', '_', $pluginId) => [
            'title' => $title,
        ],
    ],
];
file_put_contents($target . '/lang/en.json', json_encode($langEn, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

$pluginJson = [
    'name' => $title,
    'version' => '0.1.0',
    'description' => $title . ' — scaffolded by scripts/scaffold-plugin.php',
    'author' => 'Your name',
    'requires' => '1.0.0',
    'hooks' => [],
    'permissions' => [],
    'capabilities' => new stdClass(),
    'navigation' => [
        'name' => $title,
        'page' => $pageSlug,
        'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect></svg>',
    ],
    'settings_schema' => new stdClass(),
    'admin' => [
        'i18n' => [
            'path' => 'lang',
            'namespace' => 'plugins.' . str_replace('-', '_', $pluginId),
        ],
    ],
];

if ($privileged) {
    $pluginJson['capabilities'] = [
        'manage_' . str_replace('-', '_', $pluginId) => [
            'description' => 'Manage ' . $title,
            'scope' => 'global',
        ],
    ];
    $pluginJson['permissions'] = ['manage_' . str_replace('-', '_', $pluginId)];
}

file_put_contents(
    $target . '/plugin.json',
    json_encode($pluginJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
);

$pluginPhp = <<<'PHP'
<?php
/**
 * {TITLE} — minimal plugin scaffold (UI shelf only).
 */

if (!get_plugin_state('{ID}')) {
    return;
}

if (function_exists('register_admin_page')) {
    register_admin_page('{PAGE}', __DIR__ . '/admin/{ADMIN_HTML}');
}

if (function_exists('register_plugin_admin_meta')) {
    register_plugin_admin_meta('{ID}', [
        'title' => '@i18n:{I18N_NS}.title',
        'root_page' => '{PAGE}',
        'permission' => null,
        'page_slugs' => ['{PAGE}'],
        'sidebar' => [
            'show' => true,
            'label' => '@i18n:{I18N_NS}.title',
            'order' => 90,
        ],
    ]);
}

if (function_exists('register_plugin_docs')) {
    register_plugin_docs('{ID}', [
        'title' => '{TITLE}',
        'summary' => 'Short summary for AI and Help.',
        'docs' => [
            'help' => __DIR__ . '/docs/help-overview.md',
            'ai' => __DIR__ . '/docs/ai-context.md',
            'public_path' => '06-extensibility/plugins/{ID}.html',
        ],
        'keywords' => ['{ID}'],
    ]);
}
PHP;

$pluginPhp = str_replace(
    ['{ID}', '{TITLE}', '{PAGE}', '{ADMIN_HTML}', '{I18N_NS}'],
    [$pluginId, $title, $pageSlug, $adminHtml, $i18nNamespace],
    $pluginPhp
);
file_put_contents($target . '/plugin.php', $pluginPhp);

$adminPageHtml = <<<HTML
<div class="plugin-page" data-plugin-id="{$pluginId}">
    <header class="page-header">
        <h1>{$title}</h1>
        <p class="text-secondary">Minimal admin shelf — extend this page in <code>admin/{$adminHtml}</code>.</p>
    </header>
    <section class="card">
        <p>Plugin <strong>{$pluginId}</strong> is active.</p>
    </section>
</div>
<script src="admin/{$adminJs}"></script>
HTML;

file_put_contents($target . '/admin/' . $adminHtml, $adminPageHtml);

file_put_contents($target . '/admin/' . $adminJs, <<<'JS'
(function () {
    'use strict';
    // Plugin admin JS — add interactions here.
})();
JS
);

file_put_contents($target . '/docs/help-overview.md', "# {$title}\n\nOverview for admin Help.\n");
file_put_contents($target . '/docs/ai-context.md', "# {$title} — AI context\n\nDescribe capabilities and boundaries for the AI assistant.\n");

file_put_contents($target . '/README.md', <<<MD
# {$title}

Scaffolded plugin `{$pluginId}`.

## Structure

- `plugin.json` — metadata and navigation
- `plugin.php` — bootstrap (admin page + docs shelf)
- `admin/` — admin UI shelf
- `docs/` — Help and AI markdown

## Next steps

1. Activate in **Settings → Plugins**
2. Extend admin UI under `admin/`
3. Add API routes with `register_api_endpoint()` when needed
MD
);

fwrite(STDOUT, "Created plugin at: {$target}\n");

if ($privileged) {
    fwrite(STDOUT, "\nPrivileged plugin checklist:\n");
    fwrite(STDOUT, "  [ ] Declare capabilities in plugin.json (done — review scopes)\n");
    fwrite(STDOUT, "  [ ] Review plugin security denylist (docs/plugin-security-denylist)\n");
    fwrite(STDOUT, "  [ ] Add service contracts + version if exposing register_plugin_service()\n");
    fwrite(STDOUT, "  [ ] Gate data access with capability checks before reading/writing content\n");
    fwrite(STDOUT, "  [ ] Add tests under tests/ or plugin-local tests\n");
    fwrite(STDOUT, "  [ ] Document public Birkly-Docs path in register_plugin_docs public_path\n");
    fwrite(STDOUT, "  [ ] Run preflight: php tests/scaffold_plugin_test.php (after wiring)\n");
}

exit(0);
