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

function birkly_ai_mcp_cleanup_stray_settings(): void
{
    require_once __DIR__ . '/birkly_paths.php';
    $path = birkly_project_root() . '/settings/general.json';
    if (is_file($path)) {
        @unlink($path);
    }
}
