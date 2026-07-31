<?php
/**
 * Project SSR pipeline (P51-M2).
 * ------------------------------
 * Optional server-side rendering for Birkly-hosted project sites (`project/`).
 * Renders the shared template engine (`birkly-template-engine.js`) through a
 * tiny Node CLI bridge (`scripts/birkly-ssr-cli.js`) and caches the output.
 *
 * Design guarantees:
 *   - SSR is opt-in (render_mode=server|auto) and only for Birkly hosting with
 *     public_site_mode=project. External hosting never triggers SSR.
 *   - Graceful fallback: any failure (Node missing, timeout, engine error)
 *     returns false so the caller serves the original static file and the
 *     client renders normally.
 *   - Only published content is exposed — data is loaded through the existing
 *     public read APIs (`page_bundle_runner`), never raw drafts.
 *   - Untrusted field values are HTML-escaped by the engine.
 *
 * See docs/reviews/P51-ssr-notes.md.
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';
require_once __DIR__ . '/hosting_urls.php';

/**
 * Effective SSR settings (defaults merged with settings/project.json + env).
 *
 * @return array{render_mode:string,hosting_type:string,ssr_cache_ttl:int}
 */
function birkly_project_ssr_settings(): array
{
    $defaults = [
        'render_mode' => 'client',
        'hosting_type' => 'birkly',
        'ssr_cache_ttl' => 60,
    ];

    $settings = [];
    if (function_exists('birkly_load_project_settings')) {
        $loaded = birkly_load_project_settings();
        if (is_array($loaded)) {
            $settings = $loaded;
        }
    } else {
        $path = birkly_project_root() . '/settings/project.json';
        if (is_readable($path)) {
            $decoded = json_decode((string) @file_get_contents($path), true);
            if (is_array($decoded)) {
                $settings = $decoded;
            }
        }
    }

    $merged = array_merge($defaults, $settings);

    // Optional env override (handy for tests / staging).
    $envMode = getenv('BIRKLY_SSR_RENDER_MODE');
    if ($envMode !== false && trim((string) $envMode) !== '') {
        $merged['render_mode'] = trim((string) $envMode);
    }

    $merged['render_mode'] = in_array($merged['render_mode'], ['client', 'server', 'auto'], true)
        ? $merged['render_mode']
        : 'client';
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
 * birkly-template-engine.js. Kept in sync with that function; the Node CLI
 * "collect" mode exposes the canonical implementation for tests/parity.
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

    $bundle = birkly_run_page_bundle_requests($storage, $requests, $language, ['lean' => true]);

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
 * Locate a usable Node binary (env override, then PATH / common locations).
 * Returns null when Node is not available — callers must fall back to client.
 */
function birkly_project_ssr_node_binary(): ?string
{
    static $cached = false;
    static $resolved = null;

    if ($cached) {
        return $resolved;
    }
    $cached = true;

    $candidates = [];
    $env = getenv('BIRKLY_NODE_BIN');
    if ($env !== false && trim((string) $env) !== '') {
        $candidates[] = trim((string) $env);
    }
    $candidates[] = 'node';
    $candidates[] = '/usr/bin/node';
    $candidates[] = '/usr/local/bin/node';

    foreach ($candidates as $candidate) {
        if ($candidate === '') {
            continue;
        }
        // Absolute / relative path: must be an executable file.
        if (str_contains($candidate, '/') || str_starts_with($candidate, '.')) {
            if (is_file($candidate) && is_executable($candidate)) {
                $resolved = $candidate;
                return $resolved;
            }
            continue;
        }
        // Bare command name: probe PATH via `command -v`.
        $probe = @shell_exec('command -v ' . escapeshellarg($candidate) . ' 2>/dev/null');
        if (is_string($probe)) {
            $probe = trim($probe);
            if ($probe !== '' && is_file($probe) && is_executable($probe)) {
                $resolved = $probe;
                return $resolved;
            }
        }
    }

    $resolved = null;
    return null;
}

/**
 * Runtime readiness for SSR (used by admin overview + diagnostics).
 *
 * @return array{ready:bool,node:string|null,cli:bool,engine:bool,reason:?string}
 */
function birkly_project_ssr_runtime_status(): array
{
    $node = birkly_project_ssr_node_binary();
    $cli = is_file(birkly_project_root() . '/scripts/birkly-ssr-cli.js');
    $engine = is_file(birkly_project_root() . '/birkly-template-engine.js');
    $procOpen = function_exists('proc_open');

    $reason = null;
    if (!$procOpen) {
        $reason = 'proc_open_disabled';
    } elseif ($node === null) {
        $reason = 'node_missing';
    } elseif (!$cli) {
        $reason = 'cli_missing';
    } elseif (!$engine) {
        $reason = 'engine_missing';
    }

    return [
        'ready' => $reason === null,
        'node' => $node,
        'cli' => $cli,
        'engine' => $engine,
        'reason' => $reason,
    ];
}

/**
 * Run the Node CLI with a JSON payload on stdin and a wall-clock timeout.
 * Returns stdout on success (exit code 0), or null on any failure/timeout.
 */
function birkly_project_ssr_run_node(string $node, string $script, string $payload, float $timeoutSeconds = 5.0): ?string
{
    if (!function_exists('proc_open')) {
        return null;
    }

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $cmd = escapeshellarg($node) . ' ' . escapeshellarg($script);
    $process = @proc_open($cmd, $descriptors, $pipes, birkly_project_root());
    if (!is_resource($process)) {
        return null;
    }

    // Write payload and close stdin so the CLI's 'end' handler fires.
    fwrite($pipes[0], $payload);
    fclose($pipes[0]);

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout = '';
    $stderr = '';
    $start = microtime(true);
    $timedOut = false;

    while (true) {
        $status = proc_get_status($process);

        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);

        if (!$status['running']) {
            break;
        }

        if ((microtime(true) - $start) > $timeoutSeconds) {
            $timedOut = true;
            proc_terminate($process, 9);
            break;
        }

        usleep(2000);
    }

    // Drain any remaining output.
    $stdout .= (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exit = proc_close($process);

    if ($timedOut) {
        error_log('birkly_project_ssr: node render timed out after ' . $timeoutSeconds . 's');
        return null;
    }
    if ($exit !== 0) {
        error_log('birkly_project_ssr: node exited ' . $exit . ' stderr=' . trim($stderr));
        return null;
    }

    return $stdout;
}

/**
 * Render template HTML server-side via the Node engine bridge.
 * Returns rendered HTML, or null on any failure (caller falls back to static).
 *
 * @param array<string,mixed> $data
 * @param array<string,mixed> $options
 */
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

function birkly_project_ssr_render(string $html, array $data, array $options = []): ?string
{
    birkly_project_ssr_last_error('');

    $node = birkly_project_ssr_node_binary();
    if ($node === null) {
        birkly_project_ssr_last_error('node_missing');
        error_log('birkly_project_ssr: Node.js not found — install nodejs in the runtime image');
        return null;
    }
    $script = birkly_project_root() . '/scripts/birkly-ssr-cli.js';
    if (!is_file($script)) {
        birkly_project_ssr_last_error('cli_missing');
        return null;
    }

    $payload = json_encode([
        'mode' => 'render',
        'html' => $html,
        'data' => $data,
        'options' => $options !== [] ? $options : (object) [],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($payload === false) {
        birkly_project_ssr_last_error('payload_encode');
        return null;
    }

    // Homepage-sized templates can exceed 5s on cold start; keep a generous budget.
    $timeout = isset($options['timeout']) ? (float) $options['timeout'] : 15.0;
    $raw = birkly_project_ssr_run_node($node, $script, $payload, max(5.0, $timeout));
    if ($raw === null || trim($raw) === '') {
        birkly_project_ssr_last_error('node_timeout_or_fail');
        return null;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || empty($decoded['ok'])) {
        if (is_array($decoded) && isset($decoded['error'])) {
            error_log('birkly_project_ssr: engine error ' . $decoded['error']);
            birkly_project_ssr_last_error('engine_error');
        } else {
            birkly_project_ssr_last_error('bad_cli_response');
        }
        return null;
    }

    return isset($decoded['html']) ? (string) $decoded['html'] : null;
}

/* -------------------------------------------------------------------------
 * Cache
 * ---------------------------------------------------------------------- */

function birkly_project_ssr_cache_dir(): string
{
    return birkly_project_root() . '/cache/ssr';
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
 */
function birkly_project_ssr_send(string $html, string $cacheState): void
{
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Birkly-Render: server');
        header('X-Birkly-SSR-Cache: ' . $cacheState);
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
    $revision = birkly_page_bundle_compute_revision($storage, $revisionRequests, $language);

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

    if ($ttl > 0) {
        birkly_project_ssr_cache_put($cacheKey, $rendered, $ttl, $relative, $revision);
    }

    birkly_project_ssr_send($rendered, 'MISS');
    return true;
}
