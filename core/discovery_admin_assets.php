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
        echo '<script src="' . $src . '?v=' . $v . '"' . $nonce . '></script>' . "\n";
    }
}