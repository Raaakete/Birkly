<?php
/**
 * Admin CSS bundle for built-in server (avoids @import request waterfalls).
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/admin_css_bundle.php';

$failures = 0;
$assert = static function (bool $cond, string $msg) use (&$failures): void {
    if ($cond) {
        echo "ok - {$msg}\n";
        return;
    }
    echo "FAIL - {$msg}\n";
    $failures++;
};

$paths = birkly_admin_css_bundle_paths();
$imports = birkly_admin_css_import_list($paths['hub']);
$assert(count($imports) >= 20, 'style.css declares many @import parts');

$built = birkly_build_admin_css_bundle();
$assert($built && is_file($paths['bundle']), 'bundle file is written');
$assert(birkly_admin_css_bundle_is_fresh(), 'bundle is fresh after build');

$bundle = (string) file_get_contents($paths['bundle']);
$assert(str_contains($bundle, 'core/variables.css') || str_contains($bundle, '--bg'), 'bundle contains admin variables');

$layoutHead = (string) file_get_contents(dirname(__DIR__) . '/admin/partials/layout_head.php');
$assert(str_contains($layoutHead, 'theme_early_init.php'), 'layout head runs theme before CSS');
$assert(str_contains($layoutHead, 'birkly_admin_main_stylesheet_url'), 'layout head uses bundle-aware stylesheet URL');

@unlink($paths['bundle']);

exit($failures === 0 ? 0 : 1);
