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

    // P58 follow-up: production had ssr_cache_ttl=0 saved, which forced every
    // request through Cache-Write: skip (always MISS). For Birkly-hosted server
    // SSR, treat 0 as "use default" so cold-load caching stays on. Explicit
    // disable remains available via BIRKLY_SSR_CACHE_TTL=0 or render_mode=client.
    $envTtl = getenv('BIRKLY_SSR_CACHE_TTL');
    if ($envTtl !== false && trim((string) $envTtl) !== '') {
        $merged['ssr_cache_ttl'] = max(0, (int) $envTtl);
    } elseif (
        (int) $merged['ssr_cache_ttl'] === 0
        && ($merged['hosting_type'] ?? '') === 'birkly'
        && in_array($merged['render_mode'] ?? '', ['server', 'auto'], true)
    ) {
        $merged['ssr_cache_ttl'] = (int) $defaults['ssr_cache_ttl'];
    }

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

    if (preg_match('/\{from\s+[\'"]agent-guide[\'"]\s+get\s+summary\}/i', $text)) {
        $add(['type' => 'agent_guide_summary', 'into' => 'agent_guide']);
    }

    // {for each entry in 'sub' of 'parent'.current ...}
    if (preg_match_all('/\{for\s+(?:each\s+)?[a-zA-Z0-9_]+\s+in\s+\'([^\']+)\'\s+of\s+\'([^\']+)\'\.\s*current/i', $text, $m, PREG_SET_ORDER)) {
        foreach ($m as $match) {
            $add(['type' => 'list_entries', 'into' => $match[2], 'collection' => $match[2]]);
        }
    }

    // {for each entry in 'sub' of 'parent'.'parent-slug' ...} — parent list + get_entry cover SSR prefetch.
    if (preg_match_all(
        '/\{for\s+(?:each\s+)?[a-zA-Z0-9_]+\s+in\s+\'([^\']+)\'\s+of\s+\'([^\']+)\'\.\s*\'([^\']+)\'/i',
        $text,
        $m,
        PREG_SET_ORDER
    )) {
        foreach ($m as $match) {
            $parentSlug = trim($match[3]);
            if ($parentSlug === '' || in_array(strtolower($parentSlug), ['current', 'newest', 'last-updated'], true)) {
                continue;
            }
            $parentCollection = $match[2];
            $add(['type' => 'list_entries', 'into' => $parentCollection, 'collection' => $parentCollection]);
            $add([
                'type' => 'get_entry',
                'into' => '__entry__:' . $parentCollection . ':' . $parentSlug,
                'collection' => $parentCollection,
                'slug' => $parentSlug,
            ]);
        }
    }

    return $requests;
}

/**
 * Sub-entry bundle requests for {for … in 'sub' of 'parent'.'slug'} on list/project pages.
 *
 * @param array<string,mixed> $data Render data after the first page_bundle pass (needs __entry__:* keys).
 * @return list<array<string,mixed>>
 */
function birkly_project_ssr_build_slug_sub_entry_requests(string $html, array $data): array
{
    require_once __DIR__ . '/birkly_template_engine.php';
    require_once __DIR__ . '/public_read.php';

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

    if (!preg_match_all(
        '/\{for\s+(?:each\s+)?[a-zA-Z0-9_]+\s+in\s+\'([^\']+)\'\s+of\s+\'([^\']+)\'\.\s*\'([^\']+)\'/i',
        $html,
        $m,
        PREG_SET_ORDER
    )) {
        return [];
    }

    foreach ($m as $match) {
        $subCollection = $match[1];
        $parentCollection = $match[2];
        $parentSlug = trim($match[3]);
        if ($parentSlug === '' || in_array(strtolower($parentSlug), ['current', 'newest', 'last-updated'], true)) {
            continue;
        }

        $parent = birkly_template_engine_resolve_entry($parentCollection, $parentSlug, $data);
        if (!is_array($parent)) {
            continue;
        }
        $parentEntryId = trim((string) ($parent['id'] ?? $parent['slug'] ?? ''));
        if ($parentEntryId === '') {
            continue;
        }

        $into = birkly_public_sub_entries_bundle_key($parentCollection, $parentEntryId, $subCollection);
        $add([
            'type' => 'sub_entries',
            'into' => $into,
            'parent_collection' => $parentCollection,
            'parent_entry_id' => $parentEntryId,
            'sub_collection' => $subCollection,
        ]);
    }

    return $requests;
}

/**
 * Build page_bundle sub_entries requests from template HTML + detail context.
 *
 * @return list<array<string,mixed>>
 */
function birkly_project_ssr_build_sub_entry_requests(string $html, array $query, ?array $entry = null): array
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

    $topCollection = trim((string) ($query['collection'] ?? ''));
    $topEntryId = '';
    if (is_array($entry)) {
        $topEntryId = trim((string) ($entry['id'] ?? $entry['slug'] ?? ''));
        if ($topCollection === '') {
            $topCollection = trim((string) ($entry['parent_collection'] ?? ''));
        }
    }
    if ($topCollection === '' || $topEntryId === '') {
        return [];
    }

    if (preg_match_all('/\{for\s+(?:each\s+)?[a-zA-Z0-9_]+\s+in\s+\'([^\']+)\'\s+of\s+\'([^\']+)\'\.\s*current/i', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $match) {
            $subCollection = $match[1];
            $parentCollection = $match[2];
            if ($parentCollection !== $topCollection) {
                continue;
            }
            $into = birkly_public_sub_entries_bundle_key($topCollection, $topEntryId, $subCollection);
            $add([
                'type' => 'sub_entries',
                'into' => $into,
                'parent_collection' => $topCollection,
                'parent_entry_id' => $topEntryId,
                'sub_collection' => $subCollection,
            ]);
        }
    }

    return $requests;
}

/**
 * Expand nested sub-entry bundle requests after first-level sub-entries are loaded.
 *
 * @param array<string,mixed> $data
 * @return list<array<string,mixed>>
 */
function birkly_project_ssr_build_nested_sub_entry_requests(string $html, array $query, array $data): array
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

    $topCollection = trim((string) ($query['collection'] ?? ''));
    $topEntry = $data['entry'] ?? null;
    $topEntryId = is_array($topEntry) ? trim((string) ($topEntry['id'] ?? $topEntry['slug'] ?? '')) : '';
    if ($topCollection === '' || $topEntryId === '') {
        return [];
    }

    if (!preg_match_all('/\{for\s+(?:each\s+)?[a-zA-Z0-9_]+\s+in\s+\'([^\']+)\'\s+of\s+\'([^\']+)\'\.\s*\{([^}]+)\}/i', $html, $m, PREG_SET_ORDER)) {
        return [];
    }

    foreach ($m as $match) {
        $nestedSub = $match[1];
        $parentSubCollection = $match[2];
        $parentRef = trim($match[3]);
        $parentField = $parentRef;
        if (strpos($parentRef, '.') !== false) {
            $parentField = substr($parentRef, strrpos($parentRef, '.') + 1);
        }

        $firstLevelKey = birkly_public_sub_entries_bundle_key($topCollection, $topEntryId, $parentSubCollection);
        $parentItems = $data[$firstLevelKey] ?? null;
        if (!is_array($parentItems)) {
            continue;
        }

        foreach ($parentItems as $parentItem) {
            if (!is_array($parentItem)) {
                continue;
            }
            $parentSubEntryId = trim((string) ($parentItem[$parentField] ?? $parentItem['id'] ?? $parentItem['slug'] ?? ''));
            if ($parentSubEntryId === '') {
                continue;
            }
            $into = birkly_public_sub_entries_bundle_key(
                $topCollection,
                $topEntryId,
                $nestedSub,
                $parentSubCollection,
                $parentSubEntryId
            );
            $add([
                'type' => 'sub_entries',
                'into' => $into,
                'parent_collection' => $topCollection,
                'parent_entry_id' => $topEntryId,
                'parent_sub_collection' => $parentSubCollection,
                'parent_sub_entry_id' => $parentSubEntryId,
                'sub_collection' => $nestedSub,
            ]);
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

    if (stripos($html, '<birkly-header') !== false) {
        $hasNavLinks = false;
        foreach ($requests as $req) {
            $into = (string) ($req['into'] ?? '');
            $collection = (string) ($req['collection'] ?? '');
            if ($into === 'nav-links' || $collection === 'nav-links') {
                $hasNavLinks = true;
                break;
            }
        }
        if (!$hasNavLinks) {
            $requests[] = ['type' => 'list_entries', 'into' => 'nav-links', 'collection' => 'nav-links'];
        }
    }

    $detailReq = birkly_project_ssr_detail_request($query);
    if ($detailReq !== null) {
        $requests[] = $detailReq;
    }

    // trusted_ssr: SSR has no browser Origin / domain-auth context. Without this,
    // page_bundle denies every non-site_elements collection and loops render empty
    // (then get cached), which looks like "missing styles/content" on the public site.
    $bundle = birkly_run_page_bundle_requests($storage, $requests, $language, [
        'lean' => true,
        'trusted_ssr' => true,
    ]);

    $userCurrent = birkly_public_resolve_user_current();

    $data = [
        'current_year' => (int) date('Y'),
        'query' => $query,
        'current' => ['language' => $language],
    ];

    if ($userCurrent !== null) {
        $data['user_current'] = $userCurrent;
        $data['user_logged_in'] = true;
    } else {
        require_once __DIR__ . '/page_bundle_template_functions.php';
        $loggedIn = birkly_page_bundle_call_template_function('user_logged_in', []);
        $data['user_logged_in'] = (bool) $loggedIn;
    }

    $siteElementsList = null;
    foreach (($bundle['data'] ?? []) as $into => $value) {
        if ($into === 'site_elements') {
            $siteElementsList = $value;
            continue;
        }
        $data[$into] = $value;
    }
    $data['site_elements'] = birkly_project_ssr_site_elements_map($siteElementsList);

    $slugSubRequests = birkly_project_ssr_build_slug_sub_entry_requests($html, $data);
    if ($slugSubRequests !== []) {
        $slugSubBundle = birkly_run_page_bundle_requests($storage, $slugSubRequests, $language, [
            'lean' => true,
            'trusted_ssr' => true,
        ]);
        birkly_page_bundle_merge_into_data($data, $slugSubBundle['data'] ?? []);
    }

    // Detail templates often use top-level field tags without a collection loop.
    // Ensure the detail collection's editor fields are trusted even if bundle
    // metadata was incomplete.
    $htmlFieldNames = isset($data['_html_fields']) && is_array($data['_html_fields'])
        ? $data['_html_fields']
        : [];
    $detailCollection = trim((string) ($query['collection'] ?? ''));
    if ($detailCollection !== '') {
        require_once __DIR__ . '/fieldtype_html.php';
        $detailHtmlFields = birkly_page_bundle_html_field_names($storage, [$detailCollection]);
        if ($detailHtmlFields !== []) {
            $htmlFieldNames = array_values(array_unique(array_merge($htmlFieldNames, $detailHtmlFields)));
        }
    }
    if ($htmlFieldNames !== []) {
        $data['_html_fields'] = $htmlFieldNames;
    }

    $fieldTypeNames = isset($data['_field_types']) && is_array($data['_field_types'])
        ? $data['_field_types']
        : [];
    if ($detailCollection !== '') {
        require_once __DIR__ . '/fieldtype_presentation.php';
        $detailFieldTypes = birkly_page_bundle_field_type_map($storage, [$detailCollection]);
        if ($detailFieldTypes !== []) {
            $fieldTypeNames = array_merge($fieldTypeNames, $detailFieldTypes);
        }
    }
    if ($fieldTypeNames !== []) {
        $data['_field_types'] = $fieldTypeNames;
    }

    if (stripos($html, "agent-guide") !== false && stripos($html, 'get summary') !== false) {
        require_once __DIR__ . '/ai_presence_discovery.php';
        $summary = birkly_ai_presence_discovery_template_summary();
        if ($summary !== null) {
            $data['agent_guide'] = $summary;
        }
    }

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

                $subRequests = birkly_project_ssr_build_sub_entry_requests($html, $query, $entry);
                if ($subRequests !== []) {
                    $subBundle = birkly_run_page_bundle_requests($storage, $subRequests, $language, [
                        'lean' => true,
                        'trusted_ssr' => true,
                    ]);
                    birkly_page_bundle_merge_into_data($data, $subBundle['data'] ?? []);

                    $nestedRequests = birkly_project_ssr_build_nested_sub_entry_requests($html, $query, $data);
                    if ($nestedRequests !== []) {
                        $nestedBundle = birkly_run_page_bundle_requests($storage, $nestedRequests, $language, [
                            'lean' => true,
                            'trusted_ssr' => true,
                        ]);
                        birkly_page_bundle_merge_into_data($data, $nestedBundle['data'] ?? []);
                    }
                }
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
        // WebMCP/guide injection state (P60-M7): toggling WebMCP or
        // (un)publishing the guide changes what gets injected, so it must bust
        // the SSR cache. Folded in here so every caller stays consistent.
        'inj:' . birkly_project_ssr_inject_signature(),
    ];
    return hash('sha256', implode('|', $parts));
}

/**
 * Fingerprint of a project HTML source file for SSR cache validation.
 *
 * @return array{mtime:int,size:int,path:string}|null
 */
function birkly_project_ssr_source_fingerprint(string $filePath): ?array
{
    $real = realpath($filePath);
    if ($real === false || !is_file($real)) {
        return null;
    }
    return [
        'mtime' => (int) filemtime($real),
        'size' => (int) filesize($real),
        'path' => $real,
    ];
}

/**
 * Read cached HTML if present and within TTL.
 *
 * @param array{mtime:int,size:int,path:string}|null $sourceFingerprint
 */
function birkly_project_ssr_cache_get(string $key, int $ttl, ?array $sourceFingerprint = null): ?string
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
    if ($sourceFingerprint !== null) {
        $storedMtime = (int) ($meta['source_mtime'] ?? 0);
        $storedSize = (int) ($meta['source_size'] ?? 0);
        $liveMtime = (int) ($sourceFingerprint['mtime'] ?? 0);
        $liveSize = (int) ($sourceFingerprint['size'] ?? 0);
        if ($storedMtime !== $liveMtime || $storedSize !== $liveSize) {
            @unlink($metaFile);
            @unlink($htmlFile);
            return null;
        }
    }
    $html = @file_get_contents($htmlFile);
    return $html === false ? null : $html;
}

/**
 * Persist rendered HTML + metadata.
 *
 * @param array{mtime:int,size:int,path:string}|null $sourceFingerprint
 */
function birkly_project_ssr_cache_put(
    string $key,
    string $html,
    int $ttl,
    string $relativePath = '',
    string $revision = '',
    ?array $sourceFingerprint = null
): bool
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
    if ($sourceFingerprint !== null) {
        $meta['source_mtime'] = (int) ($sourceFingerprint['mtime'] ?? 0);
        $meta['source_size'] = (int) ($sourceFingerprint['size'] ?? 0);
        $meta['source_path'] = (string) ($sourceFingerprint['path'] ?? '');
    }
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
 * Inject pre-rendered nav link JSON for <birkly-header> so the client header
 * can paint navigation without waiting for a template API round-trip.
 *
 * @param array<string,mixed> $data
 */
/**
 * Resolve nav hrefs relative to the site root, not the current page directory.
 */
function birkly_project_ssr_normalize_site_href(string $href): string
{
    $href = trim($href);
    if ($href === '' || $href[0] === '#') {
        return $href;
    }
    if (preg_match('#^(?:https?:|mailto:|tel:|javascript:)#i', $href)) {
        return $href;
    }
    if ($href[0] === '/') {
        return $href;
    }
    return '/' . ltrim($href, './');
}

function birkly_project_ssr_inject_nav_links(string $html, array $data): string
{
    if (stripos($html, '<birkly-header') === false) {
        return $html;
    }
    if (stripos($html, 'id="birkly-nav-links-ssr"') !== false || stripos($html, "id='birkly-nav-links-ssr'") !== false) {
        return $html;
    }

    $entries = $data['nav-links'] ?? null;
    if (!is_array($entries) || $entries === []) {
        return $html;
    }

    $links = [];
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $label = trim((string) ($entry['label'] ?? $entry['title'] ?? ''));
        $href = trim((string) ($entry['href'] ?? ''));
        if ($label === '' || $href === '') {
            continue;
        }
        $links[] = [
            'label' => $label,
            'href' => birkly_project_ssr_normalize_site_href($href),
            'page_id' => trim((string) ($entry['page_id'] ?? '')),
            'sort_order' => (int) ($entry['sort_order'] ?? $entry['order'] ?? 0),
        ];
    }

    if ($links === []) {
        return $html;
    }

    usort($links, static function (array $a, array $b): int {
        return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
    });

    $json = json_encode($links, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return $html;
    }

    $script = '<script type="application/json" id="birkly-nav-links-ssr">' . $json . '</script>';
    if (preg_match('/<head[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
        $pos = $m[0][1] + strlen($m[0][0]);
        return substr($html, 0, $pos) . "\n    " . $script . substr($html, $pos);
    }
    if (stripos($html, '</head>') !== false) {
        return preg_replace('/<\/head>/i', '    ' . $script . "\n</head>", $html, 1) ?? $html;
    }
    return $script . "\n" . $html;
}

/**
 * Inject Discovery packs into a rendered project page (P67).
 * Order: SEO head → Agent Guide discovery → WebMCP runtime.
 */
function birkly_project_ssr_inject_discovery_packs(string $html, array $context = []): string
{
    require_once __DIR__ . '/site_seo.php';
    require_once __DIR__ . '/webmcp.php';
    require_once __DIR__ . '/ai_presence.php';

    $html = birkly_site_seo_inject_head($html, $context);
    $html = birkly_ai_presence_inject_discovery_into_html($html);

    if (birkly_webmcp_is_enabled() && stripos($html, 'birkly-webmcp.js') === false) {
        require_once __DIR__ . '/build_info.php';
        $src = htmlspecialchars(birkly_public_runtime_asset_url('birkly-webmcp.js'), ENT_QUOTES, 'UTF-8');
        $inline = birkly_webmcp_inline_config_script();
        $packScripts = birkly_webmcp_render_pack_script_tags();
        $tag = $inline . "\n    " . $packScripts . '    <script src="' . $src . '" defer></script>';
        if (stripos($html, '</body>') !== false) {
            $html = preg_replace('/<\/body>/i', '    ' . $tag . "\n</body>", $html, 1) ?? $html;
        } else {
            $html .= "\n" . $tag;
        }
    }

    return $html;
}

/**
 * @deprecated Use birkly_project_ssr_inject_discovery_packs()
 */
function birkly_project_ssr_inject_webmcp(string $html): string
{
    return birkly_project_ssr_inject_discovery_packs($html);
}

/**
 * Build the inline `<script>` that publishes the public WebMCP config on
 * `window.BIRKLY_WEBMCP_CONFIG` so the runtime can register tools synchronously
 * on boot instead of awaiting `/api/public/webmcp.php`.
 *
 * Secret-free by construction: uses `birkly_webmcp_public_config()`.
 */
function birkly_webmcp_inline_config_script(): string
{
    require_once __DIR__ . '/webmcp.php';

    $config = birkly_webmcp_public_config();
    $json = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $json = '{"enabled":false}';
    }
    // Guard against `</script>` breaking out of the inline block.
    $json = str_replace('</', '<\/', $json);

    return '<script>window.BIRKLY_WEBMCP_CONFIG = ' . $json . ';</script>';
}

/**
 * Cache signature for injections whose state is independent of page content
 * (WebMCP enablement + guide published state). Folded into the SSR cache
 * revision so toggling WebMCP or (un)publishing the guide busts the cache.
 */
function birkly_project_ssr_inject_signature(): string
{
    require_once __DIR__ . '/webmcp.php';
    require_once __DIR__ . '/ai_presence.php';
    require_once __DIR__ . '/site_seo.php';
    $meta = birkly_ai_presence_load_meta();
    $wm = birkly_webmcp_is_enabled() ? '1' : '0';
    $guide = birkly_ai_presence_is_publicly_live(null, $meta) ? '1' : '0';
    $ver = (int) ($meta['published_version'] ?? 0);
    $seo = birkly_site_seo_signature_part();
    // dsc7 = P67 Discovery packs (SEO inject + pack masters).
    return 'wm' . $wm . 'g' . $guide . 'v' . $ver . $seo . 'dsc7';
}

/**
 * Emit rendered HTML with SSR headers.
 *
 * @param string|null $cacheWrite Optional write diagnostic: ok|fail|skip
 */
function birkly_project_ssr_send(string $html, string $cacheState, ?string $cacheWrite = null, array $sendOptions = []): void
{
    if (!headers_sent()) {
        $httpStatus = (int) ($sendOptions['http_status'] ?? 200);
        if ($httpStatus !== 200) {
            http_response_code($httpStatus);
        }
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Birkly-Render: server');
        header('X-Birkly-SSR-Cache: ' . $cacheState);
        if ($cacheWrite !== null && $cacheWrite !== '') {
            header('X-Birkly-SSR-Cache-Write: ' . $cacheWrite);
        }
        if (!empty($sendOptions['not_found_uri'])) {
            header('X-Robots-Tag: noindex');
            header('Cache-Control: no-store');
        }
    }
    echo birkly_project_prepare_html_for_serve($html);
}

/**
 * Serve a project HTML file server-side rendered.
 * Returns true when the response was emitted; false to fall back to static.
 *
 * @param array<string,mixed> $query
 * @param array<string,mixed> $serveOptions http_status, skip_cache, not_found_uri
 */
function birkly_project_ssr_serve(string $filePath, array $query = [], array $serveOptions = []): bool
{
    $html = @file_get_contents($filePath);
    if ($html === false) {
        return false;
    }

    require_once __DIR__ . '/page_bundle_runner.php';
    require_once __DIR__ . '/storage/StorageFactory.php';

    $settings = birkly_project_ssr_settings();
    $skipCache = !empty($serveOptions['skip_cache']);
    $ttl = $skipCache ? 0 : (int) $settings['ssr_cache_ttl'];
    $language = birkly_project_ssr_language($query);
    $relative = birkly_project_ssr_relative_path($filePath);
    $notFoundUri = (string) ($serveOptions['not_found_uri'] ?? '');

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
    $sourceFingerprint = birkly_project_ssr_source_fingerprint($filePath);

    if ($ttl > 0) {
        $cached = birkly_project_ssr_cache_get($cacheKey, $ttl, $sourceFingerprint);
        if ($cached !== null) {
            if ($notFoundUri !== '') {
                require_once __DIR__ . '/project_custom_404.php';
                $cached = birkly_project_custom_404_inject_not_found_meta($cached, $notFoundUri);
            }
            birkly_project_ssr_send($cached, 'HIT', null, $serveOptions);
            return true;
        }
    }

    $data = birkly_project_ssr_build_data($html, $query, $language, $storage, $requests);
    $rendered = birkly_project_ssr_render($html, $data);
    if ($rendered === null || $rendered === '') {
        return false;
    }

    $rendered = birkly_project_ssr_inject_marker($rendered);
    $rendered = birkly_project_ssr_inject_nav_links($rendered, $data);
    $seoContext = [];
    try {
        require_once __DIR__ . '/site_seo_url_map.php';
        $seoContext = birkly_seo_build_context($relative, $query, $data, $rendered);
    } catch (Throwable $e) {
        error_log('birkly_project_ssr_seo_context: ' . $e->getMessage());
    }
    try {
        $rendered = birkly_project_ssr_inject_discovery_packs($rendered, $seoContext);
    } catch (Throwable $e) {
        error_log('birkly_project_ssr_inject_discovery_packs: ' . $e->getMessage());
    }

    if ($notFoundUri !== '') {
        require_once __DIR__ . '/project_custom_404.php';
        $rendered = birkly_project_custom_404_inject_not_found_meta($rendered, $notFoundUri);
    }

    $cacheWrite = 'skip';
    if ($ttl > 0) {
        $cacheWrite = birkly_project_ssr_cache_put(
            $cacheKey,
            $rendered,
            $ttl,
            $relative,
            $revision,
            $sourceFingerprint
        ) ? 'ok' : 'fail';
    }

    birkly_project_ssr_send($rendered, 'MISS', $cacheWrite, $serveOptions);
    return true;
}
