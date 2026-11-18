<?php
/**
 * Unified JSON-LD graph for SEO + Agent Guide discovery (detail pages, Organization merge).
 */

declare(strict_types=1);

require_once __DIR__ . '/site_seo.php';
require_once __DIR__ . '/ai_presence.php';
require_once __DIR__ . '/ai_presence_discovery.php';
require_once __DIR__ . '/fieldtype_template_shelf.php';

/**
 * When SEO auto-injects Organization / WebSite JSON-LD, Agent Guide skips its duplicate Organization block.
 */
function birkly_site_seo_json_ld_defer_agent_guide_organization(): bool
{
    if (!birkly_site_seo_should_auto_inject()) {
        return false;
    }

    return birkly_site_seo_surface_enabled('json_ld_organization')
        || birkly_site_seo_surface_enabled('json_ld_website');
}

/**
 * Stable Organization @id for the public site.
 */
function birkly_site_seo_organization_id(string $siteBase): string
{
    $siteBase = rtrim(trim($siteBase), '/');
    if ($siteBase === '') {
        return '#organization';
    }

    return $siteBase . '/#organization';
}

/**
 * @return list<string>
 */
function birkly_site_seo_identity_same_as(?array $published = null): array
{
    $published = $published ?? (function_exists('birkly_ai_presence_load_published') ? birkly_ai_presence_load_published() : null);
    if (!is_array($published)) {
        return [];
    }
    $identity = is_array($published['identity'] ?? null) ? $published['identity'] : [];
    $sameAs = [];
    foreach ($identity['social_profiles'] ?? [] as $profile) {
        if (!is_array($profile)) {
            continue;
        }
        $url = trim((string) ($profile['url'] ?? ''));
        if ($url !== '' && !in_array($url, $sameAs, true)) {
            $sameAs[] = $url;
        }
    }

    return $sameAs;
}

/**
 * Agent Guide discovery URLs for Organization seeAlso (no duplicate Organization node).
 *
 * @return list<string>
 */
function birkly_site_seo_agent_guide_see_also(?array $published = null, ?array $meta = null): array
{
    if (!function_exists('birkly_ai_presence_is_publicly_live')) {
        return [];
    }
    $meta = $meta ?? birkly_ai_presence_load_meta();
    $published = $published ?? birkly_ai_presence_load_published();
    if (!birkly_ai_presence_is_publicly_live($published, $meta)) {
        return [];
    }

    $urls = birkly_ai_presence_public_urls();
    $guideUrl = (string) ($urls['well_known_url'] ?? '');
    $llmsUrl = birkly_ai_presence_llms_txt_url($published, $meta) ?? '';
    $map = birkly_ai_presence_discovery_url_map($published, $meta);
    $seeAlso = [];
    foreach (['agent_guide_json', 'agent_manifest', 'for_agents', 'for_agents_md', 'for_agents_json', 'agent_guide_shortcut', 'llms_txt', 'sitemap', 'robots'] as $key) {
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

    return $seeAlso;
}

/**
 * @return array<string, mixed>|null
 */
function birkly_site_seo_agent_guide_subject_of(?array $published = null, ?array $meta = null): ?array
{
    if (!function_exists('birkly_ai_presence_is_publicly_live')) {
        return null;
    }
    $meta = $meta ?? birkly_ai_presence_load_meta();
    $published = $published ?? birkly_ai_presence_load_published();
    if (!birkly_ai_presence_is_publicly_live($published, $meta)) {
        return null;
    }
    $urls = birkly_ai_presence_public_urls();
    $guideUrl = trim((string) ($urls['well_known_url'] ?? ''));
    if ($guideUrl === '') {
        return null;
    }

    return [
        '@type' => 'CreativeWork',
        'name' => 'Agent Guide',
        'url' => $guideUrl,
        'encodingFormat' => 'application/json',
    ];
}

/**
 * Build a single JSON-LD script for WebSite, Organization, and optional Article/BlogPosting.
 *
 * @param array<string, mixed> $context
 * @param array{title:string,description:string,canonical:string,og_image:string,site_name:string,site_description:string} $meta
 */
function birkly_site_seo_build_json_ld_script(array $context, array $meta, array $surfaces): ?string
{
    $emitWebsite = !empty($surfaces['json_ld_website']);
    $emitOrganization = !empty($surfaces['json_ld_organization']);
    $emitArticle = !empty($surfaces['json_ld_article']) && !empty($context['entry_title']);
    if (!$emitWebsite && !$emitOrganization && !$emitArticle) {
        return null;
    }

    $siteBase = '';
    if (function_exists('birkly_hosting_public_base_url')) {
        require_once __DIR__ . '/hosting_urls.php';
        $siteBase = rtrim(trim((string) birkly_hosting_public_base_url()), '/');
    }
    if ($siteBase === '' && $meta['canonical'] !== '') {
        $parts = parse_url($meta['canonical']);
        if (is_array($parts) && !empty($parts['scheme']) && !empty($parts['host'])) {
            $siteBase = $parts['scheme'] . '://' . $parts['host'];
        }
    }

    $orgId = birkly_site_seo_organization_id($siteBase);
    $siteDescription = trim((string) ($meta['site_description'] ?? ''));
    $logoUrl = trim((string) ($meta['default_og_image'] ?? $meta['og_image'] ?? ''));
    if ($logoUrl !== '' && $meta['canonical'] !== '') {
        $logoUrl = birkly_seo_absolute_public_url($logoUrl, $meta['canonical']);
    }

    $published = function_exists('birkly_ai_presence_load_published') ? birkly_ai_presence_load_published() : null;
    $agMeta = function_exists('birkly_ai_presence_load_meta') ? birkly_ai_presence_load_meta() : null;

    $graph = [];

    if ($emitOrganization) {
        $org = [
            '@type' => 'Organization',
            '@id' => $orgId,
        ];
        if ($meta['site_name'] !== '') {
            $org['name'] = $meta['site_name'];
        }
        if ($siteDescription !== '') {
            $org['description'] = $siteDescription;
        }
        if ($siteBase !== '') {
            $org['url'] = $siteBase;
        } elseif ($meta['canonical'] !== '') {
            $org['url'] = $meta['canonical'];
        }
        if ($logoUrl !== '') {
            $org['logo'] = [
                '@type' => 'ImageObject',
                'url' => $logoUrl,
            ];
        }
        $sameAs = birkly_site_seo_identity_same_as(is_array($published) ? $published : null);
        if ($sameAs !== []) {
            $org['sameAs'] = count($sameAs) === 1 ? $sameAs[0] : $sameAs;
        }
        $seeAlso = birkly_site_seo_agent_guide_see_also(
            is_array($published) ? $published : null,
            is_array($agMeta) ? $agMeta : null
        );
        if ($seeAlso !== []) {
            $org['seeAlso'] = count($seeAlso) === 1 ? $seeAlso[0] : $seeAlso;
        }
        $subjectOf = birkly_site_seo_agent_guide_subject_of(
            is_array($published) ? $published : null,
            is_array($agMeta) ? $agMeta : null
        );
        if ($subjectOf !== null) {
            $org['subjectOf'] = $subjectOf;
        }
        if (count($org) > 2) {
            $graph[] = $org;
        }
    }

    if ($emitWebsite) {
        $site = [
            '@type' => 'WebSite',
        ];
        if ($meta['site_name'] !== '') {
            $site['name'] = $meta['site_name'];
        }
        if ($siteDescription !== '') {
            $site['description'] = $siteDescription;
        }
        if ($siteBase !== '') {
            $site['url'] = $siteBase;
        } elseif ($meta['canonical'] !== '') {
            $site['url'] = $meta['canonical'];
        }
        if ($emitOrganization) {
            $site['publisher'] = ['@id' => $orgId];
        }
        if (count($site) > 1) {
            $graph[] = $site;
        }
    }

    if ($emitArticle) {
        $articleType = trim((string) ($context['schema_type'] ?? ''));
        if ($articleType === '') {
            $collection = trim((string) ($context['collection_slug'] ?? ''));
            $articleType = $collection === 'blog' ? 'BlogPosting' : 'Article';
        }
        $article = [
            '@type' => $articleType,
            'headline' => (string) $context['entry_title'],
        ];
        if ($meta['description'] !== '') {
            $article['description'] = $meta['description'];
        }
        if ($meta['canonical'] !== '') {
            $article['url'] = $meta['canonical'];
            $article['mainEntityOfPage'] = [
                '@type' => 'WebPage',
                '@id' => $meta['canonical'],
            ];
        }
        $datePublished = trim((string) ($context['entry_date_published'] ?? ''));
        if ($datePublished !== '') {
            $article['datePublished'] = $datePublished;
        }
        $dateModified = trim((string) ($context['entry_date_modified'] ?? ''));
        if ($dateModified !== '') {
            $article['dateModified'] = $dateModified;
        }
        $entryImage = trim((string) ($context['entry_image_url'] ?? ''));
        if ($entryImage === '' && $meta['og_image'] !== '') {
            $entryImage = birkly_seo_absolute_public_url($meta['og_image'], $meta['canonical']);
        }
        if ($entryImage !== '') {
            $article['image'] = [$entryImage];
        }
        $authorName = trim((string) ($context['entry_author'] ?? ''));
        if ($authorName !== '') {
            $article['author'] = [
                '@type' => 'Person',
                'name' => $authorName,
            ];
        }
        if ($emitOrganization) {
            $article['publisher'] = ['@id' => $orgId];
        } elseif ($meta['site_name'] !== '') {
            $article['publisher'] = [
                '@type' => 'Organization',
                'name' => $meta['site_name'],
            ];
        }
        $graph[] = $article;
    }

    if ($graph === []) {
        return null;
    }

    $payload = count($graph) === 1
        ? array_merge(['@context' => 'https://schema.org'], $graph[0])
        : [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return null;
    }
    $json = str_replace('</', '<\/', $json);

    return '<script type="application/ld+json">' . $json . '</script>';
}