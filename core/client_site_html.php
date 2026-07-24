<?php
/**
 * Bootstrap injection for template-first client site HTML.
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';
require_once __DIR__ . '/project_site.php';

function birkly_client_site_html_has_template_syntax(string $html): bool
{
    return (bool) preg_match('/\{\s*(from|for|if)\s+/i', $html);
}

function birkly_client_site_html_uses_birkly_client(string $html): bool
{
    return stripos($html, 'birkly-client.js') !== false
        || stripos($html, 'birkly-client-init.js') !== false;
}

function birkly_client_site_html_already_bootstrapped(string $html): bool
{
    return stripos($html, 'birkly-client-init.js') !== false
        || stripos($html, 'data-birkly-bootstrap') !== false;
}

function birkly_client_site_html_needs_bootstrap(string $html): bool
{
    if (!birkly_client_site_html_uses_birkly_client($html)) {
        return false;
    }
    if (!birkly_client_site_html_has_template_syntax($html)) {
        return false;
    }
    return !birkly_client_site_html_already_bootstrapped($html);
}

function birkly_client_site_bootstrap_script_tag(): string
{
    $src = birkly_url('/birkly-client-init.js');
    return '<script src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" data-birkly-bootstrap></script>' . "\n";
}

function birkly_bootstrap_client_site_html(string $html): string
{
    if (!birkly_client_site_html_needs_bootstrap($html)) {
        return $html;
    }

    $snippet = birkly_client_site_bootstrap_script_tag();
    if (preg_match('/<head[^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE)) {
        $insertAt = $match[0][1] + strlen($match[0][0]);
        return substr($html, 0, $insertAt) . $snippet . substr($html, $insertAt);
    }

    return $snippet . $html;
}

/**
 * Resolve a request path like /project/about.html to a file under the public site root.
 */
function birkly_resolve_project_page_file(string $uriPath): ?string
{
    $path = parse_url($uriPath, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return null;
    }

    $base = birkly_base_path();
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
        if ($path === '') {
            $path = '/';
        }
    }

    $siteDir = '/' . trim(birkly_public_site_dir(), '/');
    if (!str_starts_with($path, $siteDir . '/') && $path !== $siteDir) {
        return null;
    }

    $relative = ltrim(substr($path, strlen($siteDir)), '/');
    if ($relative === '' || !str_ends_with(strtolower($relative), '.html')) {
        return null;
    }

    if (str_contains($relative, '..')) {
        return null;
    }

    $file = birkly_public_site_path() . '/' . $relative;
    if (!is_file($file)) {
        return null;
    }

    $realFile = realpath($file);
    $realRoot = realpath(birkly_public_site_path());
    if ($realFile === false || $realRoot === false || !str_starts_with($realFile, $realRoot)) {
        return null;
    }

    return $realFile;
}

function birkly_serve_project_page_html(string $uriPath): bool
{
    $file = birkly_resolve_project_page_file($uriPath);
    if ($file === null) {
        return false;
    }

    $html = file_get_contents($file);
    if ($html === false) {
        return false;
    }

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache');
    echo birkly_bootstrap_client_site_html($html);
    return true;
}
