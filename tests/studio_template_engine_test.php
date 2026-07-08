<?php
/**
 * Studio template engine tests (P24-M2).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/plugins/studio/includes/StudioTemplateEngine.php';

test_reset_failures();
echo "Studio template engine tests\n\n";

$engine = new StudioTemplateEngine();

$blogEntries = [
    [
        'title' => 'First Post',
        'slug' => 'first-post',
        'status' => 'published',
        'excerpt' => 'Hello world',
        'created_at' => '2026-01-02T10:00:00+00:00',
        'tags' => ['cms', 'studio'],
    ],
    [
        'title' => 'Draft Post',
        'slug' => 'draft-post',
        'status' => 'draft',
        'excerpt' => 'Hidden',
        'created_at' => '2026-01-01T10:00:00+00:00',
    ],
];

$hero = [
    'title' => 'Hero',
    'content' => [
        ['value' => 'Welcome'],
        ['value' => 'Subtitle here'],
    ],
];

$data = [
    'blog' => $blogEntries,
    '__entry__:site_elements:hero' => $hero,
];

$loopHtml = <<<'HTML'
{for each entry in 'blog' with status = published ordered by newest}
<article><h2>{ title }</h2><p>{ excerpt }</p></article>
{endfor}
HTML;

$loopOut = $engine->render($loopHtml, $data);
test_assert('loop renders title', str_contains($loopOut, 'First Post'));
test_assert('loop hides draft', !str_contains($loopOut, 'Draft Post'));
test_assert('loop strips directives', !str_contains($loopOut, '{for each'));
test_assert('loop uses endfor not raw end', !preg_match('/\{end\}/', $loopOut));

$getHtml = <<<'HTML'
{from 'site_elements' get entry 'hero'}
<h1>{'content'.0.'value'}</h1>
<p>{'content'.1.'value'}</p>
{endget}
HTML;

$getOut = $engine->render($getHtml, $data);
test_assert('get block renders field', str_contains($getOut, 'Welcome'));
test_assert('get block strips endget', !str_contains($getOut, '{endget}'));

$nestedHtml = <<<'HTML'
{for each entry in 'blog' with status = published ordered by newest}
{if entry has 'tags'}
{for each item in 'tags'}
<span>{ item }</span>
{endfor}
{endif}
{endfor}
HTML;

$nestedOut = $engine->render($nestedHtml, $data);
test_assert('nested tags render', str_contains($nestedOut, 'cms') && str_contains($nestedOut, 'studio'));

$refPath = dirname(__DIR__) . '/reference-projects/blog/website/index.html';
if (is_file($refPath)) {
    $ref = (string) file_get_contents($refPath);
    $refData = [
        'blog' => $blogEntries,
        '__entry__:site_elements:hero' => $hero,
        '__entry__:site_elements:homepage_blog' => [
            'content' => [['value' => 'Latest'], ['value' => 'From the blog']],
        ],
    ];
    $refOut = $engine->render($ref, $refData);
    test_assert('reference index has no raw for loop', !str_contains($refOut, '{for each'));
    test_assert('reference index has no raw endget', !str_contains($refOut, '{endget}'));
}

$requests = StudioTemplateEngine::collectBundleRequests($loopHtml . "\n" . $getHtml);
test_assert('collects list_entries', count(array_filter($requests, fn($r) => ($r['type'] ?? '') === 'list_entries')) >= 1);
test_assert('collects get_entry', count(array_filter($requests, fn($r) => ($r['type'] ?? '') === 'get_entry')) >= 1);

exit(test_finish('studio_template_engine_test'));
