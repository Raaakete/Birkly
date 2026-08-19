<?php
/**
 * MCP tool-layer sanitization and validation (read redaction, write guards).
 */

declare(strict_types=1);

/** @return list<string> */
function birkly_ai_mcp_settings_categories(): array
{
    return ['site', 'admin', 'media', 'security'];
}

/** @return list<string> */
function birkly_ai_mcp_settings_baseline_keys(string $category): array
{
    if ($category === 'security') {
        return [
            'session_timeout',
            'password_min_length',
            'require_strong_password',
            'max_login_attempts',
            'lockout_time',
            'enable_csrf',
            'require_2fa',
            'require_2fa_grace_days',
            'github_token',
        ];
    }

    return [];
}

/** @return list<string> */
function birkly_ai_mcp_settings_allowed_data_keys(string $category): array
{
    require_once __DIR__ . '/birkly_paths.php';
    require_once dirname(__DIR__) . '/api/storage.php';

    $storage = new Storage(birkly_project_root() . '/settings');
    $current = $storage->read($category . '.json') ?? [];
    $fromDisk = is_array($current) ? array_keys($current) : [];

    return array_values(array_unique(array_merge(
        birkly_ai_mcp_settings_baseline_keys($category),
        $fromDisk
    )));
}

/**
 * @param array<string, mixed> $arguments
 */
function birkly_ai_validate_mcp_update_settings(array $arguments): ?string
{
    $category = trim((string) ($arguments['category'] ?? ''));
    if ($category === '') {
        return 'update_settings requires category';
    }
    if (!in_array($category, birkly_ai_mcp_settings_categories(), true)) {
        return 'Unknown update_settings category: ' . $category;
    }

    $data = $arguments['settings'] ?? $arguments['data'] ?? null;
    if (!is_array($data) || $data === []) {
        return 'update_settings requires category and settings object';
    }

    $allowed = birkly_ai_mcp_settings_allowed_data_keys($category);
    $unknown = [];
    foreach (array_keys($data) as $key) {
        if (!in_array((string) $key, $allowed, true)) {
            $unknown[] = (string) $key;
        }
    }
    if ($unknown !== []) {
        sort($unknown);

        return 'Unknown update_settings keys: ' . implode(', ', $unknown);
    }

    return null;
}

/** @param array<string, mixed> $data */
function birkly_ai_mcp_redact_settings_response(array $data): array
{
    if (!isset($data['settings']) || !is_array($data['settings'])) {
        return $data;
    }

    $redactKeys = [
        'encryption_key',
        'github_token',
        'password',
        'smtp_password',
        'api_key',
        'secret',
        'token',
        'mcp_api_key',
    ];

    foreach ($data['settings'] as $category => &$settings) {
        if (!is_array($settings)) {
            continue;
        }
        foreach ($redactKeys as $key) {
            if (array_key_exists($key, $settings) && $settings[$key] !== '') {
                $settings[$key] = '[redacted]';
            }
        }
    }
    unset($settings);

    return $data;
}

/**
 * Effective allow_public_read for MCP responses (mirrors core/public_read.php).
 *
 * @param array<string, mixed> $collection
 */
function birkly_ai_mcp_effective_allow_public_read(array $collection): bool
{
    require_once __DIR__ . '/public_read.php';

    return birkly_collection_allows_public_read($collection);
}

/**
 * @param array<string, mixed> $collection
 * @return array<string, mixed>
 */
function birkly_ai_mcp_enrich_collection_response(array $collection): array
{
    $collection['allow_public_read'] = birkly_ai_mcp_effective_allow_public_read($collection);

    return $collection;
}

/** @param array<string, mixed> $payload */
function birkly_ai_mcp_enrich_read_collection_payload(array $payload): array
{
    if (isset($payload['collection']) && is_array($payload['collection'])) {
        $payload['collection'] = birkly_ai_mcp_enrich_collection_response($payload['collection']);
    }

    return $payload;
}

/** @param array<string, mixed> $payload */
function birkly_ai_mcp_enrich_list_collections_payload(array $payload): array
{
    if (!isset($payload['collections']) || !is_array($payload['collections'])) {
        return $payload;
    }

    require_once __DIR__ . '/ai_collection_config.php';
    foreach ($payload['collections'] as $index => $summary) {
        if (!is_array($summary)) {
            continue;
        }
        $slug = (string) ($summary['slug'] ?? '');
        if ($slug === '') {
            $payload['collections'][$index]['allow_public_read'] = false;
            continue;
        }
        $record = birkly_ai_load_collection_record($slug);
        $payload['collections'][$index]['allow_public_read'] = is_array($record)
            ? birkly_ai_mcp_effective_allow_public_read($record)
            : false;
    }

    return $payload;
}

/** @param array<string, mixed> $result */
function birkly_ai_mcp_enrich_write_collection_result(array $result): array
{
    if (isset($result['data']['collection']) && is_array($result['data']['collection'])) {
        $result['data']['collection'] = birkly_ai_mcp_enrich_collection_response($result['data']['collection']);
    }

    return $result;
}

function birkly_ai_mcp_cleanup_stray_settings(): void
{
    require_once __DIR__ . '/birkly_paths.php';
    $path = birkly_project_root() . '/settings/general.json';
    if (is_file($path)) {
        @unlink($path);
    }
}
