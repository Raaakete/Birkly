<?php
/**
 * Agent Guide — sources of truth registry (narrative, proof, reference).
 */

declare(strict_types=1);

require_once __DIR__ . '/ai_presence.php';
require_once __DIR__ . '/hosting_urls.php';

const BIRKLY_AGENT_GUIDE_SCHEMA_VERSION = 'agent_guide.v1';
const BIRKLY_AGENT_GUIDE_LEGACY_SCHEMA_VERSION = 'ai_presence.v1';
const BIRKLY_AGENT_GUIDE_DEFAULT_WELL_KNOWN = '/.well-known/agent-guide.json';
const BIRKLY_AGENT_GUIDE_LEGACY_WELL_KNOWN = '/.well-known/brand-context.json';

/** @return list<string> */
function birkly_agent_guide_source_truth_types(): array
{
    return [
        'cms_entry',
        'cms_collection',
        'website',
        'document',
        'media_asset',
        'marketplace',
        'review_platform',
        'other',
    ];
}

/** @return list<string> */
function birkly_agent_guide_source_truth_roles(): array
{
    return [
        'narrative',
        'proof',
        'reference',
        'archive',
    ];
}

function birkly_agent_guide_is_valid_schema_version(string $version): bool
{
    return in_array($version, [
        BIRKLY_AGENT_GUIDE_SCHEMA_VERSION,
        BIRKLY_AGENT_GUIDE_LEGACY_SCHEMA_VERSION,
    ], true);
}

/**
 * @param array<string, mixed> $source
 * @return array<string, mixed>
 */
function birkly_agent_guide_normalize_source_of_truth(array $source): array
{
    $type = (string) ($source['type'] ?? 'other');
    if (!in_array($type, birkly_agent_guide_source_truth_types(), true)) {
        $type = 'other';
    }
    $role = (string) ($source['role'] ?? 'reference');
    if (!in_array($role, birkly_agent_guide_source_truth_roles(), true)) {
        $role = 'reference';
    }

    $normalized = [
        'id' => (string) ($source['id'] ?? ('truth_' . bin2hex(random_bytes(4)))),
        'type' => $type,
        'role' => $role,
        'label' => (string) ($source['label'] ?? ''),
        'url' => trim((string) ($source['url'] ?? '')),
        'notes' => (string) ($source['notes'] ?? ''),
        'priority' => max(0, min(100, (int) ($source['priority'] ?? 50))),
        'last_verified_at' => (string) ($source['last_verified_at'] ?? ''),
        'link_status' => (string) ($source['link_status'] ?? 'unknown'),
    ];

    if ($type === 'cms_entry' || $type === 'cms_collection') {
        $ref = is_array($source['ref'] ?? null) ? $source['ref'] : [];
        $normalized['ref'] = [
            'collection' => (string) ($ref['collection'] ?? $source['collection'] ?? ''),
            'entry_id' => (string) ($ref['entry_id'] ?? $source['entry_id'] ?? ''),
            'slug' => (string) ($ref['slug'] ?? $source['slug'] ?? ''),
            'label' => (string) ($ref['label'] ?? $normalized['label']),
            'ref_type' => $type === 'cms_collection' ? 'collection' : 'entry',
        ];
        if ($normalized['label'] === '' && $normalized['ref']['label'] !== '') {
            $normalized['label'] = $normalized['ref']['label'];
        }
    }

    if ($type === 'media_asset' || $type === 'document') {
        $normalized['media_id'] = (string) ($source['media_id'] ?? '');
    }

    return $normalized;
}

/**
 * Migrate legacy connected_content[] into sources_of_truth[].
 *
 * @param list<array<string, mixed>> $connected
 * @return list<array<string, mixed>>
 */
function birkly_agent_guide_sources_from_connected_content(array $connected): array
{
    $out = [];
    foreach ($connected as $ref) {
        if (!is_array($ref)) {
            continue;
        }
        $refType = (string) ($ref['ref_type'] ?? 'entry');
        $type = $refType === 'collection' ? 'cms_collection' : 'cms_entry';
        $role = match ((string) ($ref['role'] ?? '')) {
            'about', 'brand_description', 'short_description' => 'narrative',
            'wizard_source' => 'reference',
            default => 'reference',
        };
        $out[] = birkly_agent_guide_normalize_source_of_truth([
            'id' => 'truth_cc_' . md5(json_encode($ref)),
            'type' => $type,
            'role' => $role,
            'label' => (string) ($ref['label'] ?? $ref['entry_id'] ?? $ref['collection'] ?? 'CMS'),
            'ref' => [
                'collection' => (string) ($ref['collection'] ?? ''),
                'entry_id' => (string) ($ref['entry_id'] ?? ''),
                'slug' => (string) ($ref['slug'] ?? ''),
                'label' => (string) ($ref['label'] ?? ''),
                'ref_type' => $refType,
            ],
        ]);
    }
    return $out;
}

/**
 * Keep connected_content in sync for legacy code paths.
 *
 * @return list<array<string, mixed>>
 */
function birkly_agent_guide_connected_content_from_sources(array $sources): array
{
    $out = [];
    foreach ($sources as $source) {
        if (!is_array($source)) {
            continue;
        }
        $type = (string) ($source['type'] ?? '');
        if (!in_array($type, ['cms_entry', 'cms_collection'], true)) {
            continue;
        }
        $ref = is_array($source['ref'] ?? null) ? $source['ref'] : [];
        $out[] = [
            'role' => (string) ($source['role'] ?? 'reference'),
            'ref_type' => $type === 'cms_collection' ? 'collection' : 'entry',
            'collection' => (string) ($ref['collection'] ?? ''),
            'entry_id' => (string) ($ref['entry_id'] ?? ''),
            'slug' => (string) ($ref['slug'] ?? ''),
            'label' => (string) ($source['label'] ?? $ref['label'] ?? ''),
        ];
    }
    return $out;
}

/**
 * @return array<string, mixed>
 */
function birkly_agent_guide_normalize_sources_in_draft(array $draft): array
{
    $sources = [];
    foreach ($draft['sources_of_truth'] ?? [] as $item) {
        if (is_array($item)) {
            $sources[] = birkly_agent_guide_normalize_source_of_truth($item);
        }
    }

    if ($sources === [] && is_array($draft['connected_content'] ?? null) && $draft['connected_content'] !== []) {
        $sources = birkly_agent_guide_sources_from_connected_content($draft['connected_content']);
    }

    $seen = [];
    $deduped = [];
    foreach ($sources as $s) {
        $key = ($s['type'] ?? '') . '|' . ($s['url'] ?? '') . '|' . json_encode($s['ref'] ?? []);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $deduped[] = $s;
    }
    usort($deduped, static fn ($a, $b) => ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0));

    $draft['sources_of_truth'] = $deduped;
    $draft['connected_content'] = birkly_agent_guide_connected_content_from_sources($deduped);

    $pub = $draft['publication_settings'] ?? [];
    if (empty($pub['public_path']) || ($pub['public_path'] ?? '') === BIRKLY_AGENT_GUIDE_LEGACY_WELL_KNOWN) {
        $draft['publication_settings']['public_path'] = BIRKLY_AGENT_GUIDE_DEFAULT_WELL_KNOWN;
    }

    if (birkly_agent_guide_is_valid_schema_version((string) ($draft['schema_version'] ?? ''))) {
        $draft['schema_version'] = BIRKLY_AGENT_GUIDE_SCHEMA_VERSION;
    }

    $draft['guide_meta'] = array_merge([
        'product' => 'Agent Guide',
        'summary_layer' => true,
    ], is_array($draft['guide_meta'] ?? null) ? $draft['guide_meta'] : []);

    return $draft;
}

/**
 * @param array<string, mixed> $source
 * @return array<string, mixed>
 */
function birkly_agent_guide_resolve_source_of_truth(array $source): array
{
    require_once __DIR__ . '/ai_presence_links.php';
    $resolved = birkly_agent_guide_normalize_source_of_truth($source);
    if ($resolved['url'] === '' && in_array($resolved['type'] ?? '', ['cms_entry', 'cms_collection'], true)) {
        $ref = is_array($resolved['ref'] ?? null) ? $resolved['ref'] : [];
        if (($resolved['type'] ?? '') === 'cms_entry' && ($ref['entry_id'] ?? '') !== '') {
            $url = birkly_ai_presence_resolve_cms_entry_url(
                (string) ($ref['collection'] ?? ''),
                (string) ($ref['entry_id'] ?? ''),
                (string) ($ref['slug'] ?? '')
            );
            if ($url !== '') {
                $resolved['url'] = $url;
            }
        }
    }
    if (($resolved['type'] ?? '') === 'media_asset' && ($resolved['url'] ?? '') === '' && ($resolved['media_id'] ?? '') !== '') {
        $resolved['url'] = '/media/' . rawurlencode((string) $resolved['media_id']);
    }
    return $resolved;
}

function birkly_agent_guide_has_narrative_source(array $draft): bool
{
    foreach ($draft['sources_of_truth'] ?? [] as $source) {
        if (!is_array($source)) {
            continue;
        }
        $role = (string) ($source['role'] ?? '');
        if (!in_array($role, ['narrative', 'reference'], true)) {
            continue;
        }
        if (in_array($source['type'] ?? '', ['cms_entry', 'cms_collection', 'website', 'document', 'media_asset'], true)) {
            if (($source['url'] ?? '') !== '' || !empty($source['ref']['entry_id'] ?? '') || !empty($source['ref']['collection'] ?? '')) {
                return true;
            }
        }
    }
    return false;
}

/**
 * @return list<array<string, mixed>>
 */
function birkly_agent_guide_gather_sources_context(array $draft, int $maxEntries = 16): array
{
    require_once __DIR__ . '/ai_presence_links.php';
    $draft = birkly_agent_guide_normalize_sources_in_draft($draft);
    $chunks = [];
    $contentRoot = birkly_project_root() . '/content/collections';

    foreach ($draft['sources_of_truth'] ?? [] as $source) {
        if (!is_array($source)) {
            continue;
        }
        $type = (string) ($source['type'] ?? '');
        $role = (string) ($source['role'] ?? 'reference');

        if ($type === 'cms_entry') {
            $ref = is_array($source['ref'] ?? null) ? $source['ref'] : [];
            $collection = (string) ($ref['collection'] ?? '');
            $entryId = (string) ($ref['entry_id'] ?? '');
            if ($collection === '' || $entryId === '') {
                continue;
            }
            $entry = birkly_ai_presence_read_json($contentRoot . '/' . $collection . '/entries/' . $entryId . '.json');
            if ($entry === null) {
                continue;
            }
            $chunks[] = [
                'source_id' => (string) ($source['id'] ?? ''),
                'source_type' => $type,
                'role' => $role,
                'label' => (string) ($source['label'] ?? $entryId),
                'url' => birkly_ai_presence_resolve_cms_entry_url($collection, $entryId, (string) ($ref['slug'] ?? '')),
                'text' => birkly_ai_presence_entry_text_excerpt($entry, 4000),
            ];
        } elseif ($type === 'cms_collection') {
            $ref = is_array($source['ref'] ?? null) ? $source['ref'] : [];
            $collection = (string) ($ref['collection'] ?? '');
            if ($collection === '') {
                continue;
            }
            $entriesDir = $contentRoot . '/' . $collection . '/entries';
            $count = 0;
            foreach (scandir($entriesDir) ?: [] as $file) {
                if (!str_ends_with($file, '.json')) {
                    continue;
                }
                $entry = birkly_ai_presence_read_json($entriesDir . '/' . $file);
                if ($entry === null) {
                    continue;
                }
                $entryId = (string) ($entry['id'] ?? basename($file, '.json'));
                $chunks[] = [
                    'source_id' => (string) ($source['id'] ?? ''),
                    'source_type' => $type,
                    'role' => $role,
                    'label' => (string) ($entry['title'] ?? $entry['name'] ?? $entryId),
                    'url' => birkly_ai_presence_resolve_cms_entry_url($collection, $entryId, (string) ($entry['slug'] ?? '')),
                    'text' => birkly_ai_presence_entry_text_excerpt($entry, 2000),
                ];
                $count++;
                if ($count >= 8) {
                    break;
                }
            }
        } elseif (in_array($type, ['website', 'document', 'marketplace', 'review_platform', 'other'], true) && ($source['url'] ?? '') !== '') {
            $chunks[] = [
                'source_id' => (string) ($source['id'] ?? ''),
                'source_type' => $type,
                'role' => $role,
                'label' => (string) ($source['label'] ?? $source['url']),
                'url' => (string) $source['url'],
                'text' => (string) ($source['notes'] ?? ''),
            ];
        } elseif ($type === 'media_asset' && ($source['media_id'] ?? '') !== '') {
            $mediaPath = birkly_project_root() . '/media/' . $source['media_id'] . '.json';
            $media = birkly_ai_presence_read_json($mediaPath);
            $chunks[] = [
                'source_id' => (string) ($source['id'] ?? ''),
                'source_type' => $type,
                'role' => $role,
                'label' => (string) ($source['label'] ?? $source['media_id']),
                'url' => (string) ($media['url'] ?? ''),
                'text' => (string) ($media['description'] ?? $source['notes'] ?? ''),
            ];
        }

        if (count($chunks) >= $maxEntries) {
            break;
        }
    }

    if ($chunks === []) {
        return birkly_ai_presence_gather_cms_context($draft);
    }

    return $chunks;
}

/**
 * @return array<string, mixed>
 */
function birkly_agent_guide_enrich_export(array $export): array
{
    require_once __DIR__ . '/ai_presence_links.php';
    $export = birkly_ai_presence_enrich_public_export($export);

    $resolved = [];
    foreach ($export['sources_of_truth'] ?? [] as $source) {
        if (is_array($source)) {
            $resolved[] = birkly_agent_guide_resolve_source_of_truth($source);
        }
    }
    $export['sources_of_truth'] = $resolved;
    $export['guide_meta'] = array_merge([
        'product' => 'Agent Guide',
        'summary_layer' => true,
    ], is_array($export['guide_meta'] ?? null) ? $export['guide_meta'] : []);

    if (isset($export['usage_hints']) && is_array($export['usage_hints'])) {
        $export['usage_hints']['follow_sources_of_truth'] = true;
        $export['usage_hints']['citation'] = 'Prefer citing sources_of_truth and linked detail URLs over paraphrasing summaries alone.';
    }

    return $export;
}

/**
 * @return list<array<string, mixed>>
 */
function birkly_agent_guide_rule_source_suggestions(array $draft): array
{
    $suggestions = [];
    $sources = $draft['sources_of_truth'] ?? [];
    if ($sources !== []) {
        return $suggestions;
    }

    $contentRoot = birkly_project_root() . '/content/collections';
    if (!is_dir($contentRoot)) {
        return $suggestions;
    }

    foreach (scandir($contentRoot) ?: [] as $dir) {
        if ($dir === '.' || $dir === '..') {
            continue;
        }
        $collectionPath = $contentRoot . '/' . $dir;
        if (!is_dir($collectionPath)) {
            continue;
        }
        $meta = birkly_ai_presence_read_json($collectionPath . '/collection.json');
        $label = (string) ($meta['name'] ?? $dir);
        $suggestions[] = [
            'id' => 'truth_suggest_col_' . md5($dir),
            'field' => 'sources_of_truth.new',
            'label' => 'Add CMS collection as source of truth: ' . $label,
            'value' => [
                'type' => 'cms_collection',
                'role' => 'narrative',
                'label' => $label,
                'ref' => ['collection' => $dir, 'entry_id' => '', 'slug' => $dir, 'ref_type' => 'collection'],
            ],
            'source' => 'rule',
            'confidence' => 'medium',
        ];
        break;
    }

    if (trim((string) ($draft['identity']['website'] ?? '')) !== '') {
        $suggestions[] = [
            'id' => 'truth_suggest_web',
            'field' => 'sources_of_truth.new',
            'label' => 'Add website as source of truth',
            'value' => [
                'type' => 'website',
                'role' => 'narrative',
                'label' => 'Primary website',
                'url' => (string) $draft['identity']['website'],
            ],
            'source' => 'rule',
            'confidence' => 'high',
        ];
    }

    return $suggestions;
}

/**
 * @param array<string, mixed> $draft
 * @return array<string, mixed>
 */
function birkly_agent_guide_apply_source_suggestion(array $draft, array $suggestion): array
{
    $field = (string) ($suggestion['field'] ?? '');
    $value = $suggestion['value'] ?? null;
    if ($field !== 'sources_of_truth.new' || !is_array($value)) {
        return $draft;
    }
    $draft['sources_of_truth'] = is_array($draft['sources_of_truth'] ?? null) ? $draft['sources_of_truth'] : [];
    $draft['sources_of_truth'][] = birkly_agent_guide_normalize_source_of_truth($value);
    return birkly_agent_guide_normalize_sources_in_draft($draft);
}
