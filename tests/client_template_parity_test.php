<?php
/**
 * P20-M10: Client template parity tests (sub-collection loops, count of).
 * Run: php tests/client_template_parity_test.php
 */

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
$harness = $root . '/tests/harness/client-template-harness.js';
$node = trim((string) shell_exec('command -v node 2>/dev/null'));

test_reset_failures();
echo "Client template parity tests\n\n";

if ($node === '') {
    echo "SKIP: node not available\n";
    exit(test_finish('client_template_parity_test'));
}

function client_render(string $harness, string $template, array $data): array
{
    $payload = json_encode(['template' => $template, 'data' => $data], JSON_UNESCAPED_UNICODE);
    $cmd = 'echo ' . escapeshellarg($payload) . ' | node ' . escapeshellarg($harness) . ' 2>/dev/null';
    $out = shell_exec($cmd);
    if ($out === null || $out === '') {
        return ['ok' => false, 'error' => 'empty harness output'];
    }
    $lines = array_filter(array_map('trim', explode("\n", trim($out))));
    $last = end($lines);
    $json = json_decode($last, true);
    return is_array($json) ? $json : ['ok' => false, 'error' => $out];
}

$blogData = [
    'blog' => [
        ['id' => 'e1', 'slug' => 'hello-world', 'title' => 'Hello World'],
        ['id' => 'e2', 'slug' => 'second-post', 'title' => 'Second Post'],
    ],
    '__sub__:blog:hello-world:comments' => [
        ['id' => 'c1', 'author' => 'Alice', 'content' => 'Great post'],
        ['id' => 'c2', 'author' => 'Bob', 'content' => 'Thanks for sharing'],
    ],
];

$r = client_render($harness, "{count of 'blog'}", $blogData);
test_assert('count of collection', ($r['ok'] ?? false) && ($r['output'] ?? '') === '2', 'got: ' . json_encode($r));

$tpl = "{for each entry in 'comments' of 'blog'.'hello-world'}{'author'}: {'content'} {endfor}";
$r = client_render($harness, $tpl, $blogData);
test_assert('sub-collection of parent slug', ($r['ok'] ?? false)
    && strpos($r['output'] ?? '', 'Alice: Great post') !== false
    && strpos($r['output'] ?? '', 'Bob: Thanks for sharing') !== false,
    'got: ' . json_encode($r));

$currentData = [
    'blog' => $blogData['blog'],
    'entry' => ['id' => 'e1', 'slug' => 'hello-world', 'title' => 'Hello World'],
    'comments' => $blogData['__sub__:blog:hello-world:comments'],
];
$tplCurrent = "{for each entry in 'comments' of 'blog'.current}{'author'}{endfor}";
$r = client_render($harness, $tplCurrent, $currentData);
test_assert('sub-collection of parent.current', ($r['ok'] ?? false)
    && strpos($r['output'] ?? '', 'Alice') !== false
    && strpos($r['output'] ?? '', 'Bob') !== false,
    'got: ' . json_encode($r));

$r = client_render($harness, "{count of 'comments' of 'blog'.'hello-world'}", $blogData);
test_assert('count of sub-collection', ($r['ok'] ?? false) && ($r['output'] ?? '') === '2', 'got: ' . json_encode($r));

exit(test_finish('client_template_parity_test'));
