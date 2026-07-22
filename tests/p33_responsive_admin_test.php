<?php
/**
 * P33 responsive admin refinement — static asset checks.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

function p33_assert_contains(string $file, string $needle, string $label): void
{
    global $failures, $root;
    $path = $root . '/' . ltrim($file, '/');
    if (!is_file($path)) {
        $failures[] = "$label: missing file $file";
        return;
    }
    $contents = (string) file_get_contents($path);
    if (strpos($contents, $needle) === false) {
        $failures[] = "$label: expected '$needle' in $file";
    }
}

p33_assert_contains('admin/css/components/tables.css', 'width: max-content', 'M1 fields-region scroll width');
p33_assert_contains('admin/css/layout/sidebar.css', 'max-width: 1279px', 'M6 drawer breakpoint');
p33_assert_contains('admin/js/theme.js', 'min-width: 1280px', 'M6 theme drawer breakpoint');
p33_assert_contains('admin/css/components/tabs-v2.css', 'tabs-v2-picker-wrap', 'M3 picker menu affordance');
p33_assert_contains('admin/css/components/tabs-v2.css', 'tabs-v2-picker-icon', 'M3 picker hamburger icon');
p33_assert_contains('admin/templates/settings.html', 'tabs-v2-root', 'M4 settings tabs v2');
p33_assert_contains('admin/partials/sidebar.php', 'admin.trash', 'M14 trash nav');
p33_assert_contains('admin/templates/account.html', 'account-save-btn', 'M5 account sticky save');
p33_assert_contains('docs/reviews/P33-lead-engineer-handoff.md', 'P33-M20', 'M0 handoff doc');

// fields-region must NOT use fit-content
$tables = (string) file_get_contents($root . '/admin/css/components/tables.css');
if (preg_match('/\.fields-(?:head-)?region[\s\S]*?width:\s*fit-content/', $tables)) {
    $failures[] = 'M1: fields-region still uses width: fit-content';
}

// sidebar-body must NOT have overflow-y: auto (except drawer-mode sidebar itself)
$sidebar = (string) file_get_contents($root . '/admin/css/layout/sidebar.css');
if (preg_match('/\.sidebar-body\s*\{[^}]*overflow-y:\s*auto/s', $sidebar)) {
    $failures[] = 'M7: .sidebar-body still has overflow-y: auto';
}

if ($failures !== []) {
    fwrite(STDERR, "P33 responsive tests failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "P33 responsive tests passed (" . count(glob($root . '/docs/reviews/P33-*.md')) . " docs present)\n";
