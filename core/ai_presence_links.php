<?php
/**
 * AI Presence cross-links — source resolution, validation, export enrichment.
 */

declare(strict_types=1);

require_once __DIR__ . '/ai_presence.php';
require_once __DIR__ . '/hosting_urls.php';

/** @return list<string> */
function birkly_ai_presence_source_roles(): array
{
    return [
        'canonical_detail',
        'reference',
        'marketplace',
        'review',
        'docs',
        'proof',
        'purchase',
        'social',
        'other',
    ];
}

/** @return list<string> */
function birkly_ai_presence_source_types(): array
{
    return [
        'cms_entry',
        'public_page',
        'marketplace_listing',
        'review_platform',
        'docs',
        'certification',
        'other',
    ];
}

/** @return list<string> */
function birkly_ai_presence_evidence_types_extended(): array
{
    return [
        'cms_content',
        'external_url',
        'upload',
        'certification',
        'publication',
        'marketplace_listing',
        'review_platform',
        'other',
    ];
}

/**
 * @param array<string, mixed> $link
 * @return array<string, mixed>
 */
function birkly_ai_presence_normalize_source_link(array $link): array
{
    $role = (string) ($link['role'] ?? 'reference');
    if (!in_array($role, birkly_ai_presence_source_roles(), true)) {
        $role = 'reference';
    }
    $type = (string) ($link['type'] ?? 'other');
    if (!in_array($type, birkly_ai_presence_source_types(), true)) {
        $type = 'other';
    }
    $ref = is_array($link['ref'] ?? null) ? $link['ref'] : [];
    $normalized = [
        'id' => (string) ($link['id'] ?? ('src_' . bin2hex(random_bytes(4)))),
        'role' => $role,
        'type' => $type,
        'label' => (string) ($link['label'] ?? ''),
        'url' => trim((string) ($link['url'] ?? '')),
        'notes' => (string) ($link['notes'] ?? ''),
        'last_verified_at' => (string) ($link['last_verified_at'] ?? ''),
        'link_status' => (string) ($link['link_status'] ?? 'unknown'),
    ];
    if ($ref !== []) {
        $normalized['ref'] = [
            'collection' => (string) ($ref['collection'] ?? ''),
            'entry_id' => (string) ($ref['entry_id'] ?? ''),
            'slug' => (string) ($ref['slug'] ?? ''),
            'label' => (string) ($ref['label'] ?? ''),
        ];
    }
    if (isset($link['rating']) && $link['rating'] !== '' && $link['rating'] !== null) {
        $normalized['rating'] = is_numeric($link['rating']) ? (float) $link['rating'] : null;
    }
    if (isset($link['review_count']) && $link['review_count'] !== '' && $link['review_count'] !== null) {
        $normalized['review_count'] = is_numeric($link['review_count']) ? (int) $link['review_count'] : null;
    }
    return $normalized;
}

function birkly_ai_presence_resolve_cms_entry_url(string $collection, string $entryId, string $slug = ''): string
{
    $base = rtrim(birkly_hosting_public_base_url(), '/');
    if ($base === '') {
        return '';
    }
    if ($slug === '' && $collection !== '' && $entryId !== '') {
        $path = birkly_project_root() . '/content/collections/' . $collection . '/entries/' . $entryId . '.json';
        $entry = birkly_ai_presence_read_json($path);
        if (is_array($entry)) {
            $slug = (string) ($entry['slug'] ?? $entryId);
        }
    }
    if ($slug === '') {
        $slug = $entryId;
    }
    if ($slug === '') {
        return '';
    }
    return $base . '/' . rawurlencode($collection) . '/' . rawurlencode($slug);
}

/**
 * @param array<string, mixed> $link
 * @return array<string, mixed>
 */
function birkly_ai_presence_resolve_source_link(array $link): array
{
    $resolved = birkly_ai_presence_normalize_source_link($link);
    if ($resolved['url'] === '' && isset($resolved['ref']) && is_array($resolved['ref'])) {
        $ref = $resolved['ref'];
        $url = birkly_ai_presence_resolve_cms_entry_url(
            (string) ($ref['collection'] ?? ''),
            (string) ($ref['entry_id'] ?? ''),
            (string) ($ref['slug'] ?? '')
        );
        if ($url !== '') {
            $resolved['url'] = $url;
        }
    }
    return $resolved;
}

/**
 * Merge legacy connected_entry into sources[] on a product.
 *
 * @param array<string, mixed> $product
 * @return list<array<string, mixed>>
 */
function birkly_ai_presence_product_sources_raw(array $product): array
{
    $sources = [];
    foreach ($product['sources'] ?? [] as $link) {
        if (is_array($link)) {
            $sources[] = birkly_ai_presence_normalize_source_link($link);
        }
    }
    $connected = $product['connected_entry'] ?? null;
    if (is_array($connected) && ($connected['entry_id'] ?? '') !== '') {
        $exists = false;
        foreach ($sources as $s) {
            $ref = $s['ref'] ?? null;
            if (is_array($ref)
                && ($ref['entry_id'] ?? '') === ($connected['entry_id'] ?? '')
                && ($ref['collection'] ?? '') === ($connected['collection'] ?? '')) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $sources[] = birkly_ai_presence_normalize_source_link([
                'role' => 'canonical_detail',
                'type' => 'cms_entry',
                'label' => (string) ($connected['label'] ?? $connected['entry_id'] ?? 'CMS entry'),
                'ref' => $connected,
            ]);
        }
    }
    return $sources;
}

/**
 * @param array<string, mixed> $product
 * @return list<array<string, mixed>>
 */
function birkly_ai_presence_resolve_product_sources(array $product): array
{
    $out = [];
    foreach (birkly_ai_presence_product_sources_raw($product) as $link) {
        $out[] = birkly_ai_presence_resolve_source_link($link);
    }
    return $out;
}

function birkly_ai_presence_product_has_grounded_source(array $product): bool
{
    foreach (birkly_ai_presence_resolve_product_sources($product) as $link) {
        if (($link['url'] ?? '') !== '' || !empty($link['ref']['entry_id'] ?? '')) {
            return true;
        }
    }
    return false;
}

/**
 * @param array<string, mixed> $draft
 */
function birkly_ai_presence_normalize_sources_in_draft(array $draft): array
{
    foreach ($draft['products_services'] ?? [] as $i => $product) {
        if (!is_array($product)) {
            continue;
        }
        $sources = birkly_ai_presence_product_sources_raw($product);
        $product['sources'] = $sources;
        if ($sources !== [] && is_array($product['connected_entry'] ?? null)) {
            foreach ($sources as $s) {
                $ref = $s['ref'] ?? null;
                if (is_array($ref) && ($ref['entry_id'] ?? '') !== '') {
                    $product['connected_entry'] = $ref;
                    break;
                }
            }
        }
        $draft['products_services'][$i] = $product;
    }

    foreach ($draft['claims'] ?? [] as $i => $claim) {
        if (!is_array($claim)) {
            continue;
        }
        $sources = [];
        foreach ($claim['sources'] ?? [] as $link) {
            if (is_array($link)) {
                $sources[] = birkly_ai_presence_normalize_source_link($link);
            }
        }
        $claim['sources'] = $sources;
        $draft['claims'][$i] = $claim;
    }

    if (!isset($draft['identity']['derived_from']) || !is_array($draft['identity']['derived_from'])) {
        $draft['identity']['derived_from'] = [];
    }

    foreach ($draft['evidence'] ?? [] as $i => $ev) {
        if (!is_array($ev)) {
            continue;
        }
        $type = (string) ($ev['type'] ?? 'other');
        $allowed = birkly_ai_presence_evidence_types_extended();
        if ($type === 'document') {
            $type = 'upload';
        }
        if (!in_array($type, $allowed, true)) {
            $type = 'other';
        }
        $ev['type'] = $type;
        if (!isset($ev['last_verified_at'])) {
            $ev['last_verified_at'] = '';
        }
        if (!isset($ev['link_status'])) {
            $ev['link_status'] = 'unknown';
        }
        $draft['evidence'][$i] = $ev;
    }

    return $draft;
}

/**
 * @return array{ok: bool, http_code?: int, link_status: string, final_url?: string, error?: string}
 */
function birkly_ai_presence_check_url(string $url): array
{
    if ($url === '') {
        return ['ok' => false, 'link_status' => 'unknown', 'error' => 'Empty URL'];
    }
    if (!birkly_ai_presence_is_safe_external_url($url)) {
        return ['ok' => false, 'link_status' => 'blocked', 'error' => 'URL not allowed'];
    }
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'link_status' => 'unknown', 'error' => 'curl init failed'];
    }
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; Birkly/1.0; link-check)',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    ]);
    curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $err = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 400) {
        $status = ($finalUrl !== '' && $finalUrl !== $url) ? 'redirect' : 'ok';
        return ['ok' => true, 'http_code' => $httpCode, 'link_status' => $status, 'final_url' => $finalUrl ?: $url];
    }
    if ($httpCode === 0 && $err !== '') {
        return ['ok' => false, 'link_status' => 'broken', 'error' => $err];
    }
    return ['ok' => false, 'http_code' => $httpCode, 'link_status' => 'broken', 'error' => 'HTTP ' . $httpCode];
}

/**
 * @param array<string, mixed> $draft
 * @return list<array<string, mixed>>
 */
function birkly_ai_presence_validate_draft_links(array &$draft, bool $mutate = false): array
{
    $results = [];
    $now = date('c');
    $draft = birkly_ai_presence_normalize_sources_in_draft($draft);

    $check = static function (string $scope, string $id, string $url, ?array &$target) use (&$results, $now, $mutate): void {
        if ($url === '') {
            return;
        }
        $checkResult = birkly_ai_presence_check_url($url);
        $results[] = [
            'scope' => $scope,
            'id' => $id,
            'url' => $url,
            'link_status' => $checkResult['link_status'],
            'http_code' => $checkResult['http_code'] ?? null,
            'ok' => $checkResult['ok'] ?? false,
            'error' => $checkResult['error'] ?? null,
        ];
        if ($mutate && $target !== null) {
            $target['link_status'] = $checkResult['link_status'];
            $target['last_verified_at'] = $now;
            if (!empty($checkResult['final_url']) && $checkResult['final_url'] !== $url) {
                $target['url'] = (string) $checkResult['final_url'];
            }
        }
    };

    foreach ($draft['products_services'] ?? [] as $i => $product) {
        if (!is_array($product)) {
            continue;
        }
        $pid = (string) ($product['id'] ?? 'product_' . $i);
        $sources = $draft['products_services'][$i]['sources'] ?? [];
        foreach ($sources as $j => $link) {
            if (!is_array($link)) {
                continue;
            }
            $resolved = birkly_ai_presence_resolve_source_link($link);
            $url = (string) ($resolved['url'] ?? '');
            $target = $mutate ? $draft['products_services'][$i]['sources'][$j] : null;
            $check('product_source', $pid . ':' . ($link['id'] ?? $j), $url, $target);
        }
    }

    foreach ($draft['evidence'] ?? [] as $i => $ev) {
        if (!is_array($ev)) {
            continue;
        }
        $url = (string) ($ev['url'] ?? '');
        $ref = &$draft['evidence'][$i];
        $check('evidence', (string) ($ev['id'] ?? 'ev_' . $i), $url, $mutate ? $ref : null);
    }

    foreach ($draft['claims'] ?? [] as $i => $claim) {
        if (!is_array($claim)) {
            continue;
        }
        $cid = (string) ($claim['id'] ?? 'claim_' . $i);
        foreach ($claim['sources'] ?? [] as $j => $link) {
            if (!is_array($link)) {
                continue;
            }
            $resolved = birkly_ai_presence_resolve_source_link($link);
            $url = (string) ($resolved['url'] ?? '');
            $ref = &$draft['claims'][$i]['sources'][$j];
            $check('claim_source', $cid . ':' . ($link['id'] ?? $j), $url, $mutate ? $ref : null);
        }
    }

    $website = trim((string) ($draft['identity']['website'] ?? ''));
    if ($website !== '') {
        $dummy = null;
        $check('identity', 'website', $website, null);
    }

    return $results;
}

/**
 * @return list<array<string, mixed>>
 */
function birkly_ai_presence_rule_link_suggestions(array $draft): array
{
    $suggestions = [];
    $contentRoot = birkly_project_root() . '/content/collections';

    foreach ($draft['products_services'] ?? [] as $product) {
        if (!is_array($product) || trim((string) ($product['name'] ?? '')) === '') {
            continue;
        }
        if (birkly_ai_presence_product_has_grounded_source($product)) {
            continue;
        }
        $name = strtolower(trim((string) $product['name']));
        foreach ($draft['connected_content'] ?? [] as $ref) {
            if (!is_array($ref) || ($ref['ref_type'] ?? '') !== 'entry') {
                continue;
            }
            $label = strtolower((string) ($ref['label'] ?? ''));
            if ($label !== '' && (str_contains($label, $name) || str_contains($name, $label))) {
                $suggestions[] = [
                    'id' => 'link_prod_' . md5(($product['id'] ?? $name) . ($ref['entry_id'] ?? '')),
                    'field' => 'products_services.link',
                    'label' => 'Link product "' . ($product['name'] ?? '') . '" to CMS entry ' . ($ref['label'] ?? ''),
                    'value' => [
                        'product_id' => (string) ($product['id'] ?? ''),
                        'source' => birkly_ai_presence_normalize_source_link([
                            'role' => 'canonical_detail',
                            'type' => 'cms_entry',
                            'label' => (string) ($ref['label'] ?? $ref['entry_id'] ?? ''),
                            'ref' => [
                                'collection' => (string) ($ref['collection'] ?? ''),
                                'entry_id' => (string) ($ref['entry_id'] ?? ''),
                                'slug' => (string) ($ref['slug'] ?? ''),
                                'label' => (string) ($ref['label'] ?? ''),
                            ],
                        ]),
                    ],
                    'source' => 'rule',
                    'confidence' => 'high',
                ];
                break;
            }
        }
    }

    foreach ($draft['claims'] ?? [] as $claim) {
        if (!is_array($claim)) {
            continue;
        }
        $stmt = strtolower((string) ($claim['statement'] ?? ''));
        if ($stmt === '') {
            continue;
        }
        $needsReview = str_contains($stmt, 'amazon') || str_contains($stmt, 'review') || str_contains($stmt, 'rating');
        if (!$needsReview) {
            continue;
        }
        $hasReviewEvidence = false;
        foreach ($draft['evidence'] ?? [] as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            if (!in_array($ev['id'] ?? '', $claim['evidence_ids'] ?? [], true)) {
                continue;
            }
            $type = (string) ($ev['type'] ?? '');
            if (in_array($type, ['marketplace_listing', 'review_platform', 'external_url'], true) && ($ev['url'] ?? '') !== '') {
                $hasReviewEvidence = true;
                break;
            }
        }
        if ($hasReviewEvidence) {
            continue;
        }
        $suggestions[] = [
            'id' => 'link_claim_ev_' . md5((string) ($claim['id'] ?? $stmt)),
            'field' => 'evidence.new',
            'label' => 'Add review/marketplace URL for claim',
            'value' => [
                'title' => 'Review or marketplace listing',
                'url' => '',
                'type' => str_contains($stmt, 'amazon') ? 'marketplace_listing' : 'review_platform',
                'claim_statement' => (string) ($claim['statement'] ?? ''),
            ],
            'source' => 'rule',
            'confidence' => 'medium',
        ];
    }

    if (trim((string) ($draft['identity']['short_description'] ?? '')) !== '') {
        foreach ($draft['connected_content'] ?? [] as $ref) {
            if (!is_array($ref) || ($ref['ref_type'] ?? '') !== 'entry') {
                continue;
            }
            $role = (string) ($ref['role'] ?? '');
            if (!in_array($role, ['about', 'brand_description', 'reference'], true)) {
                continue;
            }
            $derived = $draft['identity']['derived_from'] ?? [];
            if (is_array($derived) && !empty($derived['short_description'])) {
                break;
            }
            $suggestions[] = [
                'id' => 'derived_short_' . md5((string) ($ref['entry_id'] ?? '')),
                'field' => 'identity.derived_from.short_description',
                'label' => 'Mark short description as derived from ' . ($ref['label'] ?? 'CMS'),
                'value' => [
                    'collection' => (string) ($ref['collection'] ?? ''),
                    'entry_id' => (string) ($ref['entry_id'] ?? ''),
                    'slug' => (string) ($ref['slug'] ?? ''),
                    'label' => (string) ($ref['label'] ?? ''),
                ],
                'source' => 'rule',
                'confidence' => 'high',
            ];
            break;
        }
    }

    return $suggestions;
}

/**
 * @return list<array{id: string, title: string, slug: string}>
 */
function birkly_ai_presence_list_collection_entries(string $collectionSlug, int $limit = 100): array
{
    $contentRoot = birkly_project_root() . '/content/collections';
    $dir = $contentRoot . '/' . $collectionSlug . '/entries';
    $out = [];
    if (!is_dir($dir)) {
        foreach (scandir($contentRoot) ?: [] as $folder) {
            if ($folder === '.' || $folder === '..') {
                continue;
            }
            $meta = birkly_ai_presence_read_json($contentRoot . '/' . $folder . '/collection.json');
            $slug = (string) ($meta['slug'] ?? $folder);
            if ($slug === $collectionSlug || $folder === $collectionSlug) {
                $dir = $contentRoot . '/' . $folder . '/entries';
                $collectionSlug = $folder;
                break;
            }
        }
    }
    if (!is_dir($dir)) {
        return $out;
    }
    foreach (scandir($dir) ?: [] as $file) {
        if (!str_ends_with($file, '.json')) {
            continue;
        }
        $entry = birkly_ai_presence_read_json($dir . '/' . $file);
        if ($entry === null) {
            continue;
        }
        $entryId = (string) ($entry['id'] ?? basename($file, '.json'));
        $out[] = [
            'id' => $entryId,
            'title' => (string) ($entry['title'] ?? $entry['name'] ?? $entryId),
            'slug' => (string) ($entry['slug'] ?? $entryId),
            'collection' => $collectionSlug,
        ];
        if (count($out) >= $limit) {
            break;
        }
    }
    usort($out, static fn ($a, $b) => strcasecmp($a['title'], $b['title']));
    return $out;
}

/**
 * @return array<string, mixed>
 */
function birkly_ai_presence_build_usage_hints(array $doc): array
{
    $supportedClaims = 0;
    foreach ($doc['claims'] ?? [] as $claim) {
        if (is_array($claim) && ($claim['status'] ?? '') === 'supported') {
            $supportedClaims++;
        }
    }
    return [
        'summary_layer' => true,
        'authoritative_fields' => ['identity.official_name', 'claims.supported', 'evidence.user_verified'],
        'marketing_fields' => ['positioning.best_known_for', 'positioning.differentiators', 'communication_guidance'],
        'citation' => 'Prefer citing linked sources (sources[], evidence.url) over paraphrasing summaries alone.',
        'claims_require_evidence' => $supportedClaims > 0,
        'follow_links_for' => ['product specs', 'pricing', 'reviews', 'certifications', 'full descriptions'],
    ];
}

/**
 * Enrich export with resolved sources, derived_from URLs, usage_hints.
 *
 * @return array<string, mixed>
 */
function birkly_ai_presence_enrich_public_export(array $export): array
{
    $products = [];
    foreach ($export['products_services'] ?? [] as $product) {
        if (!is_array($product)) {
            continue;
        }
        $resolved = birkly_ai_presence_resolve_product_sources($product);
        $product['sources'] = $resolved;
        unset($product['connected_entry']);
        $products[] = $product;
    }
    $export['products_services'] = $products;

    $evidence = [];
    foreach ($export['evidence'] ?? [] as $ev) {
        if (!is_array($ev)) {
            continue;
        }
        if (($ev['type'] ?? '') === 'cms_content' && ($ev['url'] ?? '') === '' && ($ev['notes'] ?? '') !== '') {
            if (preg_match('#^([^/]+)/([^/]+)$#', (string) $ev['notes'], $m)) {
                $ev['url'] = birkly_ai_presence_resolve_cms_entry_url($m[1], $m[2]);
            }
        }
        $evidence[] = $ev;
    }
    $export['evidence'] = $evidence;

    $claims = [];
    foreach ($export['claims'] ?? [] as $claim) {
        if (!is_array($claim)) {
            continue;
        }
        $resolvedSources = [];
        foreach ($claim['sources'] ?? [] as $link) {
            if (is_array($link)) {
                $resolvedSources[] = birkly_ai_presence_resolve_source_link($link);
            }
        }
        $claim['sources'] = $resolvedSources;

        $linkedEvidence = [];
        foreach ($export['evidence'] ?? [] as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            if (in_array($ev['id'] ?? '', $claim['evidence_ids'] ?? [], true)) {
                $linkedEvidence[] = $ev;
            }
        }
        $claim['linked_evidence'] = $linkedEvidence;
        $claims[] = $claim;
    }
    $export['claims'] = $claims;

    $derived = $export['identity']['derived_from'] ?? [];
    if (is_array($derived)) {
        $resolvedDerived = [];
        foreach ($derived as $field => $ref) {
            if (!is_array($ref)) {
                continue;
            }
            $url = birkly_ai_presence_resolve_cms_entry_url(
                (string) ($ref['collection'] ?? ''),
                (string) ($ref['entry_id'] ?? ''),
                (string) ($ref['slug'] ?? '')
            );
            $resolvedDerived[$field] = array_merge($ref, ['url' => $url]);
        }
        $export['identity']['derived_from'] = $resolvedDerived;
    }

    $export['usage_hints'] = birkly_ai_presence_build_usage_hints($export);
    return $export;
}

/**
 * @return array<string, mixed>|null
 */
function birkly_ai_presence_public_product_graph(array $export, string $id): ?array
{
    $product = null;
    foreach ($export['products_services'] ?? [] as $p) {
        if (!is_array($p)) {
            continue;
        }
        if (($p['id'] ?? '') === $id || ($p['name'] ?? '') === $id) {
            if (!isset($p['public']) || $p['public']) {
                $product = $p;
            }
            break;
        }
    }
    if ($product === null) {
        return null;
    }

    $claims = [];
    foreach ($export['claims'] ?? [] as $claim) {
        if (!is_array($claim)) {
            continue;
        }
        if (in_array($claim['id'] ?? '', $product['claim_ids'] ?? [], true)) {
            $claims[] = $claim;
        }
    }

    return [
        'product' => $product,
        'sources' => $product['sources'] ?? [],
        'linked_claims' => $claims,
        'usage_hints' => [
            'follow_sources_for' => 'full specs, pricing, purchase links, and proof',
        ],
    ];
}

/**
 * @return array<string, mixed>|null
 */
function birkly_ai_presence_public_claim_graph(array $export, string $id): ?array
{
    $claim = null;
    foreach ($export['claims'] ?? [] as $c) {
        if (!is_array($c) || ($c['id'] ?? '') !== $id) {
            continue;
        }
        if (($c['status'] ?? '') === 'supported') {
            $claim = $c;
        }
        break;
    }
    if ($claim === null) {
        return null;
    }

    $evidence = [];
    foreach ($export['evidence'] ?? [] as $ev) {
        if (!is_array($ev)) {
            continue;
        }
        if (in_array($ev['id'] ?? '', $claim['evidence_ids'] ?? [], true)) {
            $evidence[] = $ev;
        }
    }

    return [
        'claim' => $claim,
        'evidence' => $evidence,
        'sources' => $claim['sources'] ?? [],
        'usage_hints' => [
            'repeat_only_with_evidence' => true,
        ],
    ];
}

/**
 * @param array<string, mixed> $draft
 * @return array<string, mixed>
 */
function birkly_ai_presence_apply_link_suggestion(array $draft, array $suggestion): array
{
    $field = (string) ($suggestion['field'] ?? '');
    $value = $suggestion['value'] ?? null;

    if ($field === 'products_services.link' && is_array($value)) {
        $productId = (string) ($value['product_id'] ?? '');
        $source = is_array($value['source'] ?? null) ? birkly_ai_presence_normalize_source_link($value['source']) : null;
        if ($productId === '' || $source === null) {
            return $draft;
        }
        foreach ($draft['products_services'] ?? [] as $i => $product) {
            if (!is_array($product) || ($product['id'] ?? '') !== $productId) {
                continue;
            }
            $sources = birkly_ai_presence_product_sources_raw($product);
            $sources[] = $source;
            $product['sources'] = $sources;
            if (($source['type'] ?? '') === 'cms_entry' && isset($source['ref'])) {
                $product['connected_entry'] = $source['ref'];
            }
            $draft['products_services'][$i] = $product;
            break;
        }
        return $draft;
    }

    if ($field === 'identity.derived_from.short_description' && is_array($value)) {
        $draft['identity']['derived_from'] = is_array($draft['identity']['derived_from'] ?? null)
            ? $draft['identity']['derived_from'] : [];
        $draft['identity']['derived_from']['short_description'] = $value;
        return $draft;
    }

    return $draft;
}
