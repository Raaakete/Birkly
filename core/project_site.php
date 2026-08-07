<?php
/**
 * Public site files live under project/ (P36-D4). Legacy demo-website/ is migrated on demand.
 * Public URL resolution uses hosting settings (P43).
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';

function birkly_public_site_dir(): string
{
    return 'project';
}

function birkly_public_site_path(): string
{
    return birkly_project_root() . '/' . birkly_public_site_dir();
}

function birkly_public_site_url(string $suffix = '/'): string
{
    require_once __DIR__ . '/hosting_urls.php';

    $suffix = $suffix === '' ? '/' : $suffix;
    if ($suffix[0] !== '/') {
        $suffix = '/' . $suffix;
    }

    $config = birkly_hosting_load();
    $publicBase = birkly_hosting_public_base_url();

    if ($config['public_site_mode'] === 'project' && $publicBase !== '') {
        if (str_starts_with($suffix, '/project/')) {
            $suffix = birkly_hosting_clean_public_url($suffix);
        }
        if ($suffix === '/project' || $suffix === '/project/') {
            $suffix = '/';
        }
        return rtrim($publicBase, '/') . $suffix;
    }

    if ($config['public_site_mode'] === 'external' && $publicBase !== '') {
        return rtrim($publicBase, '/') . $suffix;
    }

    return birkly_url('/' . birkly_public_site_dir() . $suffix);
}

function birkly_legacy_demo_website_path(): string
{
    return birkly_project_root() . '/demo-website';
}

/**
 * Move demo-website/ → project/ when upgrading legacy installs.
 */
function birkly_migrate_demo_website_to_project(): bool
{
    $legacy = birkly_legacy_demo_website_path();
    if (!is_dir($legacy)) {
        return false;
    }

    $target = birkly_public_site_path();
    if (!is_dir($target) && !mkdir($target, 0755, true)) {
        return false;
    }

    $targetHasContent = false;
    foreach (scandir($target) ?: [] as $item) {
        if ($item === '.' || $item === '..' || $item === '.gitkeep') {
            continue;
        }
        $targetHasContent = true;
        break;
    }
    if ($targetHasContent) {
        return false;
    }

    foreach (scandir($legacy) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        if (!@rename($legacy . '/' . $item, $target . '/' . $item)) {
            return false;
        }
    }

    @rmdir($legacy);

    return true;
}

function birkly_ensure_public_site_path(): void
{
    birkly_migrate_demo_website_to_project();
}

/**
 * Git metadata must never be readable over HTTP (P59-M2). Git sync keeps a
 * managed worktree at project/.git; serving it would leak history, the remote
 * URL, and anything a user committed by hand.
 */
function birkly_is_git_metadata_path(string $uri): bool
{
    return preg_match('#(^|/)\.git(/|$)#', rawurldecode($uri)) === 1;
}

/**
 * @return bool True when the request was rejected and the caller should stop.
 */
function birkly_deny_git_metadata_request(string $uri): bool
{
    if (!birkly_is_git_metadata_path($uri)) {
        return false;
    }

    http_response_code(403);
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "Forbidden\n";
    return true;
}
