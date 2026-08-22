<?php
/**
 * Agent Guide discovery for search/chat AIs — sitemap, robots, IndexNow, status.
 * P66 — search-path + verify & monitor.
 */

declare(strict_types=1);

require_once __DIR__ . '/ai_presence.php';
require_once __DIR__ . '/hosting_urls.php';
require_once __DIR__ . '/project_site.php';

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

    $hosting = birkly_hosting_load();
    if (($hosting['public_site_mode'] ?? 'none') === 'project') {
        $projectDir = birkly_project_root() . '/' . birkly_public_site_dir();
        if (is_dir($projectDir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($projectDir, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $path = $file->getPathname();
                $rel = substr($path, strlen($projectDir));
                $rel = str_replace('\\', '/', $rel);
                if (!preg_match('/\.html?$/i', $rel)) {
                    continue;
                }
                if (str_contains($rel, '/vendor/') || str_contains($rel, '/_p64/')) {
                    continue;
                }
                $loc = $base . ($rel === '/index.html' ? '/' : $rel);
                $entries[] = [
                    'loc' => $loc,
                    'lastmod' => gmdate('c', (int) $file->getMTime()),
                    'priority' => str_ends_with(strtolower($rel), 'index.html') ? '1.0' : '0.8',
                ];
            }
        }
    }

    if ($guideLive) {
        $forAgents = birkly_ai_presence_for_agents_url() ?? ($base . '/for-agents');
        $entries[] = [
            'loc' => $forAgents,
            'lastmod' => $lastmod !== '' ? $lastmod : null,
            'priority' => '0.9',
        ];
        if ($settings['discovery_sitemap']) {
            $llms = birkly_ai_presence_llms_txt_url();
            if ($llms !== null) {
                $entries[] = ['loc' => $llms, 'lastmod' => $lastmod !== '' ? $lastmod : null, 'priority' => '0.7'];
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
    if (!birkly_ai_presence_is_publicly_live()) {
        return $body;
    }

    $settings = birkly_ai_presence_discovery_publication_settings();
    $base = birkly_ai_presence_discovery_public_base();
    $append = '';

    $sitemapUrl = birkly_ai_presence_discovery_sitemap_url();
    if ($settings['discovery_sitemap'] && $sitemapUrl !== null && stripos($body, 'Sitemap:') === false) {
        $append .= "\nSitemap: " . $sitemapUrl . "\n";
    }

    if ($settings['discovery_ai_crawlers'] && $base !== '') {
        $paths = ['/for-agents', '/llms.txt', '/.well-known/agent-guide.json', '/sitemap.xml'];
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
        $llmsUrl = birkly_ai_presence_llms_txt_url();
        if ($llmsUrl !== null) {
            $append .= "\n# Agent Guide — AI discovery (appended when Agent Guide is published)\n";
            $append .= 'LLMS: ' . $llmsUrl . '; type=summary' . "\n";
            $guideUrl = (string) (birkly_ai_presence_public_urls()['well_known_url'] ?? '');
            if ($guideUrl !== '') {
                $append .= '# Agent Guide JSON: ' . $guideUrl . "\n";
            }
            $forAgentsUrl = birkly_ai_presence_for_agents_url();
            if ($forAgentsUrl !== null) {
                $append .= '# Agent Guide HTML: ' . $forAgentsUrl . "\n";
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
    $forAgents = birkly_ai_presence_for_agents_url();
    if ($forAgents !== null) {
        $urlList[] = $forAgents;
    }
    $llms = birkly_ai_presence_llms_txt_url();
    if ($llms !== null) {
        $urlList[] = $llms;
    }
    $guideUrl = (string) (birkly_ai_presence_public_urls()['well_known_url'] ?? '');
    if ($guideUrl !== '') {
        $urlList[] = $guideUrl;
    }
    $sitemap = birkly_ai_presence_discovery_sitemap_url();
    if ($sitemap !== null) {
        $urlList[] = $sitemap;
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
    birkly_ai_presence_save_meta($meta);

    return ['ok' => $ok, 'status' => $status, 'submitted' => count($urlList)];
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
