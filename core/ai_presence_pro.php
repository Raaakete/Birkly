<?php
/**
 * AI Presence Pro (P47) — advanced authoring, products, sync, AI assist, public views.
 */

declare(strict_types=1);

require_once __DIR__ . '/ai_presence.php';

/**
 * @return list<array{id: string, name: string, provider: string, model: string}>
 */
function birkly_ai_presence_list_ai_connections_for_user(string $userId): array
{
    require_once __DIR__ . '/../api/ai_helpers.php';

    $sessionUser = $_SESSION['user'] ?? [];
    if (!is_array($sessionUser)) {
        $sessionUser = [];
    }
    if ($userId !== '' && ($sessionUser['id'] ?? '') === '') {
        $sessionUser['id'] = $userId;
    }

    $out = [];
    foreach (birkly_list_accessible_ai_connections_for_session_user($sessionUser) as $conn) {
        $out[] = [
            'id' => (string) ($conn['id'] ?? ''),
            'name' => (string) ($conn['name'] ?? $conn['id'] ?? 'AI'),
            'provider' => (string) ($conn['provider'] ?? ''),
            'model' => (string) ($conn['model'] ?? ''),
        ];
    }
    return $out;
}

/**
 * @return array{ok: bool, text?: string, error?: string}
 */
function birkly_ai_presence_call_ai(string $connectionId, string $systemPrompt, string $userPrompt): array
{
    if ($connectionId === '') {
        return ['ok' => false, 'error' => 'AI connection not selected'];
    }

    require_once __DIR__ . '/../api/ai_helpers.php';
    birkly_ai_bootstrap_call_provider();

    if (!function_exists('callAiProvider')) {
        return ['ok' => false, 'error' => 'AI provider unavailable'];
    }

    ensureEncryptionKeyBootstrapped();

    $conn = birkly_ai_load_connection_record($connectionId);
    if (!is_array($conn) || ($conn['type'] ?? '') !== 'ai') {
        return ['ok' => false, 'error' => 'Invalid AI connection'];
    }

    try {
        $apiKey = decryptApiKey($conn['api_key_encrypted'] ?? '');
        $response = callAiProvider(
            (string) ($conn['provider'] ?? 'openai'),
            $apiKey,
            birkly_ai_resolve_model((string) ($conn['provider'] ?? 'openai'), $conn['model'] ?? null),
            $systemPrompt,
            $userPrompt,
            '',
            (string) ($conn['api_url'] ?? '')
        );
        if (is_array($response)) {
            if (!($response['success'] ?? false)) {
                return ['ok' => false, 'error' => (string) ($response['message'] ?? 'AI request failed')];
            }
            $text = trim((string) ($response['message'] ?? ''));
            if ($text === '') {
                return ['ok' => false, 'error' => 'Empty AI response'];
            }
            return ['ok' => true, 'text' => $text];
        }
        if (!is_string($response) || trim($response) === '') {
            return ['ok' => false, 'error' => 'Empty AI response'];
        }
        return ['ok' => true, 'text' => $response];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * @return list<array<string, mixed>>
 */
function birkly_ai_presence_parse_ai_suggestions(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }
    if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $raw, $m)) {
        $raw = trim($m[1]);
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    if (isset($decoded['suggestions']) && is_array($decoded['suggestions'])) {
        $decoded = $decoded['suggestions'];
    }
    $out = [];
    foreach ($decoded as $i => $row) {
        if (!is_array($row) || empty($row['field'])) {
            continue;
        }
        $out[] = [
            'id' => (string) ($row['id'] ?? ('ai_' . md5(json_encode($row) . $i))),
            'field' => (string) $row['field'],
            'label' => (string) ($row['label'] ?? 'AI suggestion'),
            'value' => $row['value'] ?? '',
            'source' => (string) ($row['source'] ?? 'ai'),
            'confidence' => (string) ($row['confidence'] ?? 'medium'),
        ];
    }
    return $out;
}

/**
 * @return array{suggestions: list<array<string, mixed>>, ai_error: string|null}
 */
function birkly_ai_presence_copilot_suggestions(array $draft, string $connectionId = '', string $mode = 'all', bool $creative = false): array
{
    $suggestions = birkly_ai_presence_rule_suggestions($draft);
    require_once __DIR__ . '/agent_guide_sources.php';
    foreach (birkly_agent_guide_rule_source_suggestions($draft) as $sourceSuggestion) {
        $suggestions[] = $sourceSuggestion;
    }
    require_once __DIR__ . '/ai_presence_links.php';
    foreach (birkly_ai_presence_rule_link_suggestions($draft) as $linkSuggestion) {
        $suggestions[] = $linkSuggestion;
    }
    $aiError = null;

    if ($connectionId === '') {
        return ['suggestions' => $suggestions, 'ai_error' => null];
    }

    $chunks = birkly_agent_guide_gather_sources_context($draft);
    $context = json_encode($chunks, JSON_UNESCAPED_UNICODE);
    $draftSummary = json_encode($draft, JSON_UNESCAPED_UNICODE);
    $completeness = birkly_ai_presence_completeness($draft);
    $gaps = [];
    foreach ($completeness['items'] as $item) {
        if (empty($item['complete'])) {
            $gaps[] = ($item['label'] ?? $item['key'] ?? '') . ' (' . ($item['score'] ?? 0) . '/' . ($item['max'] ?? 0) . ')';
        }
    }
    $gapsText = $gaps !== [] ? implode('; ', $gaps) : 'All sections complete';

    $creative = $creative || $mode === 'fill_complete';
    $modeHint = match ($mode) {
        'fill_gaps' => 'Focus on empty or weak completeness fields only.',
        'fill_complete' => 'Generate a COMPLETE Agent Guide profile targeting 100% completeness. Fill every missing section: sources of truth, identity, descriptions, positioning, products, claims with evidence, terminology, relationships, and media context. Infer plausible values from existing draft when declared sources are insufficient.',
        'improve' => 'Suggest improvements to existing copy — clearer, more precise, and faithful to sources.',
        'claims' => 'Suggest factual claims that can be supported by declared sources. Use field claims.new.',
        'links' => 'Suggest cross-links only: products_services.link, evidence.new with URLs, identity.derived_from.short_description. Prefer CMS entries and verifiable external URLs (marketplace, reviews).',
        'sources' => 'Suggest sources_of_truth.new entries only — CMS collections, entries, websites, documents. Do not rewrite identity fields.',
        default => 'Suggest sources_of_truth.new, identity, positioning, communication, products_services.new, relationships.new, and claims.new fields as appropriate. Prefer declared sources_of_truth before inferring.',
    };

    $grounding = $creative
        ? 'Prefer declared sources_of_truth when available. When sources are missing or insufficient, infer plausible values consistent with the existing draft. Mark source as "cms", "source", or "inferred" and confidence accordingly.'
        : 'Do not invent facts not supported by declared sources_of_truth.';
    $system = 'You are an Agent Guide strategist helping publish structured context for AI systems. Return JSON only: {"suggestions":[{"id":"...","field":"sources_of_truth.new|identity.short_description|positioning.best_known_for|products_services.new|products_services.link|relationships.new|claims.new|evidence.new|media_context.new|communication_guidance.preferred_terms|identity.derived_from.short_description|publication_settings.enabled","label":"...","value":...,"source":"...","confidence":"high|medium|low"}]}. ' . $grounding . ' For array fields use JSON arrays. For sources_of_truth.new use {type,role,label,url?,ref?,notes?}. For products use products_services.new with object value {name,type,short_description,full_description,category,best_for,not_for,sources:[{role,type,label,url}]}. For product links use products_services.link with {product_id,source:{role,type,label,url,ref?}}. For claims use claims.new with {statement,status,notes}. For evidence use evidence.new with {title,url,type,claim_statement,rating?,review_count?}. Evidence types include marketplace_listing and review_platform. Never auto-publish.';
    $user = "$modeHint\n\nCompleteness gaps: $gapsText\n\nCurrent draft:\n$draftSummary\n\nDeclared sources (read these first):\n$context";

    $ai = birkly_ai_presence_call_ai($connectionId, $system, $user);
    if ($ai['ok'] ?? false) {
        foreach (birkly_ai_presence_parse_ai_suggestions((string) ($ai['text'] ?? '')) as $row) {
            $suggestions[] = $row;
        }
    } else {
        $aiError = (string) ($ai['error'] ?? 'AI request failed');
    }

    $seen = [];
    $deduped = [];
    foreach ($suggestions as $s) {
        $key = ($s['field'] ?? '') . '|' . md5(json_encode($s['value'] ?? ''));
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $deduped[] = $s;
        if (count($deduped) >= 24) {
            break;
        }
    }
    return ['suggestions' => $deduped, 'ai_error' => $aiError];
}

/**
 * @return array{suggestions: list<array<string, mixed>>, error: string|null}
 */
function birkly_ai_presence_field_ai(string $field, array $draft, string $connectionId, bool $creative = true): array
{
    $chunks = birkly_agent_guide_gather_sources_context($draft);
    $context = json_encode($chunks, JSON_UNESCAPED_UNICODE);
    $current = birkly_ai_presence_get_field_value($draft, $field);
    $draftSummary = json_encode($draft, JSON_UNESCAPED_UNICODE);

    $grounding = $creative
        ? 'Prefer declared sources_of_truth when available. If insufficient, infer a plausible value consistent with the full draft context. Mark source as "source" or "inferred".'
        : 'Ground in declared sources_of_truth; do not invent facts.';
    $system = 'Return JSON only: {"suggestions":[{"id":"field_ai","field":"' . addslashes($field) . '","label":"Suggested value","value":...,"source":"source|inferred|ai","confidence":"high|medium|low"}]}. ' . $grounding;
    $user = "Generate value for field: $field\nCurrent value: " . json_encode($current) . "\n\nFull draft context:\n$draftSummary\n\nDeclared sources:\n$context";

    $ai = birkly_ai_presence_call_ai($connectionId, $system, $user);
    if (!($ai['ok'] ?? false)) {
        return ['suggestions' => [], 'error' => (string) ($ai['error'] ?? 'AI request failed')];
    }
    return [
        'suggestions' => birkly_ai_presence_parse_ai_suggestions((string) ($ai['text'] ?? '')),
        'error' => null,
    ];
}

function birkly_ai_presence_get_field_value(array $draft, string $field): mixed
{
    if ($field === '' || !str_contains($field, '.')) {
        return null;
    }
    $parts = explode('.', $field);
    $cur = $draft;
    foreach ($parts as $part) {
        if (!is_array($cur) || !array_key_exists($part, $cur)) {
            return null;
        }
        $cur = $cur[$part];
    }
    return $cur;
}

/**
 * @param list<string> $collectionSlugs
 * @return array{suggestions: list<array<string, mixed>>, draft_patch: array<string, mixed>}
 */
function birkly_ai_presence_wizard_generate(array $collectionSlugs, string $connectionId = '', bool $creative = false): array
{
    $contentRoot = birkly_project_root() . '/content/collections';
    $chunks = [];
    foreach ($collectionSlugs as $slug) {
        $slug = trim($slug);
        if ($slug === '' || !is_dir($contentRoot . '/' . $slug)) {
            continue;
        }
        $entriesDir = $contentRoot . '/' . $slug . '/entries';
        if (!is_dir($entriesDir)) {
            continue;
        }
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
                'source' => 'entry:' . $slug . '/' . $entryId,
                'collection' => $slug,
                'entry_id' => $entryId,
                'label' => (string) ($entry['title'] ?? $entry['name'] ?? $entryId),
                'text' => birkly_ai_presence_entry_text_excerpt($entry, 4000),
            ];
            $count++;
            if ($count >= 12) {
                break;
            }
        }
    }

    $suggestions = [];
    $draftPatch = ['connected_content' => []];
    $aiError = null;

    foreach ($collectionSlugs as $slug) {
        $slug = trim($slug);
        if ($slug === '') {
            continue;
        }
        $meta = birkly_ai_presence_read_json($contentRoot . '/' . $slug . '/collection.json');
        $draftPatch['connected_content'][] = [
            'role' => 'wizard_source',
            'ref_type' => 'collection',
            'collection' => $slug,
            'entry_id' => '',
            'slug' => $slug,
            'label' => (string) ($meta['name'] ?? $slug),
        ];
    }

    if ($connectionId !== '') {
        $draft = birkly_ai_presence_load_draft();
        $completeness = birkly_ai_presence_completeness($draft);
        $gaps = [];
        foreach ($completeness['items'] as $item) {
            if (empty($item['complete'])) {
                $gaps[] = ($item['label'] ?? $item['key'] ?? '');
            }
        }
        $grounding = $creative
            ? 'Prefer CMS sources when available. When sources are missing or insufficient, infer plausible brand-consistent values from the existing draft. Mark source as "cms" or "inferred". Target 100% profile completeness.'
            : 'Do not invent facts.';
        $system = 'You are building authoritative brand context for AI systems. Return JSON only with keys: suggestions (array of {id,field,label,value,source,confidence}) and optional products (array of {name,type,short_description,full_description,category,best_for,connected_entry:{collection,entry_id,slug,label}}). Fields: identity.*, positioning.*, communication_guidance.*, claims.new, evidence.new, products_services.new, relationships.new, media_context.new, publication_settings.enabled. ' . $grounding;
        $userPayload = [
            'mode' => $creative ? 'fill_complete' : 'cms_grounded',
            'completeness_gaps' => $gaps,
            'current_draft' => $draft,
            'cms_sources' => $chunks,
        ];
        $user = ($creative
            ? 'Complete this brand profile to 100% — fill ALL missing sections using CMS sources first, then infer from existing context.'
            : 'Analyze these CMS sources and propose a complete brand context draft.')
            . "\n\n" . json_encode($userPayload, JSON_UNESCAPED_UNICODE);
        $ai = birkly_ai_presence_call_ai($connectionId, $system, $user);
        if ($ai['ok'] ?? false) {
            $raw = (string) ($ai['text'] ?? '');
            if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $raw, $m)) {
                $raw = trim($m[1]);
            }
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                if (isset($decoded['suggestions']) && is_array($decoded['suggestions'])) {
                    $suggestions = birkly_ai_presence_parse_ai_suggestions(json_encode($decoded['suggestions']));
                } else {
                    $suggestions = birkly_ai_presence_parse_ai_suggestions($raw);
                }
                if (isset($decoded['products']) && is_array($decoded['products'])) {
                    foreach ($decoded['products'] as $p) {
                        if (!is_array($p) || empty($p['name'])) {
                            continue;
                        }
                        $suggestions[] = [
                            'id' => 'product_' . md5((string) $p['name']),
                            'field' => 'products_services.new',
                            'label' => 'Product/service: ' . $p['name'],
                            'value' => $p,
                            'source' => 'wizard',
                            'confidence' => 'medium',
                        ];
                    }
                }
            }
        } else {
            $aiError = (string) ($ai['error'] ?? 'AI request failed');
        }
    }

    if ($suggestions === []) {
        $draft = birkly_ai_presence_merge_draft($draftPatch);
        $suggestions = birkly_ai_presence_rule_suggestions($draft);
    }

    return ['suggestions' => $suggestions, 'draft_patch' => $draftPatch, 'ai_error' => $aiError ?? null];
}

/**
 * @return list<array<string, mixed>>
 */
function birkly_ai_presence_cms_sync_diff(?array $draft = null): array
{
    $draft = $draft ?? birkly_ai_presence_load_draft();
    $contentRoot = birkly_project_root() . '/content/collections';
    $diffs = [];

    foreach ($draft['connected_content'] ?? [] as $idx => $ref) {
        if (!is_array($ref) || ($ref['ref_type'] ?? '') !== 'entry') {
            continue;
        }
        $collection = (string) ($ref['collection'] ?? '');
        $entryId = (string) ($ref['entry_id'] ?? '');
        if ($collection === '' || $entryId === '') {
            continue;
        }
        $path = $contentRoot . '/' . $collection . '/entries/' . $entryId . '.json';
        $entry = birkly_ai_presence_read_json($path);
        if ($entry === null) {
            $diffs[] = [
                'ref_index' => $idx,
                'type' => 'missing',
                'label' => (string) ($ref['label'] ?? $entryId),
                'message' => 'Linked entry no longer exists.',
            ];
            continue;
        }
        $excerpt = birkly_ai_presence_entry_text_excerpt($entry, 500);
        $role = (string) ($ref['role'] ?? '');
        $targetField = match ($role) {
            'brand_description', 'about' => 'identity.full_description',
            'short_description' => 'identity.short_description',
            default => '',
        };
        if ($targetField === '') {
            continue;
        }
        $current = (string) (birkly_ai_presence_get_field_value($draft, $targetField) ?? '');
        if ($current === '' && $excerpt !== '') {
            $diffs[] = [
                'ref_index' => $idx,
                'type' => 'empty_field',
                'field' => $targetField,
                'label' => (string) ($ref['label'] ?? $entryId),
                'proposed' => substr($excerpt, 0, 2000),
                'message' => 'CMS entry has content but target field is empty.',
            ];
            continue;
        }
        similar_text(strtolower(substr($current, 0, 500)), strtolower(substr($excerpt, 0, 500)), $pct);
        if ($pct < 55 && $excerpt !== '') {
            $diffs[] = [
                'ref_index' => $idx,
                'type' => 'drift',
                'field' => $targetField,
                'label' => (string) ($ref['label'] ?? $entryId),
                'current' => substr($current, 0, 400),
                'proposed' => substr($excerpt, 0, 400),
                'similarity' => round($pct, 1),
                'message' => 'CMS entry content may have drifted from draft field.',
            ];
        }
    }

    return $diffs;
}

/**
 * @return array<string, mixed>
 */
function birkly_ai_presence_cms_sync_apply(array $draft, int $refIndex, ?string $field = null): array
{
    $refs = $draft['connected_content'] ?? [];
    if (!isset($refs[$refIndex]) || !is_array($refs[$refIndex])) {
        return $draft;
    }
    $ref = $refs[$refIndex];
    if (($ref['ref_type'] ?? '') !== 'entry') {
        return $draft;
    }
    $collection = (string) ($ref['collection'] ?? '');
    $entryId = (string) ($ref['entry_id'] ?? '');
    $path = birkly_project_root() . '/content/collections/' . $collection . '/entries/' . $entryId . '.json';
    $entry = birkly_ai_presence_read_json($path);
    if ($entry === null) {
        return $draft;
    }
    $excerpt = birkly_ai_presence_entry_text_excerpt($entry, 2000);
    $targetField = $field ?: match ((string) ($ref['role'] ?? '')) {
        'short_description' => 'identity.short_description',
        default => 'identity.full_description',
    };
    $draft = birkly_ai_presence_apply_suggestion($draft, [
        'field' => $targetField,
        'value' => $excerpt,
    ]);
    return $draft;
}

/**
 * @return array<string, mixed>
 */
function birkly_ai_presence_apply_suggestion_pro(array $draft, array $suggestion): array
{
    $field = (string) ($suggestion['field'] ?? '');
    $value = $suggestion['value'] ?? '';

    if ($field === 'products_services.new' && is_array($value)) {
        require_once __DIR__ . '/ai_presence_links.php';
        $id = (string) ($value['id'] ?? ('prod_' . bin2hex(random_bytes(4))));
        $draft['products_services'][] = [
            'id' => $id,
            'name' => (string) ($value['name'] ?? ''),
            'type' => in_array(($value['type'] ?? 'product'), ['product', 'service', 'bundle'], true) ? $value['type'] : 'product',
            'short_description' => (string) ($value['short_description'] ?? ''),
            'full_description' => (string) ($value['full_description'] ?? ''),
            'category' => (string) ($value['category'] ?? ''),
            'pricing_model' => (string) ($value['pricing_model'] ?? ''),
            'price_display' => (string) ($value['price_display'] ?? ''),
            'price_source' => is_array($value['price_source'] ?? null) ? [
                'type' => in_array(($value['price_source']['type'] ?? 'manual'), ['manual', 'cms_field', 'external'], true)
                    ? $value['price_source']['type'] : 'manual',
                'collection' => (string) ($value['price_source']['collection'] ?? ''),
                'entry_id' => (string) ($value['price_source']['entry_id'] ?? ''),
                'field' => (string) ($value['price_source']['field'] ?? ''),
                'url' => (string) ($value['price_source']['url'] ?? ''),
            ] : null,
            'best_for' => is_array($value['best_for'] ?? null) ? array_values(array_map('strval', $value['best_for'])) : [],
            'not_for' => is_array($value['not_for'] ?? null) ? array_values(array_map('strval', $value['not_for'])) : [],
            'claim_ids' => is_array($value['claim_ids'] ?? null) ? array_values(array_map('strval', $value['claim_ids'])) : [],
            'sources' => is_array($value['sources'] ?? null)
                ? array_values(array_map('birkly_ai_presence_normalize_source_link', array_filter($value['sources'], 'is_array')))
                : [],
            'connected_entry' => is_array($value['connected_entry'] ?? null) ? $value['connected_entry'] : null,
            'media_id' => (string) ($value['media_id'] ?? ''),
            'media_url' => (string) ($value['media_url'] ?? ''),
            'media_name' => (string) ($value['media_name'] ?? ''),
            'external_image_url' => (string) ($value['external_image_url'] ?? ''),
            'public' => !isset($value['public']) || (bool) $value['public'],
        ];
        return $draft;
    }

    if ($field === 'relationships.new' && is_array($value)) {
        $draft['relationships'][] = [
            'id' => (string) ($value['id'] ?? ('rel_' . bin2hex(random_bytes(4)))),
            'type' => (string) ($value['type'] ?? 'partner'),
            'name' => (string) ($value['name'] ?? ''),
            'description' => (string) ($value['description'] ?? ''),
            'url' => (string) ($value['url'] ?? ''),
        ];
        return $draft;
    }

    if ($field === 'evidence.new' && is_array($value)) {
        require_once __DIR__ . '/ai_presence_links.php';
        $id = (string) ($value['id'] ?? ('evidence_' . bin2hex(random_bytes(4))));
        $types = birkly_ai_presence_evidence_types_extended();
        $type = (string) ($value['type'] ?? 'external_url');
        if (!in_array($type, $types, true)) {
            $type = 'external_url';
        }
        $item = [
            'id' => $id,
            'type' => $type,
            'title' => (string) ($value['title'] ?? 'Evidence'),
            'url' => (string) ($value['url'] ?? ''),
            'media_id' => '',
            'publisher' => (string) ($value['publisher'] ?? ''),
            'published_at' => '',
            'reviewed_at' => '',
            'verification_status' => 'unverified',
            'claim_ids' => [],
            'notes' => (string) ($value['notes'] ?? ''),
            'link_status' => 'unknown',
            'last_verified_at' => '',
        ];
        if (isset($value['rating']) && is_numeric($value['rating'])) {
            $item['rating'] = (float) $value['rating'];
        }
        if (isset($value['review_count']) && is_numeric($value['review_count'])) {
            $item['review_count'] = (int) $value['review_count'];
        }
        $draft['evidence'][] = $item;
        $claimStatement = (string) ($value['claim_statement'] ?? '');
        if ($claimStatement !== '') {
            foreach ($draft['claims'] ?? [] as $i => $claim) {
                if (!is_array($claim)) {
                    continue;
                }
                if (strcasecmp((string) ($claim['statement'] ?? ''), $claimStatement) === 0) {
                    $claimId = (string) ($claim['id'] ?? '');
                    $draft['claims'][$i]['evidence_ids'] = array_values(array_unique(array_merge(
                        $claim['evidence_ids'] ?? [],
                        [$id]
                    )));
                    $last = count($draft['evidence']) - 1;
                    $draft['evidence'][$last]['claim_ids'] = [$claimId];
                    break;
                }
            }
        }
        return $draft;
    }

    if (str_starts_with($field, 'communication_guidance.')) {
        $key = substr($field, strlen('communication_guidance.'));
        if ($key === 'preferred_terms' && is_array($value)) {
            $draft['communication_guidance']['preferred_terms'] = array_merge(
                $draft['communication_guidance']['preferred_terms'] ?? [],
                $value
            );
        } elseif ($key === 'avoided_terms' && is_array($value)) {
            $draft['communication_guidance']['avoided_terms'] = array_merge(
                $draft['communication_guidance']['avoided_terms'] ?? [],
                $value
            );
        } else {
            $draft['communication_guidance'][$key] = $value;
        }
        return $draft;
    }

    if (str_starts_with($field, 'identity.') && str_ends_with($field, '_lines')) {
        $key = substr($field, strlen('identity.'), -strlen('_lines'));
        if (is_string($value)) {
            $draft['identity'][$key] = array_values(array_filter(array_map('trim', preg_split('/\n+/', $value) ?: [])));
        }
        return $draft;
    }

    return birkly_ai_presence_apply_suggestion($draft, $suggestion);
}

/**
 * @return array{answer: string, sources: list<array<string, string>>, ai_used?: bool}
 */
function birkly_ai_presence_preview_query_pro(string $question, string $connectionId = ''): array
{
    $base = birkly_ai_presence_preview_query($question);
    if ($connectionId === '' || ($base['sources'] ?? []) !== []) {
        return $base;
    }

    $published = birkly_ai_presence_load_published();
    if ($published === null) {
        return $base;
    }

    $context = json_encode(birkly_ai_presence_build_public_export($published, (int) (birkly_ai_presence_load_meta()['published_version'] ?? 0), (string) (birkly_ai_presence_load_meta()['last_published_at'] ?? date('c'))), JSON_UNESCAPED_UNICODE);
    $system = 'Answer ONLY from the provided published brand context JSON. Cite which sections you used. If unknown, say so. Return JSON: {"answer":"...","sources":[{"type":"...","label":"..."}]}';
    $ai = birkly_ai_presence_call_ai($connectionId, $system, "Question: $question\n\nContext:\n" . substr($context, 0, 12000));
    if (!($ai['ok'] ?? false)) {
        return $base;
    }
    $raw = (string) ($ai['text'] ?? '');
    $decoded = json_decode($raw, true);
    if (is_array($decoded) && !empty($decoded['answer'])) {
        return [
            'answer' => (string) $decoded['answer'],
            'sources' => is_array($decoded['sources'] ?? null) ? $decoded['sources'] : [],
            'ai_used' => true,
        ];
    }
    return ['answer' => $raw, 'sources' => [['type' => 'ai', 'label' => 'AI retrieval']], 'ai_used' => true];
}

/**
 * @return list<array<string, mixed>>
 */
function birkly_ai_presence_list_versions(): array
{
    $dir = birkly_ai_presence_versions_dir();
    if (!is_dir($dir)) {
        return [];
    }
    $versions = [];
    foreach (scandir($dir) ?: [] as $file) {
        if (!preg_match('/^(\d+)\.json$/', $file, $m)) {
            continue;
        }
        $path = $dir . '/' . $file;
        $publishedAt = null;
        $summary = '';
        $decoded = birkly_ai_presence_read_json($path);
        if (is_array($decoded)) {
            $publishedAt = (string) ($decoded['_published']['published_at'] ?? '');
            if ($publishedAt === '') {
                $publishedAt = null;
            }
            $name = trim((string) ($decoded['identity']['official_name'] ?? ''));
            if ($name !== '') {
                $summary = $name;
            }
        }
        $versions[] = [
            'version' => (int) $m[1],
            'path' => $path,
            'size' => filesize($path) ?: 0,
            'published_at' => $publishedAt,
            'summary' => $summary,
        ];
    }
    usort($versions, static fn ($a, $b) => ($b['version'] <=> $a['version']));
    return $versions;
}

/**
 * @return array<string, mixed>
 */
function birkly_ai_presence_public_view(array $export, string $view): array
{
    $view = strtolower(trim($view));
    if ($view === '' || $view === 'full') {
        return $export;
    }

    $identity = $export['identity'] ?? [];
    $positioning = $export['positioning'] ?? [];

    if ($view === 'summary') {
        require_once __DIR__ . '/ai_presence_links.php';
        $supported = array_values(array_filter($export['claims'] ?? [], static fn ($c) => is_array($c) && ($c['status'] ?? '') === 'supported'));
        return [
            'schema_version' => $export['schema_version'] ?? BIRKLY_AI_PRESENCE_SCHEMA_VERSION,
            'published_version' => $export['published_version'] ?? 0,
            'generated_at' => $export['generated_at'] ?? date('c'),
            'view' => 'summary',
            'identity' => [
                'official_name' => $identity['official_name'] ?? '',
                'short_description' => $identity['short_description'] ?? '',
                'website' => $identity['website'] ?? '',
                'derived_from' => $identity['derived_from'] ?? [],
            ],
            'positioning' => [
                'best_known_for' => $positioning['best_known_for'] ?? [],
                'differentiators' => $positioning['differentiators'] ?? [],
            ],
            'supported_claims' => array_values(array_map(
                static fn ($c) => [
                    'id' => $c['id'] ?? '',
                    'statement' => $c['statement'] ?? '',
                    'sources' => $c['sources'] ?? [],
                    'evidence_ids' => $c['evidence_ids'] ?? [],
                ],
                $supported
            )),
            'sources_of_truth' => $export['sources_of_truth'] ?? [],
            'usage_hints' => $export['usage_hints'] ?? birkly_ai_presence_build_usage_hints($export),
        ];
    }

    if ($view === 'claims') {
        $claims = array_values(array_filter(
            $export['claims'] ?? [],
            static fn ($c) => is_array($c) && ($c['status'] ?? '') === 'supported'
        ));
        $evidenceIds = [];
        foreach ($claims as $claim) {
            foreach ($claim['evidence_ids'] ?? [] as $evidenceId) {
                if (is_string($evidenceId) && $evidenceId !== '') {
                    $evidenceIds[$evidenceId] = true;
                }
            }
        }
        $evidence = array_values(array_filter(
            $export['evidence'] ?? [],
            static fn ($ev) => is_array($ev) && isset($evidenceIds[(string) ($ev['id'] ?? '')])
        ));
        return [
            'schema_version' => $export['schema_version'] ?? BIRKLY_AI_PRESENCE_SCHEMA_VERSION,
            'published_version' => $export['published_version'] ?? 0,
            'generated_at' => $export['generated_at'] ?? date('c'),
            'view' => 'claims',
            'claims' => $claims,
            'evidence' => $evidence,
            'usage_hints' => $export['usage_hints'] ?? [],
        ];
    }

    if ($view === 'products') {
        $products = array_values(array_filter(
            $export['products_services'] ?? [],
            static fn ($p) => is_array($p) && (!isset($p['public']) || $p['public'])
        ));
        return [
            'schema_version' => $export['schema_version'] ?? BIRKLY_AI_PRESENCE_SCHEMA_VERSION,
            'published_version' => $export['published_version'] ?? 0,
            'generated_at' => $export['generated_at'] ?? date('c'),
            'view' => 'products',
            'products_services' => $products,
        ];
    }

    return $export;
}

/**
 * @return array<string, mixed>
 */
function birkly_ai_presence_export_json_ld_pro(array $doc): array
{
    require_once __DIR__ . '/ai_presence_links.php';
    $enriched = birkly_ai_presence_enrich_public_export($doc);
    $base = birkly_ai_presence_export_json_ld($enriched);
    $graph = $base['@graph'] ?? [$base];
    $identity = $enriched['identity'] ?? [];
    $sameAs = [];
    foreach ($identity['social_profiles'] ?? [] as $profile) {
        if (is_array($profile) && !empty($profile['url'])) {
            $sameAs[] = (string) $profile['url'];
        }
    }
    if ($sameAs !== [] && isset($graph[0]) && is_array($graph[0])) {
        $graph[0]['sameAs'] = $sameAs;
    }

    foreach ($enriched['products_services'] ?? [] as $product) {
        if (!is_array($product) || empty($product['name'])) {
            continue;
        }
        if (isset($product['public']) && !$product['public']) {
            continue;
        }
        $type = ($product['type'] ?? 'product') === 'service' ? 'Service' : 'Product';
        $sources = $product['sources'] ?? [];
        $canonicalUrl = '';
        $sameAsProduct = [];
        foreach ($sources as $src) {
            if (!is_array($src) || empty($src['url'])) {
                continue;
            }
            $url = (string) $src['url'];
            if (($src['role'] ?? '') === 'canonical_detail' && $canonicalUrl === '') {
                $canonicalUrl = $url;
            } else {
                $sameAsProduct[] = $url;
            }
        }
        $node = [
            '@type' => $type,
            'name' => (string) $product['name'],
            'description' => (string) ($product['short_description'] ?? $product['full_description'] ?? ''),
            'category' => (string) ($product['category'] ?? ''),
            'url' => $canonicalUrl !== '' ? $canonicalUrl : null,
            'sameAs' => $sameAsProduct !== [] ? $sameAsProduct : null,
        ];
        if ($node['description'] === '') {
            unset($node['description']);
        }
        if ($node['category'] === '') {
            unset($node['category']);
        }
        if ($node['url'] === null) {
            unset($node['url']);
        }
        if ($node['sameAs'] === null) {
            unset($node['sameAs']);
        }
        $imageUrl = (string) ($product['media_url'] ?? $product['external_image_url'] ?? '');
        if ($imageUrl !== '') {
            $node['image'] = $imageUrl;
        }
        $graph[] = array_filter($node);
    }

    foreach ($enriched['claims'] ?? [] as $claim) {
        if (!is_array($claim) || ($claim['status'] ?? '') !== 'supported') {
            continue;
        }
        $evidenceUrls = [];
        foreach ($claim['linked_evidence'] ?? [] as $ev) {
            if (is_array($ev) && !empty($ev['url'])) {
                $evidenceUrls[] = (string) $ev['url'];
            }
        }
        $claimNode = [
            '@type' => 'Claim',
            'name' => (string) ($claim['statement'] ?? ''),
            'identifier' => (string) ($claim['id'] ?? ''),
        ];
        if ($evidenceUrls !== []) {
            $claimNode['appearance'] = ['@type' => 'CreativeWork', 'url' => $evidenceUrls[0]];
            if (count($evidenceUrls) > 1) {
                $claimNode['isBasedOn'] = array_map(static fn ($u) => ['@type' => 'CreativeWork', 'url' => $u], $evidenceUrls);
            }
        }
        $graph[] = array_filter($claimNode);
    }

    return ['@context' => 'https://schema.org', '@graph' => array_values($graph)];
}

function birkly_ai_presence_public_brand_for_tools(string $view = 'summary'): ?array
{
    return birkly_ai_presence_public_agent_guide_for_tools($view);
}

/**
 * MCP / public read helpers for Agent Guide.
 *
 * @return array<string, mixed>|null
 */
function birkly_ai_presence_public_agent_guide_for_tools(string $view = 'summary'): ?array
{
    $meta = birkly_ai_presence_load_meta();
    $published = birkly_ai_presence_load_published();
    if (!birkly_ai_presence_is_publicly_live($published, $meta)) {
        return null;
    }
    $export = birkly_ai_presence_build_public_export($published, (int) ($meta['published_version'] ?? 0), (string) ($meta['last_published_at'] ?? date('c')));
    return birkly_ai_presence_public_view($export, $view);
}

function birkly_ai_presence_pro_default_products(): array
{
    return [];
}

function birkly_ai_presence_pro_normalize(array $draft): array
{
    if (!isset($draft['products_services']) || !is_array($draft['products_services'])) {
        $draft['products_services'] = [];
    }
    if (!isset($draft['relationships']) || !is_array($draft['relationships'])) {
        $draft['relationships'] = [];
    }
    $pub = $draft['publication_settings'] ?? [];
    if (!isset($pub['redact_public_fields'])) {
        $draft['publication_settings']['redact_public_fields'] = [];
    }
    if (!isset($draft['identity']['derived_from']) || !is_array($draft['identity']['derived_from'])) {
        $draft['identity']['derived_from'] = [];
    }
    return $draft;
}
