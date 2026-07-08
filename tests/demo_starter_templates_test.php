<?php
/**
 * Starter template install — per-template collections + demo website.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../core/demo_starter_templates.php';

test_reset_failures();
echo "Demo starter templates tests\n\n";

$root = dirname(__DIR__);
$contentRoot = $root . '/content/collections';
$websiteRoot = $root . '/demo-website';
$backupCollections = sys_get_temp_dir() . '/birkly-starter-collections-' . getmypid();
$backupWebsite = sys_get_temp_dir() . '/birkly-starter-website-' . getmypid();

function birkly_test_backup_dir_if_exists(string $src, string $dest): void
{
    if (is_dir($src)) {
        exec('cp -a ' . escapeshellarg($src) . ' ' . escapeshellarg($dest));
    }
}

function birkly_test_restore_dir(string $src, string $dest): void
{
    if (is_dir($dest)) {
        exec('rm -rf ' . escapeshellarg($dest));
    }
    if (is_dir($src)) {
        exec('cp -a ' . escapeshellarg($src) . ' ' . escapeshellarg($dest));
        exec('rm -rf ' . escapeshellarg($src));
    }
}

try {
    birkly_test_backup_dir_if_exists($contentRoot, $backupCollections);
    birkly_test_backup_dir_if_exists($websiteRoot, $backupWebsite);

    $catalog = birkly_demo_starter_catalog();
    test_assert('catalog includes shop', isset($catalog['shop']));
    test_assert('catalog includes blank', isset($catalog['blank']));
    test_assert('blog is selectable in dev tree', birkly_demo_starter_is_selectable($root, 'blog'));
    test_assert('blank is selectable', birkly_demo_starter_is_selectable($root, 'blank'));
    test_assert('shop is not selectable yet', !birkly_demo_starter_is_selectable($root, 'shop'));

    $blog = birkly_demo_install_starter_template('blog', $root);
    test_assert('blog install succeeds', $blog['success'] === true);
    test_assert('blog installs blog collection', is_dir($contentRoot . '/blog'));

    $shop = birkly_demo_install_starter_template('shop', $root);
    test_assert('shop install blocked while coming soon', $shop['success'] === false);

    $blank = birkly_demo_install_starter_template('blank', $root);
    test_assert('blank install succeeds', $blank['success'] === true);
    test_assert('blank removes collections', !is_dir($contentRoot . '/blog'));
    test_assert('blank removes demo website', !is_dir($websiteRoot));
} finally {
    birkly_test_restore_dir($backupCollections, $contentRoot);
    birkly_test_restore_dir($backupWebsite, $websiteRoot);
}

exit(test_finish('demo_starter_templates_test'));
