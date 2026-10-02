<?php
/**
 * Flatten admin/css/style.css @imports into one file for local built-in server performance.
 *
 * Browsers load @import chains serially; php -S serves one request at a time, so
 * dozens of CSS round-trips make Windows admin feel unusable. One bundle fixes that.
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';

function birkly_admin_css_bundle_paths(): array
{
    $cssRoot = birkly_project_root() . '/admin/css';
    return [
        'css_root' => $cssRoot,
        'hub' => $cssRoot . '/style.css',
        'bundle' => $cssRoot . '/style.bundled.css',
        'bundle_rel' => 'admin/css/style.bundled.css',
    ];
}

/**
 * @return list<string> Relative paths from admin/css/ in hub order.
 */
function birkly_admin_css_import_list(string $hubPath): array
{
    if (!is_file($hubPath)) {
        return [];
    }
    $content = (string) file_get_contents($hubPath);
    if (!preg_match_all("/@import\\s+url\\(['\"]?([^'\"\\)]+)['\"]?\\)\\s*;/", $content, $matches)) {
        return [];
    }
    $imports = [];
    foreach ($matches[1] as $rel) {
        $rel = ltrim(str_replace('\\', '/', (string) $rel), '/');
        if ($rel !== '') {
            $imports[] = $rel;
        }
    }
    return $imports;
}

function birkly_admin_css_bundle_is_fresh(): bool
{
    $paths = birkly_admin_css_bundle_paths();
    if (!is_file($paths['bundle']) || !is_file($paths['hub'])) {
        return false;
    }
    $bundleMtime = (int) filemtime($paths['bundle']);
    $hubMtime = (int) filemtime($paths['hub']);
    if ($bundleMtime < $hubMtime) {
        return false;
    }
    foreach (birkly_admin_css_import_list($paths['hub']) as $rel) {
        $part = $paths['css_root'] . '/' . $rel;
        if (is_file($part) && (int) filemtime($part) > $bundleMtime) {
            return false;
        }
    }
    return true;
}

function birkly_build_admin_css_bundle(): bool
{
    $paths = birkly_admin_css_bundle_paths();
    if (!is_file($paths['hub'])) {
        return false;
    }
    $imports = birkly_admin_css_import_list($paths['hub']);
    if ($imports === []) {
        return false;
    }

    $chunks = [
        '/* Birkly admin CSS bundle — generated from admin/css/style.css; do not edit by hand. */',
        '/* Regenerated automatically on the built-in PHP server when sources change. */',
    ];
    foreach ($imports as $rel) {
        $part = $paths['css_root'] . '/' . $rel;
        if (!is_file($part)) {
            continue;
        }
        $chunks[] = "\n/* --- {$rel} --- */\n";
        $chunks[] = (string) file_get_contents($part);
    }

    $written = file_put_contents($paths['bundle'], implode('', $chunks));
    return $written !== false;
}

function birkly_ensure_admin_css_bundle(): void
{
    if (birkly_admin_css_bundle_is_fresh()) {
        return;
    }
    birkly_build_admin_css_bundle();
}

/**
 * Main admin stylesheet path relative to project root (no leading slash).
 */
function birkly_admin_main_stylesheet_relative(): string
{
    $paths = birkly_admin_css_bundle_paths();
    $useBundle = PHP_SAPI === 'cli-server' || (function_exists('birkly_is_local_dev') && birkly_is_local_dev());
    if (!$useBundle) {
        return 'admin/css/style.css';
    }
    birkly_ensure_admin_css_bundle();
    if (is_file($paths['bundle'])) {
        return $paths['bundle_rel'];
    }
    return 'admin/css/style.css';
}

function birkly_is_local_dev(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    return $host === 'localhost'
        || $host === '127.0.0.1'
        || str_starts_with($host, 'localhost:')
        || str_starts_with($host, '127.0.0.1:');
}
