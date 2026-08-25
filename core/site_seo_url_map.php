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
    $rel = str_replace('\\', '/', $relativePath);
    $lower = strtolower($rel);
    foreach (['/vendor/', '/node_modules/', '/partials/'] as $segment) {
        if (str_contains($lower, $segment)) {
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
        $rel = str_replace('\\', '/', substr($file->getPathname(), strlen(realpath($projectDir) ?: $projectDir)));
        $rel = ltrim($rel, '/');
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
    $config = $config ?? birkly_site_seo_load();
    $config = birkly_seo_normalize_url_map_sections($config);

    $projectDir = birkly_project_root() . '/project';
    $pages = $config['url_map']['pages'];
    $excludes = $config['url_map']['excludes'];
    $detectedTemplates = [];

    if (is_dir($projectDir)) {
        $rootReal = realpath($projectDir) ?: $projectDir;
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
            $full = $file->getPathname();
            $rel = str_replace('\\', '/', substr($full, strlen($rootReal)));
            $rel = ltrim($rel, '/');
            if (birkly_seo_scan_should_exclude($rel, $excludes)) {
                continue;
            }

            $content = @file_get_contents($full);
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
                continue;
            }

            $pathKey = birkly_seo_public_path_from_file($rel);
            $existing = is_array($pages[$pathKey] ?? null) ? $pages[$pathKey] : [];
            $overrides = is_array($existing['overrides'] ?? null) ? $existing['overrides'] : [];
            $pages[$pathKey] = array_merge([
                'label' => birkly_seo_label_from_path($pathKey),
                'source' => 'scan',
                'file' => $rel,
                'overrides' => [],
            ], $existing, [
                'source' => 'scan',
                'file' => $rel,
                'overrides' => $overrides,
            ]);
        }
    }

    $config['url_map']['pages'] = $pages;
    $config['scan']['last_at'] = date('c');
    $config['scan']['project_revision'] = birkly_seo_project_revision_hash($projectDir);
    $config['scan']['detected_templates'] = $detectedTemplates;

    $bindings = $config['detail_bindings'];
    $knownTemplates = array_map(
        static fn (array $b): string => (string) ($b['template_path'] ?? ''),
        $bindings
    );
    foreach ($detectedTemplates as $dt) {
        $templatePath = (string) ($dt['template_path'] ?? '');
        if ($templatePath === '' || in_array($templatePath, $knownTemplates, true)) {
            continue;
        }
        $collection = (string) ($dt['collection'] ?? '');
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
    $config['url_map'] = $current;
    return birkly_site_seo_save($config);
}

/**
 * @return array{ok:bool,config?:array<string,mixed>,rows?:list<array<string,mixed>>,error?:string}
 */
function birkly_seo_scan_and_save(): array
{
    $config = birkly_seo_url_map_scan_project();
    $save = birkly_site_seo_save($config);
    if (!$save['ok']) {
        return $save;
    }
    return [
        'ok' => true,
        'config' => $save['config'],
        'rows' => birkly_seo_url_map_admin_rows($save['config'] ?? $config),
    ];
}

/**
 * Flatten url_map for admin table display.
 *
 * @param array<string, mixed> $config
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
        $rows[] = [
            'key' => (string) $path,
            'type' => 'page',
            'url' => (string) $path,
            'label' => (string) ($page['label'] ?? birkly_seo_label_from_path((string) $path)),
            'title' => (string) ($overrides['title'] ?? ''),
            'description' => (string) ($overrides['description'] ?? ''),
            'source' => (string) ($page['source'] ?? 'scan'),
            'file' => (string) ($page['file'] ?? ''),
        ];
    }
    foreach ($config['url_map']['details'] as $key => $detail) {
        if (!is_array($detail)) {
            continue;
        }
        $overrides = is_array($detail['overrides'] ?? null) ? $detail['overrides'] : [];
        $rows[] = [
            'key' => (string) $key,
            'type' => 'detail',
            'url' => (string) $key,
            'label' => (string) ($detail['label'] ?? ''),
            'title' => (string) ($overrides['title'] ?? ''),
            'description' => (string) ($overrides['description'] ?? ''),
            'collection' => (string) ($detail['collection'] ?? ''),
            'slug' => (string) ($detail['slug'] ?? ''),
        ];
    }
    foreach ($config['url_map']['redirects'] as $i => $redirect) {
        if (!is_array($redirect)) {
            continue;
        }
        $rows[] = [
            'key' => 'redirect:' . $i,
            'type' => 'redirect',
            'url' => (string) ($redirect['from'] ?? ''),
            'label' => 'Redirect → ' . (string) ($redirect['to'] ?? ''),
            'status' => (int) ($redirect['status'] ?? 301),
        ];
    }
    return $rows;
}
