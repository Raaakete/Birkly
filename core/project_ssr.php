<?php
/**
 * Project SSR pipeline (P51-M2; PHP-native since P52-M2).
 * -------------------------------------------------------
 * Optional server-side rendering for Birkly-hosted project sites (`project/`).
 * Renders templates in-process with the PHP template engine
 * (`core/birkly_template_engine.php`, fixture-parity-tested against the shared
 * JS engine `birkly-template-engine.js`) and caches the output. No Node
 * runtime is involved — the former Node CLI bridge and its helpers were
 * deleted in P52-M4.
 *
 * Design guarantees:
 *   - SSR is opt-in (render_mode=server|auto) and only for Birkly hosting with
 *     public_site_mode=project. External hosting never triggers SSR.
 *   - Graceful fallback: any failure (engine missing, engine exception)
 *     returns null so the caller serves the original static file and the
 *     client renders normally.
 *   - Only published content is exposed — data is loaded through the existing
 *     public read APIs (`page_bundle_runner`), never raw drafts.
 *   - Untrusted field values are HTML-escaped by the engine.
 *
 * See docs/reviews/P51-ssr-notes.md and docs/reviews/P52-php-native-ssr-plan.md.
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';
require_once __DIR__ . '/birkly_data_paths.php';
require_once __DIR__ . '/hosting_urls.php';
require_once __DIR__ . '/project_settings.php';

/**
 * Effective SSR settings (defaults merged with settings/project.json + env).
 *
 * @return array{render_mode:string,hosting_type:string,ssr_cache_ttl:int}
 */
function birkly_project_ssr_settings(): array
{
    // Single source of truth for defaults (P52-M3: render_mode=server for
    // Birkly hosting). A saved "client" in settings/project.json still wins —
    // defaults only fill missing keys.
    $defaults = birkly_project_settings_defaults();

    $settings = birkly_load_project_settings();
    $merged = array_merge($defaults, $settings);

    // Optional env override (handy for tests / staging).
    $envMode = getenv('BIRKLY_SSR_RENDER_MODE');
    if ($envMode !== false && trim((string) $envMode) !== '') {
        $merged['render_mode'] = trim((string) $envMode);
    }

    $merged['render_mode'] = in_array($merged['render_mode'], ['client', 'server', 'auto'], true)
        ? $merged['render_mode']
        : $defaults['render_mode'];
    $merged['hosting_type'] = in_array($merged['hosting_type'], ['birkly', 'external'], true)
        ? $merged['hosting_type']
        : 'birkly';
    $merged['ssr_cache_ttl'] = max(0, (int) $merged['ssr_cache_ttl']);

    return [
        'render_mode' => $merged['render_mode'],
        'hosting_type' => $merged['hosting_type'],
        'ssr_cache_ttl' => $merged['ssr_cache_ttl'],
    ];
}

/**
 * Validate render-related settings from an admin save_settings payload.
 *
 * Whitelists render_mode / hosting_type and clamps ssr_cache_ttl. Only keys
 * present in $input are validated; anything else is ignored so callers can mix
 * these with other project settings (e.g. show_visit_button).
 *
 * @param array<string,mixed> $input
 * @return array{patch:array<string,mixed>,error:?string,ssr_affected:bool}
 */
function birkly_project_ssr_validate_render_settings(array $input): array
{
    $patch = [];
    $ssrAffected = false;

    if (array_key_exists('render_mode', $input)) {
        $mode = strtolower(trim((string) $input['render_mode']));
        if (!in_array($mode, ['client', 'server', 'auto'], true)) {
            return ['patch' => [], 'error' => 'Invalid render_mode', 'ssr_affected' => false];
        }
        $patch['render_mode'] = $mode;
        $ssrAffected = true;
    }

    if (array_key_exists('hosting_type', $input)) {
        $type = strtolower(trim((string) $input['hosting_type']));
        if (!in_array($type, ['birkly', 'external'], true)) {
            return ['patch' => [], 'error' => 'Invalid hosting_type', 'ssr_affected' => false];
        }
        $patch['hosting_type'] = $type;
        $ssrAffected = true;
    }

    if (array_key_exists('ssr_cache_ttl', $input)) {
        if (!is_numeric($input['ssr_cache_ttl'])) {
            return ['patch' => [], 'error' => 'Invalid ssr_cache_ttl', 'ssr_affected' => false];
        }
        $ttl = max(0, min(86400, (int) $input['ssr_cache_ttl']));
        $patch['ssr_cache_ttl'] = $ttl;
        $ssrAffected = true;
    }

    return ['patch' => $patch, 'error' => null, 'ssr_affected' => $ssrAffected];
}

/**
 * SSR is enabled only for Birkly-hosted project sites in server/auto mode.
 */
function birkly_project_ssr_enabled(): bool
{
    $settings = birkly_project_ssr_settings();
    if ($settings['hosting_type'] !== 'birkly') {
        return false;
    }
    if (!in_array($settings['render_mode'], ['server', 'auto'], true)) {
        return false;
    }

    $hosting = birkly_hosting_load();
    if (($hosting['public_site_mode'] ?? 'none') !== 'project') {
        return false;
    }

    return true;
}

/**
 * Gate: only HTML files inside project/, when SSR is enabled.
 */
function birkly_project_ssr_should_render(string $filePath): bool
{
    if (!birkly_project_ssr_enabled()) {
        return false;
    }

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    if (!in_array($ext, ['html', 'htm'], true)) {
        return false;
    }

    $projectRoot = realpath(birkly_project_root() . '/project');
    $real = realpath($filePath);
    if ($projectRoot === false || $real === false) {
        return false;
    }

    return str_starts_with($real, $projectRoot . DIRECTORY_SEPARATOR) || $real === $projectRoot;
}

/**
 * Resolve the render language from the query string (mirrors client rules,
 * server-side subset: ?lang= then default 'en').
 */
function birkly_project_ssr_language(array $query): string
{
    $lang = trim((string) ($query['lang'] ?? ''));
    if ($lang !== '' && preg_match('/^[a-zA-Z-]{2,10}$/', $lang)) {
        return strtolower(substr($lang, 0, 2));
    }
    return 'en';
}

/**
 * Project-relative path (used for cache keys), e.g. "/blog.html".
 */
function birkly_project_ssr_relative_path(string $filePath): string
{
    $projectRoot = realpath(birkly_project_root() . '/project');
    $real = realpath($filePath);
    if ($projectRoot !== false && $real !== false && str_starts_with($real, $projectRoot)) {
        $rel = substr($real, strlen($projectRoot));
        return str_replace('\\', '/', $rel === '' ? '/index.html' : $rel);
    }
    return '/' . ltrim(str_replace('\\', '/', basename($filePath)), '/');
}

/**
 * Collect page_bundle-shaped data requests from template HTML.
 *
 * PHP heuristic mirroring `collectBundleRequests()` in
 * birkly-template-engine.js (the canonical implementation, which the browser
 * client runs). Keep the two in sync when the grammar grows.
 *
 * @return list<array<string,mixed>>
 */
function birkly_project_ssr_collect_requests(string $html): array
{
    $requests = [];
    $seen = [];
    $add = static function (array $req) use (&$requests, &$seen): void {
        $key = json_encode($req);
        if ($key === false || isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;
        $requests[] = $req;
    };

    $text = $html;

    // {for each entry in 'collection' ...}
    if (preg_match_all('/\{for\s+(?:each\s+)?[a-zA-Z0-9_]+\s+in\s+\'([a-z0-9_-]+)\'/i', $text, $m)) {
        foreach ($m[1] as $col) {
            $add(['type' => 'list_entries', 'into' => $col, 'collection' => $col]);
        }
    }

    // {from 'collection' get entry 'slug'}
    if (preg_match_all('/\{from\s+\'([a-z0-9_-]+)\'\s+get\s+entry\s+\'([^\']+)\'\}/i', $text, $m, PREG_SET_ORDER)) {
        foreach ($m as $match) {
            $col = $match[1];
            $slug = $match[2];
            if ($col !== 'site_elements') {
                $add(['type' => 'list_entries', 'into' => $col, 'collection' => $col]);
            }
            $add([
                'type' => 'get_entry',
                'into' => '__entry__:' . $col . ':' . $slug,
                'collection' => $col,
                'slug' => $slug,
            ]);
        }
    }

    if (strpos($text, 'site_elements') !== false) {
        $add(['type' => 'list_entries', 'into' => 'site_elements', 'collection' => 'site_elements']);
    }

    // {for each entry in 'sub' of 'parent'.current ...}
    if (preg_match_all('/\{for\s+(?:each\s+)?[a-zA-Z0-9_]+\s+in\s+\'([^\']+)\'\s+of\s+\'([^\']+)\'\.\s*(current|[^\'\s{}]+)/i', $text, $m, PREG_SET_ORDER)) {
        foreach ($m as $match) {
            $add(['type' => 'list_entries', 'into' => $match[2], 'collection' => $match[2]]);
        }
    }

    return $requests;
}

/**
 * Detail-page get_entry request derived from the query string (?collection=&slug=).
 *
 * @return array<string,mixed>|null
 */
function birkly_project_ssr_detail_request(array $query): ?array
{
    $collection = trim((string) ($query['collection'] ?? ''));
    $slug = trim((string) ($query['slug'] ?? ''));
    $entryId = trim((string) ($query['id'] ?? $query['entry'] ?? ''));
    if ($collection === '' || ($slug === '' && $entryId === '')) {
        return null;
    }
    $req = ['type' => 'get_entry', 'collection' => $collection];
    if ($slug !== '') {
        $req['slug'] = $slug;
        $req['into'] = '__entry__:' . $collection . ':' . $slug;
    } else {
        $req['entry_id'] = $entryId;
        $req['into'] = '__entry__:' . $collection . ':' . $entryId;
    }
    return $req;
}

/**
 * Normalize a list of site_elements entries into a slug/key-keyed map, matching
 * the shape the engine expects (`data.site_elements[key]`).
 *
 * @param mixed $list
 * @return array<string,mixed>
 */
function birkly_project_ssr_site_elements_map($list): array
{
    if (!is_array($list)) {
        return [];
    }
    $map = [];
    foreach ($list as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $key = $entry['key'] ?? $entry['slug'] ?? $entry['id'] ?? null;
        if ($key !== null && $key !== '') {
            $map[(string) $key] = $entry;
        }
    }
    return $map;
}

/**
 * Build the render data context for a template (system vars + published data).
 *
 * @param array<string,mixed> $query
 * @param mixed $storage  optional pre-created storage instance
 * @param list<array<string,mixed>>|null $requests optional pre-collected requests
 * @return array<string,mixed>
 */
function birkly_project_ssr_build_data(string $html, array $query = [], string $language = 'en', $storage = null, ?array $requests = null): array
{
    require_once __DIR__ . '/page_bundle_runner.php';
    require_once __DIR__ . '/storage/StorageFactory.php';

    if ($storage === null) {
        $storage = get_storage(birkly_project_root() . '/content');
    }
    if ($requests === null) {
        $requests = birkly_project_ssr_collect_requests($html);
    }

    // trusted_ssr: SSR has no browser Origin / domain-auth context. Without this,
    // page_bundle denies every non-site_elements collection and loops render empty
    // (then get cached), which looks like "missing styles/content" on the public site.
    $bundle = birkly_run_page_bundle_requests($storage, $requests, $language, [
        'lean' => true,
        'trusted_ssr' => true,
    ]);

    $data = [
        'current_year' => (int) date('Y'),
        'query' => $query,
        'current' => ['language' => $language],
    ];

    $siteElementsList = null;
    foreach (($bundle['data'] ?? []) as $into => $value) {
        if ($into === 'site_elements') {
            $siteElementsList = $value;
            continue;
        }
        $data[$into] = $value;
    }
    $data['site_elements'] = birkly_project_ssr_site_elements_map($siteElementsList);

    // Detail page: load the current entry (published only) and expose it at the
    // top level + `data.entry`, mirroring the client.
    $collection = trim((string) ($query['collection'] ?? ''));
    $slug = trim((string) ($query['slug'] ?? ''));
    $entryId = trim((string) ($query['id'] ?? $query['entry'] ?? ''));
    if ($collection !== '' && ($slug !== '' || $entryId !== '')) {
        $result = birkly_public_get_entry($storage, $collection, $slug, $entryId, $language);
        if (($result['status'] ?? '') === 'ok') {
            $entry = $result['data']['entry'] ?? null;
            if (is_array($entry)) {
                $data = array_merge($data, $entry);
                $data['entry'] = $entry;
                $slugKey = $slug !== '' ? $slug : (string) ($entry['slug'] ?? $entryId);
                $data['__entry__:' . $collection . ':' . $slugKey] = $entry;
            }
        }
    }

    return $data;
}

/**
 * Absolute path of the in-process PHP template engine.
 */
function birkly_project_ssr_engine_path(): string
{
    return __DIR__ . '/birkly_template_engine.php';
}

/**
 * Load the PHP template engine on demand and report availability.
 * Skips the require when `birkly_template_engine_render()` is already defined
 * (tests may inject a stub before loading this file).
 */
function birkly_project_ssr_engine_available(): bool
{
    if (function_exists('birkly_template_engine_render')) {
        return true;
    }
    $path = birkly_project_ssr_engine_path();
    if (!is_file($path)) {
        return false;
    }
    require_once $path;
    return function_exists('birkly_template_engine_render');
}

/**
 * Runtime readiness for SSR (used by admin overview + diagnostics).
 *
 * P52-M2: readiness means the in-process PHP template engine is available.
 * Node / proc_open / CLI checks are gone — `reason` is never `node_missing`
 * (admin copy for the retired Node reasons was removed in P52-M3).
 *
 * @return array{ready:bool,engine:'php'|false,reason:?string}
 */
function birkly_project_ssr_runtime_status(): array
{
    $available = birkly_project_ssr_engine_available();

    return [
        'ready' => $available,
        'engine' => $available ? 'php' : false,
        'reason' => $available ? null : 'engine_missing',
    ];
}

/**
 * Last SSR render failure reason (short machine code for diagnostics headers).
 */
function birkly_project_ssr_last_error(?string $set = null): ?string
{
    static $last = null;
    if ($set !== null) {
        $last = $set;
    }
    return $last;
}

/**
 * Render template HTML server-side, in-process via the PHP template engine
 * (P52-M2 — no Node / proc_open involved).
 * Returns rendered HTML, or null on any failure (caller falls back to the
 * static file + client render).
 *
 * @param array<string,mixed> $data
 * @param array<string,mixed> $options engine options passthrough (escapeHtml, …)
 */
function birkly_project_ssr_render(string $html, array $data, array $options = []): ?string
{
    birkly_project_ssr_last_error('');

    if (!birkly_project_ssr_engine_available()) {
        birkly_project_ssr_last_error('engine_missing');
        error_log('birkly_project_ssr: PHP template engine missing (' . birkly_project_ssr_engine_path() . ')');
        return null;
    }

    try {
        $rendered = birkly_template_engine_render($html, $data, $options);
    } catch (Throwable $e) {
        birkly_project_ssr_last_error('engine_error');
        error_log('birkly_project_ssr: engine error ' . $e->getMessage());
        return null;
    }

    if ($html !== '' && $rendered === '') {
        // A non-empty template should never render to nothing — treat it as a
        // failure so the caller serves the static file instead of a blank page.
        birkly_project_ssr_last_error('engine_empty');
        return null;
    }

    return $rendered;
}

/* -------------------------------------------------------------------------
 * Cache
 * ---------------------------------------------------------------------- */

function birkly_project_ssr_cache_dir(): string
{
    return birkly_data_disk_path() . '/cache/ssr';
}

function birkly_project_ssr_ensure_cache_dir(): bool
{
    $dir = birkly_project_ssr_cache_dir();
    if (is_dir($dir)) {
        return is_writable($dir);
    }
    return @mkdir($dir, 0755, true) && is_writable($dir);
}

/**
 * Build a stable cache key from relevant inputs.
 *
 * @param array<string,mixed> $query
 */
function birkly_project_ssr_cache_key(string $relativePath, array $query, string $language, string $revision): string
{
    $relevant = [];
    foreach (['collection', 'slug', 'id', 'entry'] as $param) {
        if (isset($query[$param]) && $query[$param] !== '') {
            $relevant[$param] = (string) $query[$param];
        }
    }
    ksort($relevant);
    $parts = [
        'path:' . $relativePath,
        'lang:' . $language,
        'rev:' . $revision,
        'q:' . json_encode($relevant),
    ];
    return hash('sha256', implode('|', $parts));
}

/**
 * Read cached HTML if present and within TTL.
 */
function birkly_project_ssr_cache_get(string $key, int $ttl): ?string
{
    $dir = birkly_project_ssr_cache_dir();
    $metaFile = $dir . '/' . $key . '.meta.json';
    $htmlFile = $dir . '/' . $key . '.html';
    if (!is_file($metaFile) || !is_file($htmlFile)) {
        return null;
    }
    $meta = json_decode((string) @file_get_contents($metaFile), true);
    if (!is_array($meta)) {
        return null;
    }
    $created = (int) ($meta['created_at'] ?? 0);
    $metaTtl = (int) ($meta['ttl'] ?? $ttl);
    if ($metaTtl > 0 && (time() - $created) > $metaTtl) {
        @unlink($metaFile);
        @unlink($htmlFile);
        return null;
    }
    $html = @file_get_contents($htmlFile);
    return $html === false ? null : $html;
}

/**
 * Persist rendered HTML + metadata.
 */
function birkly_project_ssr_cache_put(string $key, string $html, int $ttl, string $relativePath = '', string $revision = ''): bool
{
    if (!birkly_project_ssr_ensure_cache_dir()) {
        return false;
    }
    $dir = birkly_project_ssr_cache_dir();
    $meta = [
        'created_at' => time(),
        'ttl' => $ttl,
        'path' => $relativePath,
        'revision' => $revision,
    ];
    $okMeta = @file_put_contents($dir . '/' . $key . '.meta.json', json_encode($meta)) !== false;
    $okHtml = @file_put_contents($dir . '/' . $key . '.html', $html) !== false;
    return $okMeta && $okHtml;
}

/**
 * Remove all cached SSR output (called on content changes).
 */
function birkly_project_ssr_invalidate_all(): void
{
    require_once __DIR__ . '/page_bundle_helpers.php';
    birkly_public_content_revision_bump();

    $dir = birkly_project_ssr_cache_dir();
    if (!is_dir($dir)) {
        return;
    }
    foreach (glob($dir . '/*') ?: [] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

/**
 * Remove cached SSR output whose source path starts with $prefix.
 */
function birkly_project_ssr_invalidate_by_prefix(string $prefix): void
{
    $dir = birkly_project_ssr_cache_dir();
    if (!is_dir($dir) || $prefix === '') {
        return;
    }
    foreach (glob($dir . '/*.meta.json') ?: [] as $metaFile) {
        $meta = json_decode((string) @file_get_contents($metaFile), true);
        $path = is_array($meta) ? (string) ($meta['path'] ?? '') : '';
        if ($path !== '' && str_starts_with($path, $prefix)) {
            @unlink($metaFile);
            $htmlFile = preg_replace('/\.meta\.json$/', '.html', $metaFile);
            if (is_string($htmlFile) && is_file($htmlFile)) {
                @unlink($htmlFile);
            }
        }
    }
}

/**
 * Ensure the SSR marker meta tag exists so the client knows the page is
 * pre-rendered (and can skip re-rendering / reveal immediately).
 */
function birkly_project_ssr_inject_marker(string $html): string
{
    if (stripos($html, 'name="birkly-ssr"') !== false || stripos($html, "name='birkly-ssr'") !== false) {
        return $html;
    }
    $marker = '<meta name="birkly-ssr" content="1">';
    if (preg_match('/<head[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
        $pos = $m[0][1] + strlen($m[0][0]);
        return substr($html, 0, $pos) . "\n    " . $marker . substr($html, $pos);
    }
    // No <head> — prepend so at least the marker is present.
    return $marker . "\n" . $html;
}

/**
 * Emit rendered HTML with SSR headers.
 *
 * @param string|null $cacheWrite Optional write diagnostic: ok|fail|skip
 */
function birkly_project_ssr_send(string $html, string $cacheState, ?string $cacheWrite = null): void
{
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Birkly-Render: server');
        header('X-Birkly-SSR-Cache: ' . $cacheState);
        if ($cacheWrite !== null && $cacheWrite !== '') {
            header('X-Birkly-SSR-Cache-Write: ' . $cacheWrite);
        }
    }
    echo $html;
}

/**
 * Serve a project HTML file server-side rendered.
 * Returns true when the response was emitted; false to fall back to static.
 *
 * @param array<string,mixed> $query
 */
function birkly_project_ssr_serve(string $filePath, array $query = []): bool
{
    $html = @file_get_contents($filePath);
    if ($html === false) {
        return false;
    }

    require_once __DIR__ . '/page_bundle_runner.php';
    require_once __DIR__ . '/storage/StorageFactory.php';

    $settings = birkly_project_ssr_settings();
    $ttl = (int) $settings['ssr_cache_ttl'];
    $language = birkly_project_ssr_language($query);
    $relative = birkly_project_ssr_relative_path($filePath);

    $storage = get_storage(birkly_project_root() . '/content');
    $requests = birkly_project_ssr_collect_requests($html);

    // Include the detail entry's collection in the revision inputs so content
    // updates to it bust the cache automatically.
    $revisionRequests = $requests;
    $detail = birkly_project_ssr_detail_request($query);
    if ($detail !== null) {
        $revisionRequests[] = $detail;
    }
    $revision = birkly_page_bundle_compute_revision($storage, $revisionRequests, $language, [$filePath]);

    $cacheKey = birkly_project_ssr_cache_key($relative, $query, $language, $revision);

    if ($ttl > 0) {
        $cached = birkly_project_ssr_cache_get($cacheKey, $ttl);
        if ($cached !== null) {
            birkly_project_ssr_send($cached, 'HIT');
            return true;
        }
    }

    $data = birkly_project_ssr_build_data($html, $query, $language, $storage, $requests);
    $rendered = birkly_project_ssr_render($html, $data);
    if ($rendered === null || $rendered === '') {
        return false;
    }

    $rendered = birkly_project_ssr_inject_marker($rendered);

    $cacheWrite = 'skip';
    if ($ttl > 0) {
        $cacheWrite = birkly_project_ssr_cache_put($cacheKey, $rendered, $ttl, $relative, $revision) ? 'ok' : 'fail';
    }

    birkly_project_ssr_send($rendered, 'MISS', $cacheWrite);
    return true;
}
