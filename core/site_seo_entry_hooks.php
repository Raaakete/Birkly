<?php
/**
 * Keep SEO url_map.details in sync when entries change (P67+).
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $payload
 */
function birkly_site_seo_on_entry_saved(array $payload): void
{
    if (!birkly_site_seo_is_published()) {
        return;
    }
    $collection = trim((string) ($payload['collection'] ?? ''));
    if ($collection === '') {
        return;
    }
    $config = birkly_site_seo_load();
    $hasBinding = false;
    foreach ($config['detail_bindings'] ?? [] as $binding) {
        if (is_array($binding) && ($binding['collection'] ?? '') === $collection) {
            $hasBinding = true;
            break;
        }
    }
    if (!$hasBinding) {
        return;
    }

    $config = birkly_seo_materialize_detail_urls($config);
    $save = birkly_site_seo_save($config);
    if (empty($save['ok'])) {
        return;
    }

    if (!birkly_site_seo_surface_enabled('indexnow')) {
        return;
    }
    require_once __DIR__ . '/ai_presence_discovery.php';
    $entry = is_array($payload['entry'] ?? null) ? $payload['entry'] : [];
    $status = (string) ($entry['status'] ?? '');
    if ($status !== 'published') {
        return;
    }
    $slug = trim((string) ($entry['slug'] ?? $entry['id'] ?? ''));
    if ($slug === '') {
        return;
    }
    foreach ($config['detail_bindings'] as $binding) {
        if (!is_array($binding) || ($binding['collection'] ?? '') !== $collection) {
            continue;
        }
        $url = birkly_seo_detail_public_url(
            (string) ($binding['template_path'] ?? ''),
            $collection,
            $slug,
            $binding
        );
        if ($url !== '' && !str_starts_with($url, '/')) {
            birkly_ai_presence_discovery_indexnow_submit_urls([$url]);
        }
    }
}

/**
 * @param mixed ...$args entry, collection from after_entry_delete
 */
function birkly_site_seo_on_entry_deleted(...$args): void
{
    if (!birkly_site_seo_is_published()) {
        return;
    }
    $collection = is_string($args[1] ?? null) ? $args[1] : '';
    if ($collection === '') {
        return;
    }
    $config = birkly_site_seo_load();
    $config = birkly_seo_materialize_detail_urls($config);
    birkly_site_seo_save($config);
}

if (!function_exists('register_hook')) {
    require_once __DIR__ . '/plugins.php';
}

register_hook('birkly_entry_saved', static function ($payload): void {
    if (!is_array($payload)) {
        return;
    }
    birkly_site_seo_on_entry_saved($payload);
}, 20);

register_hook('after_entry_delete', static function (...$args): void {
    birkly_site_seo_on_entry_deleted(...$args);
}, 20);