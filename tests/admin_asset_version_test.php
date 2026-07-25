<?php
/**
 * Admin asset cache-bust helpers (Docker / production without .git).
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/birkly_paths.php';
require_once __DIR__ . '/../core/build_info.php';

$failed = 0;

function assert_true(string $label, bool $condition): void
{
    global $failed;
    if (!$condition) {
        echo "FAIL: $label\n";
        $failed++;
        return;
    }
    echo "OK: $label\n";
}

$settingsJs = 'admin/js/settings-page.js';
$version = birkly_admin_asset_version($settingsJs);
assert_true('asset version is non-empty', $version !== '');
assert_true('asset version uses file mtime or release id', ctype_digit($version) || strlen($version) >= 7);

$url = birkly_admin_asset_url('/admin/js/settings-page.js');
assert_true('asset url includes version query', str_contains($url, '?v='));
assert_true('asset url points at settings-page.js', str_contains($url, '/admin/js/settings-page.js'));

$release = birkly_release_identifier();
if (is_readable(birkly_project_root() . '/.git/HEAD')) {
    assert_true('release id present in git checkout', is_string($release) && $release !== '');
}

exit($failed > 0 ? 1 : 0);
