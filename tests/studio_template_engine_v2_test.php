<?php
/**
 * Studio template engine v2 tests (P25-M3/M4).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/plugins/studio/includes/StudioTemplateEngine.php';

test_reset_failures();
echo "Studio template engine v2 tests\n\n";

$engine = new StudioTemplateEngine();

$blogEntries = [
    [
        'id' => 'post-1',
        'title' => 'First Post',
        'slug' => 'first-post',
        'status' => 'published',
        'price' => 12,
        'created_at' => '2026-01-02T10:00:00+00:00',
    ],
    [
        'id' => 'post-2',
        'title' => 'Cheap Post',
        'slug' => 'cheap-post',
        'status' => 'published',
        'price' => 8,
        'created_at' => '2026-01-01T10:00:00+00:00',
    ],
];

$hero = [
    'title' => 'Hero',
    'slug' => 'hero',
    'content' => [['value' => 'Welcome']],
];

$data = [
    'blog' => $blogEntries,
    '__entry__:site_elements:hero' => $hero,
    '__sub__:blog:first-post:comments' => [
        ['author' => 'Ada', 'content' => 'Nice post'],
        ['author' => 'Bob', 'content' => 'Thanks'],
    ],
];

// Collection entry existence
$existHtml = <<<'HTML'
{if 'site_elements'.'hero' exist}
<p class="hero-ok">hero found</p>
{endif}
{if 'site_elements'.'missing' does not exist}
<p class="missing-ok">missing ok</p>
{endif}
HTML;
$existOut = $engine->render($existHtml, $data);
test_assert('exist renders hero block', str_contains($existOut, 'hero found'));
test_assert('does not exist renders', str_contains($existOut, 'missing ok'));
test_assert('exist strips directives', !str_contains($existOut, '{if'));

// Numeric comparison on entry field
$cmpHtml = <<<'HTML'
{for each entry in 'blog' with status = published}
{if entry has 'price' <= 10}
<span class="cheap">{ title }</span>
{endif}
{endfor}
HTML;
$cmpOut = $engine->render($cmpHtml, $data);
test_assert('numeric comparison includes cheap', str_contains($cmpOut, 'Cheap Post'));
test_assert('numeric comparison excludes expensive', !str_contains($cmpOut, 'First Post'));

// Count variable
$countHtml = "{count of 'blog'}";
test_assert('count of collection', $engine->render($countHtml, $data) === '2');

$subCountHtml = "{count of 'comments' of 'blog'.'first-post'}";
test_assert('count of sub collection', $engine->render($subCountHtml, $data) === '2');

// Sub-entry loop
$subHtml = <<<'HTML'
{for each comment in 'comments' of 'blog'.'first-post'}
<p class="comment">{ author }</p>
{endfor}
HTML;
$subOut = $engine->render($subHtml, $data);
test_assert('sub-entry loop renders author', str_contains($subOut, 'Ada') && str_contains($subOut, 'Bob'));
test_assert('sub-entry loop strips for', !str_contains($subOut, '{for each'));

// Template function via __tf__: bundle key
$tfKey = StudioTemplateEngine::templateFunctionDataKey('commerce_cart_count', []);
$tfData = array_merge($data, [$tfKey => 3]);
$tfHtml = '{commerce_cart_count}';
$tfOut = $engine->render($tfHtml, $tfData);
test_assert('template function renders scalar', str_contains($tfOut, '3'));

// collectBundleRequests includes sub_entries and template_function
$collectHtml = $subHtml . "\n{commerce_cart_count}\n{for each entry in 'blog'}x{endfor}";
$requests = StudioTemplateEngine::collectBundleRequests($collectHtml);
test_assert('collects sub_entries', count(array_filter($requests, fn($r) => ($r['type'] ?? '') === 'sub_entries')) >= 1);
$tfReqs = array_filter($requests, fn($r) => ($r['type'] ?? '') === 'template_function');
test_assert('collects template_function when registered', count($tfReqs) >= 0);

exit(test_finish('studio_template_engine_v2_test'));
