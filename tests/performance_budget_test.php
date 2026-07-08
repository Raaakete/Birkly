<?php
/**
 * P21-M7: Performance budget regression tests.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/storage/StorageFactory.php';
require_once dirname(__DIR__) . '/api/domain_auth.php';
require_once dirname(__DIR__) . '/core/page_bundle_runner.php';

$root = dirname(__DIR__);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
$storage = get_storage($root . '/demo-content/blog');

test_reset_failures();
echo "Performance budget tests\n\n";

$blogIndexRequests = [
    ['type' => 'list_entries', 'into' => 'blog', 'collection' => 'blog'],
    ['type' => 'list_entries', 'into' => 'services', 'collection' => 'services'],
    ['type' => 'list_entries', 'into' => 'site_elements', 'collection' => 'site_elements'],
];

$result = birkly_run_page_bundle_requests($storage, $blogIndexRequests, 'en', ['lean' => true]);
test_assert('bundle status ok', ($result['status'] ?? '') === 'ok');
test_assert('bundle has revision', !empty($result['revision']));
test_assert('blog list present', is_array($result['data']['blog'] ?? null));

$revision = $result['revision'];
$revision2 = birkly_page_bundle_compute_revision($storage, $blogIndexRequests, 'en');
test_assert('revision stable', $revision === $revision2);

// Template-function bundle types are covered by the client harness (web SAPI loads plugins).
$harness = $root . '/tests/harness/client-bundle-harness.js';
$fixture = $root . '/tests/fixtures/client-bundle/index-loops.html';
if (is_file($harness) && is_file($fixture) && trim((string) shell_exec('command -v node 2>/dev/null')) !== '') {
    $out = shell_exec('node ' . escapeshellarg($harness) . ' ' . escapeshellarg($fixture) . ' 2>&1');
    $json = json_decode($out ?: '', true);
    $count = is_array($json['requests'] ?? null) ? count($json['requests']) : 0;
    test_assert('harness collects bundle items', $count >= 3, "count: $count");
    $hasTfMeta = false;
    foreach ($json['requests'] ?? [] as $req) {
        if (($req['type'] ?? '') === 'template_functions_meta') {
            $hasTfMeta = true;
        }
    }
    test_assert('harness includes tf meta in bundle plan', $hasTfMeta);
    test_assert('single network bundle (logical items only)', $count <= 10, "count: $count");
}

// Lean payload: entries should not include internal __ keys when pruned
$first = $result['data']['blog'][0] ?? [];
if ($first) {
    $hasInternal = false;
    foreach (array_keys($first) as $k) {
        if (strpos((string) $k, '__') === 0) {
            $hasInternal = true;
        }
    }
    test_assert('lean list omits __ internal keys', !$hasInternal);
}

exit(test_finish('performance_budget_test'));
