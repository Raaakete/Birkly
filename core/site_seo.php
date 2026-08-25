<?php
/**
 * SEO pack configuration and SSR injection (P67-M4/M5/M8).
 *
 * Storage: settings/seo.json — site-owned, merge-on-upgrade.
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';

/**
 * @return array<string, mixed>
 */
function birkly_site_seo_defaults(): array
{
    return [
        'enabled' => false,
        'published' => false,
        'published_at' => null,
        'delivery' => [
            'mode' => 'auto_inject',
            'surfaces' => [
                'meta_tags' => true,
                'canonical' => true,
                'open_graph' => true,
                'json_ld_website' => true,
                'json_ld_article' => false,
                'json_ld_organization' => true,
                'twitter_cards' => true,
                'sitemap' => true,
                'robots_sitemap_line' => true,
                'indexnow' => true,
            ],
        ],
        'defaults' => [
            'site_title' => '',
            'title_suffix' => '',
            'description' => '',
            'og_image' => '',
            'twitter_card' => 'summary_large_image',
        ],
        'advanced' => [
            'robots' => 'index,follow',
            'google_site_verification' => '',
            'bing_site_verification' => '',
        ],
        '_schema_version' => 2,
    ];
}

function birkly_site_seo_settings_path(): string
{
    return birkly_project_root() . '/settings/seo.json';
}

function birkly_site_seo_reset_cache(): void
{
    unset($GLOBALS['__birkly_site_seo_config']);
}

/**
 * @return array<string, mixed>
 */
function birkly_site_seo_load(): array
{
    if (isset($GLOBALS['__birkly_site_seo_config']) && is_array($GLOBALS['__birkly_site_seo_config'])) {
        return $GLOBALS['__birkly_site_seo_config'];
    }

    $config = birkly_site_seo_defaults();
    $path = birkly_site_seo_settings_path();
    if (is_readable($path)) {
        $raw = file_get_contents($path);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $config = birkly_site_seo_normalize($decoded);
            }
        }
    }

    $GLOBALS['__birkly_site_seo_config'] = $config;
    return $config;
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function birkly_site_seo_normalize(array $input): array
{
    $defaults = birkly_site_seo_defaults();
    $out = array_replace_recursive($defaults, $input);
    $out['enabled'] = !empty($out['enabled']);
    $out['published'] = !empty($out['published']);

    $mode = (string) ($out['delivery']['mode'] ?? 'auto_inject');
    if (!in_array($mode, ['auto_inject', 'manual', 'download'], true)) {
        $out['delivery']['mode'] = 'auto_inject';
    }

    foreach ($defaults['delivery']['surfaces'] as $key => $_) {
        $out['delivery']['surfaces'][$key] = !empty($out['delivery']['surfaces'][$key]);
    }

    $twitterCard = (string) ($out['defaults']['twitter_card'] ?? 'summary_large_image');
    if (!in_array($twitterCard, ['summary', 'summary_large_image'], true)) {
        $out['defaults']['twitter_card'] = 'summary_large_image';
    }

    $robots = (string) ($out['advanced']['robots'] ?? 'index,follow');
    if (!in_array($robots, ['index,follow', 'index,nofollow', 'noindex,follow', 'noindex,nofollow'], true)) {
        $out['advanced']['robots'] = 'index,follow';
    }

    return $out;
}

/**
 * @param array<string, mixed> $input
 * @return array{ok:bool,config?:array<string,mixed>,error?:string}
 */
function birkly_site_seo_save(array $input): array
{
    $config = birkly_site_seo_normalize($input);

    $dir = dirname(birkly_site_seo_settings_path());
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'error' => 'Could not create settings directory'];
    }

    $stored = $config;
    $stored['_updated_at'] = date('c');
    $json = json_encode($stored, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return ['ok' => false, 'error' => 'Could not encode config'];
    }

    if (file_put_contents(birkly_site_seo_settings_path(), $json . "\n") === false) {
        return ['ok' => false, 'error' => 'Could not write config'];
    }

    birkly_site_seo_reset_cache();
    $GLOBALS['__birkly_site_seo_config'] = $config;

    return ['ok' => true, 'config' => $config];
}

function birkly_site_seo_is_published(): bool
{
    if (!function_exists('birkly_discovery_pack_enabled')) {
        require_once __DIR__ . '/discovery.php';
    }
    if (!birkly_discovery_pack_enabled('seo')) {
        return false;
    }
    $config = birkly_site_seo_load();
    return $config['enabled'] && $config['published'];
}

function birkly_site_seo_should_auto_inject(): bool
{
    if (!birkly_site_seo_is_published()) {
        return false;
    }
    $config = birkly_site_seo_load();
    return ($config['delivery']['mode'] ?? 'auto_inject') === 'auto_inject';
}

function birkly_site_seo_surface_enabled(string $surface): bool
{
    $config = birkly_site_seo_load();
    return !empty($config['delivery']['surfaces'][$surface]);
}

/**
 * @return array{ok:bool,config?:array<string,mixed>,error?:string}
 */
function birkly_site_seo_publish(): array
{
    $config = birkly_site_seo_load();
    $config['enabled'] = true;
    $config['published'] = true;
    $config['published_at'] = date('c');
    $result = birkly_site_seo_save($config);
    if ($result['ok']) {
        require_once __DIR__ . '/discovery.php';
        $discovery = birkly_discovery_load();
        $discovery['packs']['seo']['enabled'] = true;
        birkly_discovery_save($discovery);
        if (function_exists('birkly_ai_presence_discovery_indexnow_ping')
            && birkly_site_seo_surface_enabled('indexnow')) {
            require_once __DIR__ . '/ai_presence_discovery.php';
            birkly_ai_presence_discovery_indexnow_ping();
        }
    }
    return $result;
}

/**
 * @return array{ok:bool,config?:array<string,mixed>,error?:string}
 */
function birkly_site_seo_unpublish(): array
{
    $config = birkly_site_seo_load();
    $config['published'] = false;
    return birkly_site_seo_save($config);
}

/**
 * Resolve SEO meta from site defaults + optional Agent Guide identity + entry context.
 *
 * @param array<string, mixed> $context page_title, entry_meta, collection, slug
 * @return array{title:string,description:string,canonical:string,og_image:string,site_name:string}
 */
function birkly_site_seo_resolve_meta(array $context = []): array
{
    $seo = birkly_site_seo_load();
    $title = trim((string) ($context['page_title'] ?? ''));
    $description = trim((string) ($context['meta_description'] ?? ''));
    $canonical = trim((string) ($context['canonical'] ?? ''));
    $ogImage = trim((string) ($context['og_image'] ?? ''));

    $entryMeta = is_array($context['entry_meta'] ?? null) ? $context['entry_meta'] : [];
    if ($title === '' && !empty($entryMeta['meta_title'])) {
        $title = trim((string) $entryMeta['meta_title']);
    }
    if ($title === '') {
        $title = trim((string) ($seo['defaults']['site_title'] ?? ''));
    }
    if ($description === '' && !empty($entryMeta['meta_description'])) {
        $description = trim((string) $entryMeta['meta_description']);
    }
    if ($ogImage === '' && !empty($entryMeta['og_image'])) {
        $ogImage = trim((string) $entryMeta['og_image']);
    }

    if ($description === '') {
        $description = trim((string) ($seo['defaults']['description'] ?? ''));
    }
    if ($ogImage === '') {
        $ogImage = trim((string) ($seo['defaults']['og_image'] ?? ''));
    }

    $siteName = '';
    if (function_exists('birkly_ai_presence_load_published')) {
        require_once __DIR__ . '/ai_presence.php';
        $published = birkly_ai_presence_load_published();
        if (is_array($published)) {
            $identity = is_array($published['identity'] ?? null) ? $published['identity'] : [];
            $siteName = trim((string) ($identity['official_name'] ?? ''));
            if ($description === '') {
                $description = trim((string) ($identity['short_description'] ?? ''));
            }
        }
    }

    $suffix = trim((string) ($seo['defaults']['title_suffix'] ?? ''));
    if ($suffix !== '' && $title !== '' && !str_contains($title, $suffix)) {
        $title .= $suffix;
    }

    return [
        'title' => $title,
        'description' => $description,
        'canonical' => $canonical,
        'og_image' => $ogImage,
        'site_name' => $siteName,
    ];
}

/**
 * Inject SEO meta tags into HTML head when published and auto-inject is on.
 *
 * @param array<string, mixed> $context
 */
function birkly_site_seo_inject_head(string $html, array $context = []): string
{
    if (!birkly_site_seo_should_auto_inject()) {
        return $html;
    }

    $surfaces = birkly_site_seo_load()['delivery']['surfaces'] ?? [];
    $meta = birkly_site_seo_resolve_meta($context);
    $parts = [];
    $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

    if (!empty($surfaces['meta_tags'])) {
        if ($meta['title'] !== '' && stripos($html, '<title>') === false) {
            $parts[] = '<title>' . $esc($meta['title']) . '</title>';
        }
        if ($meta['description'] !== '' && stripos($html, 'name="description"') === false) {
            $parts[] = '<meta name="description" content="' . $esc($meta['description']) . '">';
        }
    }

    if (!empty($surfaces['canonical']) && $meta['canonical'] !== '' && stripos($html, 'rel="canonical"') === false) {
        $parts[] = '<link rel="canonical" href="' . $esc($meta['canonical']) . '">';
    }

    if (!empty($surfaces['open_graph'])) {
        if ($meta['title'] !== '' && stripos($html, 'property="og:title"') === false) {
            $parts[] = '<meta property="og:title" content="' . $esc($meta['title']) . '">';
        }
        if ($meta['description'] !== '' && stripos($html, 'property="og:description"') === false) {
            $parts[] = '<meta property="og:description" content="' . $esc($meta['description']) . '">';
        }
        if ($meta['og_image'] !== '' && stripos($html, 'property="og:image"') === false) {
            $parts[] = '<meta property="og:image" content="' . $esc($meta['og_image']) . '">';
        }
        if ($meta['canonical'] !== '' && stripos($html, 'property="og:url"') === false) {
            $parts[] = '<meta property="og:url" content="' . $esc($meta['canonical']) . '">';
        }
        if (stripos($html, 'property="og:type"') === false) {
            $parts[] = '<meta property="og:type" content="website">';
        }
    }

    if (!empty($surfaces['twitter_cards'])) {
        $card = (string) (birkly_site_seo_load()['defaults']['twitter_card'] ?? 'summary_large_image');
        if (stripos($html, 'name="twitter:card"') === false) {
            $parts[] = '<meta name="twitter:card" content="' . $esc($card) . '">';
        }
        if ($meta['title'] !== '' && stripos($html, 'name="twitter:title"') === false) {
            $parts[] = '<meta name="twitter:title" content="' . $esc($meta['title']) . '">';
        }
        if ($meta['description'] !== '' && stripos($html, 'name="twitter:description"') === false) {
            $parts[] = '<meta name="twitter:description" content="' . $esc($meta['description']) . '">';
        }
        if ($meta['og_image'] !== '' && stripos($html, 'name="twitter:image"') === false) {
            $parts[] = '<meta name="twitter:image" content="' . $esc($meta['og_image']) . '">';
        }
    }

    $advanced = birkly_site_seo_load()['advanced'] ?? [];
    $robots = trim((string) ($advanced['robots'] ?? 'index,follow'));
    if ($robots !== '' && $robots !== 'index,follow' && stripos($html, 'name="robots"') === false) {
        $parts[] = '<meta name="robots" content="' . $esc($robots) . '">';
    }
    $googleVerify = trim((string) ($advanced['google_site_verification'] ?? ''));
    if ($googleVerify !== '' && stripos($html, 'google-site-verification') === false) {
        $parts[] = '<meta name="google-site-verification" content="' . $esc($googleVerify) . '">';
    }
    $bingVerify = trim((string) ($advanced['bing_site_verification'] ?? ''));
    if ($bingVerify !== '' && stripos($html, 'msvalidate.01') === false) {
        $parts[] = '<meta name="msvalidate.01" content="' . $esc($bingVerify) . '">';
    }

    if (!empty($surfaces['json_ld_website']) && stripos($html, '"@type":"WebSite"') === false
        && stripos($html, '"@type": "WebSite"') === false) {
        $ld = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
        ];
        if ($meta['site_name'] !== '') {
            $ld['name'] = $meta['site_name'];
        }
        if ($meta['description'] !== '') {
            $ld['description'] = $meta['description'];
        }
        if ($meta['canonical'] !== '') {
            $ld['url'] = $meta['canonical'];
        }
        $json = json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json !== false) {
            $json = str_replace('</', '<\/', $json);
            $parts[] = '<script type="application/ld+json">' . $json . '</script>';
        }
    }

    if (!empty($surfaces['json_ld_organization']) && stripos($html, '"@type":"Organization"') === false
        && stripos($html, '"@type": "Organization"') === false) {
        $org = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
        ];
        if ($meta['site_name'] !== '') {
            $org['name'] = $meta['site_name'];
        }
        if ($meta['description'] !== '') {
            $org['description'] = $meta['description'];
        }
        if ($meta['canonical'] !== '') {
            $org['url'] = $meta['canonical'];
        }
        if ($meta['og_image'] !== '') {
            $org['logo'] = $meta['og_image'];
        }
        if (count($org) > 2) {
            $json = json_encode($org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json !== false) {
                $json = str_replace('</', '<\/', $json);
                $parts[] = '<script type="application/ld+json">' . $json . '</script>';
            }
        }
    }

    if (!empty($surfaces['json_ld_article']) && !empty($context['entry_title'])) {
        $article = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => (string) $context['entry_title'],
        ];
        if ($meta['description'] !== '') {
            $article['description'] = $meta['description'];
        }
        if ($meta['canonical'] !== '') {
            $article['url'] = $meta['canonical'];
        }
        $json = json_encode($article, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json !== false && stripos($html, '"@type":"Article"') === false && stripos($html, '"@type": "Article"') === false) {
            $json = str_replace('</', '<\/', $json);
            $parts[] = '<script type="application/ld+json">' . $json . '</script>';
        }
    }

    if ($parts === []) {
        return $html;
    }

    $snippet = "\n    " . implode("\n    ", $parts);
    if (preg_match('/<head[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
        $pos = $m[0][1] + strlen($m[0][0]);
        return substr($html, 0, $pos) . $snippet . substr($html, $pos);
    }

    return $snippet . "\n" . $html;
}

/**
 * Append Sitemap line to robots when SEO sitemap surface is on.
 */
function birkly_site_seo_robots_augment(string $body): string
{
    if (!birkly_site_seo_is_published() || !birkly_site_seo_surface_enabled('robots_sitemap_line')) {
        return $body;
    }
    require_once __DIR__ . '/ai_presence_discovery.php';
    $sitemap = birkly_ai_presence_discovery_sitemap_url();
    if ($sitemap === null || stripos($body, 'Sitemap:') !== false) {
        return $body;
    }
    return rtrim($body) . "\nSitemap: " . $sitemap . "\n";
}

function birkly_site_seo_signature_part(): string
{
    if (!birkly_site_seo_is_published()) {
        return 'seo0';
    }
    $surfaces = birkly_site_seo_load()['delivery']['surfaces'] ?? [];
    $bits = '';
    foreach (['meta_tags', 'canonical', 'open_graph', 'twitter_cards', 'json_ld_website', 'json_ld_organization', 'json_ld_article'] as $key) {
        $bits .= !empty($surfaces[$key]) ? '1' : '0';
    }
    return 'seo' . $bits;
}
