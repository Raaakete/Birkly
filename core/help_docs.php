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
    $path = birkly_help_core_manifest_path();
    if (!is_file($path)) {
        return ['version' => 2, 'topics' => []];
    }
    $manifest = json_decode((string) file_get_contents($path), true);
    return is_array($manifest) ? $manifest : ['version' => 2, 'topics' => []];
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
        if (empty($topic['snapshot'])) {
            $topic['snapshot'] = '/admin/docs/snapshots/' . $id . '.md';
        }
        $merged[] = $topic;
        $seen[$id] = true;
    }

    return $merged;
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
            return $data;
        }
    }

    $manifest = birkly_load_core_help_manifest();
    $manifest['manifest_source'] = 'offline';
    return $manifest;
}
