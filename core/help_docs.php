<?php
/**
 * Help manifest wiring — settings, live Birkly-Docs fetch, offline fallback (P15-M3).
 */

/**
 * Committed default; copied to settings/ on first run when missing.
 */
function birkly_help_manifest_defaults(): array
{
    return [
        'version' => 2,
        'docs_base_url' => 'https://docs.birkly.com',
        'live_manifest_url' => 'https://docs.birkly.com/help-manifest.json',
        'topics' => [],
    ];
}

function birkly_help_settings_path(): string
{
    return dirname(__DIR__) . '/settings/help_manifest.json';
}

function birkly_help_core_manifest_path(): string
{
    return dirname(__DIR__) . '/docs/help-manifest.json';
}

function birkly_help_offline_manifest_path(): string
{
    return dirname(__DIR__) . '/admin/docs/help-offline-manifest.json';
}

/**
 * Load install settings for Help (creates defaults when missing).
 *
 * @return array<string, mixed>
 */
function birkly_load_help_settings(): array
{
    $defaults = birkly_help_manifest_defaults();
    $path = birkly_help_settings_path();

    if (!is_file($path)) {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        return $defaults;
    }

    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        return $defaults;
    }

    return array_merge($defaults, $data);
}

/**
 * Fetch live manifest JSON from Birkly-Docs (or configured URL).
 *
 * @return array<string, mixed>|null
 */
function birkly_fetch_live_help_manifest(?string $url = null, int $timeoutSeconds = 4): ?array
{
    $settings = birkly_load_help_settings();
    $url = $url ?? (string) ($settings['live_manifest_url'] ?? '');
    if ($url === '') {
        return null;
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => $timeoutSeconds,
            'header' => "Accept: application/json\r\nUser-Agent: Birkly-Help/1.0\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if ($raw === false || trim($raw) === '') {
        return null;
    }

    $manifest = json_decode($raw, true);
    return is_array($manifest) ? $manifest : null;
}

/**
 * Load bundled core manifest (offline fallback for PHP/API).
 *
 * @return array<string, mixed>
 */
function birkly_load_core_help_manifest(): array
{
    $offlinePath = birkly_help_offline_manifest_path();
    if (is_file($offlinePath)) {
        $manifest = json_decode((string) file_get_contents($offlinePath), true);
        if (is_array($manifest) && !empty($manifest['topics'])) {
            return $manifest;
        }
    }

    $path = birkly_help_core_manifest_path();
    if (!is_file($path)) {
        return ['version' => 2, 'topics' => []];
    }
    $manifest = json_decode((string) file_get_contents($path), true);
    if (!is_array($manifest)) {
        return ['version' => 2, 'topics' => []];
    }

    $manifest['topics'] = birkly_normalize_help_topics(
        is_array($manifest['topics'] ?? null) ? $manifest['topics'] : []
    );

    return $manifest;
}

/**
 * Merge live topic metadata onto local topics (keeps local snapshots).
 *
 * @param list<array<string, mixed>> $localTopics
 * @param list<array<string, mixed>> $liveTopics
 * @return list<array<string, mixed>>
 */
function birkly_merge_help_topics(array $localTopics, array $liveTopics): array
{
    $liveById = [];
    foreach ($liveTopics as $topic) {
        if (!is_array($topic) || empty($topic['id'])) {
            continue;
        }
        $liveById[(string) $topic['id']] = $topic;
    }

    $merged = [];
    $seen = [];

    foreach ($localTopics as $topic) {
        if (!is_array($topic) || empty($topic['id'])) {
            continue;
        }
        $id = (string) $topic['id'];
        $seen[$id] = true;
        if (isset($liveById[$id])) {
            $live = $liveById[$id];
            if (!empty($live['title'])) {
                $topic['title'] = $live['title'];
            }
            if (!empty($live['docs_path'])) {
                $topic['docs_path'] = $live['docs_path'];
            }
            if (!empty($live['keywords']) && is_array($live['keywords'])) {
                $topic['keywords'] = $live['keywords'];
            }
            if (isset($live['version'])) {
                $topic['version'] = $live['version'];
            }
            if (!empty($live['ai_topic'])) {
                $topic['ai_topic'] = (string) $live['ai_topic'];
            }
            $topic['docs_source'] = 'live';
        }
        $merged[] = $topic;
    }

    foreach ($liveTopics as $topic) {
        if (!is_array($topic) || empty($topic['id'])) {
            continue;
        }
        $id = (string) $topic['id'];
        if (isset($seen[$id])) {
            continue;
        }
        $topic['docs_source'] = 'live';
        $topic['section'] = $topic['section'] ?? 'platform';
        $topic['source'] = $topic['source'] ?? 'core';
        unset($topic['snapshot']);
        $merged[] = $topic;
        $seen[$id] = true;
    }

    foreach ($merged as &$topic) {
        if (!is_array($topic) || empty($topic['id'])) {
            continue;
        }
        if (birkly_help_topic_is_plugin($topic)) {
            continue;
        }
        unset($topic['snapshot']);
    }
    unset($topic);

    return $merged;
}

/**
 * Plugin Docs-tab topics are served by the plugin. Product topics are not.
 */
function birkly_help_topic_is_plugin(array $topic): bool
{
    if (($topic['source'] ?? '') === 'plugin') {
        return true;
    }

    return str_contains((string) ($topic['snapshot'] ?? ''), 'plugin_doc.php');
}

/**
 * Public content-fragment URL for a product Help topic.
 * Plugin topics return an empty string; their body stays on the plugin.
 */
function birkly_help_topic_content_url(array $topic, string $docsBase = 'https://docs.birkly.com'): string
{
    if (birkly_help_topic_is_plugin($topic)) {
        return '';
    }

    $path = ltrim((string) ($topic['docs_path'] ?? ''), '/');
    if ($path === '') {
        return '';
    }

    return rtrim($docsBase, '/') . '/content/' . $path;
}

/**
 * Derive Help nav section from Birkly-Docs docs_path prefix.
 *
 * @return array{id: string, label: string}
 */
function birkly_help_nav_section_from_docs_path(string $docsPath): array
{
    /** @var array<string, array{id: string, label: string}> */
    static $map = [
        '00-overview' => ['id' => 'overview', 'label' => 'Overview'],
        '01-admin' => ['id' => 'admin', 'label' => 'Admin'],
        '02-content' => ['id' => 'content', 'label' => 'Content model'],
        '03-templating' => ['id' => 'templating', 'label' => 'Templating'],
        '04-integration' => ['id' => 'integration', 'label' => 'Integration'],
        '05-automation' => ['id' => 'automation', 'label' => 'Automation'],
        '06-extensibility' => ['id' => 'extensibility', 'label' => 'Extensibility'],
        '06-plugin-economy' => ['id' => 'plugin-economy', 'label' => 'Plugin economy'],
        '07-operations' => ['id' => 'operations', 'label' => 'Operations'],
    ];

    foreach ($map as $prefix => $section) {
        if (str_starts_with($docsPath, $prefix . '/')) {
            return $section;
        }
    }

    return ['id' => 'platform', 'label' => 'Platform'];
}

/**
 * Nav section for product topics. Article bodies are not stored on the install.
 *
 * @param list<array<string, mixed>> $topics
 * @return list<array<string, mixed>>
 */
function birkly_normalize_help_topics(array $topics): array
{
    $normalized = [];
    foreach ($topics as $topic) {
        if (!is_array($topic) || empty($topic['id'])) {
            continue;
        }
        if (!birkly_help_topic_is_plugin($topic)) {
            unset($topic['snapshot']);
        }
        if (empty($topic['nav_section'])) {
            $docsPath = (string) ($topic['docs_path'] ?? '');
            $nav = birkly_help_nav_section_from_docs_path($docsPath);
            $topic['nav_section'] = $nav['id'];
            $topic['nav_section_label'] = $nav['label'];
        }
        $normalized[] = $topic;
    }

    return $normalized;
}

/**
 * Build platform Help manifest base (before plugin merge).
 *
 * @return array<string, mixed>
 */
function birkly_build_platform_help_manifest(): array
{
    $settings = birkly_load_help_settings();
    $manifest = birkly_load_core_help_manifest();
    $manifest['docs_base_url'] = (string) ($settings['docs_base_url'] ?? $manifest['docs_base_url'] ?? 'https://docs.birkly.com');
    $manifest['live_manifest_url'] = (string) ($settings['live_manifest_url'] ?? '');

    $localTopics = is_array($manifest['topics'] ?? null) ? $manifest['topics'] : [];
    $live = birkly_fetch_live_help_manifest();
    if ($live !== null) {
        $manifest['manifest_source'] = 'live';
        $manifest['live_fetched_at'] = date('c');
        if (!empty($live['docs_base_url'])) {
            $manifest['docs_base_url'] = (string) $live['docs_base_url'];
        }
        $liveTopics = is_array($live['topics'] ?? null) ? $live['topics'] : [];
        $manifest['topics'] = birkly_merge_help_topics($localTopics, $liveTopics);
    } else {
        $manifest['manifest_source'] = 'offline';
        $manifest['topics'] = $localTopics;
    }

    if (!empty($settings['topics']) && is_array($settings['topics'])) {
        $manifest['topics'] = birkly_merge_help_topics($manifest['topics'], $settings['topics']);
    }

    $manifest['topics'] = birkly_normalize_help_topics(
        is_array($manifest['topics'] ?? null) ? $manifest['topics'] : []
    );

    return $manifest;
}

/**
 * Offline manifest served to admin JS when API is unreachable.
 *
 * @return array<string, mixed>
 */
function birkly_help_offline_manifest(): array
{
    $path = birkly_help_offline_manifest_path();
    if (is_file($path)) {
        $data = json_decode((string) file_get_contents($path), true);
        if (is_array($data)) {
            $data['manifest_source'] = 'offline';
            if (!empty($data['topics']) && is_array($data['topics'])) {
                $data['topics'] = birkly_normalize_help_topics($data['topics']);
            }
            return $data;
        }
    }

    $manifest = birkly_load_core_help_manifest();
    $manifest['manifest_source'] = 'offline';
    return $manifest;
}
