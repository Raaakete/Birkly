<?php
/**
 * Redirect manager template smoke test.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Redirects page template tests\n\n";

$path = dirname(__DIR__) . '/admin/templates/redirects.html';
test_assert('redirects.html exists', is_file($path));

$output = [];
$code = 0;
exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
test_assert('redirects.html PHP syntax valid', $code === 0, implode("\n", $output));

$content = (string) file_get_contents($path);
test_assert('redirects page wires redirects api', strpos($content, '/api/redirects.php?action=list') !== false);
test_assert('redirects page has form and table hooks', strpos($content, 'redirect-form') !== false && strpos($content, 'redirects-table-body') !== false);

exit(test_finish('redirects_page_test'));
