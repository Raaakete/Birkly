<?php
/**
 * P20-M1: Client bundle collection fixture tests (node harness, no npm).
 */

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
$harness = $root . '/tests/harness/client-bundle-harness.js';
$node = trim((string) shell_exec('command -v node 2>/dev/null'));

test_reset_failures();
echo "Client bundle fixture tests\n\n";

if ($node === '') {
    echo "SKIP: node not available\n";
    exit(test_finish('client_bundle_fixture_test'));
}

function run_bundle_harness(string $harness, string $fixture): array
{
    $cmd = escapeshellarg($harness) . ' ' . escapeshellarg($fixture);
    $out = shell_exec('node ' . $cmd . ' 2>&1');
    if ($out === null || $out === '') {
        return [];
    }
    $json = json_decode($out, true);
    return is_array($json) ? $json : [];
}

$postFixture = $root . '/tests/fixtures/client-bundle/post-slug.html';
$post = run_bundle_harness($harness, $postFixture);

test_assert('post fixture parses', !empty($post));
test_assert('post route slug', ($post['route']['slug'] ?? '') === 'welcome-to-birkly');
test_assert('post collection blog', ($post['collection'] ?? '') === 'blog');

$hasGetEntry = false;
foreach ($post['requests'] ?? [] as $req) {
    if (($req['type'] ?? '') === 'get_entry' && ($req['slug'] ?? '') === 'welcome-to-birkly') {
        $hasGetEntry = true;
        test_assert('get_entry into key', ($req['into'] ?? '') === '__entry__:blog:welcome-to-birkly');
    }
}
test_assert('post bundle includes get_entry', $hasGetEntry);

$indexFixture = $root . '/tests/fixtures/client-bundle/index-loops.html';
$index = run_bundle_harness($harness, $indexFixture);

$collections = [];
foreach ($index['requests'] ?? [] as $req) {
    if (($req['type'] ?? '') === 'list_entries') {
        $collections[] = $req['collection'] ?? '';
    }
}
test_assert('index lists services', in_array('services', $collections, true));
test_assert('index lists blog', in_array('blog', $collections, true));
test_assert('index lists site_elements', in_array('site_elements', $collections, true));

$hasTfMeta = false;
foreach ($index['requests'] ?? [] as $req) {
    if (($req['type'] ?? '') === 'template_functions_meta') {
        $hasTfMeta = true;
    }
}
test_assert('index includes template_functions_meta in bundle plan', $hasTfMeta);

exit(test_finish('client_bundle_fixture_test'));
