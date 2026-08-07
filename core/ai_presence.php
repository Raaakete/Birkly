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
    return birkly_ai_presence_normalize_draft(array_replace_recursive($current, $partial));
}

function birkly_ai_presence_load_published(): ?array
{
    $path = birkly_ai_presence_dir() . '/published.json';
    $data = birkly_ai_presence_read_json($path);
    if ($data === null) {
        return null;
    }
    return birkly_ai_presence_normalize_draft($data);
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
    if (empty($draft['publication_settings']['enabled'])) {
        $draft['publication_settings']['enabled'] = true;
        birkly_ai_presence_save_draft($draft);
    }

    $errors = birkly_ai_presence_validate($draft);
    if ($errors !== []) {
        return ['ok' => false, 'errors' => $errors];
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

    return ['ok' => true];
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
    $published = birkly_ai_presence_load_published();
    if ($published === null) {
        return null;
    }
    if (empty($published['publication_settings']['enabled'])) {
        return null;
    }
    $meta = birkly_ai_presence_load_meta();
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
    $published = birkly_ai_presence_load_published();
    if ($published === null || empty($published['publication_settings']['enabled'])) {
        return [
            'answer' => 'No published Agent Guide is available yet. Publish your guide first.',
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
    if (!birkly_ai_presence_is_safe_external_url($url)) {
        return ['ok' => false, 'error' => 'URL is not allowed'];
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'error' => 'Failed to initialize request'];
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; Birkly/1.0)',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    ]);
    $html = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($html === false || $httpCode >= 400) {
        return ['ok' => false, 'error' => 'Failed to fetch URL'];
    }

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
 *   has_unpublished_changes: bool,
 *   never_published: bool
 * }
 */
function birkly_ai_presence_publish_state(?array $draft = null): array
{
    $meta = birkly_ai_presence_load_meta();
    $published = birkly_ai_presence_load_published();
    $draft = $draft ?? birkly_ai_presence_load_draft();
    $version = (int) ($meta['published_version'] ?? 0);
    $enabled = !empty($draft['publication_settings']['enabled']);
    $neverPublished = $published === null || $version < 1;
    $isLive = !$neverPublished && $enabled;

    return [
        'is_live' => $isLive,
        'version' => $version,
        'last_published_at' => $meta['last_published_at'] ?? null,
        'publishing_enabled' => $enabled,
        'has_unpublished_changes' => $isLive && birkly_ai_presence_has_unpublished_changes($draft),
        'never_published' => $neverPublished,
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
