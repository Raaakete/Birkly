<?php

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';
require_once __DIR__ . '/build_info.php';
require_once __DIR__ . '/csp.php';

/**
 * Emit Discovery admin script tags (wizard, ai-presence, discovery.js).
 * Used from admin/index.php and layout_footer.php so Discovery always receives JS
 * even when the page template omits {include_script} tags on partial deploys.
 */
/**
 * @return array<string, mixed>
 */
function birkly_discovery_deploy_signals(): array
{
    $root = birkly_project_root();
    $discoveryJs = $root . '/admin/js/discovery.js';
    $index = $root . '/admin/index.php';
    $footer = $root . '/admin/partials/layout_footer.php';
    $indexBody = is_readable($index) ? (string) file_get_contents($index) : '';
    $footerBody = is_readable($footer) ? (string) file_get_contents($footer) : '';

    return [
        'discovery_js_on_disk' => is_file($discoveryJs),
        'discovery_js_bytes' => is_file($discoveryJs) ? filesize($discoveryJs) : 0,
        'index_emits_discovery_scripts' => str_contains($indexBody, 'birkly_echo_discovery_admin_script_tags'),
        'footer_self_heal' => str_contains($footerBody, '__DISCOVERY_JS_LOADED__'),
        'assets_helper' => is_file($root . '/core/discovery_admin_assets.php'),
    ];
}

function birkly_echo_discovery_admin_script_tags(): void
{
    static $emitted = false;
    if ($emitted) {
        return;
    }
    $emitted = true;
    $nonce = birkly_csp_nonce_attr();
    foreach (['agent-guide-wizard.js', 'ai-presence.js', 'discovery.js'] as $file) {
        $v = htmlspecialchars(birkly_admin_asset_version('admin/js/' . $file), ENT_QUOTES, 'UTF-8');
        $src = htmlspecialchars(birkly_url('/admin/js/' . $file), ENT_QUOTES, 'UTF-8');
        echo '<script defer src="' . $src . '?v=' . $v . '"' . $nonce . '></script>' . "\n";
    }
}