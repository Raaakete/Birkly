<?php
/**
 * Agent Guide discovery for search/chat AIs — sitemap, robots, IndexNow, status.
 * P66 — search-path + verify & monitor.
 */

declare(strict_types=1);

require_once __DIR__ . '/ai_presence.php';
require_once __DIR__ . '/hosting_urls.php';
require_once __DIR__ . '/project_site.php';
require_once __DIR__ . '/project_custom_404.php';

/** @return list<string> */
function birkly_ai_presence_discovery_ai_crawler_agents(): array
{
    return [
        'GPTBot',
        'OAI-SearchBot',
        'ChatGPT-User',
        'PerplexityBot',
        'ClaudeBot',
        'anthropic-ai',
        'Google-Extended',
        'Applebot-Extended',
        'DuckDuckBot',
    ];
}

/**
 * @return array<string, mixed>
 */
function birkly_ai_presence_discovery_publication_settings(?array $published = null): array
{
    $published = $published ?? birkly_ai_presence_load_published();
    $pub = is_array($published['publication_settings'] ?? null) ? $published['publication_settings'] : [];

    return [
        'footer_discovery_link' => !array_key_exists('footer_discovery_link', $pub) || !empty($pub['footer_discovery_link']),
        'footer_discovery_line' => !empty($pub['footer_discovery_line']),
        'footer_discovery_line_text' => trim((string) ($pub['footer_discovery_line_text'] ?? '')),
        'discovery_sitemap' => !array_key_exists('discovery_sitemap', $pub) || !empty($pub['discovery_sitemap']),
        'discovery_indexnow' => !array_key_exists('discovery_indexnow', $pub) || !empty($pub['discovery_indexnow']),
        'discovery_ai_crawlers' => !array_key_exists('discovery_ai_crawlers', $pub) || !empty($pub['discovery_ai_crawlers']),
    ];
}

function birkly_ai_presence_discovery_public_base(): string
{
    $urls = birkly_ai_presence_public_urls();
    $base = rtrim((string) ($urls['public_site_base'] ?? ''), '/');
    if ($base === '') {
        $base = rtrim((string) ($urls['cms_base_url'] ?? ''), '/');
    }

    return $base;
}

/**
 * Canonical discovery endpoint URLs for cross-linking (llms.txt, robots, sitemap, etc.).
 *
 * @return array{
 *   for_agents:?string,
 *   for_agents_md:?string,
 *   for_agents_json:?string,
 *   agent_guide_json:?string,
 *   agent_guide_shortcut:?string,
 *   agent_manifest:?string,
 *   llms_txt:?string,
 *   sitemap:?string,
 *   robots:?string
 * }
 */
function birkly_ai_presence_discovery_url_map(?array $published = null, ?array $meta = null): array
{
    $meta = $meta ?? birkly_ai_presence_load_meta();
    $published = $published ?? birkly_ai_presence_load_published();
    if (!birkly_ai_presence_is_publicly_live($published, $meta)) {
        return [
            'for_agents' => null,
            'for_agents_md' => null,
            'for_agents_json' => null,
            'agent_guide_json' => null,
            'agent_guide_shortcut' => null,
            'agent_manifest' => null,
            'llms_txt' => null,
            'sitemap' => null,
            'robots' => null,
        ];
    }

    $base = birkly_ai_presence_discovery_public_base();
    $urls = birkly_ai_presence_public_urls();
    $guideJson = trim((string) ($urls['well_known_url'] ?? ''));
    if ($guideJson === '' && $base !== '') {
        $guideJson = $base . '/.well-known/agent-guide.json';
    }

    return [
        'for_agents' => birkly_ai_presence_for_agents_url($published, $meta),
        'for_agents_md' => $base !== '' ? $base . '/for-agents.md' : null,
        'for_agents_json' => $base !== '' ? $base . '/for-agents.json' : null,
        'agent_guide_json' => $guideJson !== '' ? $guideJson : null,
        'agent_guide_shortcut' => $base !== '' ? $base . '/agent-guide.json' : null,
        'agent_manifest' => $base !== '' ? $base . '/.well-known/agent.json' : null,
        'llms_txt' => birkly_ai_presence_llms_txt_url($published, $meta),
        'sitemap' => birkly_ai_presence_discovery_sitemap_url(),
        'robots' => $base !== '' ? $base . '/robots.txt' : null,
    ];
}

function birkly_ai_presence_discovery_indexnow_key(): string
{
    $meta = birkly_ai_presence_load_meta();
    $key = trim((string) ($meta['discovery_indexnow_key'] ?? ''));
    if ($key !== '' && preg_match('/^[a-f0-9]{32}$/i', $key) === 1) {
        return strtolower($key);
    }

    $key = bin2hex(random_bytes(16));
    $meta['discovery_indexnow_key'] = $key;
    birkly_ai_presence_save_meta($meta);

    return $key;
}

/**
 * @return list<array{loc:string,lastmod?:string,priority?:string}>
 */
function birkly_ai_presence_discovery_sitemap_entries(): array
{
    $entries = [];
    $base = birkly_ai_presence_discovery_public_base();
    if ($base === '') {
        return $entries;
    }

    $settings = birkly_ai_presence_discovery_publication_settings();
    $guideLive = birkly_ai_presence_is_publicly_live();
    $meta = birkly_ai_presence_load_meta();
    $lastmod = trim((string) ($meta['last_published_at'] ?? ''));
    if ($lastmod !== '') {
        $ts = strtotime($lastmod);
        $lastmod = $ts !== false ? gmdate('c', $ts) : '';
    }

    $seoSitemapActive = function_exists('birkly_site_seo_is_published')
        && birkly_site_seo_is_published()
        && function_exists('birkly_site_seo_surface_enabled')
        && birkly_site_seo_surface_enabled('sitemap');

    $hosting = birkly_hosting_load();
    if (!$seoSitemapActive && ($hosting['public_site_mode'] ?? 'none') === 'project') {
        require_once __DIR__ . '/site_seo_url_map.php';
        require_once __DIR__ . '/project_custom_404.php';
        $projectDir = birkly_project_root() . '/' . birkly_public_site_dir();
        $excludes = [];
        if (function_exists('birkly_site_seo_load')) {
            $seoCfg = birkly_site_seo_load();
            $excludes = is_array($seoCfg['url_map']['excludes'] ?? null)
                ? $seoCfg['url_map']['excludes'] : [];
        }
        $detailTemplates = [];
        if (function_exists('birkly_site_seo_load')) {
            foreach (birkly_site_seo_load()['detail_bindings'] ?? [] as $binding) {
                if (is_array($binding) && !empty($binding['template_path'])) {
                    $detailTemplates[] = ltrim((string) $binding['template_path'], '/');
                }
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
                $resolved = birkly_seo_project_relative_path($projectDir, $file->getPathname());
                if ($resolved === null) {
                    continue;
                }
                $rel = $resolved['relative'];
                if (!preg_match('/\.html?$/i', $rel)) {
                    continue;
                }
                if (birkly_seo_scan_should_exclude($rel, $excludes)) {
                    continue;
                }
                if (in_array($rel, $detailTemplates, true)) {
                    continue;
                }
                if (birkly_project_custom_404_sitemap_should_skip_rel('/' . $rel)) {
                    continue;
                }
                $html = @file_get_contents($file->getPathname());
                if (is_string($html)) {
                    if (birkly_seo_html_has_noindex($html)) {
                        continue;
                    }
                    if (preg_match('/<meta\s+name=["\']birkly-collection["\']/i', $html)) {
                        continue;
                    }
                }
                $pathKey = birkly_seo_public_path_from_file($rel);
                $suffix = $pathKey === '/' ? '' : $pathKey;
                $loc = rtrim($base, '/') . $suffix;
                $entries[] = [
                    'loc' => $loc,
                    'lastmod' => gmdate('c', (int) $file->getMTime()),
                    'priority' => $pathKey === '/' ? '1.0' : '0.8',
                ];
            }
        }
    }

    if ($guideLive) {
        $map = birkly_ai_presence_discovery_url_map();
        $forAgents = $map['for_agents'] ?? ($base . '/for-agents');
        $entries[] = [
            'loc' => $forAgents,
            'lastmod' => $lastmod !== '' ? $lastmod : null,
            'priority' => '0.9',
        ];
        if ($settings['discovery_sitemap']) {
            foreach ([
                'llms_txt' => '0.7',
                'agent_guide_json' => '0.85',
                'for_agents_md' => '0.8',
                'for_agents_json' => '0.82',
                'agent_guide_shortcut' => '0.8',
                'agent_manifest' => '0.75',
            ] as $key => $priority) {
                $url = $map[$key] ?? null;
                if ($url !== null && $url !== '') {
                    $entries[] = [
                        'loc' => $url,
                        'lastmod' => $lastmod !== '' ? $lastmod : null,
                        'priority' => $priority,
                    ];
                }
            }
        }
    }

    $seen = [];
    $unique = [];
    foreach ($entries as $entry) {
        $loc = $entry['loc'];
        if (isset($seen[$loc])) {
            continue;
        }
        $seen[$loc] = true;
        $unique[] = $entry;
    }

    if ($seoSitemapActive) {
        require_once __DIR__ . '/site_seo_url_map.php';
        foreach (birkly_seo_sitemap_entries() as $seoEntry) {
            $loc = $seoEntry['loc'];
            if (isset($seen[$loc])) {
                continue;
            }
            $seen[$loc] = true;
            $unique[] = $seoEntry;
        }
    }

    return $unique;
}

function birkly_ai_presence_discovery_sitemap_xml(): string
{
    $entries = birkly_ai_presence_discovery_sitemap_entries();
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($entries as $entry) {
        $xml .= '  <url><loc>' . htmlspecialchars($entry['loc'], ENT_XML1) . '</loc>';
        if (!empty($entry['lastmod'])) {
            $xml .= '<lastmod>' . htmlspecialchars((string) $entry['lastmod'], ENT_XML1) . '</lastmod>';
        }
        if (!empty($entry['priority'])) {
            $xml .= '<priority>' . htmlspecialchars((string) $entry['priority'], ENT_XML1) . '</priority>';
        }
        $xml .= '</url>' . "\n";
    }
    $xml .= '</urlset>';

    return $xml;
}

function birkly_ai_presence_discovery_sitemap_url(): ?string
{
    $base = birkly_ai_presence_discovery_public_base();
    if ($base === '') {
        return null;
    }

    return $base . '/sitemap.xml';
}

/**
 * Append discovery directives to robots.txt (sitemap, LLMS, AI crawlers).
 */
function birkly_ai_presence_discovery_robots_augment(string $body): string
{
    require_once __DIR__ . '/site_seo.php';
    $body = birkly_site_seo_robots_augment($body);

    if (!birkly_ai_presence_is_publicly_live()) {
        return $body;
    }

    $settings = birkly_ai_presence_discovery_publication_settings();
    $base = birkly_ai_presence_discovery_public_base();
    $map = birkly_ai_presence_discovery_url_map();
    $append = '';

    $sitemapUrl = $map['sitemap'] ?? birkly_ai_presence_discovery_sitemap_url();
    if ($settings['discovery_sitemap'] && $sitemapUrl !== null && stripos($body, 'Sitemap:') === false) {
        $append .= "\nSitemap: " . $sitemapUrl . "\n";
    }

    if ($settings['discovery_ai_crawlers'] && $base !== '') {
        $paths = [];
        foreach (['for_agents', 'for_agents_md', 'for_agents_json', 'llms_txt', 'agent_guide_json', 'agent_guide_shortcut', 'agent_manifest', 'sitemap'] as $key) {
            $url = $map[$key] ?? null;
            if ($url !== null && $url !== '') {
                $path = parse_url($url, PHP_URL_PATH);
                if (is_string($path) && $path !== '') {
                    $paths[] = $path;
                }
            }
        }
        if ($paths === []) {
            $paths = [
                '/for-agents',
                '/for-agents.md',
                '/for-agents.json',
                '/llms.txt',
                '/.well-known/agent-guide.json',
                '/.well-known/agent.json',
                '/agent-guide.json',
                '/sitemap.xml',
            ];
        }
        foreach (birkly_ai_presence_discovery_ai_crawler_agents() as $agent) {
            if (stripos($body, 'User-agent: ' . $agent) !== false) {
                continue;
            }
            $append .= "\nUser-agent: " . $agent . "\n";
            foreach ($paths as $path) {
                $append .= 'Allow: ' . $path . "\n";
            }
        }
    }

    if (stripos($body, 'LLMS:') === false) {
        $llmsUrl = $map['llms_txt'] ?? birkly_ai_presence_llms_txt_url();
        if ($llmsUrl !== null) {
            $append .= "\n# Agent Guide — AI discovery (cross-linked endpoints)\n";
            $append .= 'LLMS: ' . $llmsUrl . '; type=summary' . "\n";
            foreach ([
                'agent_manifest' => 'Agent Guide discovery manifest',
                'agent_guide_json' => 'Agent Guide JSON',
                'for_agents' => 'Agent Guide HTML',
                'for_agents_md' => 'Agent Guide Markdown',
                'sitemap' => 'Sitemap',
            ] as $key => $label) {
                $url = $map[$key] ?? null;
                if ($url !== null && $url !== '') {
                    $append .= '# ' . $label . ': ' . $url . "\n";
                }
            }
        }
    }

    if ($append === '') {
        return $body;
    }

    return rtrim($body) . "\n" . $append;
}

/**
 * @return array{ok:bool,status?:int,error?:string,submitted?:int}
 */
function birkly_ai_presence_discovery_indexnow_ping(): array
{
    if (!birkly_ai_presence_is_publicly_live()) {
        return ['ok' => false, 'error' => 'guide_not_live'];
    }

    $settings = birkly_ai_presence_discovery_publication_settings();
    if (!$settings['discovery_indexnow']) {
        return ['ok' => false, 'error' => 'indexnow_disabled'];
    }

    $base = birkly_ai_presence_discovery_public_base();
    if ($base === '') {
        return ['ok' => false, 'error' => 'no_public_base'];
    }

    $key = birkly_ai_presence_discovery_indexnow_key();
    $host = parse_url($base, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return ['ok' => false, 'error' => 'invalid_host'];
    }

    $urlList = [];
    $map = birkly_ai_presence_discovery_url_map();
    foreach (['for_agents', 'llms_txt', 'agent_guide_json', 'sitemap'] as $mapKey) {
        $url = $map[$mapKey] ?? null;
        if ($url !== null && $url !== '') {
            $urlList[] = $url;
        }
    }

    $urlList = array_values(array_unique($urlList));
    if ($urlList === []) {
        return ['ok' => false, 'error' => 'no_urls'];
    }

    $payload = json_encode([
        'host' => $host,
        'key' => $key,
        'keyLocation' => $base . '/' . $key . '.txt',
        'urlList' => $urlList,
    ], JSON_UNESCAPED_SLASHES);

    if (!is_string($payload)) {
        return ['ok' => false, 'error' => 'encode_failed'];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 8,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents('https://api.indexnow.org/indexnow', false, $context);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string) $http_response_header[0], $m) === 1) {
        $status = (int) $m[1];
    }

    $ok = $status >= 200 && $status < 300;
    $meta = birkly_ai_presence_load_meta();
    $meta['discovery_last_indexnow_at'] = date('c');
    $meta['discovery_last_indexnow_status'] = $status;
    $meta['discovery_last_indexnow_ok'] = $ok;
    $meta['discovery_last_indexnow_body'] = is_string($response)
        ? substr(trim($response), 0, 2000) : '';
    birkly_ai_presence_save_meta($meta);

    return [
        'ok' => $ok,
        'status' => $status,
        'submitted' => count($urlList),
        'response_body' => $meta['discovery_last_indexnow_body'],
    ];
}

/**
 * Template / {from 'agent-guide' get summary} context.
 *
 * @return array<string, string>|null
 */
function birkly_ai_presence_discovery_template_summary(?array $published = null, ?array $meta = null): ?array
{
    $meta = $meta ?? birkly_ai_presence_load_meta();
    $published = $published ?? birkly_ai_presence_load_published();
    if (!birkly_ai_presence_is_publicly_live($published, $meta)) {
        return null;
    }

    $identity = is_array($published['identity'] ?? null) ? $published['identity'] : [];
    $name = trim((string) ($identity['official_name'] ?? ''));
    if ($name === '') {
        $name = 'This site';
    }
    $short = trim((string) ($identity['short_description'] ?? ''));
    $forAgentsUrl = birkly_ai_presence_for_agents_url($published, $meta) ?? '';
    $settings = birkly_ai_presence_discovery_publication_settings($published);
    $custom = $settings['footer_discovery_line_text'];
    $line = $custom !== ''
        ? $custom
        : ($name . ' publishes an official Agent Guide for AI systems at ' . $forAgentsUrl . '.');
    if ($short !== '' && $custom === '') {
        $line .= ' ' . $short;
    }

    return [
        'official_name' => $name,
        'short_description' => $short,
        'for_agents_url' => $forAgentsUrl,
        'summary_line' => $line,
        'line' => $line,
    ];
}

function birkly_ai_presence_discovery_footer_line_snippet(?array $published = null, ?array $meta = null): string
{
    $settings = birkly_ai_presence_discovery_publication_settings($published);
    if (!$settings['footer_discovery_line']) {
        return '';
    }

    $summary = birkly_ai_presence_discovery_template_summary($published, $meta);
    if ($summary === null) {
        return '';
    }

    $forAgentsUrl = $summary['for_agents_url'];
    if ($forAgentsUrl === '') {
        return '';
    }

    $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $text = $settings['footer_discovery_line_text'] !== ''
        ? $settings['footer_discovery_line_text']
        : 'Official AI facts for chat and search assistants:';

    return '<p class="birkly-agent-guide-footer-line" data-birkly-agent-guide="footer-line" '
        . 'style="font-size:13px;color:#A2A7B5;margin:0;">'
        . $esc($text) . ' <a href="' . $esc($forAgentsUrl) . '" rel="help" data-birkly-agent-guide="footer-link">'
        . 'Agent Guide</a></p>';
}

/**
 * Whether a discovery/SEO path reaches platform HTTP handlers on the public host
 * (hosting dispatch must fall through; index.php HTTP routes must handle the URI).
 * No outbound HTTP — used by admin discovery health checks.
 */
function birkly_ai_presence_discovery_public_route_reachable(string $uri): bool
{
    if (!function_exists('birkly_hosting_dispatch')) {
        require_once __DIR__ . '/hosting_routing.php';
    }
    require_once __DIR__ . '/http_routes.php';

    if (birkly_hosting_dispatch($uri)) {
        return false;
    }

    return birkly_http_route_uri_has_handler($uri);
}

/**
 * @return array<string, mixed>
 */
function birkly_ai_presence_discovery_status(): array
{
    $live = birkly_ai_presence_is_publicly_live();
    $settings = birkly_ai_presence_discovery_publication_settings();
    $base = birkly_ai_presence_discovery_public_base();
    $forAgents = birkly_ai_presence_for_agents_url();
    $sitemapUrl = birkly_ai_presence_discovery_sitemap_url();
    $meta = birkly_ai_presence_load_meta();
    $indexnowKey = trim((string) ($meta['discovery_indexnow_key'] ?? ''));

    $checks = [
        [
            'id' => 'guide_live',
            'label' => 'Agent Guide published',
            'status' => $live ? 'pass' : 'fail',
            'detail' => $live ? 'Public endpoints are active.' : 'Publish your guide first.',
        ],
        [
            'id' => 'for_agents_url',
            'label' => '/for-agents page',
            'status' => $forAgents !== null ? 'pass' : 'fail',
            'detail' => $forAgents ?? 'No public URL configured.',
            'url' => $forAgents,
        ],
        [
            'id' => 'sitemap',
            'label' => 'Sitemap',
            'status' => ($live && $settings['discovery_sitemap'] && $sitemapUrl !== null) ? 'pass' : ($live ? 'warn' : 'neutral'),
            'detail' => $sitemapUrl !== null ? $sitemapUrl : 'Enable project or public site hosting.',
            'url' => $sitemapUrl,
        ],
        [
            'id' => 'footer_link',
            'label' => 'Footer discovery link',
            'status' => $settings['footer_discovery_link'] ? 'pass' : 'warn',
            'detail' => $settings['footer_discovery_link']
                ? '“For agents” link is injected in the page footer when possible.'
                : 'Enable footer link in publication settings for internal discovery.',
        ],
        [
            'id' => 'footer_line',
            'label' => 'Footer one-liner',
            'status' => $settings['footer_discovery_line'] ? 'pass' : 'neutral',
            'detail' => $settings['footer_discovery_line']
                ? 'Optional crawlable sentence is enabled.'
                : 'Optional — add a one-line AI discovery hint in the footer.',
        ],
        [
            'id' => 'ai_crawlers',
            'label' => 'AI crawler policy',
            'status' => $settings['discovery_ai_crawlers'] ? 'pass' : 'warn',
            'detail' => $settings['discovery_ai_crawlers']
                ? 'robots.txt allows GPTBot, PerplexityBot, ClaudeBot, and others on guide URLs.'
                : 'Disabled — AI crawlers may not be explicitly allowed.',
        ],
        [
            'id' => 'indexnow',
            'label' => 'IndexNow ping',
            'status' => !empty($meta['discovery_last_indexnow_ok']) ? 'pass'
                : (!empty($meta['discovery_last_indexnow_at']) ? 'warn' : 'neutral'),
            'detail' => !empty($meta['discovery_last_indexnow_at'])
                ? ('Last ping: ' . (string) $meta['discovery_last_indexnow_at']
                    . ' (HTTP ' . (int) ($meta['discovery_last_indexnow_status'] ?? 0) . ')')
                : ($settings['discovery_indexnow']
                    ? 'Pings search engines when you publish.'
                    : 'Disabled in publication settings.'),
        ],
        [
            'id' => 'template_tag',
            'label' => 'Template tag',
            'status' => 'neutral',
            'detail' => "Add {from 'agent-guide' get summary}…{endget} in project HTML where you want crawlable text.",
        ],
    ];

    if ($live) {
        $map = birkly_ai_presence_discovery_url_map();
        $meshKeys = ['for_agents', 'agent_guide_json', 'llms_txt', 'sitemap'];
        $meshPresent = 0;
        foreach ($meshKeys as $key) {
            if (!empty($map[$key])) {
                $meshPresent++;
            }
        }
        $checks[] = [
            'id' => 'discovery_mesh',
            'label' => 'Discovery cross-links',
            'status' => $meshPresent >= 3 ? 'pass' : ($meshPresent >= 1 ? 'warn' : 'fail'),
            'detail' => $meshPresent >= 3
                ? 'llms.txt, robots.txt, sitemap, /for-agents, and agent-guide.json reference each other.'
                : 'Some discovery endpoints are missing — check hosting URLs and publication settings.',
        ];

        $hosting = birkly_hosting_load();
        if (($hosting['public_site_mode'] ?? '') === 'project' && $base !== '') {
            $probePaths = [
                '/sitemap.xml',
                '/llms.txt',
                '/for-agents',
                '/for-agents.md',
                '/for-agents.json',
                '/agent-guide.json',
                '/.well-known/agent-guide.json',
                '/.well-known/agent.json',
            ];
            if ($indexnowKey !== '') {
                $probePaths[] = '/' . $indexnowKey . '.txt';
            }
            $routingFailed = [];
            foreach ($probePaths as $probePath) {
                if (!birkly_ai_presence_discovery_public_route_reachable($probePath)) {
                    $routingFailed[] = $probePath;
                }
            }
            $checks[] = [
                'id' => 'public_discovery_routing',
                'label' => 'Public discovery routing',
                'status' => $routingFailed === [] ? 'pass' : 'fail',
                'detail' => $routingFailed === []
                    ? 'Discovery paths reach platform handlers on the public host before project 404.'
                    : 'Not routed on the public host: ' . implode(', ', $routingFailed),
            ];
        }
    }

    return [
        'live' => $live,
        'public_base' => $base,
        'settings' => $settings,
        'indexnow_key_url' => ($base !== '' && $indexnowKey !== '') ? $base . '/' . $indexnowKey . '.txt' : null,
        'checks' => $checks,
        'template_example' => "{from 'agent-guide' get summary}\n  <p>{'summary_line'}</p>\n{endget}",
    ];
}

function birkly_ai_presence_discovery_on_publish(): void
{
    birkly_ai_presence_discovery_indexnow_key();
    birkly_ai_presence_discovery_indexnow_ping();
}

/**
 * Serve IndexNow verification key at /{key}.txt when the URI matches.
 */
/**
 * Machine-readable discovery manifest for /.well-known/agent.json.
 */
function birkly_ai_presence_discovery_manifest_body(): ?string
{
    $meta = birkly_ai_presence_load_meta();
    $published = birkly_ai_presence_load_published();
    if (!birkly_ai_presence_is_publicly_live($published, $meta)) {
        return null;
    }

    $map = birkly_ai_presence_discovery_url_map($published, $meta);
    $identity = is_array($published['identity'] ?? null) ? $published['identity'] : [];

    $payload = [
        'schema' => 'birkly.agent_guide_discovery.v1',
        'product' => 'Agent Guide',
        'official_name' => trim((string) ($identity['official_name'] ?? '')),
        'published_version' => (int) ($meta['published_version'] ?? 0),
        'last_published_at' => (string) ($meta['last_published_at'] ?? ''),
        'canonical_json' => $map['agent_guide_json'] ?? null,
        'html' => $map['for_agents'] ?? null,
        'markdown' => $map['for_agents_md'] ?? null,
        'llms_txt' => $map['llms_txt'] ?? null,
        'json_aliases' => array_values(array_filter([
            $map['for_agents_json'] ?? null,
            $map['agent_guide_shortcut'] ?? null,
        ])),
        'endpoints' => array_filter($map, static fn (?string $url): bool => $url !== null && $url !== ''),
        'how_to_fetch' => 'Use canonical_json for authoritative facts. If your fetcher rejects application/json, '
            . 'open html (/for-agents) or markdown (/for-agents.md). Start from llms_txt for a link map.',
        'verify_claims' => 'Only treat claims with status "supported" in the JSON export as verified; follow sources_of_truth URLs.',
    ];

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return $json !== false ? $json : null;
}

/**
 * RFC 5988 Link header value cross-linking Agent Guide discovery URLs.
 */
function birkly_ai_presence_discovery_http_link_header(): string
{
    $map = birkly_ai_presence_discovery_url_map();
    $parts = [];
    $add = static function (?string $url, string $rel, ?string $type = null, ?string $title = null) use (&$parts): void {
        if ($url === null || $url === '') {
            return;
        }
        $segment = '<' . $url . '>; rel="' . $rel . '"';
        if ($type !== null && $type !== '') {
            $segment .= '; type="' . $type . '"';
        }
        if ($title !== null && $title !== '') {
            $segment .= '; title="' . str_replace('"', '', $title) . '"';
        }
        $parts[] = $segment;
    };

    $add($map['agent_guide_json'] ?? null, 'canonical', 'application/json', 'Agent Guide JSON');
    $add($map['for_agents'] ?? null, 'alternate', 'text/html', 'Agent Guide HTML');
    $add($map['for_agents_md'] ?? null, 'alternate', 'text/markdown', 'Agent Guide Markdown');
    $add($map['llms_txt'] ?? null, 'describedby', 'text/markdown', 'llms.txt');
    $add($map['agent_manifest'] ?? null, 'describedby', 'application/json', 'Agent Guide discovery manifest');

    return implode(', ', $parts);
}

function birkly_ai_presence_discovery_send_http_link_headers(): void
{
    if (!birkly_ai_presence_is_publicly_live()) {
        return;
    }

    $link = birkly_ai_presence_discovery_http_link_header();
    if ($link !== '') {
        header('Link: ' . $link, false);
    }
}

function birkly_ai_presence_discovery_try_serve_indexnow_key(string $uri): bool
{
    if (!preg_match('#^/([a-f0-9]{32})\\.txt$#i', $uri, $m)) {
        return false;
    }

    $meta = birkly_ai_presence_load_meta();
    $stored = strtolower(trim((string) ($meta['discovery_indexnow_key'] ?? '')));
    $requested = strtolower($m[1]);
    if ($stored === '' || $stored !== $requested) {
        return false;
    }

    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    echo $stored;

    return true;
}
