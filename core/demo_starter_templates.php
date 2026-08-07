<?php
/**
 * Starter template packs for onboarding (Ops-hosted; blank works offline).
 */

declare(strict_types=1);

require_once __DIR__ . '/project_site.php';
require_once __DIR__ . '/ops_starters_client.php';

function birkly_demo_starter_catalog(): array
{
    return [
        'marsk' => [
            'label' => 'Marsk — slow living journal',
            'description' => 'Flagship editorial blog starter with journal, gallery, and about pages.',
            'collections' => ['blog', 'about', 'gallery', 'site_elements'],
            'onboarding_enabled' => true,
        ],
        'blog' => [
            'label' => 'Blog starter',
            'description' => 'Blog posts, pages, and a sample front-end site.',
            'collections' => ['blog', 'about', 'contact', 'services', 'site_elements'],
            'onboarding_enabled' => false,
        ],
        'shop' => [
            'label' => 'Shop starter',
            'description' => 'Product catalog with a storefront demo site.',
            'collections' => ['products', 'about', 'contact', 'site_elements'],
            'onboarding_enabled' => false,
        ],
        'business' => [
            'label' => 'Business starter',
            'description' => 'Services, team, and a company site demo.',
            'collections' => ['services', 'about', 'contact', 'site_elements'],
            'onboarding_enabled' => false,
        ],
        'docs' => [
            'label' => 'Documentation starter',
            'description' => 'Help articles and a docs-style demo site.',
            'collections' => ['docs', 'about', 'site_elements'],
            'onboarding_enabled' => false,
        ],
        'blank' => [
            'label' => 'Start blank',
            'description' => 'No demo collections or demo website.',
            'collections' => [],
            'onboarding_enabled' => true,
        ],
    ];
}

function birkly_demo_starter_primary_collection(string $templateId): ?string
{
    $catalog = birkly_demo_starter_catalog();
    if (!isset($catalog[$templateId])) {
        return null;
    }
    return $catalog[$templateId]['collections'][0] ?? null;
}

/**
 * Bundled starter zip shipped with the CMS (offline / local-PHP fallback).
 */
function birkly_demo_local_starter_zip_path(string $projectRoot, string $templateId): string
{
    $templateId = strtolower(trim($templateId));
    if ($templateId === '' || !preg_match('/^[a-z][a-z0-9-]{0,62}$/', $templateId)) {
        return '';
    }
    return rtrim($projectRoot, '/\\') . '/starters/' . $templateId . '.zip';
}

function birkly_demo_local_starter_zip_ready(string $projectRoot, string $templateId): bool
{
    $path = birkly_demo_local_starter_zip_path($projectRoot, $templateId);
    return $path !== '' && is_file($path) && filesize($path) > 64;
}

function birkly_demo_starter_is_selectable(string $projectRoot, string $templateId): bool
{
    $catalog = birkly_demo_starter_catalog();
    if (!isset($catalog[$templateId])) {
        return false;
    }
    if ($templateId === 'blank') {
        return true;
    }
    if (empty($catalog[$templateId]['onboarding_enabled'])) {
        return false;
    }

    if (birkly_demo_local_starter_zip_ready($projectRoot, $templateId)) {
        return true;
    }

    $opsOverlay = birkly_ops_starter_availability_overlay($projectRoot);
    return isset($opsOverlay[$templateId]) && !empty($opsOverlay[$templateId]['selectable']);
}

/**
 * @param array<string, array{enabled?:bool,ready?:bool,selectable?:bool,source?:string}>|null $opsOverlay
 * @return array<string, array{enabled:bool,ready:bool,selectable:bool,source?:string}>
 */
function birkly_demo_starter_availability(string $projectRoot, ?array $opsOverlay = null): array
{
    $availability = [];

    // Skip remote catalog when every onboarding-enabled pack has a bundled zip
    // (Marsk local fallback) — avoids hanging local PHP on outbound HTTPS.
    if ($opsOverlay === null) {
        $needsRemote = false;
        foreach (birkly_demo_starter_catalog() as $templateId => $pack) {
            if ($templateId === 'blank' || empty($pack['onboarding_enabled'])) {
                continue;
            }
            if (!birkly_demo_local_starter_zip_ready($projectRoot, $templateId)) {
                $needsRemote = true;
                break;
            }
        }
        $opsOverlay = $needsRemote ? birkly_ops_starter_availability_overlay($projectRoot) : [];
    }

    foreach (birkly_demo_starter_catalog() as $templateId => $pack) {
        if ($templateId === 'blank') {
            $availability[$templateId] = [
                'enabled' => true,
                'ready' => true,
                'selectable' => true,
                'source' => 'local',
            ];
            continue;
        }

        $localReady = !empty($pack['onboarding_enabled'])
            && birkly_demo_local_starter_zip_ready($projectRoot, $templateId);

        if ($localReady) {
            $availability[$templateId] = [
                'enabled' => true,
                'ready' => true,
                'selectable' => true,
                'source' => 'local',
            ];
            continue;
        }

        if (isset($opsOverlay[$templateId]) && !empty($opsOverlay[$templateId]['selectable'])) {
            $availability[$templateId] = $opsOverlay[$templateId];
            continue;
        }

        if (isset($opsOverlay[$templateId])) {
            $availability[$templateId] = $opsOverlay[$templateId];
            continue;
        }

        // Catalog unreachable and no bundled zip.
        $availability[$templateId] = [
            'enabled' => !empty($pack['onboarding_enabled']),
            'ready' => false,
            'selectable' => false,
            'source' => 'unavailable',
            'catalog_reachable' => false,
        ];
    }

    return $availability;
}

function birkly_demo_all_starter_collection_slugs(): array
{
    $slugs = [];
    foreach (birkly_demo_starter_catalog() as $pack) {
        foreach ($pack['collections'] as $slug) {
            $slugs[$slug] = true;
        }
    }
    return array_keys($slugs);
}

function birkly_demo_remove_tree(string $dir): bool
{
    if (!is_dir($dir)) {
        return true;
    }
    $items = array_diff(scandir($dir) ?: [], ['.', '..']);
    foreach ($items as $item) {
        $path = $dir . '/' . $item;
        if (is_dir($path) && !is_link($path)) {
            birkly_demo_remove_tree($path);
        } else {
            @unlink($path);
        }
    }
    if (is_link($dir)) {
        return @unlink($dir);
    }
    return @rmdir($dir);
}

/**
 * Remove directory contents but keep the root path (for project symlink targets).
 */
function birkly_demo_remove_dir_contents(string $dir): bool
{
    if (!is_dir($dir)) {
        return true;
    }
    $items = array_diff(scandir($dir) ?: [], ['.', '..']);
    foreach ($items as $item) {
        $path = $dir . '/' . $item;
        if (is_dir($path) && !is_link($path)) {
            birkly_demo_remove_tree($path);
        } else {
            @unlink($path);
        }
    }
    return true;
}

function birkly_demo_install_starter_template(string $templateId, string $projectRoot): array
{
    $catalog = birkly_demo_starter_catalog();
    if (!isset($catalog[$templateId])) {
        return ['success' => false, 'message' => 'Unknown starter template'];
    }

    if ($templateId !== 'blank' && !birkly_demo_starter_is_selectable($projectRoot, $templateId)) {
        $diagnostics = birkly_ops_starters_diagnostics($templateId);
        $message = 'This starter template is not available yet';
        if (birkly_demo_local_starter_zip_ready($projectRoot, $templateId)) {
            $message = 'Starter template could not be validated';
        } elseif (($diagnostics['catalog_reachable'] ?? false) !== true) {
            $message = 'Internet connection needed to download this demo pack (or use Start blank).';
        } elseif (!empty($diagnostics['errors'])) {
            $message = 'Starter download is not available right now: ' . implode('; ', $diagnostics['errors']);
        }
        return [
            'success' => false,
            'message' => $message,
            'diagnostics' => $diagnostics,
        ];
    }

    $collectionsReady = birkly_ensure_content_collections_dir($projectRoot);
    if (!$collectionsReady['ok']) {
        return [
            'success' => false,
            'template' => $templateId,
            'installed_collections' => [],
            'demo_website' => false,
            'removed' => [],
            'message' => $collectionsReady['error'] ?? 'Collections directory is not writable',
            'diagnostics' => [
                'collections_dir' => $collectionsReady,
            ],
        ];
    }

    $contentRoot = $collectionsReady['path'];
    $websiteRoot = birkly_public_site_path();

    $removed = [];
    foreach (birkly_demo_all_starter_collection_slugs() as $slug) {
        $path = $contentRoot . '/' . $slug;
        if (is_dir($path)) {
            if (birkly_demo_remove_tree($path)) {
                $removed[] = $slug;
            }
        }
    }
    if (is_dir($websiteRoot)) {
        birkly_demo_remove_dir_contents($websiteRoot);
    }

    $collectionsReady = birkly_ensure_content_collections_dir($projectRoot);
    if (!$collectionsReady['ok']) {
        return [
            'success' => false,
            'template' => $templateId,
            'installed_collections' => [],
            'demo_website' => false,
            'removed' => $removed,
            'message' => $collectionsReady['error'] ?? 'Collections directory is not writable after cleanup',
            'diagnostics' => [
                'collections_dir' => $collectionsReady,
            ],
        ];
    }
    $contentRoot = $collectionsReady['path'];

    if ($templateId === 'blank') {
        if (!is_dir($websiteRoot)) {
            @mkdir($websiteRoot, 0755, true);
            @touch($websiteRoot . '/.gitkeep');
        }
        return [
            'success' => true,
            'template' => $templateId,
            'installed_collections' => [],
            'demo_website' => false,
            'project_site' => false,
            'removed' => $removed,
            'message' => 'Starter content cleared',
        ];
    }

    $tmpZip = sys_get_temp_dir() . '/birkly-starter-' . $templateId . '-' . getmypid() . '.zip';
    $downloadErrors = [];
    $download = null;
    $localZip = birkly_demo_local_starter_zip_path($projectRoot, $templateId);
    // Prefer bundled zip when present — local PHP often cannot complete outbound
    // HTTPS even when the browser can reach the internet.
    if (birkly_demo_local_starter_zip_ready($projectRoot, $templateId)) {
        if (@copy($localZip, $tmpZip)) {
            $download = [
                'ok' => true,
                'id' => $templateId,
                'source' => 'local',
                'path' => $localZip,
            ];
        } else {
            $downloadErrors[] = 'Could not read bundled starter zip';
        }
    }
    if ($download === null) {
        $download = birkly_ops_download_starter_zip($templateId, $tmpZip, $downloadErrors);
    }
    if ($download === null) {
        @unlink($tmpZip);
        $diagnostics = birkly_ops_starters_diagnostics($templateId);
        if ($downloadErrors !== []) {
            $diagnostics['errors'] = array_values(array_unique(array_merge($diagnostics['errors'], $downloadErrors)));
        }
        return [
            'success' => false,
            'template' => $templateId,
            'installed_collections' => [],
            'demo_website' => false,
            'removed' => $removed,
            'message' => !empty($diagnostics['errors'])
                ? 'Starter download failed: ' . implode('; ', $diagnostics['errors'])
                : 'Starter template is not available. Check your internet connection, or continue with Blank.',
            'diagnostics' => $diagnostics,
        ];
    }

    $extracted = birkly_ops_extract_starter_zip($tmpZip, $projectRoot);
    @unlink($tmpZip);

    if (!$extracted['success']) {
        return [
            'success' => false,
            'template' => $templateId,
            'installed_collections' => $extracted['installed_collections'] ?? [],
            'demo_website' => (bool) ($extracted['project_site'] ?? false),
            'removed' => $removed,
            'message' => $extracted['message'],
            'diagnostics' => [
                'collections_dir' => $collectionsReady,
                'extract' => $extracted,
            ],
        ];
    }

    $primary = birkly_demo_starter_primary_collection($templateId);
    $primaryInstalled = $primary === null || in_array($primary, $extracted['installed_collections'], true);

    if (!$primaryInstalled) {
        return [
            'success' => false,
            'template' => $templateId,
            'installed_collections' => $extracted['installed_collections'],
            'demo_website' => $extracted['project_site'],
            'project_site' => $extracted['project_site'],
            'removed' => $removed,
            'source' => 'ops',
            'message' => 'Starter template could not be installed: primary collection '
                . ($primary ?? 'unknown')
                . ' is missing after extract',
            'diagnostics' => [
                'collections_dir' => $collectionsReady,
                'extract' => $extracted,
                'primary_collection' => $primary,
            ],
        ];
    }

    if (!empty($extracted['project_site'])) {
        birkly_demo_enable_visit_project_after_install($projectRoot);
    }

    return [
        'success' => true,
        'template' => $templateId,
        'installed_collections' => $extracted['installed_collections'],
        'demo_website' => $extracted['project_site'],
        'project_site' => $extracted['project_site'],
        'removed' => $removed,
        'source' => (string) ($download['source'] ?? 'ops'),
        'message' => 'Starter template installed',
        'visit_url' => function_exists('birkly_public_site_url')
            ? birkly_public_site_url('/index.html')
            : '',
    ];
}

/**
 * After a starter with a project site is installed: show Visit project and
 * align local hosting URLs with the current request (including :8080).
 */
function birkly_demo_enable_visit_project_after_install(string $projectRoot): void
{
    require_once __DIR__ . '/project_settings.php';
    require_once __DIR__ . '/hosting_urls.php';
    require_once __DIR__ . '/project_site.php';
    require_once __DIR__ . '/birkly_paths.php';
    require_once __DIR__ . '/site_domain.php';

    birkly_save_project_settings(['show_visit_button' => true]);

    $origin = birkly_hosting_request_origin_url();
    if ($origin === '') {
        return;
    }

    birkly_hosting_reset_cache();
    $config = birkly_hosting_load();
    $config['public_site_mode'] = 'project';
    $config['public_site_url'] = $origin;
    $config['same_host_landing'] = 'admin';

    $cmsHost = birkly_hosting_url_host((string) ($config['cms_base_url'] ?? ''));
    if ($config['cms_base_url'] === '' || birkly_is_local_dev_host($cmsHost)) {
        $config['cms_base_url'] = $origin;
    }

    birkly_hosting_persist($config, false);
}
