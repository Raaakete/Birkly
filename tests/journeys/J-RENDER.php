<?php
/**
 * Reference demo render journeys — verifies client templating with live CMS data.
 * Run: php tests/journeys/J-RENDER.php (or via journey-runner)
 */

require_once __DIR__ . '/_helpers.php';

$root = dirname(__DIR__, 2);
$harness = $root . '/tests/harness/client-template-harness.js';
$node = trim((string) shell_exec('command -v node 2>/dev/null'));

function journey_client_render(string $harness, string $template, array $data): array
{
    if (!is_file($harness)) {
        return ['ok' => false, 'error' => 'harness missing'];
    }
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

return [
    'id' => 'J-RENDER',
    'name' => 'Reference demo client render',
    'server' => 'dev',
    'run' => function (array $ctx) use ($root, $harness, $node): void {
        if ($node === '') {
            journey_assert('node available for render harness', false, 'install node');
            journey_finish();
            return;
        }

        journey_prepare_reference_demo('blog');
        $base = $ctx['base_url'];

        $bundle = journey_page_bundle($base, [
            ['type' => 'list_entries', 'into' => 'blog', 'collection' => 'blog'],
        ]);
        journey_assert('blog bundle ok', ($bundle['status'] ?? '') === 'ok');
        $entries = $bundle['data']['blog'] ?? [];
        journey_assert('blog has entries for render', count($entries) > 0);

        $blogTplPath = $root . '/reference-projects/blog/website/blog.html';
        journey_assert('blog template exists', is_file($blogTplPath));
        $html = file_get_contents($blogTplPath);
        preg_match('/<main[^>]*>(.*)<\/main>/s', $html, $m);
        $template = $m[1] ?? '';
        journey_assert('blog main template extracted', $template !== '');

        $render = journey_client_render($harness, $template, ['blog' => $entries]);
        journey_assert('blog render harness ok', ($render['ok'] ?? false), json_encode($render));
        $out = (string) ($render['output'] ?? '');
        journey_assert('blog render has post cards', substr_count($out, 'post-card') >= 1);
        journey_assert('blog render no raw for loop', strpos($out, '{for each') === false);
        journey_assert('blog render includes entry title', strpos($out, (string) ($entries[0]['title'] ?? '')) !== false);

        journey_prepare_reference_demo('coffee');
        $coffeeBundle = journey_page_bundle($base, [
            ['type' => 'get_entry', 'into' => 'product', 'collection' => 'products', 'slug' => 'ethiopian-single-origin'],
            [
                'type' => 'sub_entries',
                'into' => 'variants',
                'parent_collection' => 'products',
                'parent_entry_id' => 'coffee-ethiopian',
                'sub_collection' => 'variants',
            ],
        ]);
        journey_assert('coffee variant bundle ok', ($coffeeBundle['status'] ?? '') === 'ok');
        $variants = $coffeeBundle['data']['variants'] ?? [];
        journey_assert('coffee variants for render', count($variants) > 0);

        $productTplPath = $root . '/reference-projects/coffee/website/product.html';
        preg_match('/<main[^>]*>(.*)<\/main>/s', file_get_contents($productTplPath), $pm);
        $productTpl = $pm[1] ?? '';
        $productData = array_merge($coffeeBundle['data']['product'] ?? [], [
            'entry' => $coffeeBundle['data']['product'] ?? [],
            'variants' => $variants,
        ]);
        $productRender = journey_client_render($harness, $productTpl, $productData);
        journey_assert('product render harness ok', ($productRender['ok'] ?? false), json_encode($productRender));
        $pout = (string) ($productRender['output'] ?? '');
        journey_assert('product render variant rows', substr_count($pout, 'variant-row') >= 1);
        journey_assert('product render variant id in form', strpos($pout, (string) ($variants[0]['id'] ?? '')) !== false);
        journey_assert('product render shows price', preg_match('/\d+\.\d{2}/', $pout) === 1);
        journey_assert('product render no raw template tags', strpos($pout, '{ variant.id }') === false);

        journey_prepare_reference_demo('yoga');
        $yogaBundle = journey_page_bundle($base, [
            ['type' => 'template_function', 'into' => 'upcoming', 'name' => 'upcoming_events', 'args' => [6]],
        ]);
        journey_assert('yoga upcoming bundle ok', ($yogaBundle['status'] ?? '') === 'ok');
        $upcoming = $yogaBundle['data']['upcoming'] ?? [];
        journey_assert('yoga upcoming for render', count($upcoming) > 0);

        $yogaTplPath = $root . '/reference-projects/yoga/website/index.html';
        preg_match('/<main[^>]*>(.*)<\/main>/s', file_get_contents($yogaTplPath), $ym);
        $yogaTpl = $ym[1] ?? '';
        $yogaRender = journey_client_render($harness, $yogaTpl, ['upcoming' => $upcoming]);
        journey_assert('yoga render harness ok', ($yogaRender['ok'] ?? false), json_encode($yogaRender));
        $yout = (string) ($yogaRender['output'] ?? '');
        journey_assert('yoga render lists events', strpos($yout, (string) ($upcoming[0]['title'] ?? '')) !== false);
        journey_assert('yoga render no raw tags', strpos($yout, '{for each') === false);

        journey_prepare_reference_demo('blog');
        $postBundle = journey_page_bundle($base, [
            ['type' => 'get_entry', 'into' => 'entry', 'collection' => 'blog', 'slug' => 'welcome-to-birkly'],
        ]);
        journey_assert('blog post bundle ok', ($postBundle['status'] ?? '') === 'ok');
        $postEntry = $postBundle['data']['entry'] ?? [];
        journey_assert('blog post entry for render', ($postEntry['title'] ?? '') !== '');

        $postTplPath = $root . '/reference-projects/blog/website/post.html';
        preg_match('/<main[^>]*>(.*)<\/main>/s', file_get_contents($postTplPath), $postm);
        $postTpl = $postm[1] ?? '';
        $postData = array_merge($postEntry, ['entry' => $postEntry, 'tags' => $postEntry['tags'] ?? []]);
        $postRender = journey_client_render($harness, $postTpl, $postData);
        journey_assert('post detail render harness ok', ($postRender['ok'] ?? false), json_encode($postRender));
        $postOut = (string) ($postRender['output'] ?? '');
        journey_assert('post detail has html content', strpos($postOut, '<p>') !== false);
        journey_assert('post detail has tag text', strpos($postOut, 'cms') !== false);
        journey_assert('post detail no raw placeholders', strpos($postOut, '{ content }') === false && strpos($postOut, '{item}') === false);

        journey_prepare_reference_demo('yoga');
        $eventBundle = journey_page_bundle($base, [
            ['type' => 'get_entry', 'into' => 'entry', 'collection' => 'events', 'slug' => 'sunrise-vinyasa'],
        ]);
        journey_assert('yoga event bundle ok', ($eventBundle['status'] ?? '') === 'ok');
        $eventEntry = $eventBundle['data']['entry'] ?? [];
        journey_assert('yoga event entry for render', ($eventEntry['title'] ?? '') !== '');

        $eventTplPath = $root . '/reference-projects/yoga/website/event.html';
        preg_match('/<main[^>]*>(.*)<\/main>/s', file_get_contents($eventTplPath), $evm);
        $eventTpl = $evm[1] ?? '';
        $eventData = array_merge($eventEntry, ['entry' => $eventEntry]);
        $eventRender = journey_client_render($harness, $eventTpl, $eventData);
        journey_assert('event detail render harness ok', ($eventRender['ok'] ?? false), json_encode($eventRender));
        $eventOut = (string) ($eventRender['output'] ?? '');
        journey_assert('event detail has description', strlen(trim(strip_tags($eventOut))) > 50);
        journey_assert('event detail no raw description tag', strpos($eventOut, '{ description }') === false);
        journey_assert('event detail has rsvp form', strpos($eventOut, 'data-birkly-action="rsvp"') !== false);
    },
];
