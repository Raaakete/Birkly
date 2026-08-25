<?php
/**
 * SEO pack configuration (P67-M1 / M4 foundation).
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
                'sitemap' => true,
                'robots_sitemap_line' => true,
                'indexnow' => true,
            ],
        ],
        'defaults' => [
            'title_suffix' => '',
            'description' => '',
            'og_image' => '',
        ],
        '_schema_version' => 1,
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
    $config = birkly_site_seo_load();
    return $config['enabled'] && $config['published'];
}

/**
 * Publish SEO pack (independent of Agent Guide).
 *
 * @return array{ok:bool,config?:array<string,mixed>,error?:string}
 */
function birkly_site_seo_publish(): array
{
    $config = birkly_site_seo_load();
    $config['enabled'] = true;
    $config['published'] = true;
    $config['published_at'] = date('c');

    return birkly_site_seo_save($config);
}

/**
 * Unpublish SEO pack — retains config data.
 *
 * @return array{ok:bool,config?:array<string,mixed>,error?:string}
 */
function birkly_site_seo_unpublish(): array
{
    $config = birkly_site_seo_load();
    $config['published'] = false;

    return birkly_site_seo_save($config);
}
