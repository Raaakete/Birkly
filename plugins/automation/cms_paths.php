<?php
/**
 * CMS path helpers for marketplace-installed plugins on BIRKLY_DATA volumes.
 *
 * Relative paths like __DIR__ . '/../../core' break when plugins live under
 * /app/data/plugins instead of /app/plugins.
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

    return dirname(__DIR__, 2);
}

function automation_settings_dir(): string
{
    if (function_exists('birkly_plugin_state_settings_dir')) {
        return birkly_plugin_state_settings_dir();
    }

    return automation_cms_root() . '/settings';
}

function automation_content_dir(): string
{
    return automation_cms_root() . '/content';
}
