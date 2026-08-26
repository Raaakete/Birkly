<?php
/**
 * Site URL Map — scan, bindings, context builder (P68-M1/M4).
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';

/**
 * @return array<string, mixed>
 */
function birkly_seo_url_map_defaults(): array
{
    return [
        'pages' => [],
        'details' => [],
        'redirects' => [],
        'custom' => [],
        'excludes' => [],
    ];
}

/**
 * Normalize url_map, detail_bindings, and scan blocks (seo.json v3).
 *
 * @param array<string, mixed> $config
 * @return array<string, mixed>
 */
function birkly_seo_normalize_url_map_sections(array $config): array
{
    $urlMap = is_array($config['url_map'] ?? null) ? $config['url_map'] : [];
    $config['url_map'] = array_merge(birkly_seo_url_map_defaults(), $urlMap);
    $config['url_map']['pages'] = is_array($config['url_map']['pages'] ?? null)
        ? $config['url_map']['pages'] : [];
    $config['url_map']['details'] = is_array($config['url_map']['details'] ?? null)
        ? $config['url_map']['details'] : [];
    $config['url_map']['redirects'] = is_array($config['url_map']['redirects'] ?? null)
        ? array_values($config['url_map']['redirects']) : [];
    $config['url_map']['custom'] = is_array($config['url_map']['custom'] ?? null)
        ? array_values($config['url_map']['custom']) : [];
    $config['url_map']['excludes'] = is_array($config['url_map']['excludes'] ?? null)
        ? array_values(array_filter(array_map('strval', $config['url_map']['excludes']))) : [];

    $bindings = is_array($config['detail_bindings'] ?? null) ? $config['detail_bindings'] : [];
    $normalizedBindings = [];
    foreach ($bindings as $binding) {
        if (!is_array($binding)) {
            continue;
        }
        $collection = trim((string) ($binding['collection'] ?? ''));
        $template = trim((string) ($binding['template_path'] ?? ''));
        if ($collection === '' || $template === '') {
            continue;
        }
        $id = trim((string) ($binding['id'] ?? ''));
        if ($id === '') {
            $id = birkly_seo_binding_id();
        }
        $excerptField = trim((string) ($binding['excerpt_field'] ?? ''));
        if ($excerptField === '') {
            $excerptField = birkly_seo_excerpt_field_guess($collection);
        }
        $normalizedBindings[] = [
            'id' => $id,
            'collection' => $collection,
            'template_path' => str_replace('\\', '/', ltrim($template, '/')),
            'excerpt_field' => $excerptField,
            'excerpt_field_auto' => !empty($binding['excerpt_field_auto']),
            'url_pattern' => isset($binding['url_pattern']) ? (string) $binding['url_pattern'] : null,
            'auto_detected' => !empty($binding['auto_detected']),
        ];
    }
    $config['detail_bindings'] = $normalizedBindings;

    $scan = is_array($config['scan'] ?? null) ? $config['scan'] : [];
    $config['scan'] = [
        'last_at' => isset($scan['last_at']) ? (string) $scan['last_at'] : null,
        'project_revision' => isset($scan['project_revision']) ? (string) $scan['project_revision'] : '',
        'detected_templates' => is_array($scan['detected_templates'] ?? null)
            ? $scan['detected_templates'] : [],
    ];

    return $config;
}

function birkly_seo_binding_id(): string
{
    return 'bind_' . bin2hex(random_bytes(4));
}

function birkly_seo_public_path_from_file(string $relativePath): string
{
    $rel = '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    if ($rel === '/index.html' || $rel === '/') {
        return '/';
    }
    return $rel;
}

function birkly_seo_label_from_path(string $pathKey): string
{
    if ($pathKey === '/' || $pathKey === '') {
        return 'Home';
    }
    $base = basename($pathKey);
    $name = preg_replace('/\.html?$/i', '', $base) ?? $base;
    $name = str_replace(['-', '_'], ' ', $name);
    return ucwords($name);
}

/**
 * @param list<string> $excludes
 */
function birkly_seo_scan_should_exclude(string $relativePath, array $excludes): bool
{
    $rel = str_replace('\\', '/', ltrim($relativePath, '/'));
    $lower = strtolower($rel);
    if (birkly_seo_is_docs_mirror_path($rel)) {
        return true;
    }
    foreach (['/vendor/', '/node_modules/', '/partials/'] as $segment) {
        if (str_contains($lower, '/' . trim($segment, '/') . '/')
            || str_starts_with($lower, trim($segment, '/') . '/')) {
            return true;
        }
    }
    $base = basename($rel);
    if ($base !== '' && $base[0] === '_') {
        return true;
    }
    foreach ($excludes as $pattern) {
        $pattern = str_replace('\\', '/', ltrim((string) $pattern, '/'));
        if ($pattern !== '' && ($rel === $pattern || str_ends_with($rel, $pattern))) {
            return true;
        }
    }
    return false;
}

/**
 * Docs/help HTML mirrored under project/ (e.g. 01-admin/dashboard.html) are not public site pages.
 */
function birkly_seo_is_docs_mirror_path(string $relativePath): bool
{
    $rel = str_replace('\\', '/', ltrim($relativePath, '/'));
    if (preg_match('#^\d{2}-[^/]+/#', $rel) === 1) {
        return true;
    }
    foreach (['meta/', 'content/', 'docs/', 'admin/', 'snapshots/', 'help/'] as $prefix) {
        if (str_starts_with($rel, $prefix)) {
            return true;
        }
    }
    return false;
}

/**
 * @return array{relative:string,root:string}|null
 */
function birkly_seo_project_relative_path(string $projectDir, string $fullPath): ?array
{
    $rootReal = rtrim(str_replace('\\', '/', realpath($projectDir) ?: $projectDir), '/');
    $fullReal = str_replace('\\', '/', realpath($fullPath) ?: $fullPath);
    if ($rootReal === '' || $fullReal === '') {
        return null;
    }
    if (!str_starts_with($fullReal, $rootReal . '/') && $fullReal !== $rootReal) {
        return null;
    }
    $rel = ltrim(substr($fullReal, strlen($rootReal)), '/');
    return ['relative' => $rel, 'root' => $rootReal];
}

function birkly_seo_template_file_exists(string $templatePath): bool
{
    require_once __DIR__ . '/project_site.php';
    $rel = str_replace('\\', '/', ltrim($templatePath, '/'));
    if ($rel === '') {
        return false;
    }
    $candidates = [$rel];
    if (!str_ends_with(strtolower($rel), '.html') && !str_ends_with(strtolower($rel), '.htm')) {
        $candidates[] = $rel . '.html';
        $candidates[] = $rel . '.htm';
    }
    $root = birkly_public_site_path();
    foreach ($candidates as $candidate) {
        if (is_readable($root . '/' . $candidate)) {
            return true;
        }
    }
    return false;
}

/**
 * Find a detail template HTML file for a collection via birkly-collection meta.
 */
function birkly_seo_find_template_for_collection(string $projectDir, string $collection): ?string
{
    $collection = trim($collection);
    if ($collection === '' || !is_dir($projectDir)) {
        return null;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($projectDir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $ext = strtolower($file->getExtension());
        if ($ext !== 'html' && $ext !== 'htm') {
            continue;
        }
        $resolved = birkly_seo_project_relative_path($projectDir, $file->getPathname());
        if ($resolved === null) {
            continue;
        }
        $content = @file_get_contents($file->getPathname());
        if (!is_string($content)) {
            continue;
        }
        if (preg_match(
            '/<meta\s+name=["\']birkly-collection["\']\s+content=["\']([^"\']+)["\']/i',
            $content,
            $m
        ) && trim($m[1]) === $collection) {
            return $resolved['relative'];
        }
    }
    return null;
}

/**
 * Fix bindings whose template_path does not exist on disk (e.g. truncated "ml" from bad scan).
 *
 * @param array<string, mixed> $config
 * @return array<string, mixed>
 */
function birkly_seo_repair_detail_bindings(array $config): array
{
    require_once __DIR__ . '/project_site.php';
    $config = birkly_seo_normalize_url_map_sections($config);
    $projectDir = birkly_public_site_path();
    $detected = is_array($config['scan']['detected_templates'] ?? null)
        ? $config['scan']['detected_templates'] : [];
    $byCollection = [];
    foreach ($detected as $row) {
        if (!is_array($row)) {
            continue;
        }
        $collection = trim((string) ($row['collection'] ?? ''));
        $template = trim((string) ($row['template_path'] ?? ''));
        if ($collection !== '' && $template !== '') {
            $byCollection[$collection] = $template;
        }
    }

    $bindings = $config['detail_bindings'];
    foreach ($bindings as $i => $binding) {
        if (!is_array($binding)) {
            continue;
        }
        $collection = trim((string) ($binding['collection'] ?? ''));
        $template = trim((string) ($binding['template_path'] ?? ''));
        if ($collection === '') {
            continue;
        }
        if ($template !== '' && birkly_seo_template_file_exists($template)) {
            continue;
        }
        $fixed = $byCollection[$collection] ?? birkly_seo_find_template_for_collection($projectDir, $collection);
        if ($fixed !== null && $fixed !== '') {
            $bindings[$i]['template_path'] = $fixed;
            $bindings[$i]['auto_detected'] = true;
        }
    }
    $config['detail_bindings'] = $bindings;
    return $config;
}

function birkly_seo_project_revision_hash(string $projectDir): string
{
    if (!is_dir($projectDir)) {
        return '';
    }
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($projectDir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $ext = strtolower($file->getExtension());
        if ($ext !== 'html' && $ext !== 'htm') {
            continue;
        }
        $resolved = birkly_seo_project_relative_path($projectDir, $file->getPathname());
        if ($resolved === null) {
            continue;
        }
        $rel = $resolved['relative'];
        $files[$rel] = (int) $file->getMTime();
    }
    ksort($files);
    return substr(hash('sha256', json_encode($files) ?: ''), 0, 12);
}

/**
 * Scan project HTML files and merge into url_map.pages; auto-suggest detail bindings.
 *
 * @param array<string, mixed>|null $config
 * @return array<string, mixed>
 */
function birkly_seo_url_map_scan_project(?array $config = null): array
{
    require_once __DIR__ . '/project_site.php';
    $config = $config ?? birkly_site_seo_load();
    $config = birkly_seo_normalize_url_map_sections($config);

    $projectDir = birkly_project_root() . '/' . birkly_public_site_dir();
    $excludes = $config['url_map']['excludes'];
    $detectedTemplates = [];
    $freshPages = [];
    $scanStats = ['files_seen' => 0, 'excluded' => 0, 'pages' => 0, 'templates' => 0, 'page_files' => [], 'template_files' => []];

    // Keep manual/custom page rows; replace all scan-sourced pages on each rescan.
    $manualPages = [];
    foreach ($config['url_map']['pages'] as $pathKey => $page) {
        if (!is_array($page)) {
            continue;
        }
        if (($page['source'] ?? 'scan') !== 'scan') {
            $manualPages[$pathKey] = $page;
        }
    }

    if (is_dir($projectDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($projectDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            if ($ext !== 'html' && $ext !== 'htm') {
                continue;
            }
            $scanStats['files_seen']++;
            $resolved = birkly_seo_project_relative_path($projectDir, $file->getRealPath() ?: $file->getPathname());
            if ($resolved === null) {
                continue;
            }
            $rel = $resolved['relative'];
            if (birkly_seo_scan_should_exclude($rel, $excludes)) {
                $scanStats['excluded']++;
                continue;
            }

            $content = @file_get_contents($file->getPathname());
            $collectionMeta = null;
            if (is_string($content)
                && preg_match(
                    '/<meta\s+name=["\']birkly-collection["\']\s+content=["\']([^"\']+)["\']/i',
                    $content,
                    $m
                )) {
                $collectionMeta = trim($m[1]);
            }

            if ($collectionMeta !== null && $collectionMeta !== '') {
                $detectedTemplates[] = [
                    'template_path' => $rel,
                    'collection' => $collectionMeta,
                    'auto_detected' => true,
                ];
                $scanStats['templates']++;
                $scanStats['template_files'][] = $rel;
                continue;
            }

            $pathKey = birkly_seo_public_path_from_file($rel);
            $existing = is_array($manualPages[$pathKey] ?? null) ? $manualPages[$pathKey]
                : (is_array($config['url_map']['pages'][$pathKey] ?? null) ? $config['url_map']['pages'][$pathKey] : []);
            $overrides = is_array($existing['overrides'] ?? null) ? $existing['overrides'] : [];
            $freshPages[$pathKey] = [
                'label' => birkly_seo_label_from_path($pathKey),
                'source' => 'scan',
                'file' => $rel,
                'overrides' => $overrides,
                'composition_sources' => is_string($content)
                    ? birkly_seo_parse_composition_hints($content) : [],
            ];
            $scanStats['pages']++;
            $scanStats['page_files'][] = $rel;
        }
    }

    $config['url_map']['pages'] = array_merge($manualPages, $freshPages);
    $config['scan']['last_at'] = date('c');
    $config['scan']['project_revision'] = birkly_seo_project_revision_hash($projectDir);
    $config['scan']['detected_templates'] = $detectedTemplates;
    $scanStats['page_files'] = array_values(array_unique($scanStats['page_files']));
    $scanStats['template_files'] = array_values(array_unique($scanStats['template_files']));

    $bindings = $config['detail_bindings'];
    $knownTemplates = array_map(
        static fn (array $b): string => (string) ($b['template_path'] ?? ''),
        $bindings
    );
    foreach ($detectedTemplates as $dt) {
        $templatePath = (string) ($dt['template_path'] ?? '');
        $collection = (string) ($dt['collection'] ?? '');
        if ($templatePath === '' || $collection === '') {
            continue;
        }
        $matched = false;
        foreach ($bindings as $i => $binding) {
            if (!is_array($binding)) {
                continue;
            }
            if ((string) ($binding['collection'] ?? '') !== $collection) {
                continue;
            }
            $current = (string) ($binding['template_path'] ?? '');
            if ($current === $templatePath) {
                $matched = true;
                break;
            }
            if (!birkly_seo_template_file_exists($current)) {
                $bindings[$i]['template_path'] = $templatePath;
                $bindings[$i]['auto_detected'] = true;
                $knownTemplates[$i] = $templatePath;
                $matched = true;
                break;
            }
        }
        if ($matched || in_array($templatePath, $knownTemplates, true)) {
            continue;
        }
        $bindings[] = [
            'id' => birkly_seo_binding_id(),
            'collection' => $collection,
            'template_path' => $templatePath,
            'excerpt_field' => birkly_seo_excerpt_field_guess($collection),
            'excerpt_field_auto' => true,
            'url_pattern' => null,
            'auto_detected' => true,
        ];
        $knownTemplates[] = $templatePath;
    }
    $config['detail_bindings'] = $bindings;
    $config = birkly_seo_repair_detail_bindings($config);
    $config['scan']['stats'] = $scanStats;

    return $config;
}

/**
 * @return list<array<string, mixed>>
 */
function birkly_seo_detect_detail_templates(?array $config = null): array
{
    $config = birkly_seo_url_map_scan_project($config);
    return is_array($config['scan']['detected_templates'] ?? null)
        ? $config['scan']['detected_templates']
        : [];
}

function birkly_seo_excerpt_field_guess(string $collection): string
{
    if ($collection === '') {
        return 'excerpt';
    }
    require_once __DIR__ . '/entry_storage.php';
    if (!birkly_entry_collection_exists($collection)) {
        return 'excerpt';
    }
    $schema = birkly_entry_read_schema($collection);
    $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : $schema;
    if (!is_array($fields)) {
        return 'excerpt';
    }
    $names = [];
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $name = trim((string) ($field['name'] ?? $field['slug'] ?? ''));
        if ($name !== '') {
            $names[] = $name;
        }
    }
    foreach (['excerpt', 'summary', 'teaser', 'description'] as $preferred) {
        if (in_array($preferred, $names, true)) {
            return $preferred;
        }
    }
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        if ((string) ($field['type'] ?? '') === 'textarea') {
            $name = trim((string) ($field['name'] ?? $field['slug'] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }
    }
    return 'excerpt';
}

/**
 * @param array<string, mixed> $entry
 */
function birkly_seo_entry_field(array $entry, string $field): string
{
    if ($field === 'title') {
        return trim((string) ($entry['title'] ?? ''));
    }
    if (array_key_exists($field, $entry) && !is_array($entry[$field])) {
        return trim((string) $entry[$field]);
    }
    $fields = is_array($entry['fields'] ?? null) ? $entry['fields'] : [];
    if (array_key_exists($field, $fields) && !is_array($fields[$field])) {
        return trim((string) $fields[$field]);
    }
    return '';
}

function birkly_seo_detail_key(string $templatePath, string $collection, string $slug): string
{
    $path = birkly_seo_public_path_from_file($templatePath);
    return $path . '?collection=' . rawurlencode($collection) . '&slug=' . rawurlencode($slug);
}

/**
 * @param array<string, mixed> $config
 * @return array<string, mixed>|null
 */
function birkly_seo_find_binding(array $config, string $relativePath, string $collection): ?array
{
    $rel = str_replace('\\', '/', ltrim($relativePath, '/'));
    $basename = basename($rel);
    foreach ($config['detail_bindings'] ?? [] as $binding) {
        if (!is_array($binding)) {
            continue;
        }
        if ((string) ($binding['collection'] ?? '') !== $collection) {
            continue;
        }
        $template = str_replace('\\', '/', ltrim((string) ($binding['template_path'] ?? ''), '/'));
        if ($template === $rel || $template === $basename || basename($template) === $basename) {
            return $binding;
        }
    }
    return null;
}

/**
 * @param array<string, mixed> $query
 */
function birkly_seo_canonical_url(string $pathKey, array $query = []): string
{
    require_once __DIR__ . '/hosting_urls.php';
    $base = rtrim(birkly_hosting_public_base_url(), '/');
    if ($base === '') {
        $base = rtrim((string) (birkly_hosting_load()['cms_base_url'] ?? ''), '/');
    }
    $path = $pathKey === '/' ? '/index.html' : $pathKey;
    $url = $base !== '' ? $base . $path : $path;
    $collection = trim((string) ($query['collection'] ?? ''));
    $slug = trim((string) ($query['slug'] ?? ''));
    if ($collection !== '' && $slug !== '') {
        $url .= (str_contains($url, '?') ? '&' : '?')
            . 'collection=' . rawurlencode($collection)
            . '&slug=' . rawurlencode($slug);
    }
    return $url;
}

/**
 * Materialize published detail URLs into url_map.details (on publish).
 *
 * @param array<string, mixed> $config
 * @return array<string, mixed>
 */
function birkly_seo_materialize_detail_urls(array $config): array
{
    require_once __DIR__ . '/entry_storage.php';
    $config = birkly_seo_normalize_url_map_sections($config);
    $details = is_array($config['url_map']['details'] ?? null) ? $config['url_map']['details'] : [];
    $existingOverrides = [];
    foreach ($details as $key => $row) {
        if (!is_array($row)) {
            continue;
        }
        if (is_array($row['overrides'] ?? null)) {
            $existingOverrides[(string) $key] = $row['overrides'];
        }
    }

    $details = [];
    foreach ($config['detail_bindings'] as $binding) {
        if (!is_array($binding)) {
            continue;
        }
        $collection = (string) ($binding['collection'] ?? '');
        $template = (string) ($binding['template_path'] ?? '');
        if ($collection === '' || $template === '' || !birkly_entry_collection_exists($collection)) {
            continue;
        }
        foreach (birkly_entry_list_records($collection) as $record) {
            if (!is_array($record)) {
                continue;
            }
            if (($record['status'] ?? '') !== 'published') {
                continue;
            }
            $slug = trim((string) ($record['slug'] ?? $record['id'] ?? ''));
            if ($slug === '') {
                continue;
            }
            $key = birkly_seo_detail_key($template, $collection, $slug);
            $details[$key] = [
                'type' => 'detail',
                'collection' => $collection,
                'slug' => $slug,
                'template_path' => $template,
                'path' => birkly_seo_public_path_from_file($template),
                'label' => trim((string) ($record['title'] ?? $slug)),
                'updated_at' => $record['updated_at'] ?? $record['modified_at'] ?? null,
                'overrides' => $existingOverrides[$key] ?? [],
            ];
        }
    }

    $config['url_map']['details'] = $details;
    return $config;
}

/**
 * Build SEO inject context for SSR / CSR (P68-M4).
 *
 * @param array<string, mixed> $query
 * @param array<string, mixed> $ssrData
 * @return array<string, mixed>
 */
function birkly_seo_build_context(
    string $relativePath,
    array $query = [],
    array $ssrData = [],
    string $renderedHtml = ''
): array {
    $config = birkly_site_seo_load();
    $config = birkly_seo_normalize_url_map_sections($config);
    $pathKey = birkly_seo_public_path_from_file(ltrim(str_replace('\\', '/', $relativePath), '/'));

    $context = [];
    $overrides = [];
    $collection = trim((string) ($query['collection'] ?? ''));
    $slug = trim((string) ($query['slug'] ?? ''));
    $entry = is_array($ssrData['entry'] ?? null) ? $ssrData['entry'] : null;

    if ($collection !== '' && $slug !== '' && $entry !== null) {
        $binding = birkly_seo_find_binding($config, $relativePath, $collection);
        $excerptField = $binding !== null
            ? (string) ($binding['excerpt_field'] ?? 'excerpt')
            : birkly_seo_excerpt_field_guess($collection);

        $context['page_title'] = birkly_seo_entry_field($entry, 'title');
        $context['entry_title'] = $context['page_title'];
        $context['meta_description'] = birkly_seo_entry_field($entry, $excerptField);

        $detailKey = birkly_seo_detail_key(
            $binding !== null ? (string) $binding['template_path'] : $relativePath,
            $collection,
            $slug
        );
        $detailRow = $config['url_map']['details'][$detailKey] ?? null;
        if (is_array($detailRow) && is_array($detailRow['overrides'] ?? null)) {
            $overrides = $detailRow['overrides'];
        }
    } else {
        $page = $config['url_map']['pages'][$pathKey] ?? null;
        if (is_array($page) && is_array($page['overrides'] ?? null)) {
            $overrides = $page['overrides'];
        }
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $renderedHtml, $m)) {
            $parsed = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($parsed !== '') {
                $context['page_title'] = $parsed;
            }
        }
    }

    if (!empty($overrides['title'])) {
        $context['page_title'] = trim((string) $overrides['title']);
    }
    if (!empty($overrides['description'])) {
        $context['meta_description'] = trim((string) $overrides['description']);
    }
    if (!empty($overrides['og_image'])) {
        $context['og_image'] = trim((string) $overrides['og_image']);
    }

    $context['canonical'] = birkly_seo_canonical_url($pathKey, $query);

    if ($entry !== null && $context['entry_title'] ?? '' !== '') {
        $context['is_detail'] = true;
    }

    return $context;
}

/**
 * @return array{ok:bool,config?:array<string,mixed>,error?:string}
 */
function birkly_seo_save_detail_binding(array $binding): array
{
    $config = birkly_site_seo_load();
    $config = birkly_seo_normalize_url_map_sections($config);
    $id = trim((string) ($binding['id'] ?? ''));
    $found = false;
    $bindings = $config['detail_bindings'];
    foreach ($bindings as $i => $row) {
        if (!is_array($row)) {
            continue;
        }
        if ($id !== '' && (string) ($row['id'] ?? '') === $id) {
            $bindings[$i] = array_merge($row, $binding);
            $found = true;
            break;
        }
    }
    if (!$found) {
        $binding['id'] = $id !== '' ? $id : birkly_seo_binding_id();
        $bindings[] = $binding;
    }
    $config['detail_bindings'] = $bindings;
    $config = birkly_seo_normalize_url_map_sections($config);
    $config = birkly_seo_materialize_detail_urls($config);
    return birkly_site_seo_save($config);
}

/**
 * @param array<string, mixed> $urlMapPatch partial url_map merge
 * @return array{ok:bool,config?:array<string,mixed>,error?:string}
 */
function birkly_seo_save_url_map(array $urlMapPatch): array
{
    $config = birkly_site_seo_load();
    $config = birkly_seo_normalize_url_map_sections($config);
    $current = $config['url_map'];
    if (isset($urlMapPatch['pages']) && is_array($urlMapPatch['pages'])) {
        foreach ($urlMapPatch['pages'] as $path => $row) {
            if (!is_array($row)) {
                continue;
            }
            $existing = is_array($current['pages'][$path] ?? null) ? $current['pages'][$path] : [];
            $current['pages'][$path] = array_merge($existing, $row);
        }
    }
    foreach (['redirects', 'custom', 'excludes'] as $listKey) {
        if (isset($urlMapPatch[$listKey]) && is_array($urlMapPatch[$listKey])) {
            $current[$listKey] = $urlMapPatch[$listKey];
        }
    }
    if (isset($urlMapPatch['details']) && is_array($urlMapPatch['details'])) {
        foreach ($urlMapPatch['details'] as $key => $row) {
            if (!is_array($row)) {
                continue;
            }
            $existing = is_array($current['details'][$key] ?? null) ? $current['details'][$key] : [];
            $current['details'][$key] = array_merge($existing, $row);
        }
    }
    $warnings = birkly_seo_detect_redirect_loops($current['redirects']);
    $config['url_map'] = $current;
    $save = birkly_site_seo_save($config);
    if ($save['ok']) {
        $save['warnings'] = $warnings;
    }
    return $save;
}

/**
 * @return array{ok:bool,config?:array<string,mixed>,rows?:list<array<string,mixed>>,error?:string}
 */
function birkly_seo_url_map_summary(array $config): array
{
    $stats = is_array($config['scan']['stats'] ?? null) ? $config['scan']['stats'] : [];
    return [
        'pages' => (int) ($stats['pages'] ?? 0),
        'templates' => (int) ($stats['templates'] ?? 0),
        'bindings' => count($config['detail_bindings'] ?? []),
        'detail_urls' => count($config['url_map']['details'] ?? []),
        'excluded' => (int) ($stats['excluded'] ?? 0),
        'page_files' => is_array($stats['page_files'] ?? null) ? $stats['page_files'] : [],
        'template_files' => is_array($stats['template_files'] ?? null) ? $stats['template_files'] : [],
    ];
}

/**
 * @param array<string, mixed> $query
 */
function birkly_seo_admin_live_url(string $pathKey, array $query = []): string
{
    require_once __DIR__ . '/hosting_urls.php';
    require_once __DIR__ . '/project_site.php';
    $hosting = birkly_hosting_load();
    $base = rtrim((string) ($hosting['public_site_url'] ?? ''), '/');
    if ($base === '') {
        $base = rtrim(birkly_hosting_public_base_url(), '/');
    }
    if ($base === '') {
        return '';
    }
    $mode = (string) ($hosting['public_site_mode'] ?? 'none');
    $rel = $pathKey === '/' ? '/index.html' : '/' . ltrim($pathKey, '/');
    if ($mode === 'project') {
        $rel = rtrim(birkly_public_site_url_prefix(), '/') . $rel;
    }
    $url = $base . $rel;
    $collection = trim((string) ($query['collection'] ?? ''));
    $slug = trim((string) ($query['slug'] ?? ''));
    if ($collection !== '' && $slug !== '') {
        $url .= (str_contains($url, '?') ? '&' : '?')
            . 'collection=' . rawurlencode($collection)
            . '&slug=' . rawurlencode($slug);
    }
    return $url;
}

/**
 * @return array{ok:bool,config?:array<string,mixed>,rows?:list<array<string,mixed>>,error?:string,stats?:array<string,int>,summary?:array<string,int>}
 */
function birkly_seo_scan_and_save(): array
{
    $config = birkly_seo_url_map_scan_project();
    $config = birkly_seo_materialize_detail_urls($config);
    $save = birkly_site_seo_save($config);
    if (!$save['ok']) {
        return $save;
    }
    $saved = $save['config'] ?? $config;
    return [
        'ok' => true,
        'config' => $saved,
        'rows' => birkly_seo_url_map_admin_rows($saved),
        'stats' => is_array($saved['scan']['stats'] ?? null) ? $saved['scan']['stats'] : [],
        'summary' => birkly_seo_url_map_summary($saved),
    ];
}

/**
 * One-click SEO URL map setup: scan, bindings, propose overrides, import legacy meta (P68).
 *
 * @return array{ok:bool,config?:array<string,mixed>,rows?:list<array<string,mixed>>,summary?:array<string,mixed>,error?:string}
 */
function birkly_seo_automated_setup(): array
{
    $config = birkly_seo_url_map_scan_project();
    $config = birkly_seo_import_legacy_entry_meta($config);
    $proposed = birkly_seo_propose_overrides();
    foreach ($proposed['pages'] as $path => $row) {
        if (!is_array($row)) {
            continue;
        }
        $existing = is_array($config['url_map']['pages'][$path] ?? null)
            ? $config['url_map']['pages'][$path] : [];
        $config['url_map']['pages'][$path] = array_merge($existing, $row);
    }
    foreach ($proposed['details'] as $key => $row) {
        if (!is_array($row)) {
            continue;
        }
        $existing = is_array($config['url_map']['details'][$key] ?? null)
            ? $config['url_map']['details'][$key] : [];
        $config['url_map']['details'][$key] = array_merge($existing, $row);
    }
    $config = birkly_seo_materialize_detail_urls($config);
    $save = birkly_site_seo_save($config);
    if (!$save['ok']) {
        return $save;
    }
    $saved = $save['config'] ?? $config;
    return [
        'ok' => true,
        'config' => $saved,
        'rows' => birkly_seo_url_map_admin_rows($saved),
        'summary' => birkly_seo_url_map_summary($saved),
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function birkly_seo_url_map_admin_rows(array $config): array
{
    $config = birkly_seo_normalize_url_map_sections($config);
    $rows = [];
    foreach ($config['url_map']['pages'] as $path => $page) {
        if (!is_array($page)) {
            continue;
        }
        $overrides = is_array($page['overrides'] ?? null) ? $page['overrides'] : [];
        $label = (string) ($page['label'] ?? birkly_seo_label_from_path((string) $path));
        $file = (string) ($page['file'] ?? '');
        $rows[] = [
            'key' => (string) $path,
            'type' => 'page',
            'url' => (string) $path,
            'project_file' => $file,
            'label' => $label,
            'listing_title' => $label,
            'listing_subtitle' => (string) $path,
            'title' => (string) ($overrides['title'] ?? ''),
            'description' => (string) ($overrides['description'] ?? ''),
            'source' => (string) ($page['source'] ?? 'scan'),
            'file' => $file,
            'live_url' => birkly_seo_admin_live_url((string) $path),
            'composition_sources' => is_array($page['composition_sources'] ?? null)
                ? $page['composition_sources'] : [],
        ];
    }
    foreach ($config['url_map']['details'] as $key => $detail) {
        if (!is_array($detail)) {
            continue;
        }
        $overrides = is_array($detail['overrides'] ?? null) ? $detail['overrides'] : [];
        $collection = (string) ($detail['collection'] ?? '');
        $slug = (string) ($detail['slug'] ?? '');
        $template = (string) ($detail['template_path'] ?? '');
        $label = (string) ($detail['label'] ?? '');
        $rows[] = [
            'key' => (string) $key,
            'type' => 'detail',
            'url' => (string) $key,
            'project_file' => $template,
            'label' => $label,
            'listing_title' => $label,
            'listing_subtitle' => $template !== '' && $collection !== '' && $slug !== ''
                ? $template . ' → ' . $collection . ' / ' . $slug
                : (string) $key,
            'title' => (string) ($overrides['title'] ?? ''),
            'description' => (string) ($overrides['description'] ?? ''),
            'collection' => $collection,
            'slug' => $slug,
            'template_path' => $template,
            'live_url' => $template !== ''
                ? birkly_seo_admin_live_url(
                    birkly_seo_public_path_from_file($template),
                    ['collection' => $collection, 'slug' => $slug]
                )
                : '',
        ];
    }
    foreach ($config['url_map']['redirects'] as $i => $redirect) {
        if (!is_array($redirect)) {
            continue;
        }
        $from = (string) ($redirect['from'] ?? '');
        $to = (string) ($redirect['to'] ?? '');
        $status = (int) ($redirect['status'] ?? 301);
        $rows[] = [
            'key' => 'redirect:' . $i,
            'type' => 'redirect',
            'redirect_index' => $i,
            'url' => $from,
            'label' => $to,
            'listing_title' => $from,
            'listing_subtitle' => '→ ' . $to,
            'title' => (string) $status,
            'redirect_to' => $to,
            'redirect_status' => $status,
            'status' => $status,
        ];
    }
    return $rows;
}

/**
 * Normalize redirect path (shared with hosting redirects).
 */
function birkly_seo_normalize_path(string $path): string
{
    require_once __DIR__ . '/hosting_redirects.php';
    return birkly_normalize_redirect_path($path);
}

/**
 * @return array{to_path:string,type:int}|null
 */
function birkly_seo_match_redirect(string $uri): ?array
{
    if (!birkly_site_seo_is_published()) {
        return null;
    }
    $path = birkly_seo_normalize_path(parse_url($uri, PHP_URL_PATH) ?: $uri);
    if ($path === '') {
        return null;
    }
    $config = birkly_site_seo_normalize_url_map_sections(birkly_site_seo_load());
    foreach ($config['url_map']['redirects'] as $rule) {
        if (!is_array($rule)) {
            continue;
        }
        $from = birkly_seo_normalize_path((string) ($rule['from'] ?? ''));
        if ($from === '' || $from !== $path) {
            continue;
        }
        $to = trim((string) ($rule['to'] ?? ''));
        if ($to === '') {
            continue;
        }
        $type = (int) ($rule['status'] ?? 301);
        if (!in_array($type, [301, 302], true)) {
            $type = 301;
        }
        return ['to_path' => $to, 'type' => $type];
    }
    return null;
}

/**
 * @param list<array<string,mixed>> $redirects
 * @return list<string> human-readable loop warnings
 */
function birkly_seo_detect_redirect_loops(array $redirects): array
{
    $map = [];
    foreach ($redirects as $rule) {
        if (!is_array($rule)) {
            continue;
        }
        $from = birkly_seo_normalize_path((string) ($rule['from'] ?? ''));
        $to = trim((string) ($rule['to'] ?? ''));
        if ($from === '' || $to === '') {
            continue;
        }
        if (!preg_match('#^https?://#i', $to)) {
            $to = birkly_seo_normalize_path($to);
        }
        $map[$from] = $to;
    }
    $warnings = [];
    foreach (array_keys($map) as $start) {
        $seen = [];
        $current = $start;
        while (isset($map[$current])) {
            if (isset($seen[$current])) {
                $warnings[] = 'Redirect loop involving ' . $start;
                break;
            }
            $seen[$current] = true;
            $next = $map[$current];
            if (preg_match('#^https?://#i', $next)) {
                break;
            }
            $current = $next;
        }
    }
    return $warnings;
}

/**
 * Resolve pretty URL pattern to detail template + query (P68-M8).
 *
 * @return array{template_path:string,collection:string,slug:string}|null
 */
function birkly_seo_resolve_pretty_url(string $uri): ?array
{
    if (!birkly_site_seo_is_published()) {
        return null;
    }
    $path = birkly_seo_normalize_path(parse_url($uri, PHP_URL_PATH) ?: $uri);
    $config = birkly_seo_normalize_url_map_sections(birkly_site_seo_load());
    foreach ($config['detail_bindings'] as $binding) {
        if (!is_array($binding)) {
            continue;
        }
        $pattern = trim((string) ($binding['url_pattern'] ?? ''));
        if ($pattern === '' || !str_contains($pattern, '{slug}')) {
            continue;
        }
        $regex = '#^' . str_replace('\{slug\}', '([^/]+)', preg_quote($pattern, '#')) . '$#';
        if (preg_match($regex, $path, $m) !== 1) {
            continue;
        }
        return [
            'template_path' => (string) ($binding['template_path'] ?? ''),
            'collection' => (string) ($binding['collection'] ?? ''),
            'slug' => rawurldecode($m[1]),
        ];
    }
    return null;
}

/**
 * Serve a detail page via pretty URL pattern. Returns true when handled (exits).
 */
function birkly_seo_try_pretty_url_route(string $uri): bool
{
    $resolved = birkly_seo_resolve_pretty_url($uri);
    if ($resolved === null) {
        return false;
    }
    $template = ltrim(str_replace('\\', '/', $resolved['template_path']), '/');
    if ($template === '') {
        return false;
    }
    $file = birkly_project_root() . '/project/' . $template;
    if (!is_readable($file)) {
        return false;
    }
    $_GET['collection'] = $resolved['collection'];
    $_GET['slug'] = $resolved['slug'];
    birkly_hosting_serve_public_file($file);
    return true;
}

/**
 * @return list<string>
 */
function birkly_seo_parse_composition_hints(string $html): array
{
    $hints = [];
    if (preg_match_all("/\{for each entry in '([^']+)'/i", $html, $m)) {
        foreach ($m[1] as $slug) {
            $hints[] = (string) $slug;
        }
    }
    if (preg_match_all('/<birkly-region\s+collection=["\']([^"\']+)["\']/i', $html, $m2)) {
        foreach ($m2[1] as $slug) {
            $hints[] = (string) $slug;
        }
    }
    return array_values(array_unique(array_filter($hints)));
}

/**
 * @return list<array{loc:string,lastmod?:string,priority?:string}>
 */
function birkly_seo_sitemap_entries(): array
{
    if (!birkly_site_seo_is_published() || !birkly_site_seo_surface_enabled('sitemap')) {
        return [];
    }
    $config = birkly_seo_normalize_url_map_sections(birkly_site_seo_load());
    $entries = [];
    $detailTemplates = [];
    foreach ($config['detail_bindings'] as $binding) {
        if (is_array($binding) && !empty($binding['template_path'])) {
            $detailTemplates[] = ltrim((string) $binding['template_path'], '/');
        }
    }

    foreach ($config['url_map']['pages'] as $path => $page) {
        if (!is_array($page)) {
            continue;
        }
        $file = ltrim((string) ($page['file'] ?? ''), '/');
        if ($file !== '' && in_array($file, $detailTemplates, true)) {
            continue;
        }
        $loc = birkly_seo_canonical_url((string) $path, []);
        if ($loc === '' || $loc[0] === '/') {
            continue;
        }
        $entries[] = [
            'loc' => $loc,
            'priority' => $path === '/' ? '1.0' : '0.8',
        ];
    }

    foreach ($config['url_map']['details'] as $detail) {
        if (!is_array($detail)) {
            continue;
        }
        $collection = (string) ($detail['collection'] ?? '');
        $slug = (string) ($detail['slug'] ?? '');
        $template = (string) ($detail['template_path'] ?? '');
        if ($collection === '' || $slug === '' || $template === '') {
            continue;
        }
        $binding = null;
        foreach ($config['detail_bindings'] as $b) {
            if (is_array($b) && ($b['collection'] ?? '') === $collection
                && ($b['template_path'] ?? '') === $template) {
                $binding = $b;
                break;
            }
        }
        $pattern = is_array($binding) ? trim((string) ($binding['url_pattern'] ?? '')) : '';
        if ($pattern !== '' && str_contains($pattern, '{slug}')) {
            $loc = birkly_seo_canonical_url(str_replace('{slug}', $slug, $pattern), []);
        } else {
            $loc = birkly_seo_canonical_url(
                birkly_seo_public_path_from_file($template),
                ['collection' => $collection, 'slug' => $slug]
            );
        }
        if ($loc === '' || $loc[0] === '/') {
            continue;
        }
        $entry = ['loc' => $loc, 'priority' => '0.6'];
        if (!empty($detail['updated_at'])) {
            $entry['lastmod'] = (string) $detail['updated_at'];
        }
        $entries[] = $entry;
    }

    foreach ($config['url_map']['custom'] as $custom) {
        if (!is_array($custom)) {
            continue;
        }
        if (!empty($custom['noindex']) || empty($custom['include_sitemap'])) {
            continue;
        }
        $url = trim((string) ($custom['url'] ?? ''));
        if ($url === '') {
            continue;
        }
        $entries[] = ['loc' => $url, 'priority' => '0.5'];
    }

    return $entries;
}

/**
 * @return list<array{id:string,pack:string,label:string,status:string,detail:string}>
 */
function birkly_seo_health_checks(): array
{
    if (!birkly_site_seo_is_published()) {
        return [];
    }
    $config = birkly_seo_normalize_url_map_sections(birkly_site_seo_load());
    $checks = [];
    $titles = [];
    $rows = birkly_seo_url_map_admin_rows($config);

    foreach ($rows as $row) {
        if (($row['type'] ?? '') === 'redirect') {
            continue;
        }
        $title = trim((string) ($row['title'] ?? ''));
        if ($title === '' && ($row['type'] ?? '') === 'page') {
            $checks[] = [
                'id' => 'seo_missing_title_' . md5((string) ($row['key'] ?? '')),
                'pack' => 'seo',
                'label' => 'Missing title: ' . ($row['url'] ?? ''),
                'status' => 'warn',
                'detail' => 'Add a title override or ensure the page template has a title.',
            ];
        }
        if ($title !== '') {
            $titles[$title][] = (string) ($row['url'] ?? '');
        }
    }

    foreach ($titles as $title => $urls) {
        if (count($urls) > 1) {
            $checks[] = [
                'id' => 'seo_dup_title_' . md5($title),
                'pack' => 'seo',
                'label' => 'Duplicate title: ' . $title,
                'status' => 'warn',
                'detail' => 'Used on: ' . implode(', ', $urls),
            ];
        }
    }

    $loops = birkly_seo_detect_redirect_loops($config['url_map']['redirects']);
    foreach ($loops as $loop) {
        $checks[] = [
            'id' => 'seo_redirect_loop_' . md5($loop),
            'pack' => 'seo',
            'label' => 'Redirect loop',
            'status' => 'fail',
            'detail' => $loop,
        ];
    }

    foreach ($config['url_map']['redirects'] as $i => $rule) {
        if (!is_array($rule)) {
            continue;
        }
        $to = trim((string) ($rule['to'] ?? ''));
        if ($to === '' || preg_match('#^https?://#i', $to)) {
            continue;
        }
        $toPath = birkly_seo_normalize_path($to);
        $found = isset($config['url_map']['pages'][$toPath])
            || $toPath === '/'
            || is_readable(birkly_project_root() . '/project' . ($toPath === '/' ? '/index.html' : $toPath));
        if (!$found) {
            $checks[] = [
                'id' => 'seo_broken_redirect_' . $i,
                'pack' => 'seo',
                'label' => 'Broken redirect target',
                'status' => 'warn',
                'detail' => (string) ($rule['from'] ?? '') . ' → ' . $to,
            ];
        }
    }

    return $checks;
}

/**
 * One-time import of legacy entry meta_* into detail URL overrides (P68-M10).
 *
 * @return array<string, mixed>
 */
function birkly_seo_import_legacy_entry_meta(array $config): array
{
    require_once __DIR__ . '/entry_storage.php';
    $config = birkly_seo_normalize_url_map_sections($config);
    foreach ($config['detail_bindings'] as $binding) {
        if (!is_array($binding)) {
            continue;
        }
        $collection = (string) ($binding['collection'] ?? '');
        $template = (string) ($binding['template_path'] ?? '');
        if ($collection === '' || !birkly_entry_collection_exists($collection)) {
            continue;
        }
        foreach (birkly_entry_list_records($collection) as $record) {
            if (!is_array($record) || ($record['status'] ?? '') !== 'published') {
                continue;
            }
            $slug = trim((string) ($record['slug'] ?? $record['id'] ?? ''));
            if ($slug === '') {
                continue;
            }
            $metaTitle = trim((string) ($record['meta_title'] ?? ''));
            $metaDesc = trim((string) ($record['meta_description'] ?? ''));
            if ($metaTitle === '' && $metaDesc === '') {
                continue;
            }
            $key = birkly_seo_detail_key($template, $collection, $slug);
            $existing = is_array($config['url_map']['details'][$key] ?? null)
                ? $config['url_map']['details'][$key] : [];
            $overrides = is_array($existing['overrides'] ?? null) ? $existing['overrides'] : [];
            if ($metaTitle !== '' && empty($overrides['title'])) {
                $overrides['title'] = $metaTitle;
            }
            if ($metaDesc !== '' && empty($overrides['description'])) {
                $overrides['description'] = $metaDesc;
            }
            $config['url_map']['details'][$key] = array_merge($existing, [
                'type' => 'detail',
                'collection' => $collection,
                'slug' => $slug,
                'template_path' => $template,
                'overrides' => $overrides,
            ]);
        }
    }
    return $config;
}

/**
 * @return array{pages:array<string,array<string,mixed>>,details:array<string,array<string,mixed>>}
 */
function birkly_seo_propose_overrides(): array
{
    $config = birkly_seo_normalize_url_map_sections(birkly_site_seo_load());
    $siteTitle = trim((string) ($config['defaults']['site_title'] ?? ''));
    $suffix = trim((string) ($config['defaults']['title_suffix'] ?? ''));
    $proposed = ['pages' => [], 'details' => []];

    foreach ($config['url_map']['pages'] as $path => $page) {
        if (!is_array($page)) {
            continue;
        }
        $overrides = is_array($page['overrides'] ?? null) ? $page['overrides'] : [];
        if (!empty($overrides['title'])) {
            continue;
        }
        $label = trim((string) ($page['label'] ?? birkly_seo_label_from_path((string) $path)));
        $title = $label;
        if ($suffix !== '' && $title !== '' && !str_contains($title, $suffix)) {
            $title .= $suffix;
        } elseif ($siteTitle !== '' && $path === '/' && $title === 'Home') {
            $title = $siteTitle;
        }
        $proposed['pages'][$path] = [
            'overrides' => [
                'title' => $title,
                'description' => trim((string) ($config['defaults']['description'] ?? '')),
            ],
        ];
    }

    require_once __DIR__ . '/entry_storage.php';
    foreach ($config['detail_bindings'] as $binding) {
        if (!is_array($binding)) {
            continue;
        }
        $collection = (string) ($binding['collection'] ?? '');
        $template = (string) ($binding['template_path'] ?? '');
        $excerptField = (string) ($binding['excerpt_field'] ?? 'excerpt');
        if ($collection === '' || !birkly_entry_collection_exists($collection)) {
            continue;
        }
        foreach (birkly_entry_list_records($collection) as $record) {
            if (!is_array($record) || ($record['status'] ?? '') !== 'published') {
                continue;
            }
            $slug = trim((string) ($record['slug'] ?? $record['id'] ?? ''));
            if ($slug === '') {
                continue;
            }
            $key = birkly_seo_detail_key($template, $collection, $slug);
            $detail = is_array($config['url_map']['details'][$key] ?? null)
                ? $config['url_map']['details'][$key] : [];
            $overrides = is_array($detail['overrides'] ?? null) ? $detail['overrides'] : [];
            if (!empty($overrides['title'])) {
                continue;
            }
            $proposed['details'][$key] = [
                'overrides' => [
                    'title' => birkly_seo_entry_field($record, 'title'),
                    'description' => birkly_seo_entry_field($record, $excerptField),
                ],
            ];
        }
    }

    return $proposed;
}

/**
 * Public SEO head payload for CSR boot (P68-M7).
 *
 * @param array<string, mixed> $query
 * @return array<string, mixed>
 */
function birkly_seo_public_context(string $path, array $query = [], string $renderedHtml = ''): array
{
    $relative = ltrim($path, '/');
    if ($relative === '' || $relative === '/') {
        $relative = 'index.html';
    } elseif (!str_contains($relative, '.')) {
        $relative .= '.html';
    }
    $context = birkly_seo_build_context('/' . $relative, $query, [], $renderedHtml);
    $meta = birkly_site_seo_resolve_meta($context);
    return [
        'title' => $meta['title'],
        'description' => $meta['description'],
        'canonical' => $meta['canonical'],
        'og_image' => $meta['og_image'],
        'site_name' => $meta['site_name'],
        'twitter_card' => (string) (birkly_site_seo_load()['defaults']['twitter_card'] ?? 'summary_large_image'),
        'robots' => (string) (birkly_site_seo_load()['advanced']['robots'] ?? 'index,follow'),
        'published_at' => birkly_site_seo_load()['published_at'] ?? null,
    ];
}

/**
 * Admin deep-link for entry editor when collection has a detail binding.
 */
function birkly_seo_entry_discovery_link(string $collection, string $slug): ?string
{
    if ($collection === '' || $slug === '') {
        return null;
    }
    $config = birkly_seo_normalize_url_map_sections(birkly_site_seo_load());
    foreach ($config['detail_bindings'] as $binding) {
        if (!is_array($binding)) {
            continue;
        }
        if ((string) ($binding['collection'] ?? '') !== $collection) {
            continue;
        }
        require_once __DIR__ . '/hosting_urls.php';
        return birkly_hosting_admin_url('discovery', [
            'tab' => 'seo',
            'filter' => 'detail',
            'entry' => $collection . '/' . $slug,
        ]);
    }
    return null;
}

/**
 * @return array{ok:bool,config?:array<string,mixed>,error?:string,warnings?:list<string>}
 */
function birkly_seo_delete_detail_binding(string $bindingId): array
{
    $config = birkly_site_seo_normalize_url_map_sections(birkly_site_seo_load());
    $bindings = [];
    foreach ($config['detail_bindings'] as $binding) {
        if (!is_array($binding)) {
            continue;
        }
        if ((string) ($binding['id'] ?? '') === $bindingId) {
            continue;
        }
        $bindings[] = $binding;
    }
    $config['detail_bindings'] = $bindings;
    $config = birkly_seo_materialize_detail_urls($config);
    return birkly_site_seo_save($config);
}
