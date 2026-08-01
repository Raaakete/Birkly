<?php
/**
 * CMS path helpers for marketplace / data-volume plugin installs.
 *
 * Relative paths like __DIR__ . '/../../settings' break when plugins live under
 * /app/data/plugins instead of /app/plugins (realpath differs from symlink path).
 */

declare(strict_types=1);

function automation_cms_root(): string
{
    if (function_exists('birkly_project_root')) {
        return birkly_project_root();
    }
    if (defined('BIRKLY_CMS_ROOT')) {
        return (string) BIRKLY_CMS_ROOT;
    }
    $env = getenv('BIRKLY_CMS_ROOT');
    if (is_string($env) && $env !== '') {
        return rtrim($env, '/');
    }
    if (is_dir('/app/core')) {
        return '/app';
    }

    // Official plugin repo layout: plugins/automation → CMS root is two levels up
    // when installed at CMS/plugins/automation. For /app/data/plugins prefer /app above.
    $candidate = dirname(__DIR__, 2);
    if (is_dir($candidate . '/core') || is_dir($candidate . '/settings')) {
        return $candidate;
    }

    return dirname(__DIR__, 2);
}

function automation_settings_dir(): string
{
    if (function_exists('birkly_plugin_state_settings_dir')) {
        return birkly_plugin_state_settings_dir();
    }
    if (isset($GLOBALS['automation_test_cms_root'])) {
        return rtrim((string) $GLOBALS['automation_test_cms_root'], '/') . '/settings';
    }

    return automation_cms_root() . '/settings';
}

function automation_content_dir(): string
{
    return automation_cms_root() . '/content';
}

function automation_storage()
{
    require_once automation_cms_root() . '/core/storage/StorageFactory.php';
    return StorageFactory::createStorage(automation_settings_dir());
}
