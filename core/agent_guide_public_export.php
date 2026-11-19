<?php
/**
 * Agent Guide public text exports — shared llms.txt, /for-agents, /for-agents.md rendering.
 *
 * Map/summary modes preserve legacy short outputs when publication_settings omit detail keys.
 * Full modes emit the complete published mental model for AI readers (plain Markdown/HTML only).
 */

declare(strict_types=1);

require_once __DIR__ . '/ai_presence.php';
require_once __DIR__ . '/ai_presence_discovery.php';
require_once __DIR__ . '/agent_guide_sources.php';

const BIRKLY_AGENT_GUIDE_EXPORT_LLMS_TXT_WARN_BYTES = 262144;
const BIRKLY_AGENT_GUIDE_EXPORT_LLMS_FULL_DEFAULT_BYTES = 2097152;
const BIRKLY_AGENT_GUIDE_EXPORT_LLMS_FULL_SNAPSHOT = 'llms-full.snapshot.md';
const BIRKLY_AGENT_GUIDE_EXPORT_LLMS_FULL_META = 'llms_full_meta.json';

/**
 * @return array{
 *   llms_txt_detail: 'map'|'full',
 *   for_agents_detail: 'summary'|'full',
 *   llms_full_txt_enabled: bool,
 *   llms_full_max_bytes: int,
 *   llms_txt_max_bytes: int,
 *   llms_full_allowed_hosts: list<string>
 * }
 */
function birkly_agent_guide_public_export_output_settings(?array $published): array
{
    $pub = is_array($published['publication_settings'] ?? null) ? $published['publication_settings'] : [];
    $llmsDetail = (string) ($pub['llms_txt_detail'] ?? 'map');
    if (!in_array($llmsDetail, ['map', 'full'], true)) {
        $llmsDetail = 'map';
    }
    $forAgentsDetail = (string) ($pub['for_agents_detail'] ?? 'summary');
    if (!in_array($forAgentsDetail, ['summary', 'full'], true)) {
        $forAgentsDetail = 'summary';
    }
    $maxFull = (int) ($pub['llms_full_max_bytes'] ?? BIRKLY_AGENT_GUIDE_EXPORT_LLMS_FULL_DEFAULT_BYTES);
    if ($maxFull < 65536) {
        $maxFull = BIRKLY_AGENT_GUIDE_EXPORT_LLMS_FULL_DEFAULT_BYTES;
    }
    $maxLlms = (int) ($pub['llms_txt_max_bytes'] ?? BIRKLY_AGENT_GUIDE_EXPORT_LLMS_TXT_WARN_BYTES);
    if ($maxLlms < 1024) {
        $maxLlms = BIRKLY_AGENT_GUIDE_EXPORT_LLMS_TXT_WARN_BYTES;
    }
    $hosts = $pub['llms_full_allowed_hosts'] ?? [];
    if (!is_array($hosts)) {
        $hosts = [];
    }
    $hosts = array_values(array_filter(array_map(static fn ($h) => strtolower(trim((string) $h)), $hosts)));

    return [
        'llms_txt_detail' => $llmsDetail,
        'for_agents_detail' => $forAgentsDetail,
        'llms_full_txt_enabled' => !empty($pub['llms_full_txt_enabled']),
        'llms_full_max_bytes' => $maxFull,
        'llms_txt_max_bytes' => $maxLlms,
        'llms_full_allowed_hosts' => $hosts,
    ];
}

/**
 * @param array<string, mixed> $doc
 * @return array<string, mixed>
 */
function birkly_agent_guide_public_export_apply_redactions(array $doc): array
{
    $redact = $doc['publication_settings']['redact_public_fields'] ?? [];
    if (!is_array($redact) || $redact === []) {
        return $doc;
    }
    foreach ($redact as $path) {
        if (!is_string($path) || $path === '') {
            continue;
        }
        birkly_agent_guide_public_export_unset_dot_path($doc, $path);
    }

    return $doc;
}

/**
 * @param array<string, mixed> $doc
 */
function birkly_agent_guide_public_export_unset_dot_path(array &$doc, string $path): void
{
    $parts = explode('.', $path);
    $cur = &$doc;
    $last = array_pop($parts);
    foreach ($parts as $segment) {
        if (!is_array($cur) || !array_key_exists($segment, $cur)) {
            return;
        }
        $cur = &$cur[$segment];
    }
    if (is_array($cur) && array_key_exists($last, $cur)) {
        unset($cur[$last]);
    }
}

/**
 * Legacy short llms.txt (unchanged output shape).
 */
function birkly_agent_guide_public_export_llms_txt_map(?array $published, ?array $meta): ?string
{
    if ($published === null || !birkly_ai_presence_is_publicly_live($published, $meta)) {
        return null;
    }

    $published = birkly_agent_guide_public_export_apply_redactions($published);

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
    if (!empty($map['for_agents_md'])) {
        $lines[] = '- [Agent Guide (Markdown)](' . $map['for_agents_md'] . ')';
    }
    if (!empty($map['agent_manifest'])) {
        $lines[] = '- [Agent Guide discovery manifest](' . $map['agent_manifest'] . ')';
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
    $lines[] = 'If your client rejects `application/json`, fetch the HTML or Markdown Agent Guide instead.';
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
    if (strlen($body) < 50) {
        $body .= "\nSee the [Agent Guide](" . ($guideUrl !== '' ? $guideUrl : ($siteUrl !== '' ? $siteUrl : 'https://llmstxt.org/')) . ") for details.\n";
    }

    return $body;
}

/**
 * @param array<string, string|null> $map
 * @return list<string>
 */
function birkly_agent_guide_public_export_discovery_link_lines(array $map, bool $includeLlmsFull = false): array
{
    $lines = [];
    $lines[] = '## Discovery links';
    $lines[] = '';
    $keys = [
        'for_agents' => 'Agent Guide (HTML)',
        'for_agents_md' => 'Agent Guide (Markdown)',
        'for_agents_json' => 'Agent Guide (JSON shortcut)',
        'agent_guide_json' => 'Agent Guide (canonical JSON)',
        'agent_manifest' => 'Discovery manifest',
        'llms_txt' => 'llms.txt',
        'llms_full_txt' => 'llms-full.txt',
        'sitemap' => 'Sitemap',
        'robots' => 'robots.txt',
    ];
    foreach ($keys as $key => $label) {
        if ($key === 'llms_full_txt' && !$includeLlmsFull) {
            continue;
        }
        $url = $map[$key] ?? null;
        if ($url === null || $url === '') {
            continue;
        }
        $lines[] = '- [' . $label . '](' . $url . ')';
    }
    $lines[] = '';

    return $lines;
}

/**
 * @param list<array<string, mixed>> $sources
 * @return list<array<string, mixed>>
 */
function birkly_agent_guide_public_export_sorted_sources(array $sources): array
{
    $out = [];
    foreach ($sources as $source) {
        if (!is_array($source)) {
            continue;
        }
        $out[] = birkly_agent_guide_normalize_source_of_truth($source);
    }
    usort($out, static function (array $a, array $b): int {
        $pa = (int) ($a['priority'] ?? 50);
        $pb = (int) ($b['priority'] ?? 50);
        if ($pa !== $pb) {
            return $pb <=> $pa;
        }

        return strcmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
    });

    return $out;
}

/**
 * @param array<string, mixed> $published
 * @return list<string>
 */
function birkly_agent_guide_public_export_full_markdown_lines(
    array $published,
    ?array $meta,
    bool $includeDiscoveryHeader = true
): array {
    $published = birkly_agent_guide_public_export_apply_redactions($published);
    $identity = is_array($published['identity'] ?? null) ? $published['identity'] : [];
    $name = trim((string) ($identity['official_name'] ?? ''));
    if ($name === '') {
        $name = 'Site';
    }
    $short = trim((string) ($identity['short_description'] ?? ''));
    $full = trim((string) ($identity['full_description'] ?? $identity['what_we_do'] ?? ''));

    $map = birkly_ai_presence_discovery_url_map($published, $meta);
    $settings = birkly_agent_guide_public_export_output_settings($published);
    $map['llms_full_txt'] = $settings['llms_full_txt_enabled']
        ? birkly_agent_guide_public_export_llms_full_txt_url($published, $meta)
        : null;

    $lines = ['# ' . $name, ''];
    if ($short !== '') {
        $lines[] = '> ' . $short;
        $lines[] = '';
    }
    if ($full !== '' && $full !== $short) {
        $lines[] = $full;
        $lines[] = '';
    }

    $architecture = trim((string) ($identity['architecture'] ?? ''));
    if ($architecture !== '') {
        $lines[] = '## How it works';
        $lines[] = '';
        $lines[] = $architecture;
        $lines[] = '';
    }

    $mcpSupport = trim((string) ($identity['mcp_support'] ?? ''));
    if ($mcpSupport !== '') {
        $lines[] = '## AI and MCP';
        $lines[] = '';
        $lines[] = $mcpSupport;
        $lines[] = '';
    }

    $products = is_array($published['products_services'] ?? null) ? $published['products_services'] : [];
    if ($products !== []) {
        $lines[] = '## Products and services';
        $lines[] = '';
        foreach ($products as $product) {
            if (!is_array($product)) {
                continue;
            }
            $title = trim((string) ($product['name'] ?? $product['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $price = trim((string) ($product['price_display'] ?? ''));
            $desc = trim((string) ($product['short_description'] ?? $product['description'] ?? ''));
            $link = birkly_agent_guide_public_export_product_canonical_url($product);
            if ($link !== '') {
                $line = '- [' . $title . '](' . $link . ')';
            } else {
                $line = '- ' . $title;
            }
            if ($price !== '') {
                $line .= ' — ' . $price;
            }
            if ($desc !== '') {
                $line .= ': ' . $desc;
            }
            $lines[] = $line;
        }
        $lines[] = '';
    }

    $claims = is_array($published['claims'] ?? null) ? $published['claims'] : [];
    $evidence = is_array($published['evidence'] ?? null) ? $published['evidence'] : [];
    $evidenceById = [];
    foreach ($evidence as $ev) {
        if (!is_array($ev)) {
            continue;
        }
        $eid = (string) ($ev['id'] ?? '');
        if ($eid !== '') {
            $evidenceById[$eid] = $ev;
        }
    }
    $claimLines = [];
    foreach ($claims as $claim) {
        if (!is_array($claim) || ($claim['status'] ?? '') !== 'supported') {
            continue;
        }
        $statement = trim((string) ($claim['statement'] ?? $claim['text'] ?? $claim['claim'] ?? ''));
        if ($statement === '') {
            continue;
        }
        $evLinks = [];
        foreach ($claim['evidence_ids'] ?? [] as $eid) {
            $eid = (string) $eid;
            if ($eid === '' || !isset($evidenceById[$eid])) {
                continue;
            }
            $ev = $evidenceById[$eid];
            $evUrl = trim((string) ($ev['url'] ?? ''));
            $evTitle = trim((string) ($ev['title'] ?? $ev['label'] ?? 'Evidence'));
            if ($evUrl !== '') {
                $evLinks[] = '[' . $evTitle . '](' . $evUrl . ')';
            }
        }
        $line = '- ' . $statement;
        if ($evLinks !== []) {
            $line .= ' — evidence: ' . implode(', ', $evLinks);
        }
        $claimLines[] = $line;
    }
    if ($claimLines !== []) {
        $lines[] = '## Supported claims';
        $lines[] = '';
        foreach ($claimLines as $cl) {
            $lines[] = $cl;
        }
        $lines[] = '';
    }

    $sources = birkly_agent_guide_public_export_sorted_sources(
        is_array($published['sources_of_truth'] ?? null) ? $published['sources_of_truth'] : []
    );
    if ($sources !== []) {
        $lines[] = '## Sources of truth';
        $lines[] = '';
        foreach ($sources as $source) {
            $label = trim((string) ($source['label'] ?? ''));
            $url = trim((string) ($source['url'] ?? ''));
            if ($label === '' || $url === '') {
                continue;
            }
            $notes = trim((string) ($source['notes'] ?? ''));
            $line = '- [' . $label . '](' . $url . ')';
            if ($notes !== '') {
                $line .= ': ' . $notes;
            }
            $lines[] = $line;
        }
        $lines[] = '';
    }

    $comm = is_array($published['communication_guidance'] ?? null) ? $published['communication_guidance'] : [];
    $commBlock = birkly_agent_guide_public_export_communication_markdown($comm);
    if ($commBlock !== []) {
        $lines[] = '## Communication guidance';
        $lines[] = '';
        foreach ($commBlock as $row) {
            $lines[] = $row;
        }
        $lines[] = '';
    }

    $relationships = is_array($published['relationships'] ?? null) ? $published['relationships'] : [];
    $relLines = [];
    foreach ($relationships as $rel) {
        if (!is_array($rel)) {
            continue;
        }
        $line = birkly_agent_guide_public_export_relationship_markdown_line($rel);
        if ($line !== '') {
            $relLines[] = $line;
        }
    }
    if ($relLines !== []) {
        $lines[] = '## Related';
        $lines[] = '';
        foreach ($relLines as $rl) {
            $lines[] = $rl;
        }
        $lines[] = '';
    }

    if ($includeDiscoveryHeader) {
        $lines = array_merge($lines, birkly_agent_guide_public_export_discovery_link_lines($map, $settings['llms_full_txt_enabled']));
    }

    $version = (int) ($meta['published_version'] ?? 0);
    $lines[] = '_Published Agent Guide v' . $version . '_';
    $lines[] = '';

    return $lines;
}

/**
 * @param array<string, mixed> $product
 */
function birkly_agent_guide_public_export_product_canonical_url(array $product): string
{
    $direct = trim((string) ($product['url'] ?? $product['link'] ?? $product['canonical_url'] ?? ''));
    if ($direct !== '') {
        return $direct;
    }
    foreach ($product['sources'] ?? [] as $source) {
        if (!is_array($source)) {
            continue;
        }
        if (($source['role'] ?? '') === 'canonical_detail') {
            $url = trim((string) ($source['url'] ?? ''));
            if ($url !== '') {
                return $url;
            }
        }
    }
    foreach ($product['sources'] ?? [] as $source) {
        if (!is_array($source)) {
            continue;
        }
        $url = trim((string) ($source['url'] ?? ''));
        if ($url !== '') {
            return $url;
        }
    }

    return '';
}

/**
 * @param array<string, mixed> $comm
 * @return list<string>
 */
function birkly_agent_guide_public_export_communication_markdown(array $comm): array
{
    $rows = [];
    foreach ($comm['preferred_terms'] ?? [] as $term) {
        if (is_string($term)) {
            $rows[] = '- Preferred: ' . $term;
            continue;
        }
        if (!is_array($term)) {
            continue;
        }
        $t = trim((string) ($term['term'] ?? ''));
        $m = trim((string) ($term['meaning'] ?? ''));
        if ($t === '') {
            continue;
        }
        $rows[] = $m !== '' ? '- Preferred **' . $t . '**: ' . $m : '- Preferred: ' . $t;
    }
    foreach ($comm['avoided_terms'] ?? [] as $term) {
        if (is_string($term)) {
            $rows[] = '- Avoid: ' . $term;
            continue;
        }
        if (!is_array($term)) {
            continue;
        }
        $t = trim((string) ($term['term'] ?? ''));
        $r = trim((string) ($term['reason'] ?? ''));
        if ($t === '') {
            continue;
        }
        $rows[] = $r !== '' ? '- Avoid **' . $t . '**: ' . $r : '- Avoid: ' . $t;
    }
    foreach ($comm['required_qualifications'] ?? [] as $qual) {
        if (!is_string($qual)) {
            continue;
        }
        $qual = trim($qual);
        if ($qual !== '') {
            $rows[] = '- Required qualification: ' . $qual;
        }
    }
    $custom = trim((string) ($comm['custom_guidance'] ?? ''));
    if ($custom !== '') {
        $rows[] = '';
        $rows[] = $custom;
    }

    return $rows;
}

function birkly_agent_guide_public_export_relationship_markdown_line(array $rel): string
{
    $name = trim((string) ($rel['name'] ?? ''));
    if ($name === '') {
        return '';
    }
    $url = trim((string) ($rel['url'] ?? ''));
    $type = trim((string) ($rel['type'] ?? ''));
    $description = trim((string) ($rel['description'] ?? ''));
    $typeLabel = $type !== '' ? ucwords(str_replace('_', ' ', strtolower($type))) : '';

    if ($url !== '') {
        $line = '- [' . $name . '](' . $url . ')';
    } else {
        $line = '- ' . $name;
    }
    if ($typeLabel !== '') {
        $line .= ' (' . $typeLabel . ')';
    }
    if ($description !== '') {
        $line .= ': ' . $description;
    }

    return $line;
}

function birkly_agent_guide_public_export_truncate_text_bytes(string $text, int $maxBytes, string $ellipsis = '…'): string
{
    if ($maxBytes <= 0 || strlen($text) <= $maxBytes) {
        return $text;
    }
    $cut = $maxBytes - strlen($ellipsis);
    if ($cut < 1) {
        return $ellipsis;
    }

    return rtrim(substr($text, 0, $cut)) . $ellipsis;
}

function birkly_agent_guide_public_export_normalize_plaintext(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
    $text = preg_replace("/\n[ \t]+/", "\n", $text) ?? $text;
    $text = preg_replace("/[ \t]{2,}/", ' ', $text) ?? $text;
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

    return trim($text);
}

function birkly_agent_guide_public_export_llms_txt_full(?array $published, ?array $meta): ?string
{
    if ($published === null || !birkly_ai_presence_is_publicly_live($published, $meta)) {
        return null;
    }
    $settings = birkly_agent_guide_public_export_output_settings($published);
    $lines = birkly_agent_guide_public_export_full_markdown_lines($published, $meta, true);
    $body = implode("\n", $lines);
    if (strlen($body) < 50) {
        $map = birkly_ai_presence_discovery_url_map($published, $meta);
        $fallback = $map['agent_guide_json'] ?? 'https://llmstxt.org/';
        $body .= "\nSee the [Agent Guide](" . $fallback . ") for details.\n";
    }
    if (strlen($body) > $settings['llms_txt_max_bytes']) {
        $body = birkly_agent_guide_public_export_truncate_text_bytes($body, $settings['llms_txt_max_bytes']);
    }

    return $body;
}

/**
 * @return list<string>
 */
function birkly_agent_guide_public_export_for_agents_summary_lines(
    array $published,
    ?array $meta
): array {
    $published = birkly_agent_guide_public_export_apply_redactions($published);
    $identity = is_array($published['identity'] ?? null) ? $published['identity'] : [];
    $name = trim((string) ($identity['official_name'] ?? 'Site'));
    $short = trim((string) ($identity['short_description'] ?? ''));
    $full = trim((string) ($identity['full_description'] ?? $identity['what_we_do'] ?? ''));

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
        $text = trim((string) ($claim['statement'] ?? $claim['text'] ?? $claim['claim'] ?? ''));
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

    $version = (int) ($meta['published_version'] ?? 0);
    $lines[] = '';
    $lines[] = '_Published Agent Guide v' . $version . '_';
    $lines[] = '';

    return $lines;
}

function birkly_agent_guide_public_export_for_agents_markdown(?array $published, ?array $meta): ?string
{
    if ($published === null || !birkly_ai_presence_is_publicly_live($published, $meta)) {
        return null;
    }
    $settings = birkly_agent_guide_public_export_output_settings($published);
    $lines = $settings['for_agents_detail'] === 'full'
        ? birkly_agent_guide_public_export_full_markdown_lines($published, $meta, true)
        : birkly_agent_guide_public_export_for_agents_summary_lines($published, $meta);

    return implode("\n", $lines);
}

function birkly_agent_guide_public_export_llms_full_txt_url(?array $published, ?array $meta): ?string
{
    $settings = birkly_agent_guide_public_export_output_settings($published ?? []);
    if (!$settings['llms_full_txt_enabled']) {
        return null;
    }
    if (!birkly_ai_presence_is_publicly_live($published, $meta)) {
        return null;
    }
    $base = birkly_ai_presence_discovery_public_base();
    if ($base === '') {
        return null;
    }

    return $base . '/llms-full.txt';
}

function birkly_agent_guide_public_export_output_metrics(?array $published, ?array $meta): array
{
    if ($published === null || !birkly_ai_presence_is_publicly_live($published, $meta)) {
        return [];
    }
    $settings = birkly_agent_guide_public_export_output_settings($published);
    $llms = $settings['llms_txt_detail'] === 'full'
        ? birkly_agent_guide_public_export_llms_txt_full($published, $meta)
        : birkly_agent_guide_public_export_llms_txt_map($published, $meta);
    $md = birkly_agent_guide_public_export_for_agents_markdown($published, $meta);
    $llmsBytes = is_string($llms) ? strlen($llms) : 0;
    $mdBytes = is_string($md) ? strlen($md) : 0;
    $fullMeta = birkly_agent_guide_public_export_load_llms_full_meta();

    return [
        'llms_txt_detail' => $settings['llms_txt_detail'],
        'for_agents_detail' => $settings['for_agents_detail'],
        'llms_txt_bytes' => $llmsBytes,
        'for_agents_md_bytes' => $mdBytes,
        'llms_txt_over_cap' => $llmsBytes > $settings['llms_txt_max_bytes'],
        'llms_full_enabled' => $settings['llms_full_txt_enabled'],
        'llms_full_bytes' => (int) ($fullMeta['bytes'] ?? 0),
        'llms_full_skipped' => $fullMeta['skipped'] ?? [],
    ];
}

/**
 * @return array{bytes:int,skipped:list<string>,built_at?:string}
 */
function birkly_agent_guide_public_export_load_llms_full_meta(): array
{
    $path = birkly_ai_presence_dir() . '/' . BIRKLY_AGENT_GUIDE_EXPORT_LLMS_FULL_META;
    $data = birkly_ai_presence_read_json($path);

    return is_array($data) ? $data : ['bytes' => 0, 'skipped' => []];
}

function birkly_agent_guide_public_export_llms_full_body(): ?string
{
    $meta = birkly_ai_presence_load_meta();
    $published = birkly_ai_presence_load_published();
    $settings = birkly_agent_guide_public_export_output_settings($published ?? []);
    if (!$settings['llms_full_txt_enabled'] || !birkly_ai_presence_is_publicly_live($published, $meta)) {
        return null;
    }
    $path = birkly_ai_presence_dir() . '/' . BIRKLY_AGENT_GUIDE_EXPORT_LLMS_FULL_SNAPSHOT;
    if (!is_readable($path)) {
        return null;
    }
    $body = file_get_contents($path);

    return is_string($body) && $body !== '' ? $body : null;
}

/**
 * Build llms-full snapshot at publish time (optional).
 *
 * @return array{ok:bool,bytes?:int,skipped?:list<string>,errors?:list<string>}
 */
function birkly_agent_guide_public_export_build_llms_full_snapshot(array $snapshot, ?array $meta = null): array
{
    $settings = birkly_agent_guide_public_export_output_settings($snapshot);
    if (!$settings['llms_full_txt_enabled']) {
        return ['ok' => true, 'bytes' => 0, 'skipped' => []];
    }

    $meta = $meta ?? birkly_ai_presence_load_meta();
    $base = birkly_agent_guide_public_export_full_markdown_lines($snapshot, $meta, true);
    $body = implode("\n", $base);
    $skipped = [];
    $maxBytes = $settings['llms_full_max_bytes'];

    require_once __DIR__ . '/safe_http_fetch.php';
    $publicBase = birkly_ai_presence_discovery_public_base();
    $publicHost = $publicBase !== '' ? strtolower((string) parse_url($publicBase, PHP_URL_HOST)) : '';
    $allowed = array_merge([$publicHost], $settings['llms_full_allowed_hosts']);
    $allowed = array_values(array_unique(array_filter($allowed)));

    $sources = birkly_agent_guide_public_export_sorted_sources(
        is_array($snapshot['sources_of_truth'] ?? null) ? $snapshot['sources_of_truth'] : []
    );

    $body .= "\n## Referenced page text (publish snapshot)\n\n";

    foreach ($sources as $source) {
        if (strlen($body) >= $maxBytes) {
            $skipped[] = (string) ($source['url'] ?? 'cap reached');
            continue;
        }
        $url = trim((string) ($source['url'] ?? ''));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            continue;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || !in_array($host, $allowed, true)) {
            continue;
        }
        $fetch = birkly_safe_http_fetch($url, ['max_bytes' => 262144]);
        if (!is_array($fetch) || empty($fetch['ok']) || !is_string($fetch['body'] ?? null)) {
            $skipped[] = $url;
            continue;
        }
        $text = birkly_agent_guide_public_export_html_page_text((string) $fetch['body']);
        if ($text === '') {
            $skipped[] = $url;
            continue;
        }
        $section = '### ' . trim((string) ($source['label'] ?? $host)) . "\n\n"
            . 'Source: ' . $url . "\n\n" . $text . "\n\n";
        if (strlen($body) + strlen($section) > $maxBytes) {
            $skipped[] = $url . ' (over cap)';
            continue;
        }
        $body .= $section;
    }

    $dir = birkly_ai_presence_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $body = birkly_agent_guide_public_export_normalize_plaintext($body);
    file_put_contents($dir . '/' . BIRKLY_AGENT_GUIDE_EXPORT_LLMS_FULL_SNAPSHOT, $body);
    $metaOut = [
        'bytes' => strlen($body),
        'skipped' => $skipped,
        'built_at' => date('c'),
    ];
    birkly_ai_presence_write_json($dir . '/' . BIRKLY_AGENT_GUIDE_EXPORT_LLMS_FULL_META, $metaOut);

    return ['ok' => true, 'bytes' => $metaOut['bytes'], 'skipped' => $skipped];
}

function birkly_agent_guide_public_export_html_strip_boilerplate(string $html): string
{
    $patterns = [
        '/<noscript\b[^>]*>[\s\S]*?<\/noscript>/i',
        '/<script\b[^>]*>[\s\S]*?<\/script>/i',
        '/<style\b[^>]*>[\s\S]*?<\/style>/i',
        '/<template\b[^>]*>[\s\S]*?<\/template>/i',
        '/<svg\b[^>]*>[\s\S]*?<\/svg>/i',
    ];
    foreach ($patterns as $pattern) {
        $html = preg_replace($pattern, '', $html) ?? $html;
    }

    return $html;
}

function birkly_agent_guide_public_export_html_page_text(string $html): string
{
    $html = birkly_agent_guide_public_export_html_strip_boilerplate($html);
    if (trim($html) === '') {
        return '';
    }

    if (class_exists(DOMDocument::class)) {
        $dom = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $wrapped = '<?xml encoding="UTF-8"><!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'
            . $html . '</body></html>';
        $dom->loadHTML($wrapped, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xpath = new DOMXPath($dom);
        foreach (['nav', 'header', 'footer', 'script', 'style', 'template', 'svg', 'noscript'] as $tag) {
            $nodes = $xpath->query('//' . $tag);
            if ($nodes === false) {
                continue;
            }
            $remove = [];
            foreach ($nodes as $node) {
                $remove[] = $node;
            }
            foreach ($remove as $node) {
                if ($node->parentNode !== null) {
                    $node->parentNode->removeChild($node);
                }
            }
        }
        $hidden = $xpath->query('//*[@aria-hidden="true" or contains(@class,"birkly-agent-guide")]');
        if ($hidden !== false) {
            $remove = [];
            foreach ($hidden as $node) {
                $remove[] = $node;
            }
            foreach ($remove as $node) {
                if ($node->parentNode !== null) {
                    $node->parentNode->removeChild($node);
                }
            }
        }
        $discovery = $xpath->query('//*[@data-birkly-agent-guide]');
        if ($discovery !== false) {
            $remove = [];
            foreach ($discovery as $node) {
                $remove[] = $node;
            }
            foreach ($remove as $node) {
                if ($node->parentNode !== null) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        $root = $xpath->query('//main')->item(0)
            ?? $xpath->query('//article')->item(0)
            ?? $dom->getElementsByTagName('body')->item(0);

        if ($root !== null) {
            $text = birkly_agent_guide_public_export_dom_collect_text($root);
            return birkly_agent_guide_public_export_normalize_plaintext($text);
        }
    }

    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return birkly_agent_guide_public_export_normalize_plaintext($text);
}

function birkly_agent_guide_public_export_dom_collect_text(DOMNode $node): string
{
    if ($node->nodeType === XML_TEXT_NODE) {
        return $node->textContent ?? '';
    }
    if ($node->nodeType !== XML_ELEMENT_NODE) {
        return '';
    }

    $name = strtolower($node->nodeName);
    if ($name === 'br') {
        return "\n";
    }
    if ($name === 'pre' || $name === 'code') {
        $inner = '';
        foreach ($node->childNodes as $child) {
            $inner .= birkly_agent_guide_public_export_dom_collect_text($child);
        }

        return $inner . "\n\n";
    }

    $block = in_array($name, [
        'p', 'div', 'section', 'article', 'main', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'tr', 'table', 'blockquote', 'hr',
    ], true);

    $out = '';
    foreach ($node->childNodes as $child) {
        $out .= birkly_agent_guide_public_export_dom_collect_text($child);
    }
    if ($block && $out !== '') {
        return rtrim($out) . "\n\n";
    }

    return $out;
}

function birkly_agent_guide_public_export_markdown_list_item_to_html(string $line, callable $esc): string
{
    $trim = trim($line);
    if (!str_starts_with($trim, '- ')) {
        return '<p>' . $esc($trim) . '</p>';
    }
    $content = substr($trim, 2);
    if (preg_match('/^\[([^\]]+)\]\(([^)]+)\)(.*)$/u', $content, $m)) {
        $tail = $m[3];
        return '<p><a href="' . $esc($m[2]) . '">' . $esc($m[1]) . '</a>' . $esc($tail) . '</p>';
    }

    return '<p>' . $esc($content) . '</p>';
}

/**
 * HTML body for /for-agents (full or summary layout).
 */
function birkly_agent_guide_public_export_for_agents_html_body(?array $published, ?array $meta): string
{
    if ($published === null) {
        return '';
    }
    $settings = birkly_agent_guide_public_export_output_settings($published);
    $lines = $settings['for_agents_detail'] === 'full'
        ? birkly_agent_guide_public_export_full_markdown_lines($published, $meta, false)
        : [];

    if ($settings['for_agents_detail'] !== 'full') {
        return '';
    }

    $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $html = ['<article class="birkly-agent-guide-public birkly-agent-guide-public--full">'];
    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '') {
            continue;
        }
        if (str_starts_with($trim, '# ')) {
            $html[] = '<h1>' . $esc(substr($trim, 2)) . '</h1>';
            continue;
        }
        if (str_starts_with($trim, '## ')) {
            $html[] = '<h2>' . $esc(substr($trim, 3)) . '</h2>';
            continue;
        }
        if (str_starts_with($trim, '> ')) {
            $html[] = '<blockquote><p>' . $esc(substr($trim, 2)) . '</p></blockquote>';
            continue;
        }
        if (str_starts_with($trim, '- ')) {
            $html[] = birkly_agent_guide_public_export_markdown_list_item_to_html($trim, $esc);
            continue;
        }
        if (str_starts_with($trim, '_') && str_ends_with($trim, '_')) {
            $html[] = '<p><em>' . $esc(trim($trim, '_')) . '</em></p>';
            continue;
        }
        $html[] = '<p>' . $esc($trim) . '</p>';
    }
    $html[] = '</article>';

    return implode("\n", $html);
}