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

/**
 * Web path prefix for project/ assets on the current CMS host.
 * Includes the install base path when Birkly is not at the web root
 * (e.g. "/subdir/Birkly/project" instead of "/project").
 */
function birkly_public_site_url_prefix(): string
{
    return birkly_url('/' . birkly_public_site_dir());
}

/**
 * True when project HTML should rewrite root-absolute /project/ and /birkly-client.js paths.
 */
function birkly_project_needs_install_path_prefix(): bool
{
    return birkly_base_path() !== '';
}

/**
 * Rewrite root-absolute project and client script paths for subdirectory installs.
 * Starter packs (Marsk, etc.) ship href="/project/…" and src="/birkly-client.js";
 * without this, browsers request those from the domain root and miss CSS + client JS.
 */
function birkly_project_rewrite_install_paths(string $html): string
{
    if (!birkly_project_needs_install_path_prefix()) {
        return $html;
    }

    $projectPrefix = birkly_public_site_url_prefix();
    require_once __DIR__ . '/build_info.php';
    $clientSrc = birkly_public_runtime_asset_url('birkly-client.js');
    $webmcpSrc = birkly_public_runtime_asset_url('birkly-webmcp.js');
    $hydrateSrc = birkly_public_runtime_asset_url('birkly-hydrate.js');

    $replacements = [
        'href="/project/' => 'href="' . $projectPrefix . '/',
        "href='/project/" => "href='" . $projectPrefix . '/',
        'src="/project/' => 'src="' . $projectPrefix . '/',
        "src='/project/" => "src='" . $projectPrefix . '/',
        'href="/project"' => 'href="' . $projectPrefix . '"',
        "href='/project'" => "href='" . $projectPrefix . "'",
        'src="/birkly-client.js"' => 'src="' . $clientSrc . '"',
        "src='/birkly-client.js'" => "src='" . $clientSrc . "'",
        'src="/birkly-webmcp.js"' => 'src="' . $webmcpSrc . '"',
        "src='/birkly-webmcp.js'" => "src='" . $webmcpSrc . "'",
        'src="/birkly-hydrate.js"' => 'src="' . $hydrateSrc . '"',
        "src='/birkly-hydrate.js'" => "src='" . $hydrateSrc . "'",
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $html);
}

/**
 * True when visitors should see clean URLs (no /project/ prefix) on the public host.
 */
function birkly_project_serves_clean_public_urls(): bool
{
    require_once __DIR__ . '/hosting_urls.php';
    $config = birkly_hosting_load();

    return ($config['public_site_mode'] ?? 'none') === 'project'
        && birkly_hosting_public_base_url() !== '';
}

/**
 * Strip legacy /project/ prefixes for clean public URL hosting (P66).
 */
function birkly_project_rewrite_clean_public_paths(string $html): string
{
    if (!birkly_project_serves_clean_public_urls() || birkly_project_needs_install_path_prefix()) {
        return $html;
    }

    $replacements = [
        'href="/project/' => 'href="/',
        "href='/project/" => "href='/",
        'src="/project/' => 'src="/',
        "src='/project/" => "src='/",
        'href="/project"' => 'href="/"',
        "href='/project'" => "href='/'",
        'action="/project/' => 'action="/',
        "action='/project/" => "action='/",
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $html);
}

/**
 * Ensure birkly-client.js uses absolute CMS URL + data-cms-url when serving project HTML.
 */
function birkly_project_inject_runtime_scripts(string $html): string
{
    require_once __DIR__ . '/hosting_urls.php';
    $config = birkly_hosting_load();
    if (($config['public_site_mode'] ?? 'none') !== 'project') {
        return $html;
    }

    $cmsBase = birkly_cms_web_base_url();
    if ($cmsBase === '') {
        return $html;
    }

    require_once __DIR__ . '/build_info.php';
    $clientSrc = htmlspecialchars(rtrim($cmsBase, '/') . birkly_public_runtime_asset_url('birkly-client.js'), ENT_QUOTES, 'UTF-8');
    $cmsAttr = htmlspecialchars($cmsBase, ENT_QUOTES, 'UTF-8');
    $clientTag = '<script src="' . $clientSrc . '" data-cms-url="' . $cmsAttr . '"></script>';

    if (preg_match('#<script[^>]+src=["\'][^"\']*birkly-client\.js[^"\']*["\'][^>]*></script>#i', $html)) {
        return preg_replace(
            '#<script[^>]+src=["\'][^"\']*birkly-client\.js[^"\']*["\'][^>]*></script>#i',
            $clientTag,
            $html,
            1
        ) ?? $html;
    }

    if (stripos($html, 'birkly-client.js') === false && preg_match('/<\/body>/i', $html)) {
        return preg_replace('/<\/body>/i', '    ' . $clientTag . "\n</body>", $html, 1) ?? $html;
    }

    return $html;
}

/**
 * Inject CMS base meta + rewrite paths before serving project HTML.
 */
function birkly_project_prepare_html_for_serve(string $html): string
{
    $html = birkly_project_rewrite_install_paths($html);
    $html = birkly_project_rewrite_clean_public_paths($html);
    $html = birkly_project_inject_runtime_scripts($html);

    require_once __DIR__ . '/hosting_urls.php';
    $cmsBase = birkly_cms_web_base_url();
    if ($cmsBase === '') {
        return $html;
    }

    if (stripos($html, 'name="birkly-cms-base"') !== false || stripos($html, "name='birkly-cms-base'") !== false) {
        return $html;
    }

    $meta = '<meta name="birkly-cms-base" content="' . htmlspecialchars($cmsBase, ENT_QUOTES, 'UTF-8') . '">';
    if (preg_match('/<head[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
        $pos = $m[0][1] + strlen($m[0][0]);
        return substr($html, 0, $pos) . "\n    " . $meta . substr($html, $pos);
    }

    return $meta . "\n" . $html;
}

/**
 * Detect legacy /project/ paths in raw project HTML (before serve-time rewrite).
 */
function birkly_project_raw_html_uses_legacy_paths(string $html): bool
{
    return preg_match('#(?:href|src|action)=["\']/project/#', $html) === 1
        || preg_match("#(?:href|src|action)=['']/project/#", $html) === 1;
}

/**
 * Persist clean-path rewrites into project HTML files (Repair delivery).
 *
 * @return array{changed: bool, files: list<string>}
 */
function birkly_project_repair_legacy_paths(bool $persist = true): array
{
    $projectRoot = birkly_public_site_path();
    if (!is_dir($projectRoot)) {
        return ['changed' => false, 'files' => []];
    }

    $changedFiles = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($projectRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['html', 'htm'], true)) {
            continue;
        }
        $path = $file->getPathname();
        $original = (string) file_get_contents($path);
        $repaired = birkly_project_rewrite_clean_public_paths($original);
        $repaired = birkly_project_inject_runtime_scripts($repaired);
        if ($repaired === $original) {
            continue;
        }
        if ($persist) {
            file_put_contents($path, $repaired);
        }
        $changedFiles[] = str_replace($projectRoot . '/', '', $path);
    }

    return ['changed' => $changedFiles !== [], 'files' => $changedFiles];
}

/** @return list<string> */
function birkly_visit_url_format_options(): array
{
    return ['auto', 'directory', 'index'];
}

function birkly_normalize_visit_url_format(string $value): string
{
    $value = strtolower(trim($value));
    return in_array($value, birkly_visit_url_format_options(), true) ? $value : 'auto';
}

/**
 * Resolve visit_url_format to directory | index (auto picks based on hosting/SSR).
 *
 * @param array<string,mixed>|null $settings
 */
function birkly_resolve_visit_url_format(?array $settings = null): string
{
    if ($settings === null) {
        require_once __DIR__ . '/project_settings.php';
        $settings = birkly_load_project_settings();
    }

    $format = birkly_normalize_visit_url_format((string) ($settings['visit_url_format'] ?? 'auto'));
    if ($format !== 'auto') {
        return $format;
    }

    require_once __DIR__ . '/hosting_urls.php';

    // Subdirectory installs, SSR, and clean public URLs need the site root — explicit
    // index.html is often served as a static file and skips PHP routing.
    if (birkly_base_path() !== '') {
        return 'directory';
    }

    require_once __DIR__ . '/project_ssr.php';
    if (birkly_project_ssr_enabled()) {
        return 'directory';
    }

    $hosting = birkly_hosting_load();
    if (($hosting['public_site_mode'] ?? 'none') === 'project' && birkly_hosting_public_base_url() !== '') {
        return 'directory';
    }

    $indexFile = birkly_public_site_path() . '/index.html';
    return is_file($indexFile) ? 'index' : 'directory';
}

/**
 * Public URL for the project site home page (Visit project button, previews).
 */
function birkly_public_site_visit_url(?array $settings = null): string
{
    birkly_ensure_public_site_path();

    $format = birkly_resolve_visit_url_format($settings);
    if ($format === 'index' && is_file(birkly_public_site_path() . '/index.html')) {
        return birkly_public_site_url('/index.html');
    }

    return birkly_public_site_url('/');
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

/**
 * Asset URL for project templates — clean public URL when mode=project, else CMS /project prefix.
 */
function birkly_project_asset_url(string $relativePath = ''): string
{
    $relativePath = trim($relativePath);
    if ($relativePath !== '' && $relativePath[0] !== '/') {
        $relativePath = '/' . $relativePath;
    }

    require_once __DIR__ . '/hosting_urls.php';
    $config = birkly_hosting_load();
    if ($config['public_site_mode'] === 'project' && birkly_hosting_public_base_url() !== '') {
        return birkly_public_site_url($relativePath === '' ? '/' : $relativePath);
    }

    return birkly_public_site_url_prefix() . $relativePath;
}

/**
 * Visitor-facing page URL (alias of public_site_url for agents/templates).
 */
function birkly_project_page_url(string $relativePath = '/'): string
{
    return birkly_public_site_url($relativePath);
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
