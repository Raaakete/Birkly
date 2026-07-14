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
    $tmp = tempnam(sys_get_temp_dir(), 'birkly-harness-');
    if ($tmp === false) {
        return ['ok' => false, 'error' => 'temp file failed'];
    }
    file_put_contents($tmp, json_encode(['template' => $template, 'data' => $data], JSON_UNESCAPED_UNICODE));
    $cmd = 'node ' . escapeshellarg($harness) . ' < ' . escapeshellarg($tmp) . ' 2>/dev/null';
    $out = shell_exec($cmd);
    @unlink($tmp);
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

$nestedBlogTpl = <<<'TPL'
{for each entry in 'blog' with status = published ordered by newest}
<article class="post-card">
<h3>{ title }</h3>
{if entry has 'tags'}
<div class="tags">
{for each item in 'tags'}
<span class="tag">{item}</span>
{endfor}
</div>
{endif}
<p class="excerpt">{ excerpt }</p>
</article>
{endfor}
TPL;
$nestedBlogData = [
    'blog' => [
        ['id' => 'e1', 'slug' => 'a', 'title' => 'Post A', 'status' => 'published', 'tags' => ['news'], 'excerpt' => 'First excerpt'],
        ['id' => 'e2', 'slug' => 'b', 'title' => 'Post B', 'status' => 'published', 'tags' => ['update'], 'excerpt' => 'Second excerpt'],
    ],
];
$r = client_render($harness, $nestedBlogTpl, $nestedBlogData);
test_assert('nested blog loop keeps full card markup', ($r['ok'] ?? false)
    && substr_count($r['output'] ?? '', '<article class="post-card">') === 2
    && strpos($r['output'] ?? '', 'First excerpt') !== false
    && strpos($r['output'] ?? '', 'Second excerpt') !== false
    && strpos($r['output'] ?? '', '<span class="tag">news</span>') !== false,
    'got: ' . json_encode($r));

$variantTpl = <<<'TPL'
{for each variant in variants}
<div class="variant-row">
<h3>{ variant.title }</h3>
<p>€{commerce_price_from variant.commerce}</p>
</div>
{endfor}
TPL;
$variantData = [
    'entry' => ['id' => 'parent-1', 'title' => 'Parent'],
    'variants' => [
        [
            'id' => 'var-1',
            'title' => '250g',
            'commerce' => ['purchase_options' => [['id' => 'buy_once', 'price_cents' => 1499]]],
        ],
    ],
];
$r = client_render($harness, $variantTpl, $variantData);
test_assert('variant loop renders scoped commerce price', ($r['ok'] ?? false)
    && strpos($r['output'] ?? '', '250g') !== false
    && strpos($r['output'] ?? '', '14.99') !== false,
    'got: ' . json_encode($r));

$limitTpl = "{for each entry in 'blog' ordered by newest limit 1}{ title }{endfor}";
$r = client_render($harness, $limitTpl, $nestedBlogData);
test_assert('loop limit 1', ($r['ok'] ?? false) && trim($r['output'] ?? '') === 'Post A', 'got: ' . json_encode($r));

exit(test_finish('client_template_parity_test'));
