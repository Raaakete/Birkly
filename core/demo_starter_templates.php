<?php
/**
 * Starter template packs for onboarding (demo collections + demo website).
 */

declare(strict_types=1);

function birkly_demo_starter_catalog(): array
{
    return [
        'blog' => [
            'label' => 'Blog starter',
            'description' => 'Blog posts, pages, and a sample front-end site.',
            'collections' => ['blog', 'about', 'contact', 'services', 'site_elements'],
        ],
        'shop' => [
            'label' => 'Shop starter',
            'description' => 'Product catalog with a storefront demo site.',
            'collections' => ['products', 'about', 'contact', 'site_elements'],
        ],
        'business' => [
            'label' => 'Business starter',
            'description' => 'Services, team, and a company site demo.',
            'collections' => ['services', 'about', 'contact', 'site_elements'],
        ],
        'docs' => [
            'label' => 'Documentation starter',
            'description' => 'Help articles and a docs-style demo site.',
            'collections' => ['docs', 'about', 'site_elements'],
        ],
        'blank' => [
            'label' => 'Start blank',
            'description' => 'No demo collections or demo website.',
            'collections' => [],
        ],
    ];
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

function birkly_demo_copy_tree(string $src, string $dest): bool
{
    if (!is_dir($src)) {
        return false;
    }
    if (!is_dir($dest) && !mkdir($dest, 0755, true)) {
        return false;
    }
    $items = array_diff(scandir($src) ?: [], ['.', '..']);
    foreach ($items as $item) {
        $from = $src . '/' . $item;
        $to = $dest . '/' . $item;
        if (is_dir($from)) {
            if (!birkly_demo_copy_tree($from, $to)) {
                return false;
            }
        } elseif (!copy($from, $to)) {
            return false;
        }
    }
    return true;
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

function birkly_demo_install_starter_template(string $templateId, string $projectRoot): array
{
    $catalog = birkly_demo_starter_catalog();
    if (!isset($catalog[$templateId])) {
        return ['success' => false, 'message' => 'Unknown starter template'];
    }

    $pack = $catalog[$templateId];
    $contentRoot = $projectRoot . '/content/collections';
    $websiteRoot = $projectRoot . '/demo-website';
    $packRoot = $projectRoot . '/demo-content/' . $templateId;

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
        return [
            'success' => true,
            'template' => $templateId,
            'installed_collections' => [],
            'demo_website' => false,
            'removed' => $removed,
            'message' => 'Starter content cleared',
        ];
    }

    $installed = [];
    $srcCollections = $packRoot . '/collections';
    if (is_dir($srcCollections)) {
        foreach ($pack['collections'] as $slug) {
            $from = $srcCollections . '/' . $slug;
            $to = $contentRoot . '/' . $slug;
            if (is_dir($from) && birkly_demo_copy_tree($from, $to)) {
                $installed[] = $slug;
            }
        }
    }

    $hasWebsite = false;
    $srcWebsite = $packRoot . '/website';
    if (is_dir($srcWebsite)) {
        $hasWebsite = birkly_demo_copy_tree($srcWebsite, $websiteRoot);
    }

    return [
        'success' => count($installed) > 0 || $templateId === 'blank',
        'template' => $templateId,
        'installed_collections' => $installed,
        'demo_website' => $hasWebsite,
        'removed' => $removed,
        'message' => 'Starter template installed',
    ];
}
