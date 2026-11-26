<?php
/**
 * RSS/Atom feeds for collections bound in the SEO pack.
 */

declare(strict_types=1);

require_once __DIR__ . '/site_seo.php';
require_once __DIR__ . '/site_seo_url_map.php';
require_once __DIR__ . '/entry_storage.php';

/**
 * @return list<array{collection:string,path:string,title:string,description:string}>
 */
function birkly_seo_feed_definitions(): array
{
    if (!birkly_site_seo_is_published()) {
        return [];
    }
    $config = birkly_seo_normalize_url_map_sections(birkly_site_seo_load());
    $feeds = [];
    $seenPaths = [];
    foreach ($config['detail_bindings'] as $binding) {
        if (!is_array($binding) || empty($binding['feed_enabled'])) {
            continue;
        }
        $collection = (string) ($binding['collection'] ?? '');
        if ($collection === '') {
            continue;
        }
        $path = trim((string) ($binding['feed_path'] ?? ''));
        if ($path === '') {
            $path = '/feed/' . rawurlencode($collection) . '.xml';
        }
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        if (isset($seenPaths[$path])) {
            continue;
        }
        $seenPaths[$path] = true;
        $title = trim((string) ($binding['feed_title'] ?? ''));
        if ($title === '') {
            $title = ucfirst(str_replace(['-', '_'], ' ', $collection));
        }
        $feeds[] = [
            'collection' => $collection,
            'path' => $path,
            'title' => $title,
            'description' => trim((string) ($config['defaults']['description'] ?? '')),
        ];
    }
    return $feeds;
}

/**
 * @return list<string> HTML link tags for <head>
 */
function birkly_seo_feed_alternate_link_tags(): array
{
    if (!birkly_site_seo_surface_enabled('rss')) {
        return [];
    }
    require_once __DIR__ . '/hosting_urls.php';
    $base = rtrim(birkly_hosting_public_base_url(), '/');
    if ($base === '') {
        return [];
    }
    $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $tags = [];
    foreach (birkly_seo_feed_definitions() as $feed) {
        $href = $base . $feed['path'];
        $tags[] = '<link rel="alternate" type="application/rss+xml" title="' . $esc($feed['title'])
            . '" href="' . $esc($href) . '">';
    }
    return $tags;
}

function birkly_seo_feed_should_serve(string $uri): bool
{
    $path = birkly_seo_normalize_path(parse_url($uri, PHP_URL_PATH) ?: $uri);
    foreach (birkly_seo_feed_definitions() as $feed) {
        if ($path === $feed['path']) {
            return true;
        }
    }
    return $path === '/feed.xml' && birkly_seo_feed_definitions() !== [];
}

/**
 * Resolve feed definition for a request path.
 *
 * @return array{collection:string,path:string,title:string,description:string}|null
 */
function birkly_seo_feed_for_path(string $uri): ?array
{
    $path = birkly_seo_normalize_path(parse_url($uri, PHP_URL_PATH) ?: $uri);
    $defs = birkly_seo_feed_definitions();
    if ($defs === []) {
        return null;
    }
    if ($path === '/feed.xml') {
        return $defs[0];
    }
    foreach ($defs as $feed) {
        if ($feed['path'] === $path) {
            return $feed;
        }
    }
    return null;
}

function birkly_seo_feed_rss_xml(?array $feedDef = null, int $limit = 25): string
{
    $feedDef ??= birkly_seo_feed_definitions()[0] ?? null;
    if ($feedDef === null) {
        return '';
    }
    require_once __DIR__ . '/hosting_urls.php';
    $base = rtrim(birkly_hosting_public_base_url(), '/');
    $config = birkly_seo_normalize_url_map_sections(birkly_site_seo_load());
    $collection = $feedDef['collection'];
    $binding = null;
    foreach ($config['detail_bindings'] as $b) {
        if (is_array($b) && ($b['collection'] ?? '') === $collection) {
            $binding = $b;
            break;
        }
    }
    $excerptField = is_array($binding)
        ? trim((string) ($binding['excerpt_field'] ?? 'excerpt'))
        : 'excerpt';

    $items = [];
    $records = birkly_entry_list_records($collection);
    foreach ($records as $record) {
        if (!is_array($record) || ($record['status'] ?? '') !== 'published') {
            continue;
        }
        $slug = trim((string) ($record['slug'] ?? $record['id'] ?? ''));
        if ($slug === '') {
            continue;
        }
        $template = is_array($binding) ? (string) ($binding['template_path'] ?? '') : '';
        $link = $template !== ''
            ? birkly_seo_detail_public_url($template, $collection, $slug, $binding)
            : $base . '/';
        $title = trim((string) ($record['title'] ?? $slug));
        $desc = birkly_seo_entry_field($record, $excerptField);
        $pub = trim((string) ($record['published_at'] ?? $record['created_at'] ?? ''));
        $items[] = [
            'title' => $title,
            'link' => $link,
            'description' => $desc,
            'pubDate' => $pub,
            'guid' => $link,
        ];
        if (count($items) >= $limit) {
            break;
        }
    }

    $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $channelLink = $base !== '' ? $base . ($feedDef['path'] ?? '/feed.xml') : ($feedDef['path'] ?? '/feed.xml');
    $lastBuild = date('r');
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
    $xml .= '<channel>' . "\n";
    $xml .= '<title>' . $esc($feedDef['title']) . '</title>' . "\n";
    $xml .= '<link>' . $esc($channelLink) . '</link>' . "\n";
    $xml .= '<description>' . $esc($feedDef['description']) . '</description>' . "\n";
    $xml .= '<lastBuildDate>' . $esc($lastBuild) . '</lastBuildDate>' . "\n";
    $xml .= '<atom:link href="' . $esc($channelLink) . '" rel="self" type="application/rss+xml"/>' . "\n";
    foreach ($items as $item) {
        $xml .= '<item>' . "\n";
        $xml .= '<title>' . $esc($item['title']) . '</title>' . "\n";
        $xml .= '<link>' . $esc($item['link']) . '</link>' . "\n";
        $xml .= '<guid isPermaLink="true">' . $esc($item['guid']) . '</guid>' . "\n";
        if ($item['description'] !== '') {
            $xml .= '<description>' . $esc($item['description']) . '</description>' . "\n";
        }
        if ($item['pubDate'] !== '') {
            $ts = strtotime($item['pubDate']);
            $xml .= '<pubDate>' . $esc($ts !== false ? date('r', $ts) : $item['pubDate']) . '</pubDate>' . "\n";
        }
        $xml .= '</item>' . "\n";
    }
    $xml .= '</channel></rss>';

    return $xml;
}