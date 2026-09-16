<?php
/**
 * Agent Guide — structured context for AI systems (P46, formerly AI Presence).
 * Storage: settings/ai_presence/
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';
require_once __DIR__ . '/hosting_urls.php';

const BIRKLY_AI_PRESENCE_SCHEMA_VERSION = 'agent_guide.v1';
const BIRKLY_AI_PRESENCE_LEGACY_SCHEMA_VERSION = 'ai_presence.v1';
const BIRKLY_AI_PRESENCE_VERSIONS_CAP = 50;
const BIRKLY_AI_PRESENCE_EXPORT_MAX_BYTES = 2097152;

function birkly_ai_presence_dir(): string
{
    return birkly_project_root() . '/settings/ai_presence';
}

function birkly_ai_presence_versions_dir(): string
{
    return birkly_ai_presence_dir() . '/versions';
}

function birkly_ai_presence_schema_path(): string
{
    return birkly_project_root() . '/schema/ai_presence.schema.json';
}

function birkly_ai_presence_ensure_dir(): void
{
    $dir = birkly_ai_presence_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

function birkly_ai_presence_write_json(string $path, array $data): bool
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $tmp = $path . '.tmp';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    $fp = fopen($tmp, 'c');
    if (!$fp) {
        return false;
    }
    $ok = false;
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
        $ok = rename($tmp, $path);
    }
    fclose($fp);
    if (!$ok && is_file($tmp)) {
        @unlink($tmp);
    }
    return $ok;
}

function birkly_ai_presence_read_json(string $path): ?array
{
    if (!is_readable($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * @return array<string, mixed>
 */
function birkly_ai_presence_default_draft(): array
{
    return [
        'schema_version' => BIRKLY_AI_PRESENCE_SCHEMA_VERSION,
        'identity' => [
            'official_name' => '',
            'short_description' => '',
            'full_description' => '',
            'what_we_do' => '',
            'industry' => [],
            'categories' => [],
            'locations' => [],
            'website' => '',
            'social_profiles' => [],
        ],
        'positioning' => [
            'best_known_for' => [],
            'differentiators' => [],
            'best_suited_for' => [],
            'not_ideal_for' => [],
            'competitive_positioning' => '',
        ],
        'communication_guidance' => [
            'preferred_terms' => [],
            'avoided_terms' => [],
            'required_qualifications' => [],
            'tone' => [],
            'custom_guidance' => '',
        ],
        'connected_content' => [],
        'sources_of_truth' => [],
        'guide_meta' => [
            'product' => 'Agent Guide',
            'summary_layer' => true,
        ],
        'claims' => [],
        'evidence' => [],
        'media_context' => [],
        'products_services' => [],
        'relationships' => [],
        'publication_settings' => [
            'enabled' => false,
            'public_path' => '/.well-known/agent-guide.json',
            'include_json_ld' => true,
            'redact_public_fields' => [],
            'public_views_enabled' => true,
            'footer_discovery_link' => true,
            'footer_discovery_line' => false,
            'footer_discovery_line_text' => '',
            'discovery_sitemap' => true,
            'discovery_indexnow' => true,
            'discovery_ai_crawlers' => true,
        ],
    ];
}

function birkly_ai_presence_default_meta(): array
{
    return [
        'published_version' => 0,
        'last_published_at' => null,
        'draft_updated_at' => null,
        'draft_checksum' => null,
        'published_checksum' => null,
    ];
}

function birkly_ai_presence_normalize_draft(array $draft): array
{
    $defaults = birkly_ai_presence_default_draft();
    $merged = array_replace_recursive($defaults, $draft);
    if (!birkly_ai_presence_is_valid_schema_version((string) ($merged['schema_version'] ?? ''))) {
        $merged['schema_version'] = BIRKLY_AI_PRESENCE_SCHEMA_VERSION;
    }

    if (!is_array($merged['connected_content'])) {
        $merged['connected_content'] = [];
    }
    if (!is_array($merged['sources_of_truth'])) {
        $merged['sources_of_truth'] = [];
    }
    if (!is_array($merged['guide_meta'])) {
        $merged['guide_meta'] = ['product' => 'Agent Guide', 'summary_layer' => true];
    }
    if (!is_array($merged['claims'])) {
        $merged['claims'] = [];
    }
    if (!is_array($merged['evidence'])) {
        $merged['evidence'] = [];
    }
    if (!is_array($merged['media_context'])) {
        $merged['media_context'] = [];
    }
    if (!is_array($merged['products_services'])) {
        $merged['products_services'] = [];
    }
    if (!is_array($merged['relationships'])) {
        $merged['relationships'] = [];
    }

    if (is_file(__DIR__ . '/ai_presence_pro.php')) {
        require_once __DIR__ . '/ai_presence_pro.php';
        $merged = birkly_ai_presence_pro_normalize($merged);
    }
    if (is_file(__DIR__ . '/ai_presence_links.php')) {
        require_once __DIR__ . '/ai_presence_links.php';
        $merged = birkly_ai_presence_normalize_sources_in_draft($merged);
    }
    if (is_file(__DIR__ . '/agent_guide_sources.php')) {
        require_once __DIR__ . '/agent_guide_sources.php';
        $merged = birkly_agent_guide_normalize_sources_in_draft($merged);
    }

    return $merged;
}

function birkly_ai_presence_is_valid_schema_version(string $version): bool
{
    return in_array($version, [
        BIRKLY_AI_PRESENCE_SCHEMA_VERSION,
        BIRKLY_AI_PRESENCE_LEGACY_SCHEMA_VERSION,
        'agent_guide.v1',
        'ai_presence.v1',
    ], true);
}

function birkly_ai_presence_load_draft(): array
{
    birkly_ai_presence_ensure_dir();
    $path = birkly_ai_presence_dir() . '/draft.json';
    $data = birkly_ai_presence_read_json($path);
    if ($data === null) {
        return birkly_ai_presence_default_draft();
    }
    return birkly_ai_presence_normalize_draft($data);
}

function birkly_ai_presence_save_draft(array $draft): bool
{
    birkly_ai_presence_ensure_dir();
    $normalized = birkly_ai_presence_normalize_draft($draft);
    $errors = birkly_ai_presence_validate($normalized);
    if ($errors !== []) {
        throw new InvalidArgumentException(implode('; ', $errors));
    }

    $path = birkly_ai_presence_dir() . '/draft.json';
    $saved = birkly_ai_presence_write_json($path, $normalized);
    if ($saved) {
        $meta = birkly_ai_presence_load_meta();
        $meta['draft_updated_at'] = date('c');
        $meta['draft_checksum'] = hash('sha256', json_encode($normalized));
        birkly_ai_presence_save_meta($meta);
    }
    return $saved;
}

function birkly_ai_presence_merge_draft(array $partial): array
{
    $current = birkly_ai_presence_load_draft();
    $merged = birkly_ai_presence_merge_draft_arrays($current, $partial);
    return birkly_ai_presence_normalize_draft($merged);
}

/**
 * Keys whose values are ordered lists — replaced entirely when present in a patch.
 * array_replace_recursive merges numeric keys by index, which breaks save_draft
 * semantics for products_services, claims, and other list fields.
 *
 * @return list<string>
 */
function birkly_ai_presence_draft_list_keys(): array
{
    return [
        'connected_content',
        'sources_of_truth',
        'claims',
        'evidence',
        'media_context',
        'products_services',
        'relationships',
        'industry',
        'categories',
        'locations',
        'social_profiles',
        'best_known_for',
        'differentiators',
        'best_suited_for',
        'not_ideal_for',
        'preferred_terms',
        'avoided_terms',
        'required_qualifications',
        'tone',
        'sources',
        'best_for',
        'not_for',
        'claim_ids',
        'redact_public_fields',
    ];
}

function birkly_ai_presence_is_draft_list_key(string $key): bool
{
    return in_array($key, birkly_ai_presence_draft_list_keys(), true);
}

/**
 * @param array<string, mixed> $base
 * @param array<string, mixed> $patch
 * @return array<string, mixed>
 */
function birkly_ai_presence_merge_draft_arrays(array $base, array $patch): array
{
    if ($patch !== [] && array_is_list($patch)) {
        return array_values($patch);
    }

    $result = $base;
    foreach ($patch as $key => $value) {
        if (is_string($key) && birkly_ai_presence_is_draft_list_key($key)) {
            $result[$key] = is_array($value) ? array_values($value) : [];
            continue;
        }
        if (is_array($value) && isset($result[$key]) && is_array($result[$key])) {
            $result[$key] = birkly_ai_presence_merge_draft_arrays($result[$key], $value);
            continue;
        }
        $result[$key] = $value;
    }
    return $result;
}

function birkly_ai_presence_load_published(): ?array
{
    $path = birkly_ai_presence_dir() . '/published.json';
    $raw = birkly_ai_presence_read_json($path);
    if ($raw === null) {
        return null;
    }
    $rawPub = is_array($raw['publication_settings'] ?? null) ? $raw['publication_settings'] : [];
    $hadExplicitEnabled = array_key_exists('enabled', $rawPub);
    $data = birkly_ai_presence_normalize_draft($raw);
    if (!$hadExplicitEnabled) {
        $meta = birkly_ai_presence_load_meta();
        if ((int) ($meta['published_version'] ?? 0) >= 1) {
            $data['publication_settings']['enabled'] = true;
        }
    }
    return $data;
}

/**
 * Whether the published snapshot is enabled for public/MCP reads.
 * Uses the snapshot flag (not the editable draft toggle).
 */
function birkly_ai_presence_published_snapshot_enabled(?array $published, ?array $meta = null): bool
{
    if ($published === null) {
        return false;
    }
    $pub = is_array($published['publication_settings'] ?? null) ? $published['publication_settings'] : [];
    if (array_key_exists('enabled', $pub)) {
        return !empty($pub['enabled']);
    }
    $meta = $meta ?? birkly_ai_presence_load_meta();
    return (int) ($meta['published_version'] ?? 0) >= 1;
}

/**
 * Whether external AI and public endpoints should serve the Agent Guide.
 */
function birkly_ai_presence_is_publicly_live(?array $published = null, ?array $meta = null): bool
{
    if (!function_exists('birkly_discovery_pack_enabled')) {
        require_once __DIR__ . '/discovery.php';
    }
    if (!birkly_discovery_pack_enabled('agent_guide')) {
        return false;
    }

    $meta = $meta ?? birkly_ai_presence_load_meta();
    $published = $published ?? birkly_ai_presence_load_published();
    $version = (int) ($meta['published_version'] ?? 0);
    if ($published === null || $version < 1) {
        return false;
    }
    return birkly_ai_presence_published_snapshot_enabled($published, $meta);
}

/**
 * Human-readable reason when public/MCP reads are unavailable.
 */
function birkly_ai_presence_not_published_reason(?array $published = null, ?array $meta = null): string
{
    $meta = $meta ?? birkly_ai_presence_load_meta();
    $published = $published ?? birkly_ai_presence_load_published();
    $version = (int) ($meta['published_version'] ?? 0);
    if ($published === null) {
        return 'Agent Guide not published (no published snapshot — click Publish in Agent Guide)';
    }
    if ($version < 1) {
        return 'Agent Guide not published (never published — click Publish in Agent Guide)';
    }
    if (!birkly_ai_presence_published_snapshot_enabled($published, $meta)) {
        return 'Agent Guide not published (publishing is off on the live snapshot — click Publish again in Agent Guide)';
    }
    return 'Agent Guide not published';
}

function birkly_ai_presence_load_meta(): array
{
    birkly_ai_presence_ensure_dir();
    $path = birkly_ai_presence_dir() . '/meta.json';
    $data = birkly_ai_presence_read_json($path);
    if ($data === null) {
        return birkly_ai_presence_default_meta();
    }
    return array_merge(birkly_ai_presence_default_meta(), $data);
}

function birkly_ai_presence_save_meta(array $meta): bool
{
    birkly_ai_presence_ensure_dir();
    return birkly_ai_presence_write_json(
        birkly_ai_presence_dir() . '/meta.json',
        array_merge(birkly_ai_presence_default_meta(), $meta)
    );
}

/**
 * @return list<string>
 */
function birkly_ai_presence_validate(array $doc): array
{
    $errors = [];

    if (!birkly_ai_presence_is_valid_schema_version((string) ($doc['schema_version'] ?? ''))) {
        $errors[] = 'Invalid schema_version';
    }

    $claimStatuses = ['supported', 'pending_review', 'unsupported', 'conflicting', 'expired'];
    foreach ($doc['claims'] ?? [] as $i => $claim) {
        if (!is_array($claim)) {
            $errors[] = "claims[$i] must be an object";
            continue;
        }
        $status = (string) ($claim['status'] ?? '');
        if ($status !== '' && !in_array($status, $claimStatuses, true)) {
            $errors[] = "claims[$i].status invalid";
        }
    }

    require_once __DIR__ . '/ai_presence_links.php';
    $evidenceTypes = birkly_ai_presence_evidence_types_extended();
    foreach ($doc['evidence'] ?? [] as $i => $item) {
        if (!is_array($item)) {
            $errors[] = "evidence[$i] must be an object";
            continue;
        }
        $type = (string) ($item['type'] ?? '');
        if ($type !== '' && !in_array($type, $evidenceTypes, true)) {
            $errors[] = "evidence[$i].type invalid";
        }
        $verification = (string) ($item['verification_status'] ?? 'unverified');
        if (!in_array($verification, ['user_verified', 'unverified'], true)) {
            $errors[] = "evidence[$i].verification_status invalid";
        }
    }

    foreach ($doc['sources_of_truth'] ?? [] as $i => $source) {
        if (!is_array($source)) {
            $errors[] = "sources_of_truth[$i] must be an object";
            continue;
        }
        $type = (string) ($source['type'] ?? '');
        if ($type !== '' && is_file(__DIR__ . '/agent_guide_sources.php')) {
            require_once __DIR__ . '/agent_guide_sources.php';
            if (!in_array($type, birkly_agent_guide_source_truth_types(), true)) {
                $errors[] = "sources_of_truth[$i].type invalid";
            }
            $role = (string) ($source['role'] ?? '');
            if ($role !== '' && !in_array($role, birkly_agent_guide_source_truth_roles(), true)) {
                $errors[] = "sources_of_truth[$i].role invalid";
            }
        }
    }

    foreach ($doc['connected_content'] ?? [] as $i => $ref) {
        if (!is_array($ref)) {
            continue;
        }
        $refType = (string) ($ref['ref_type'] ?? '');
        if ($refType !== '' && !in_array($refType, ['entry', 'collection'], true)) {
            $errors[] = "connected_content[$i].ref_type invalid";
        }
    }

    return $errors;
}

function birkly_ai_presence_checksum(array $doc): string
{
    return hash('sha256', json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

/**
 * @return array{score: int, max: int, percent: int, items: list<array<string, mixed>>}
 */
function birkly_ai_presence_completeness(?array $draft = null): array
{
    $draft = $draft ?? birkly_ai_presence_load_draft();
    $identity = $draft['identity'] ?? [];
    $positioning = $draft['positioning'] ?? [];
    $pub = $draft['publication_settings'] ?? [];

    $items = [];

    $hasName = trim((string) ($identity['official_name'] ?? '')) !== '';
    $hasShort = trim((string) ($identity['short_description'] ?? '')) !== '';
    $identityScore = ($hasName ? 10 : 0) + ($hasShort ? 10 : 0);
    $items[] = [
        'key' => 'identity',
        'label' => 'Profile identity',
        'score' => $identityScore,
        'max' => 20,
        'complete' => $identityScore >= 20,
        'tab' => 'context',
    ];

    $hasDesc = trim((string) ($identity['full_description'] ?? '')) !== ''
        || trim((string) ($identity['what_we_do'] ?? '')) !== '';
    $items[] = [
        'key' => 'description',
        'label' => 'Description',
        'score' => $hasDesc ? 15 : 0,
        'max' => 15,
        'complete' => $hasDesc,
        'tab' => 'context',
    ];

    require_once __DIR__ . '/agent_guide_sources.php';
    $hasNarrativeSource = birkly_agent_guide_has_narrative_source($draft);
    $sourcesCount = is_array($draft['sources_of_truth'] ?? null) ? count($draft['sources_of_truth']) : 0;
    $connected = is_array($draft['connected_content'] ?? null) ? count($draft['connected_content']) : 0;
    $sourcesScore = $hasNarrativeSource ? 15 : ($sourcesCount > 0 || $connected > 0 ? 8 : 0);
    $items[] = [
        'key' => 'sources_of_truth',
        'label' => 'Sources of truth',
        'score' => $sourcesScore,
        'max' => 15,
        'complete' => $hasNarrativeSource,
        'tab' => 'sources',
        'detail' => $sourcesCount > 0 && !$hasNarrativeSource ? 'Add at least one narrative source (CMS, website, or document)' : '',
    ];

    $claims = $draft['claims'] ?? [];
    $supportedWithEvidence = 0;
    foreach ($claims as $claim) {
        if (!is_array($claim)) {
            continue;
        }
        if (($claim['status'] ?? '') === 'supported' && !empty($claim['evidence_ids'])) {
            $supportedWithEvidence++;
        }
    }
    $claimsScore = $supportedWithEvidence > 0 ? 20 : (count($claims) > 0 ? 8 : 0);
    $items[] = [
        'key' => 'claims',
        'label' => 'Reviewed claims',
        'score' => $claimsScore,
        'max' => 20,
        'complete' => $supportedWithEvidence > 0,
        'tab' => 'claims',
    ];

    $evidenceCount = is_array($draft['evidence'] ?? null) ? count($draft['evidence']) : 0;
    $urlEvidence = 0;
    foreach ($draft['evidence'] ?? [] as $ev) {
        if (is_array($ev) && trim((string) ($ev['url'] ?? '')) !== '') {
            $urlEvidence++;
        }
    }
    $evidenceScore = ($evidenceCount > 0 ? 10 : 0) + ($urlEvidence > 0 ? 5 : 0);
    $items[] = [
        'key' => 'evidence',
        'label' => 'Evidence sources',
        'score' => $evidenceScore,
        'max' => 15,
        'complete' => $urlEvidence > 0,
        'tab' => 'claims',
        'detail' => $evidenceCount > 0 && $urlEvidence === 0 ? 'Add verifiable URLs to evidence' : '',
    ];

    $knownFor = $positioning['best_known_for'] ?? [];
    $positioningScore = (is_array($knownFor) && count($knownFor) > 0) ? 10 : 0;
    $items[] = [
        'key' => 'positioning',
        'label' => 'Positioning',
        'score' => $positioningScore,
        'max' => 10,
        'complete' => $positioningScore > 0,
        'tab' => 'context',
    ];

    $products = is_array($draft['products_services'] ?? null) ? count($draft['products_services']) : 0;
    require_once __DIR__ . '/ai_presence_links.php';
    $groundedProducts = 0;
    foreach ($draft['products_services'] ?? [] as $product) {
        if (is_array($product) && birkly_ai_presence_product_has_grounded_source($product)) {
            $groundedProducts++;
        }
    }
    $productsScore = ($products > 0 ? 5 : 0) + ($groundedProducts > 0 ? 5 : 0);
    $items[] = [
        'key' => 'products',
        'label' => 'Products & services',
        'score' => $productsScore,
        'max' => 10,
        'complete' => $products > 0 && $groundedProducts > 0,
        'tab' => 'products',
        'detail' => $products > 0 && $groundedProducts === 0 ? 'Add canonical or external source links' : '',
    ];

    $terms = $draft['communication_guidance']['preferred_terms'] ?? [];
    $items[] = [
        'key' => 'terminology',
        'label' => 'Terminology & voice',
        'score' => (is_array($terms) && count($terms) > 0) ? 5 : 0,
        'max' => 5,
        'complete' => is_array($terms) && count($terms) > 0,
        'tab' => 'context',
    ];

    $enabled = !empty($pub['enabled']);
    $items[] = [
        'key' => 'publishing',
        'label' => 'Publishing enabled',
        'score' => $enabled ? 5 : 0,
        'max' => 5,
        'complete' => $enabled,
        'tab' => 'publish',
    ];

    $score = 0;
    $maxRaw = 0;
    foreach ($items as $item) {
        $score += (int) $item['score'];
        $maxRaw += (int) $item['max'];
    }

    $percent = $maxRaw > 0 ? (int) round(($score / $maxRaw) * 100) : 0;
    if ($percent > 100) {
        $percent = 100;
    }

    return [
        'score' => $percent,
        'max' => 100,
        'percent' => $percent,
        'items' => $items,
    ];
}

/**
 * @return list<array<string, string>>
 */
function birkly_ai_presence_next_steps(?array $draft = null): array
{
    $completeness = birkly_ai_presence_completeness($draft);
    $steps = [];
    foreach ($completeness['items'] as $item) {
        if (!empty($item['complete'])) {
            continue;
        }
        $tab = (string) ($item['tab'] ?? 'overview');
        $label = (string) ($item['label'] ?? '');
        $message = match ($item['key'] ?? '') {
            'identity' => 'Add an official name and short description.',
            'description' => 'Add a full description or what you do.',
            'sources_of_truth' => 'Add sources of truth — CMS entries, websites, or documents agents should read first.',
            'connected_content' => 'Add CMS entries or collections as narrative sources.',
            'claims' => 'Add claims with supported evidence.',
            'evidence' => 'Attach evidence with verifiable URLs (marketplace, reviews, docs).',
            'positioning' => 'Describe what you are best known for.',
            'products' => 'Add products with canonical CMS or external source links.',
            'terminology' => 'Define preferred terms and voice guidance.',
            'publishing' => 'Enable publishing when ready to go live.',
            default => 'Complete: ' . $label,
        };
        $steps[] = [
            'key' => (string) ($item['key'] ?? ''),
            'message' => $message,
            'tab' => $tab,
        ];
    }
    return $steps;
}

function birkly_ai_presence_public_urls(): array
{
    $hosting = birkly_hosting_load();
    $mode = (string) ($hosting['public_site_mode'] ?? 'none'); // none | project | external
    $publicBase = birkly_hosting_public_base_url(); // '' when mode=none
    $cmsBase = birkly_cms_base_url();

    $path = '/.well-known/agent-guide.json';
    $legacyPath = '/.well-known/brand-context.json';
    $draft = birkly_ai_presence_load_draft();
    if (!empty($draft['publication_settings']['public_path'])) {
        $path = (string) $draft['publication_settings']['public_path'];
    }

    // The CMS PHP route is the guaranteed source of truth for the guide JSON and
    // the Birkly MCP endpoint — it is always served by Birkly regardless of how
    // the public site is hosted (P60-M0 §D). These addresses never 404.
    $cmsCanonicalUrl = $cmsBase !== '' ? rtrim($cmsBase, '/') . '/.well-known/agent-guide.json' : '';
    $cmsLegacyUrl = $cmsBase !== '' ? rtrim($cmsBase, '/') . $legacyPath : '';
    $mcpUrl = $cmsBase !== '' ? rtrim($cmsBase, '/') . '/mcp' : '';
    // Public read API is served by the CMS host (external hosts do not proxy it).
    $apiUrl = $cmsBase !== '' ? rtrim($cmsBase, '/') . '/api/public/ai_presence.php?action=guide' : '';
    $legacyApiUrl = $cmsBase !== '' ? rtrim($cmsBase, '/') . '/api/public/ai_presence.php?action=brand' : '';

    // Birkly only serves the guide on the *public* address when it hosts the
    // project itself (mode=project). In external mode Birkly never serves that
    // host, so we must not advertise a public_site_url as verified (gap G5).
    $birklyServesPublic = ($mode === 'project') && $publicBase !== '';
    $publicSiteUrl = $birklyServesPublic ? rtrim($publicBase, '/') . $path : '';

    // Back-compat `well_known_url`: always resolve to an address that actually
    // works — the public URL when Birkly serves it, otherwise the CMS canonical.
    $wellKnownUrl = $birklyServesPublic ? $publicSiteUrl : $cmsCanonicalUrl;
    $legacyWellKnownUrl = $birklyServesPublic
        ? rtrim($publicBase, '/') . $legacyPath
        : $cmsLegacyUrl;

    return [
        // Hosting honesty (P60-M7)
        'hosting_mode' => $mode,
        'birkly_serves_public' => $birklyServesPublic,
        'cms_base_url' => $cmsBase,
        'cms_canonical_url' => $cmsCanonicalUrl,
        'mcp_url' => $mcpUrl,
        'public_site_base' => $publicBase,
        // Only populated when Birkly actually serves the public address.
        'public_site_url' => $publicSiteUrl,
        // Guaranteed-working guide address (public when served, else CMS route).
        'well_known_url' => $wellKnownUrl,
        'legacy_well_known_url' => $legacyWellKnownUrl,
        'api_url' => $apiUrl,
        'legacy_api_url' => $legacyApiUrl,
        'public_path' => $path,
        'legacy_public_path' => $legacyPath,
    ];
}

/**
 * @return array{ok: bool, version?: int, errors?: list<string>}
 */
function birkly_ai_presence_publish(): array
{
    $draft = birkly_ai_presence_load_draft();
    if (!isset($draft['publication_settings']) || !is_array($draft['publication_settings'])) {
        $draft['publication_settings'] = [];
    }
    $enabledChanged = false;
    if (empty($draft['publication_settings']['enabled'])) {
        $draft['publication_settings']['enabled'] = true;
        $enabledChanged = true;
    }

    $errors = birkly_ai_presence_validate($draft);
    if ($errors !== []) {
        return ['ok' => false, 'errors' => $errors];
    }

    if ($enabledChanged) {
        if (!birkly_ai_presence_save_draft($draft)) {
            return ['ok' => false, 'errors' => ['Failed to save draft before publish']];
        }
    }

    birkly_ai_presence_ensure_dir();
    $meta = birkly_ai_presence_load_meta();
    $version = (int) ($meta['published_version'] ?? 0) + 1;
    $now = date('c');

    $snapshot = $draft;
    $snapshot['_published'] = [
        'version' => $version,
        'published_at' => $now,
    ];

    $publishedPath = birkly_ai_presence_dir() . '/published.json';
    if (!birkly_ai_presence_write_json($publishedPath, $snapshot)) {
        return ['ok' => false, 'errors' => ['Failed to write published snapshot']];
    }

    $meta['published_version'] = $version;
    $meta['last_published_at'] = $now;
    $meta['published_checksum'] = birkly_ai_presence_checksum($snapshot);
    birkly_ai_presence_save_meta($meta);

    $versionsDir = birkly_ai_presence_versions_dir();
    if (!is_dir($versionsDir)) {
        mkdir($versionsDir, 0755, true);
    }
    birkly_ai_presence_write_json($versionsDir . '/' . $version . '.json', $snapshot);
    birkly_ai_presence_prune_versions();

    birkly_ai_presence_write_public_exports($snapshot, $version, $now);

    birkly_ai_presence_invalidate_project_ssr_cache();

    require_once __DIR__ . '/ai_presence_discovery.php';
    birkly_ai_presence_discovery_on_publish();

    require_once __DIR__ . '/discovery.php';
    birkly_discovery_save_pack('agent_guide', ['enabled' => true]);

    return ['ok' => true, 'version' => $version];
}

/**
 * Take the Agent Guide offline: disable publishing and remove public export files.
 *
 * @return array{ok: bool, errors?: list<string>}
 */
function birkly_ai_presence_unpublish(): array
{
    birkly_ai_presence_ensure_dir();
    $draft = birkly_ai_presence_load_draft();
    if (!isset($draft['publication_settings']) || !is_array($draft['publication_settings'])) {
        $draft['publication_settings'] = [];
    }
    $draft['publication_settings']['enabled'] = false;
    birkly_ai_presence_save_draft($draft);

    $published = birkly_ai_presence_load_published();
    if ($published !== null) {
        if (!isset($published['publication_settings']) || !is_array($published['publication_settings'])) {
            $published['publication_settings'] = [];
        }
        $published['publication_settings']['enabled'] = false;
        birkly_ai_presence_write_json(birkly_ai_presence_dir() . '/published.json', $published);
    }

    birkly_ai_presence_remove_public_exports();

    birkly_ai_presence_invalidate_project_ssr_cache();

    return ['ok' => true];
}

/**
 * Bust SSR HTML cache when Agent Guide publish state affects page injections.
 */
function birkly_ai_presence_invalidate_project_ssr_cache(): void
{
    $ssr = __DIR__ . '/project_ssr.php';
    if (!is_file($ssr)) {
        return;
    }
    require_once $ssr;
    if (function_exists('birkly_project_ssr_invalidate_all')) {
        birkly_project_ssr_invalidate_all();
    }
}

function birkly_ai_presence_remove_public_exports(): void
{
    $draft = birkly_ai_presence_load_draft();
    $paths = [
        (string) ($draft['publication_settings']['public_path'] ?? '/.well-known/agent-guide.json'),
        '/.well-known/agent-guide.json',
        '/.well-known/brand-context.json',
    ];
    $paths = array_values(array_unique(array_filter($paths, static fn ($p) => is_string($p) && $p !== '' && $p[0] === '/')));

    $hosting = birkly_hosting_load();
    $mode = (string) ($hosting['public_site_mode'] ?? 'none');
    if ($mode !== 'project') {
        return;
    }

    $wellKnownDir = birkly_project_root() . '/project/.well-known';
    if (!is_dir($wellKnownDir)) {
        return;
    }
    foreach ($paths as $path) {
        $filename = basename($path) ?: 'agent-guide.json';
        $file = $wellKnownDir . '/' . $filename;
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

function birkly_ai_presence_prune_versions(): void
{
    $dir = birkly_ai_presence_versions_dir();
    if (!is_dir($dir)) {
        return;
    }
    $files = [];
    foreach (scandir($dir) ?: [] as $file) {
        if (preg_match('/^(\d+)\.json$/', $file, $m)) {
            $files[(int) $m[1]] = $dir . '/' . $file;
        }
    }
    ksort($files);
    $count = count($files);
    if ($count <= BIRKLY_AI_PRESENCE_VERSIONS_CAP) {
        return;
    }
    $toRemove = $count - BIRKLY_AI_PRESENCE_VERSIONS_CAP;
    $keys = array_keys($files);
    for ($i = 0; $i < $toRemove; $i++) {
        @unlink($files[$keys[$i]]);
    }
}

function birkly_ai_presence_write_public_exports(array $snapshot, int $version, string $publishedAt): void
{
    $export = birkly_ai_presence_build_public_export($snapshot, $version, $publishedAt);
    $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false || strlen($json) > BIRKLY_AI_PRESENCE_EXPORT_MAX_BYTES) {
        return;
    }

    $paths = [
        (string) ($snapshot['publication_settings']['public_path'] ?? '/.well-known/agent-guide.json'),
        '/.well-known/agent-guide.json',
        '/.well-known/brand-context.json',
    ];
    $paths = array_values(array_unique(array_filter($paths, static fn ($p) => is_string($p) && $p !== '' && $p[0] === '/')));

    $hosting = birkly_hosting_load();
    $mode = (string) ($hosting['public_site_mode'] ?? 'none');
    if ($mode === 'project') {
        $wellKnownDir = birkly_project_root() . '/project/.well-known';
        if (!is_dir($wellKnownDir)) {
            mkdir($wellKnownDir, 0755, true);
        }
        foreach ($paths as $path) {
            $filename = basename($path) ?: 'agent-guide.json';
            file_put_contents($wellKnownDir . '/' . $filename, $json);
        }
    }
}

/**
 * @return array<string, mixed>
 */
function birkly_ai_presence_build_public_export(array $snapshot, int $version, string $publishedAt): array
{
    unset($snapshot['_published']);
    $export = $snapshot;
    $export['published_version'] = $version;
    $export['generated_at'] = $publishedAt;
    $export['schema_version'] = BIRKLY_AI_PRESENCE_SCHEMA_VERSION;

    if (!empty($snapshot['publication_settings']['include_json_ld'])) {
        if (function_exists('birkly_ai_presence_export_json_ld_pro')) {
            require_once __DIR__ . '/ai_presence_pro.php';
            $export['json_ld'] = birkly_ai_presence_export_json_ld_pro($snapshot);
        } else {
            $export['json_ld'] = birkly_ai_presence_export_json_ld($snapshot);
        }
    }

    require_once __DIR__ . '/ai_presence_links.php';
    require_once __DIR__ . '/agent_guide_sources.php';
    return birkly_agent_guide_enrich_export($export);
}

/**
 * @return array<string, mixed>
 */
function birkly_ai_presence_export_json_ld(array $doc): array
{
    $identity = $doc['identity'] ?? [];
    $org = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => (string) ($identity['official_name'] ?? ''),
        'description' => (string) ($identity['short_description'] ?? ''),
        'url' => (string) ($identity['website'] ?? ''),
    ];
    if ($org['name'] === '') {
        unset($org['name']);
    }
    if ($org['description'] === '') {
        unset($org['description']);
    }
    if ($org['url'] === '') {
        unset($org['url']);
    }

    $graph = [$org];
    foreach ($doc['claims'] ?? [] as $claim) {
        if (!is_array($claim) || ($claim['status'] ?? '') !== 'supported') {
            continue;
        }
        $graph[] = [
            '@type' => 'Claim',
            'name' => (string) ($claim['statement'] ?? ''),
            'identifier' => (string) ($claim['id'] ?? ''),
        ];
    }

    return ['@context' => 'https://schema.org', '@graph' => $graph];
}

function birkly_ai_presence_get_public_export_body(): ?string
{
    $meta = birkly_ai_presence_load_meta();
    $published = birkly_ai_presence_load_published();
    if (!birkly_ai_presence_is_publicly_live($published, $meta)) {
        return null;
    }
    $version = (int) ($meta['published_version'] ?? 0);
    $at = (string) ($meta['last_published_at'] ?? date('c'));
    $export = birkly_ai_presence_build_public_export($published, $version, $at);
    $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false || strlen($json) > BIRKLY_AI_PRESENCE_EXPORT_MAX_BYTES) {
        return null;
    }
    return $json;
}

/**
 * Markdown body for `/llms.txt` (Lighthouse Agentic Browsing).
 *
 * Requirements from Lighthouse 13.3 `llms-txt` audit: Markdown with at least
 * one H1 (`# …`), at least one Markdown link, and length ≥ 50. Returns null
 * when the Agent Guide is not published (404 → audit N/A).
 */
function birkly_ai_presence_llms_txt_body(): ?string
{
    $meta = birkly_ai_presence_load_meta();
    $published = birkly_ai_presence_load_published();
    if (!birkly_ai_presence_is_publicly_live($published, $meta)) {
        return null;
    }

    $identity = is_array($published['identity'] ?? null) ? $published['identity'] : [];
    $name = trim((string) ($identity['official_name'] ?? ''));
    if ($name === '') {
        $name = 'Site';
    }
    $short = trim((string) ($identity['short_description'] ?? ''));
    $full = trim((string) ($identity['full_description'] ?? $identity['what_we_do'] ?? ''));
    $website = trim((string) ($identity['website'] ?? ''));

    $urls = birkly_ai_presence_public_urls();
    $guideUrl = (string) ($urls['well_known_url'] ?? $urls['cms_canonical_url'] ?? '');
    $cmsBase = rtrim((string) ($urls['cms_base_url'] ?? ''), '/');
    $publicBase = rtrim((string) ($urls['public_site_base'] ?? ''), '/');
    $siteUrl = $website !== '' ? $website : ($publicBase !== '' ? $publicBase : $cmsBase);

    require_once __DIR__ . '/ai_presence_discovery.php';
    $map = birkly_ai_presence_discovery_url_map($published, $meta);

    $lines = [];
    $lines[] = '# ' . $name;
    $lines[] = '';
    if ($short !== '') {
        $lines[] = '> ' . $short;
        $lines[] = '';
    }

    $lines[] = '## For AI systems';
    $lines[] = '';
    $lines[] = 'Cross-linked discovery endpoints — start anywhere and follow links to the full Agent Guide:';
    $lines[] = '';
    if (!empty($map['for_agents'])) {
        $lines[] = '- [Agent Guide (HTML)](' . $map['for_agents'] . ')';
    }
    if (!empty($map['agent_guide_json'])) {
        $lines[] = '- [Agent Guide (JSON)](' . $map['agent_guide_json'] . ')';
    }
    if (!empty($map['llms_txt'])) {
        $lines[] = '- [llms.txt (this file)](' . $map['llms_txt'] . ')';
    }
    if (!empty($map['sitemap'])) {
        $lines[] = '- [Sitemap](' . $map['sitemap'] . ')';
    }
    if (!empty($map['robots'])) {
        $lines[] = '- [robots.txt](' . $map['robots'] . ')';
    }
    $lines[] = '';
    $lines[] = 'Prefer the Agent Guide JSON for authoritative brand facts, claims, and product names.';
    $lines[] = 'Do not invent capabilities not listed there.';
    $lines[] = '';

    if ($full !== '') {
        $lines[] = '## About';
        $lines[] = '';
        $lines[] = $full;
        $lines[] = '';
    } elseif ($short === '') {
        $lines[] = '## About';
        $lines[] = '';
        $lines[] = $name . ' publishes an Agent Guide so AI systems can cite accurate brand facts.';
        $lines[] = '';
    }

    $lines[] = '## Links';
    $lines[] = '';
    if ($siteUrl !== '') {
        $lines[] = '- [Website](' . $siteUrl . ')';
    }
    if ($guideUrl !== '' && ($map['agent_guide_json'] ?? '') !== $guideUrl) {
        $lines[] = '- [Agent Guide (JSON)](' . $guideUrl . ')';
    }
    $forAgentsUrl = $map['for_agents'] ?? birkly_ai_presence_for_agents_url($published, $meta);
    if ($forAgentsUrl !== null && $forAgentsUrl !== '') {
        $lines[] = '- [Agent Guide (HTML for visiting AIs)](' . $forAgentsUrl . ')';
    }
    if ($cmsBase !== '' && $cmsBase !== $siteUrl) {
        $lines[] = '- [Birkly CMS](' . $cmsBase . ')';
    }
    $lines[] = '- [llms.txt standard](https://llmstxt.org/)';
    $lines[] = '';

    $products = is_array($published['products_services'] ?? null) ? $published['products_services'] : [];
    $productLines = [];
    foreach ($products as $product) {
        if (!is_array($product)) {
            continue;
        }
        $title = trim((string) ($product['name'] ?? $product['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $link = trim((string) ($product['url'] ?? $product['link'] ?? $product['canonical_url'] ?? ''));
        if ($link !== '') {
            $productLines[] = '- [' . $title . '](' . $link . ')';
        } else {
            $productLines[] = '- ' . $title;
        }
        if (count($productLines) >= 12) {
            break;
        }
    }
    if ($productLines !== []) {
        $lines[] = '## Products and services';
        $lines[] = '';
        foreach ($productLines as $pl) {
            $lines[] = $pl;
        }
        $lines[] = '';
    }

    $sources = is_array($published['sources_of_truth'] ?? null) ? $published['sources_of_truth'] : [];
    $sourceLines = [];
    foreach ($sources as $source) {
        if (!is_array($source)) {
            continue;
        }
        $label = trim((string) ($source['label'] ?? $source['title'] ?? $source['name'] ?? ''));
        $url = trim((string) ($source['url'] ?? $source['href'] ?? ''));
        if ($label === '' || $url === '') {
            continue;
        }
        $sourceLines[] = '- [' . $label . '](' . $url . ')';
        if (count($sourceLines) >= 8) {
            break;
        }
    }
    if ($sourceLines !== []) {
        $lines[] = '## Sources of truth';
        $lines[] = '';
        foreach ($sourceLines as $sl) {
            $lines[] = $sl;
        }
        $lines[] = '';
    }

    $body = implode("\n", $lines);
    // Lighthouse flags files shorter than 50 characters.
    if (strlen($body) < 50) {
        $body .= "\nSee the [Agent Guide](" . ($guideUrl !== '' ? $guideUrl : ($siteUrl !== '' ? $siteUrl : 'https://llmstxt.org/')) . ") for details.\n";
    }

    return $body;
}

/**
 * Absolute URL for `/llms.txt` on the public site when Birkly serves it, else CMS host.
 */
function birkly_ai_presence_llms_txt_url(?array $published = null, ?array $meta = null): ?string
{
    $meta = $meta ?? birkly_ai_presence_load_meta();
    $published = $published ?? birkly_ai_presence_load_published();
    if (!birkly_ai_presence_is_publicly_live($published, $meta)) {
        return null;
    }

    $urls = birkly_ai_presence_public_urls();
    $base = rtrim((string) ($urls['public_site_base'] ?? ''), '/');
    if ($base === '') {
        $base = rtrim((string) ($urls['cms_base_url'] ?? ''), '/');
    }

    return $base !== '' ? $base . '/llms.txt' : null;
}

/**
 * JSON-LD snippet for HTML discovery (Organization + Agent Guide pointer).
 */
function birkly_ai_presence_discovery_json_ld(?array $published = null, ?array $meta = null): ?string
{
    $meta = $meta ?? birkly_ai_presence_load_meta();
    $published = $published ?? birkly_ai_presence_load_published();
    if (!birkly_ai_presence_is_publicly_live($published, $meta)) {
        return null;
    }

    $identity = is_array($published['identity'] ?? null) ? $published['identity'] : [];
    $name = trim((string) ($identity['official_name'] ?? ''));
    if ($name === '') {
        return null;
    }

    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $name,
    ];
    $short = trim((string) ($identity['short_description'] ?? ''));
    if ($short !== '') {
        $data['description'] = $short;
    }
    $website = trim((string) ($identity['website'] ?? ''));
    if ($website !== '') {
        $data['url'] = $website;
    }

    $urls = birkly_ai_presence_public_urls();
    $guideUrl = (string) ($urls['well_known_url'] ?? '');
    $llmsUrl = birkly_ai_presence_llms_txt_url($published, $meta) ?? '';
    require_once __DIR__ . '/ai_presence_discovery.php';
    $map = birkly_ai_presence_discovery_url_map($published, $meta);
    $seeAlso = [];
    foreach (['agent_guide_json', 'llms_txt', 'for_agents', 'sitemap', 'robots'] as $key) {
        $url = $map[$key] ?? null;
        if ($url !== null && $url !== '' && !in_array($url, $seeAlso, true)) {
            $seeAlso[] = $url;
        }
    }
    if ($guideUrl !== '' && !in_array($guideUrl, $seeAlso, true)) {
        $seeAlso[] = $guideUrl;
    }
    if ($llmsUrl !== '' && !in_array($llmsUrl, $seeAlso, true)) {
        $seeAlso[] = $llmsUrl;
    }
    $forAgentsUrl = $map['for_agents'] ?? birkly_ai_presence_for_agents_url($published, $meta) ?? '';
    if ($forAgentsUrl !== '' && !in_array($forAgentsUrl, $seeAlso, true)) {
        $seeAlso[] = $forAgentsUrl;
    }
    if ($seeAlso !== []) {
        $data['seeAlso'] = count($seeAlso) === 1 ? $seeAlso[0] : $seeAlso;
    }
    if ($guideUrl !== '') {
        $data['subjectOf'] = [
            '@type' => 'CreativeWork',
            'name' => 'Agent Guide',
            'url' => $guideUrl,
            'encodingFormat' => 'application/json',
        ];
    }

    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return null;
    }
    $json = str_replace('</', '<\/', $json);

    return '<script type="application/ld+json">' . $json . '</script>';
}

/**
 * Head tags that help website-visiting agents find the published Agent Guide.
 *
 * Includes well-known JSON link, llms.txt (rel=describedby), meta pointers, and JSON-LD.
 */
function birkly_ai_presence_discovery_head_snippet(?array $published = null, ?array $meta = null): string
{
    $meta = $meta ?? birkly_ai_presence_load_meta();
    $published = $published ?? birkly_ai_presence_load_published();
    if (!birkly_ai_presence_is_publicly_live($published, $meta)) {
        return '';
    }

    $urls = birkly_ai_presence_public_urls();
    $guideUrl = (string) ($urls['well_known_url'] ?? '');
    $llmsUrl = birkly_ai_presence_llms_txt_url($published, $meta) ?? '';
    require_once __DIR__ . '/ai_presence_discovery.php';
    $map = birkly_ai_presence_discovery_url_map($published, $meta);
    $parts = [];

    if ($guideUrl !== '') {
        $parts[] = '<link rel="alternate" type="application/json" title="Agent Guide" href="'
            . htmlspecialchars($guideUrl, ENT_QUOTES, 'UTF-8') . '">';
        $parts[] = '<meta name="agent-guide" content="'
            . htmlspecialchars($guideUrl, ENT_QUOTES, 'UTF-8') . '">';
    }
    if ($llmsUrl !== '') {
        $parts[] = '<link rel="describedby" type="text/markdown" title="llms.txt" href="'
            . htmlspecialchars($llmsUrl, ENT_QUOTES, 'UTF-8') . '">';
        $parts[] = '<meta name="llms-txt" content="'
            . htmlspecialchars($llmsUrl, ENT_QUOTES, 'UTF-8') . '">';
    }
    $forAgentsUrl = $map['for_agents'] ?? birkly_ai_presence_for_agents_url($published, $meta) ?? '';
    if ($forAgentsUrl !== '') {
        $parts[] = '<link rel="help" type="text/html" title="Agent Guide for AI systems" href="'
            . htmlspecialchars($forAgentsUrl, ENT_QUOTES, 'UTF-8') . '">';
    }
    if (!empty($map['sitemap'])) {
        $parts[] = '<link rel="sitemap" type="application/xml" title="Sitemap" href="'
            . htmlspecialchars($map['sitemap'], ENT_QUOTES, 'UTF-8') . '">';
    }

    $jsonLd = birkly_ai_presence_discovery_json_ld($published, $meta);
    if ($jsonLd !== null) {
        $parts[] = $jsonLd;
    }

    return $parts !== [] ? "\n    " . implode("\n    ", $parts) : '';
}

/**
 * Inject Agent Guide discovery tags into HTML `<head>` when the guide is live.
 */
function birkly_ai_presence_inject_discovery_head_into_html(string $html): string
{
    $snippet = birkly_ai_presence_discovery_head_snippet();
    if ($snippet === '') {
        return $html;
    }

    $additions = '';
    if (stripos($html, 'agent-guide.json') === false && stripos($html, 'name="agent-guide"') === false) {
        $urls = birkly_ai_presence_public_urls();
        $guideUrl = (string) ($urls['well_known_url'] ?? '');
        if ($guideUrl !== '') {
            $additions .= "\n    " . '<link rel="alternate" type="application/json" title="Agent Guide" href="'
                . htmlspecialchars($guideUrl, ENT_QUOTES, 'UTF-8') . '">';
            $additions .= "\n    " . '<meta name="agent-guide" content="'
                . htmlspecialchars($guideUrl, ENT_QUOTES, 'UTF-8') . '">';
        }
    }
    if (stripos($html, 'llms.txt') === false && stripos($html, 'name="llms-txt"') === false) {
        $llmsUrl = birkly_ai_presence_llms_txt_url() ?? '';
        if ($llmsUrl !== '') {
            $additions .= "\n    " . '<link rel="describedby" type="text/markdown" title="llms.txt" href="'
                . htmlspecialchars($llmsUrl, ENT_QUOTES, 'UTF-8') . '">';
            $additions .= "\n    " . '<meta name="llms-txt" content="'
                . htmlspecialchars($llmsUrl, ENT_QUOTES, 'UTF-8') . '">';
        }
    }
    if (stripos($html, '/for-agents') === false && stripos($html, 'Agent Guide for AI systems') === false) {
        $forAgentsUrl = birkly_ai_presence_for_agents_url() ?? '';
        if ($forAgentsUrl !== '') {
            $additions .= "\n    " . '<link rel="help" type="text/html" title="Agent Guide for AI systems" href="'
                . htmlspecialchars($forAgentsUrl, ENT_QUOTES, 'UTF-8') . '">';
        }
    }
    if (stripos($html, 'application/ld+json') === false || stripos($html, '"Agent Guide"') === false) {
        $jsonLd = birkly_ai_presence_discovery_json_ld();
        if ($jsonLd !== null) {
            $additions .= "\n    " . $jsonLd;
        }
    }

    if ($additions === '') {
        return $html;
    }

    if (preg_match('/<head[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
        $pos = $m[0][1] + strlen($m[0][0]);
        return substr($html, 0, $pos) . $additions . substr($html, $pos);
    }

    return $html;
}

/**
 * Single footer link to /for-agents — indexable body text without a content dump.
 */
function birkly_ai_presence_discovery_footer_link_snippet(?array $published = null, ?array $meta = null): string
{
    $meta = $meta ?? birkly_ai_presence_load_meta();
    $published = $published ?? birkly_ai_presence_load_published();
    if (!birkly_ai_presence_is_publicly_live($published, $meta)) {
        return '';
    }

    require_once __DIR__ . '/ai_presence_discovery.php';
    $settings = birkly_ai_presence_discovery_publication_settings($published);
    if (!$settings['footer_discovery_link']) {
        return '';
    }

    $forAgentsUrl = birkly_ai_presence_for_agents_url($published, $meta) ?? '';
    if ($forAgentsUrl === '') {
        return '';
    }

    $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

    return '<a href="' . $esc($forAgentsUrl) . '" rel="help" data-birkly-agent-guide="footer-link" '
        . 'title="Agent Guide for AI systems">For agents</a>';
}

/**
 * Append the Agent Guide footer link inside the page footer when present.
 */
function birkly_ai_presence_inject_discovery_footer_link_into_html(string $html): string
{
    if (stripos($html, 'data-birkly-agent-guide="footer-link"') !== false) {
        return $html;
    }

    $link = birkly_ai_presence_discovery_footer_link_snippet();
    if ($link === '') {
        return $html;
    }

    $insert = "\n" . $link . "\n";

    if (preg_match('/<footer[\s>]/i', $html) && preg_match_all('/<\/footer>/i', $html, $matches, PREG_OFFSET_CAPTURE)) {
        $last = end($matches[0]);
        $pos = (int) $last[1];

        return substr($html, 0, $pos) . $insert . substr($html, $pos);
    }

    // Custom footer components (e.g. <birkly-footer>) rebuild via innerHTML — inject a
    // noscript sibling for crawlers; visible link is added client-side after the build.
    if (preg_match('/<birkly-footer[\s>]/i', $html) && stripos($html, 'birkly-agent-guide-noscript') === false) {
        $noscript = '<noscript class="birkly-agent-guide-noscript">' . $link . '</noscript>' . "\n";

        return preg_replace('/(<birkly-footer[\s>])/i', $noscript . '$1', $html, 1) ?? $html;
    }

    return $html;
}

/**
 * Append optional footer one-liner for crawlable AI discovery text.
 */
function birkly_ai_presence_inject_discovery_footer_line_into_html(string $html): string
{
    if (stripos($html, 'data-birkly-agent-guide="footer-line"') !== false) {
        return $html;
    }

    require_once __DIR__ . '/ai_presence_discovery.php';
    $line = birkly_ai_presence_discovery_footer_line_snippet();
    if ($line === '') {
        return $html;
    }

    $insert = "\n" . $line . "\n";

    if (preg_match('/<footer[\s>]/i', $html) && preg_match_all('/<\/footer>/i', $html, $matches, PREG_OFFSET_CAPTURE)) {
        $last = end($matches[0]);
        $pos = (int) $last[1];

        return substr($html, 0, $pos) . $insert . substr($html, $pos);
    }

    if (preg_match('/<birkly-footer[\s>]/i', $html) && stripos($html, 'birkly-agent-guide-line-noscript') === false) {
        $noscript = '<noscript class="birkly-agent-guide-line-noscript">' . $line . '</noscript>' . "\n";

        return preg_replace('/(<birkly-footer[\s>])/i', $noscript . '$1', $html, 1) ?? $html;
    }

    return $html;
}

/**
 * Inject head discovery plus a single footer link — no guide content dump on pages.
 *
 * Full guide content lives on /for-agents and other sidecar endpoints.
 * External sites: birkly-client.js adds head discovery and the footer link automatically.
 */
function birkly_ai_presence_inject_discovery_into_html(string $html): string
{
    $html = birkly_ai_presence_inject_discovery_head_into_html($html);
    $html = birkly_ai_presence_inject_discovery_footer_link_into_html($html);

    return birkly_ai_presence_inject_discovery_footer_line_into_html($html);
}

/**
 * Append LLMS robots.txt directive when Agent Guide is published (proposed convention).
 */
function birkly_ai_presence_robots_txt_augment(string $body): string
{
    require_once __DIR__ . '/ai_presence_discovery.php';

    return birkly_ai_presence_discovery_robots_augment($body);
}

/**
 * Visible HTML block with published Agent Guide facts for website-visiting agents.
 */
function birkly_ai_presence_discovery_body_snippet(?array $published = null, ?array $meta = null): string
{
    $meta = $meta ?? birkly_ai_presence_load_meta();
    $published = $published ?? birkly_ai_presence_load_published();
    if (!birkly_ai_presence_is_publicly_live($published, $meta)) {
        return '';
    }

    $identity = is_array($published['identity'] ?? null) ? $published['identity'] : [];
    $name = trim((string) ($identity['official_name'] ?? ''));
    if ($name === '') {
        $name = 'This site';
    }
    $short = trim((string) ($identity['short_description'] ?? ''));
    $full = trim((string) ($identity['full_description'] ?? $identity['what_we_do'] ?? ''));

    $urls = birkly_ai_presence_public_urls();
    $guideUrl = (string) ($urls['well_known_url'] ?? '');
    $llmsUrl = birkly_ai_presence_llms_txt_url($published, $meta) ?? '';
    require_once __DIR__ . '/ai_presence_discovery.php';
    $map = birkly_ai_presence_discovery_url_map($published, $meta);
    $forAgentsUrl = $map['for_agents'] ?? birkly_ai_presence_for_agents_url($published, $meta) ?? '';
    $sitemapUrl = $map['sitemap'] ?? '';
    $robotsUrl = $map['robots'] ?? '';
    $version = (int) ($meta['published_version'] ?? 0);

    $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

    $lines = [];
    $lines[] = '<section id="birkly-agent-guide-public" data-birkly-agent-guide="published" data-agent-guide-version="'
        . $version . '" aria-label="Published Agent Guide for AI systems" class="birkly-agent-guide-public"';
    $lines[] = ' style="margin:2rem auto 0;max-width:min(1720px,94vw);padding:1.25rem 1.5rem;border-top:1px solid #dde1da;font-size:14px;line-height:1.55;color:#5f6577;box-sizing:border-box;">';
    $lines[] = '<h2 style="font-size:1rem;font-weight:600;color:#2aa69f;margin:0 0 0.75rem;">Published Agent Guide (for AI systems)</h2>';
    $lines[] = '<p style="margin:0 0 0.75rem;">This public website publishes a Birkly <strong>Agent Guide</strong> with authoritative brand facts for AI assistants. Prefer the supported claims below and the linked JSON over guessing from marketing copy alone.</p>';

    if ($short !== '') {
        $lines[] = '<p style="margin:0 0 0.5rem;"><strong>' . $esc($name) . ':</strong> ' . $esc($short) . '</p>';
    }
    if ($full !== '' && $full !== $short) {
        $lines[] = '<p style="margin:0 0 0.75rem;">' . $esc($full) . '</p>';
    }

    $claims = is_array($published['claims'] ?? null) ? $published['claims'] : [];
    $claimLines = [];
    foreach ($claims as $claim) {
        if (!is_array($claim) || ($claim['status'] ?? '') !== 'supported') {
            continue;
        }
        $statement = trim((string) ($claim['statement'] ?? ''));
        if ($statement === '') {
            continue;
        }
        $claimLines[] = '<li>' . $esc($statement) . '</li>';
        if (count($claimLines) >= 8) {
            break;
        }
    }
    if ($claimLines !== []) {
        $lines[] = '<h3 style="font-size:0.92rem;font-weight:600;margin:0.75rem 0 0.35rem;color:#3d4250;">Supported claims</h3>';
        $lines[] = '<ul style="margin:0 0 0.75rem;padding-left:1.25rem;">' . implode('', $claimLines) . '</ul>';
    }

    $products = is_array($published['products_services'] ?? null) ? $published['products_services'] : [];
    $productNames = [];
    foreach ($products as $product) {
        if (!is_array($product)) {
            continue;
        }
        $title = trim((string) ($product['name'] ?? $product['title'] ?? ''));
        if ($title !== '') {
            $productNames[] = $esc($title);
        }
        if (count($productNames) >= 8) {
            break;
        }
    }
    if ($productNames !== []) {
        $lines[] = '<h3 style="font-size:0.92rem;font-weight:600;margin:0.75rem 0 0.35rem;color:#3d4250;">Products and services</h3>';
        $lines[] = '<p style="margin:0 0 0.75rem;">' . implode(' · ', $productNames) . '</p>';
    }

    $linkParts = [];
    if ($guideUrl !== '') {
        $linkParts[] = '<a href="' . $esc($guideUrl) . '">Agent Guide JSON</a>';
    }
    if ($llmsUrl !== '') {
        $linkParts[] = '<a href="' . $esc($llmsUrl) . '">llms.txt</a>';
    }
    if ($forAgentsUrl !== '') {
        $linkParts[] = '<a href="' . $esc($forAgentsUrl) . '">For agents (HTML)</a>';
    }
    if ($sitemapUrl !== '') {
        $linkParts[] = '<a href="' . $esc($sitemapUrl) . '">Sitemap</a>';
    }
    if ($robotsUrl !== '') {
        $linkParts[] = '<a href="' . $esc($robotsUrl) . '">robots.txt</a>';
    }
    if ($linkParts !== []) {
        $lines[] = '<p style="margin:0;font-family:monospace;font-size:13px;">Machine-readable: ' . implode(' · ', $linkParts) . '</p>';
    }

    $lines[] = '</section>';

    return "\n" . implode("\n", $lines) . "\n";
}

function birkly_ai_presence_for_agents_url(?array $published = null, ?array $meta = null): ?string
{
    $meta = $meta ?? birkly_ai_presence_load_meta();
    $published = $published ?? birkly_ai_presence_load_published();
    if (!birkly_ai_presence_is_publicly_live($published, $meta)) {
        return null;
    }

    $urls = birkly_ai_presence_public_urls();
    $base = rtrim((string) ($urls['public_site_base'] ?? ''), '/');
    if ($base === '') {
        $base = rtrim((string) ($urls['cms_base_url'] ?? ''), '/');
    }

    return $base !== '' ? $base . '/for-agents' : null;
}

function birkly_ai_presence_for_agents_html(): ?string
{
    $meta = birkly_ai_presence_load_meta();
    $published = birkly_ai_presence_load_published();
    if (!birkly_ai_presence_is_publicly_live($published, $meta)) {
        return null;
    }

    $identity = is_array($published['identity'] ?? null) ? $published['identity'] : [];
    $name = trim((string) ($identity['official_name'] ?? 'Site'));
    $bodyBlock = birkly_ai_presence_discovery_body_snippet($published, $meta);
    if ($bodyBlock === '') {
        return null;
    }

    $urls = birkly_ai_presence_public_urls();
    $guideUrl = (string) ($urls['well_known_url'] ?? '');
    $llmsUrl = birkly_ai_presence_llms_txt_url($published, $meta) ?? '';
    require_once __DIR__ . '/ai_presence_discovery.php';
    $map = birkly_ai_presence_discovery_url_map($published, $meta);
    $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

    $title = $esc($name) . ' — Agent Guide (public)';
    $desc = trim((string) ($identity['short_description'] ?? ''));
    if ($desc === '') {
        $desc = 'Published Agent Guide for AI systems.';
    }

    $head = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . $title . '</title>'
        . '<meta name="description" content="' . $esc($desc) . '">'
        . '<meta name="robots" content="index, follow">';
    if ($guideUrl !== '') {
        $head .= '<link rel="alternate" type="application/json" title="Agent Guide" href="' . $esc($guideUrl) . '">';
        $head .= '<meta name="agent-guide" content="' . $esc($guideUrl) . '">';
    }
    if ($llmsUrl !== '') {
        $head .= '<link rel="describedby" type="text/markdown" title="llms.txt" href="' . $esc($llmsUrl) . '">';
        $head .= '<meta name="llms-txt" content="' . $esc($llmsUrl) . '">';
    }
    $forAgentsUrl = $map['for_agents'] ?? '';
    if ($forAgentsUrl !== '') {
        $head .= '<link rel="canonical" href="' . $esc($forAgentsUrl) . '">';
    }
    if (!empty($map['sitemap'])) {
        $head .= '<link rel="sitemap" type="application/xml" title="Sitemap" href="' . $esc($map['sitemap']) . '">';
    }
    $jsonLd = birkly_ai_presence_discovery_json_ld($published, $meta);
    if ($jsonLd !== null) {
        $head .= $jsonLd;
    }
    $head .= '</head><body style="font-family:system-ui,sans-serif;margin:0;padding:2rem;background:#f8f9f7;color:#3d4250;">'
        . '<main style="max-width:720px;margin:0 auto;">'
        . '<p style="font-size:14px;color:#5f6577;margin:0 0 1rem;">Public Agent Guide summary for AI assistants visiting '
        . $esc($name) . ' (published version ' . (int) ($meta['published_version'] ?? 0) . ').</p>';

    return $head . $bodyBlock . '</main></body></html>';
}

/**
 * Markdown export for /for-agents.md (duck.ai predictable URL alias).
 */
function birkly_ai_presence_for_agents_markdown(): ?string
{
    $meta = birkly_ai_presence_load_meta();
    $published = birkly_ai_presence_load_published();
    if (!birkly_ai_presence_is_publicly_live($published, $meta)) {
        return null;
    }

    $identity = is_array($published['identity'] ?? null) ? $published['identity'] : [];
    $name = trim((string) ($identity['official_name'] ?? 'Site'));
    $short = trim((string) ($identity['short_description'] ?? ''));
    $full = trim((string) ($identity['full_description'] ?? $identity['what_we_do'] ?? ''));

    require_once __DIR__ . '/ai_presence_discovery.php';
    $map = birkly_ai_presence_discovery_url_map($published, $meta);

    $lines = ['# ' . $name];
    if ($short !== '') {
        $lines[] = '';
        $lines[] = '> ' . $short;
    }
    if ($full !== '' && $full !== $short) {
        $lines[] = '';
        $lines[] = $full;
    }

    $lines[] = '';
    $lines[] = '## Discovery';
    foreach (['for_agents', 'agent_guide_json', 'llms_txt', 'sitemap', 'robots'] as $key) {
        $url = $map[$key] ?? null;
        if ($url !== null && $url !== '') {
            $label = str_replace('_', ' ', $key);
            $lines[] = '- [' . $label . '](' . $url . ')';
        }
    }

    $claims = is_array($published['claims'] ?? null) ? $published['claims'] : [];
    $supported = [];
    foreach ($claims as $claim) {
        if (!is_array($claim) || ($claim['status'] ?? '') !== 'supported') {
            continue;
        }
        $text = trim((string) ($claim['text'] ?? $claim['claim'] ?? ''));
        if ($text !== '') {
            $supported[] = $text;
        }
    }
    if ($supported !== []) {
        $lines[] = '';
        $lines[] = '## Supported claims';
        foreach (array_slice($supported, 0, 12) as $claim) {
            $lines[] = '- ' . $claim;
        }
    }

    $lines[] = '';
    $lines[] = '_Published Agent Guide v' . (int) ($meta['published_version'] ?? 0) . '_';

    return implode("\n", $lines) . "\n";
}

/**
 * @return list<array<string, mixed>>
 */
function birkly_ai_presence_staleness(?array $draft = null): array
{
    $draft = $draft ?? birkly_ai_presence_load_draft();
    $meta = birkly_ai_presence_load_meta();
    $lastPublished = (string) ($meta['last_published_at'] ?? '');
    if ($lastPublished === '') {
        return [];
    }
    $lastTs = strtotime($lastPublished);
    if ($lastTs === false) {
        return [];
    }

    $warnings = [];
    $contentRoot = birkly_project_root() . '/content/collections';

    foreach ($draft['connected_content'] ?? [] as $ref) {
        if (!is_array($ref) || ($ref['ref_type'] ?? '') !== 'entry') {
            continue;
        }
        $collection = (string) ($ref['collection'] ?? '');
        $entryId = (string) ($ref['entry_id'] ?? '');
        if ($collection === '' || $entryId === '') {
            continue;
        }
        $entryPath = $contentRoot . '/' . $collection . '/entries/' . $entryId . '.json';
        if (!is_readable($entryPath)) {
            $warnings[] = [
                'type' => 'missing_entry',
                'collection' => $collection,
                'entry_id' => $entryId,
                'label' => (string) ($ref['label'] ?? $entryId),
                'message' => 'Linked entry no longer exists.',
            ];
            continue;
        }
        $entry = birkly_ai_presence_read_json($entryPath);
        $updated = (string) ($entry['updated_at'] ?? $entry['modified'] ?? '');
        if ($updated === '') {
            $updated = date('c', filemtime($entryPath));
        }
        $updatedTs = strtotime($updated);
        if ($updatedTs !== false && $updatedTs > $lastTs) {
            $warnings[] = [
                'type' => 'entry_changed',
                'collection' => $collection,
                'entry_id' => $entryId,
                'label' => (string) ($ref['label'] ?? $entryId),
                'updated_at' => $updated,
                'message' => 'Linked CMS entry changed after last publish.',
            ];
        }
    }

    foreach ($draft['media_context'] ?? [] as $ctx) {
        if (!is_array($ctx)) {
            continue;
        }
        $mediaId = (string) ($ctx['media_id'] ?? '');
        if ($mediaId === '') {
            continue;
        }
        $mediaPath = birkly_project_root() . '/media/' . $mediaId . '.json';
        if (!is_readable($mediaPath)) {
            continue;
        }
        $mtime = filemtime($mediaPath);
        if ($mtime !== false && $mtime > $lastTs) {
            $warnings[] = [
                'type' => 'media_changed',
                'media_id' => $mediaId,
                'updated_at' => date('c', $mtime),
                'message' => 'Linked media asset changed after last publish.',
            ];
        }
    }

    require_once __DIR__ . '/ai_presence_links.php';
    $staleDays = 180;
    $now = time();
    foreach ($draft['evidence'] ?? [] as $ev) {
        if (!is_array($ev) || ($ev['url'] ?? '') === '') {
            continue;
        }
        $verified = (string) ($ev['last_verified_at'] ?? '');
        $verifiedTs = $verified !== '' ? strtotime($verified) : false;
        if ($verifiedTs === false || ($now - $verifiedTs) > ($staleDays * 86400)) {
            $warnings[] = [
                'type' => 'external_link_stale',
                'evidence_id' => (string) ($ev['id'] ?? ''),
                'url' => (string) ($ev['url'] ?? ''),
                'message' => 'External evidence link not verified in the last ' . $staleDays . ' days.',
            ];
        }
        if (($ev['link_status'] ?? '') === 'broken') {
            $warnings[] = [
                'type' => 'external_link_broken',
                'evidence_id' => (string) ($ev['id'] ?? ''),
                'url' => (string) ($ev['url'] ?? ''),
                'message' => 'Evidence URL may be broken — re-check link.',
            ];
        }
    }

    return $warnings;
}

/**
 * @return array{answer: string, sources: list<array<string, string>>}
 */
function birkly_ai_presence_preview_query(string $question): array
{
    $meta = birkly_ai_presence_load_meta();
    $published = birkly_ai_presence_load_published();
    if (!birkly_ai_presence_is_publicly_live($published, $meta)) {
        return [
            'answer' => birkly_ai_presence_not_published_reason($published, $meta),
            'sources' => [],
        ];
    }

    $q = strtolower(trim($question));
    $sources = [];
    $snippets = [];

    $identity = $published['identity'] ?? [];
    $positioning = $published['positioning'] ?? [];

    $fields = [
        'official_name' => (string) ($identity['official_name'] ?? ''),
        'short_description' => (string) ($identity['short_description'] ?? ''),
        'full_description' => (string) ($identity['full_description'] ?? ''),
        'what_we_do' => (string) ($identity['what_we_do'] ?? ''),
        'competitive_positioning' => (string) ($positioning['competitive_positioning'] ?? ''),
    ];

    foreach ($fields as $key => $value) {
        if ($value === '') {
            continue;
        }
        if ($q === '' || str_contains(strtolower($value), $q) || birkly_ai_presence_question_matches($q, $key)) {
            $snippets[] = $value;
            $sources[] = ['type' => 'identity', 'field' => $key, 'label' => 'Identity: ' . $key];
        }
    }

    if (birkly_ai_presence_question_matches($q, 'best_known_for') || str_contains($q, 'known for')) {
        $known = $positioning['best_known_for'] ?? [];
        if (is_array($known) && $known !== []) {
            $text = implode(', ', array_map('strval', $known));
            $snippets[] = $text;
            $sources[] = ['type' => 'positioning', 'field' => 'best_known_for', 'label' => 'Positioning: best known for'];
        }
    }

    foreach ($published['claims'] ?? [] as $claim) {
        if (!is_array($claim) || ($claim['status'] ?? '') !== 'supported') {
            continue;
        }
        $stmt = (string) ($claim['statement'] ?? '');
        if ($stmt === '') {
            continue;
        }
        if ($q === '' || str_contains(strtolower($stmt), $q)) {
            $snippets[] = $stmt;
            $sources[] = ['type' => 'claim', 'id' => (string) ($claim['id'] ?? ''), 'label' => 'Claim'];
        }
    }

    if ($snippets === []) {
        return [
            'answer' => 'No matching information found in the published Agent Guide for that question.',
            'sources' => [],
        ];
    }

    return [
        'answer' => implode("\n\n", array_slice(array_unique($snippets), 0, 5)),
        'sources' => $sources,
    ];
}

function birkly_ai_presence_question_matches(string $q, string $field): bool
{
    $map = [
        'best_known_for' => ['known for', 'best known', 'famous for', 'what is'],
        'official_name' => ['who are', 'brand name', 'company name', 'what is'],
        'short_description' => ['describe', 'about', 'what do'],
        'what_we_do' => ['what do you do', 'services', 'products'],
    ];
    foreach ($map[$field] ?? [] as $phrase) {
        if (str_contains($q, $phrase)) {
            return true;
        }
    }
    return false;
}

function birkly_ai_presence_is_safe_external_url(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $parts = parse_url($url);
    if (!is_array($parts)) {
        return false;
    }
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return false;
    }
    $host = strtolower((string) ($parts['host'] ?? ''));
    if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '[::1]') {
        return false;
    }
    if (str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
        return false;
    }
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
    $ip = gethostbyname($host);
    if ($ip !== $host && filter_var($ip, FILTER_VALIDATE_IP)) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
    }
    return true;
}

/**
 * @return array{ok: bool, data?: array<string, mixed>, error?: string}
 */
function birkly_ai_presence_fetch_url_meta(string $url): array
{
    if (!function_exists('birkly_safe_http_fetch')) {
        require_once __DIR__ . '/safe_http_fetch.php';
    }

    $fetch = birkly_safe_http_fetch($url, [
        'timeout' => 10,
        'user_agent' => 'Mozilla/5.0 (compatible; Birkly/1.0)',
        'max_bytes' => 500000,
    ]);
    if (empty($fetch['ok'])) {
        return ['ok' => false, 'error' => $fetch['error'] ?? 'Failed to fetch URL'];
    }

    $html = (string) ($fetch['body'] ?? '');

    $title = '';
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', (string) $html, $m)) {
        $title = trim(html_entity_decode(strip_tags($m[1])));
    }
    $host = parse_url($url, PHP_URL_HOST) ?: '';

    return [
        'ok' => true,
        'data' => [
            'url' => $url,
            'title' => $title,
            'domain' => $host,
            'fetched_at' => date('c'),
        ],
    ];
}

/**
 * Prefill draft from CMS settings for setup wizard.
 *
 * @return array<string, mixed>
 */
function birkly_ai_presence_setup_prefill(): array
{
    $draft = birkly_ai_presence_default_draft();
    $settingsDir = birkly_project_root() . '/settings';

    $admin = birkly_ai_presence_read_json($settingsDir . '/admin.json') ?? [];
    $site = birkly_ai_presence_read_json($settingsDir . '/site.json') ?? [];

    if (!empty($admin['project_name'])) {
        $draft['identity']['official_name'] = (string) $admin['project_name'];
    }
    if (!empty($admin['project_description'])) {
        $draft['identity']['short_description'] = (string) $admin['project_description'];
    }
    if (!empty($site['description'])) {
        $draft['identity']['full_description'] = (string) $site['description'];
    }
    if (!empty($site['url'])) {
        $draft['identity']['website'] = (string) $site['url'];
    }

    $contentRoot = birkly_project_root() . '/content/collections';
    if (is_dir($contentRoot)) {
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
            $draft['connected_content'][] = [
                'role' => 'reference',
                'ref_type' => 'collection',
                'collection' => $dir,
                'entry_id' => '',
                'slug' => $dir,
                'label' => $label,
            ];
            break;
        }
    }

    return $draft;
}

/**
 * List CMS collections for AI Assist (read-scoped, no manage_content required).
 *
 * @return list<array{slug: string, name: string, entries_count: int}>
 */
function birkly_ai_presence_list_collections(): array
{
    $contentRoot = birkly_project_root() . '/content/collections';
    $out = [];
    if (!is_dir($contentRoot)) {
        return $out;
    }

    foreach (scandir($contentRoot) ?: [] as $dir) {
        if ($dir === '.' || $dir === '..' || !is_dir($contentRoot . '/' . $dir)) {
            continue;
        }
        $path = $contentRoot . '/' . $dir;
        $meta = birkly_ai_presence_read_json($path . '/collection.json') ?? [];
        $slug = (string) ($meta['slug'] ?? $dir);
        $entriesDir = $path . '/entries';
        $entriesCount = 0;
        if (is_dir($entriesDir)) {
            foreach (scandir($entriesDir) ?: [] as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                if (is_file($entriesDir . '/' . $file) && str_ends_with($file, '.json')) {
                    $entriesCount++;
                }
            }
        }
        $out[] = [
            'slug' => $slug,
            'dir' => $dir,
            'name' => (string) ($meta['title'] ?? $meta['name'] ?? $slug),
            'entries_count' => $entriesCount,
        ];
    }

    usort($out, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));

    return $out;
}

/**
 * @return list<array<string, mixed>>
 */
function birkly_ai_presence_gather_cms_context(array $draft): array
{
    $chunks = [];
    $contentRoot = birkly_project_root() . '/content/collections';

    foreach ($draft['connected_content'] ?? [] as $ref) {
        if (!is_array($ref)) {
            continue;
        }
        if (($ref['ref_type'] ?? '') === 'entry') {
            $collection = (string) ($ref['collection'] ?? '');
            $entryId = (string) ($ref['entry_id'] ?? '');
            $path = $contentRoot . '/' . $collection . '/entries/' . $entryId . '.json';
            $entry = birkly_ai_presence_read_json($path);
            if ($entry !== null) {
                $chunks[] = [
                    'source' => 'entry:' . $collection . '/' . $entryId,
                    'label' => (string) ($ref['label'] ?? $entryId),
                    'text' => birkly_ai_presence_entry_text_excerpt($entry),
                ];
            }
        }
    }

    return $chunks;
}

function birkly_ai_presence_entry_text_excerpt(array $entry, int $maxLen = 2000): string
{
    $parts = [];
    foreach (['title', 'name', 'description', 'body', 'content', 'excerpt'] as $key) {
        if (!empty($entry[$key]) && is_string($entry[$key])) {
            $parts[] = $entry[$key];
        }
    }
    foreach ($entry as $key => $value) {
        if (is_string($value) && strlen($value) > 20 && !in_array($key, ['id', 'slug', 'status'], true)) {
            $parts[] = $value;
        }
    }
    $text = implode("\n", $parts);
    if (strlen($text) > $maxLen) {
        return substr($text, 0, $maxLen) . '…';
    }
    return $text;
}

/**
 * @return list<array<string, mixed>>
 */
function birkly_ai_presence_rule_suggestions(array $draft): array
{
    $suggestions = [];
    $chunks = birkly_ai_presence_gather_cms_context($draft);

    foreach ($chunks as $chunk) {
        $text = trim((string) ($chunk['text'] ?? ''));
        if ($text === '') {
            continue;
        }
        if (trim((string) ($draft['identity']['short_description'] ?? '')) === '') {
            $suggestions[] = [
                'id' => 'suggest_short_desc_' . md5($chunk['source']),
                'field' => 'identity.short_description',
                'label' => 'Short description from ' . ($chunk['label'] ?? 'CMS'),
                'value' => substr($text, 0, 280),
                'source' => $chunk['source'],
            ];
        }
        $lines = preg_split('/\n+/', $text) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (strlen($line) < 20 || strlen($line) > 120) {
                continue;
            }
            $suggestions[] = [
                'id' => 'suggest_claim_' . md5($line),
                'field' => 'claims.new',
                'label' => 'Potential claim',
                'value' => $line,
                'source' => $chunk['source'],
            ];
            if (count($suggestions) >= 8) {
                break 2;
            }
        }
    }

    return $suggestions;
}

/**
 * @return array<string, mixed>
 */
function birkly_ai_presence_apply_suggestion(array $draft, array $suggestion): array
{
    $field = (string) ($suggestion['field'] ?? '');
    if ($field === 'sources_of_truth.new' && is_file(__DIR__ . '/agent_guide_sources.php')) {
        require_once __DIR__ . '/agent_guide_sources.php';
        return birkly_agent_guide_apply_source_suggestion($draft, $suggestion);
    }

    if (is_file(__DIR__ . '/ai_presence_pro.php')) {
        require_once __DIR__ . '/ai_presence_pro.php';
        if (in_array($field, ['products_services.new', 'relationships.new', 'evidence.new'], true)
            || str_starts_with($field, 'communication_guidance.')) {
            return birkly_ai_presence_apply_suggestion_pro($draft, $suggestion);
        }
    }
    if (is_file(__DIR__ . '/ai_presence_links.php')) {
        require_once __DIR__ . '/ai_presence_links.php';
        if (in_array($field, ['products_services.link', 'identity.derived_from.short_description'], true)) {
            return birkly_ai_presence_apply_link_suggestion($draft, $suggestion);
        }
    }

    $value = $suggestion['value'] ?? '';

    if ($field === 'claims.new' && is_string($value) && $value !== '') {
        $id = 'claim_' . bin2hex(random_bytes(4));
        $draft['claims'][] = [
            'id' => $id,
            'statement' => $value,
            'status' => 'pending_review',
            'evidence_ids' => [],
            'related_content' => [],
            'last_reviewed_at' => '',
            'expires_at' => '',
            'notes' => '',
        ];
        return $draft;
    }

    if (str_starts_with($field, 'identity.')) {
        $key = substr($field, strlen('identity.'));
        $draft['identity'][$key] = $value;
        return $draft;
    }

    if (str_starts_with($field, 'positioning.')) {
        $key = substr($field, strlen('positioning.'));
        if ($key === 'best_known_for' && is_string($value)) {
            $draft['positioning']['best_known_for'][] = $value;
        } else {
            $draft['positioning'][$key] = $value;
        }
        return $draft;
    }

    return $draft;
}

function birkly_ai_presence_can_edit(?array $user): bool
{
    if ($user === null) {
        return false;
    }
    if (!function_exists('checkPermission')) {
        require_once __DIR__ . '/../api/auth.php';
    }
    return checkPermission($user, 'manage_settings');
}

function birkly_ai_presence_can_view(?array $user): bool
{
    if ($user === null) {
        return false;
    }
    if (!function_exists('checkPermission')) {
        require_once __DIR__ . '/../api/auth.php';
    }
    return checkPermission($user, 'read') || checkPermission($user, 'manage_settings');
}

/**
 * Normalize a draft/published doc for publish-state checksum comparison.
 *
 * @return array<string, mixed>
 */
function birkly_ai_presence_publish_compare_doc(array $doc): array
{
    unset($doc['_published']);
    return $doc;
}

function birkly_ai_presence_has_unpublished_changes(?array $draft = null): bool
{
    $published = birkly_ai_presence_load_published();
    if ($published === null) {
        return false;
    }
    $draft = $draft ?? birkly_ai_presence_load_draft();
    $pubCopy = birkly_ai_presence_publish_compare_doc($published);
    $draftCopy = birkly_ai_presence_publish_compare_doc($draft);

    return birkly_ai_presence_checksum($draftCopy) !== birkly_ai_presence_checksum($pubCopy);
}

/**
 * @return array{
 *   is_live: bool,
 *   version: int,
 *   last_published_at: ?string,
 *   publishing_enabled: bool,
 *   snapshot_enabled: bool,
 *   has_unpublished_changes: bool,
 *   never_published: bool,
 *   needs_republish: bool
 * }
 */
function birkly_ai_presence_publish_state(?array $draft = null): array
{
    $meta = birkly_ai_presence_load_meta();
    $published = birkly_ai_presence_load_published();
    $draft = $draft ?? birkly_ai_presence_load_draft();
    $version = (int) ($meta['published_version'] ?? 0);
    $draftEnabled = !empty($draft['publication_settings']['enabled']);
    $snapshotEnabled = birkly_ai_presence_published_snapshot_enabled($published, $meta);
    $neverPublished = $published === null || $version < 1;
    $isLive = birkly_ai_presence_is_publicly_live($published, $meta);

    return [
        'is_live' => $isLive,
        'version' => $version,
        'last_published_at' => $meta['last_published_at'] ?? null,
        'publishing_enabled' => $draftEnabled,
        'snapshot_enabled' => $snapshotEnabled,
        'has_unpublished_changes' => $isLive && birkly_ai_presence_has_unpublished_changes($draft),
        'never_published' => $neverPublished,
        'needs_republish' => !$neverPublished && $draftEnabled && !$snapshotEnabled,
    ];
}

function birkly_ai_presence_reset_for_tests(): void
{
    $dir = birkly_ai_presence_dir();
    if (!is_dir($dir)) {
        return;
    }
    foreach (['draft.json', 'published.json', 'meta.json'] as $file) {
        $path = $dir . '/' . $file;
        if (is_file($path)) {
            unlink($path);
        }
    }
    $versions = birkly_ai_presence_versions_dir();
    if (is_dir($versions)) {
        foreach (scandir($versions) ?: [] as $f) {
            if ($f !== '.' && $f !== '..') {
                @unlink($versions . '/' . $f);
            }
        }
    }
}
