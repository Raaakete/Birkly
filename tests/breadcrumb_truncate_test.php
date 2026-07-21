<?php
/**
 * Breadcrumb truncate integration tests (P30-M5).
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Breadcrumb truncate tests\n\n";

$root = dirname(__DIR__);

/**
 * Mirror of admin/js/breadcrumb-truncate.js collapsedStartIndex().
 */
function breadcrumb_collapsed_start_index(int $segmentCount, int $minVisible = 2): int
{
    if ($segmentCount <= $minVisible) {
        return 0;
    }
    return $segmentCount - $minVisible;
}

$jsFile = $root . '/admin/js/breadcrumb-truncate.js';
$footerFile = $root . '/admin/partials/layout_footer.php';
$headerCss = $root . '/admin/css/layout/header.css';
$contentCss = $root . '/admin/css/pages/content.css';

test_assert('breadcrumb-truncate.js exists', is_file($jsFile));
test_assert('layout_footer loads breadcrumb-truncate.js', is_file($footerFile) && str_contains(file_get_contents($footerFile), 'breadcrumb-truncate.js'));
test_assert('layout_footer calls BreadcrumbTruncate.init', is_file($footerFile) && str_contains(file_get_contents($footerFile), 'BreadcrumbTruncate.init'));
test_assert('header.css has breadcrumb-ellipsis class', is_file($headerCss) && str_contains(file_get_contents($headerCss), '.breadcrumb-ellipsis'));
test_assert('content.css stacks field-block column', is_file($contentCss) && preg_match('/\.field-block\s*\{[^}]*flex-direction:\s*column/s', file_get_contents($contentCss)) === 1);

test_assert('5 segments collapse start is 3', breadcrumb_collapsed_start_index(5) === 3);
test_assert('2 segments collapse start is 0', breadcrumb_collapsed_start_index(2) === 0);
test_assert('3 segments collapse start is 1', breadcrumb_collapsed_start_index(3) === 1);

$js = is_file($jsFile) ? file_get_contents($jsFile) : '';
test_assert('JS exports Birkly.BreadcrumbTruncate', str_contains($js, 'Birkly.BreadcrumbTruncate'));
test_assert('JS listens for birkly:layout-change', str_contains($js, 'birkly:layout-change'));
test_assert('JS uses ResizeObserver', str_contains($js, 'ResizeObserver'));

exit(test_finish('breadcrumb_truncate_test'));
