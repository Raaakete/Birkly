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

function birkly_demo_starter_is_selectable(string $projectRoot, string $templateId): bool
{
    $catalog = birkly_demo_starter_catalog();
    if (!isset($catalog[$templateId])) {
        return false;
    }
    if ($templateId === 'blank') {
        return true;
    }
    if (!empty($catalog[$templateId]['onboarding_enabled']) && birkly_demo_local_starter_dir($templateId) !== null) {
        return true;
    }
    if (empty($catalog[$templateId]['onboarding_enabled'])) {
        return false;
    }

    $opsOverlay = birkly_ops_starter_availability_overlay($projectRoot);
    return isset($opsOverlay[$templateId]) && !empty($opsOverlay[$templateId]['selectable']);
}

function birkly_demo_starter_availability(string $projectRoot): array
{
    $availability = [];
    $opsOverlay = birkly_ops_starter_availability_overlay($projectRoot);

    foreach (birkly_demo_starter_catalog() as $templateId => $pack) {
        if (isset($opsOverlay[$templateId])) {
            $availability[$templateId] = $opsOverlay[$templateId];
            if (!empty($pack['onboarding_enabled']) && birkly_demo_local_starter_dir($templateId) !== null) {
                $availability[$templateId]['selectable'] = true;
                $availability[$templateId]['ready'] = true;
                $availability[$templateId]['enabled'] = true;
                if (($availability[$templateId]['source'] ?? '') !== 'ops') {
                    $availability[$templateId]['source'] = 'bundled';
                }
            }
            continue;
        }

        if (!empty($pack['onboarding_enabled']) && birkly_demo_local_starter_dir($templateId) !== null) {
            $availability[$templateId] = [
                'enabled' => true,
                'ready' => true,
                'selectable' => true,
                'source' => 'bundled',
            ];
            continue;
        }

        $availability[$templateId] = [
            'enabled' => !empty($pack['onboarding_enabled']),
            'ready' => false,
            'selectable' => false,
            'source' => 'ops',
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
        if (is_dir($path)) {
            birkly_demo_remove_tree($path);
        } else {
            @unlink($path);
        }
    }
    return @rmdir($dir);
}

/**
 * Bundled starter pack directory (offline fallback when Ops is unreachable).
 */
function birkly_demo_local_starter_dir(string $templateId): ?string
{
    $root = birkly_project_root();
    $candidates = [
        $root . '/themes/' . $templateId . '-starter',
        $root . '/starters/' . $templateId,
    ];
    foreach ($candidates as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        if (is_dir($dir . '/collections') || is_dir($dir . '/project')) {
            return $dir;
        }
    }
    return null;
}

function birkly_demo_copy_tree(string $source, string $dest): bool
{
    if (!is_dir($source)) {
        return false;
    }
    if (!is_dir($dest) && !mkdir($dest, 0755, true)) {
        return false;
    }
    $items = array_diff(scandir($source) ?: [], ['.', '..']);
    foreach ($items as $item) {
        $from = $source . '/' . $item;
        $to = $dest . '/' . $item;
        if (is_dir($from)) {
            if (!birkly_demo_copy_tree($from, $to)) {
                return false;
            }
        } else {
            $parent = dirname($to);
            if (!is_dir($parent) && !mkdir($parent, 0755, true)) {
                return false;
            }
            if (!@copy($from, $to)) {
                return false;
            }
        }
    }
    return true;
}

/**
 * @return array{success:bool,installed_collections:list<string>,project_site:bool,message:string}
 */
function birkly_demo_install_from_local_dir(string $sourceDir, string $projectRoot): array
{
    $contentRoot = $projectRoot . '/content/collections';
    if (!is_dir($contentRoot) && !mkdir($contentRoot, 0755, true)) {
        return [
            'success' => false,
            'installed_collections' => [],
            'project_site' => false,
            'message' => 'Cannot write to content directory',
        ];
    }

    $projectSiteRoot = $projectRoot . '/' . birkly_public_site_dir();
    $installed = [];
    $hasProject = false;

    $collectionsSrc = $sourceDir . '/collections';
    if (is_dir($collectionsSrc)) {
        foreach (glob($collectionsSrc . '/*', GLOB_ONLYDIR) ?: [] as $colDir) {
            $slug = basename($colDir);
            $dest = $contentRoot . '/' . $slug;
            if (birkly_demo_copy_tree($colDir, $dest)) {
                $installed[] = $slug;
            }
        }
    }

    $projectSrc = $sourceDir . '/project';
    if (is_dir($projectSrc)) {
        if (!is_dir($projectSiteRoot) && !mkdir($projectSiteRoot, 0755, true)) {
            return [
                'success' => false,
                'installed_collections' => $installed,
                'project_site' => false,
                'message' => 'Cannot write to project site directory',
            ];
        }
        if (birkly_demo_copy_tree($projectSrc, $projectSiteRoot)) {
            $hasProject = true;
        }
    }

    return [
        'success' => $installed !== [] || $hasProject,
        'installed_collections' => $installed,
        'project_site' => $hasProject,
        'message' => ($installed !== [] || $hasProject)
            ? 'Starter template installed from bundled pack'
            : 'Bundled starter pack had no installable files',
    ];
}

function birkly_demo_install_starter_template(string $templateId, string $projectRoot): array
{
    $catalog = birkly_demo_starter_catalog();
    if (!isset($catalog[$templateId])) {
        return ['success' => false, 'message' => 'Unknown starter template'];
    }

    if ($templateId !== 'blank' && !birkly_demo_starter_is_selectable($projectRoot, $templateId)) {
        return [
            'success' => false,
            'message' => 'This starter template is not available yet',
        ];
    }

    $contentRoot = $projectRoot . '/content/collections';
    $websiteRoot = $projectRoot . '/' . birkly_public_site_dir();

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
        birkly_demo_remove_tree($websiteRoot);
    }

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
    $download = birkly_ops_download_starter_zip($templateId, $tmpZip);
    $extracted = null;
    if ($download !== null) {
        $extracted = birkly_ops_extract_starter_zip($tmpZip, $projectRoot);
        @unlink($tmpZip);
    } else {
        @unlink($tmpZip);
        $localDir = birkly_demo_local_starter_dir($templateId);
        if ($localDir !== null) {
            $extracted = birkly_demo_install_from_local_dir($localDir, $projectRoot);
            $extracted['source'] = 'bundled';
        }
    }

    if ($extracted === null) {
        return [
            'success' => false,
            'template' => $templateId,
            'installed_collections' => [],
            'demo_website' => false,
            'removed' => $removed,
            'message' => 'Starter template is not available. Check your network connection and Ops configuration.',
        ];
    }

    if (!$extracted['success']) {
        return [
            'success' => false,
            'template' => $templateId,
            'installed_collections' => [],
            'demo_website' => false,
            'removed' => $removed,
            'message' => $extracted['message'],
        ];
    }

    $primary = birkly_demo_starter_primary_collection($templateId);
    $primaryInstalled = $primary === null || in_array($primary, $extracted['installed_collections'], true);

    return [
        'success' => $primaryInstalled,
        'template' => $templateId,
        'installed_collections' => $extracted['installed_collections'],
        'demo_website' => $extracted['project_site'],
        'project_site' => $extracted['project_site'],
        'removed' => $removed,
        'source' => $extracted['source'] ?? 'ops',
        'message' => $primaryInstalled ? 'Starter template installed' : 'Starter template could not be installed',
    ];
}
