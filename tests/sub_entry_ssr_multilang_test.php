<?php
/**
 * SSR: multilanguage sub-collection loops (language merge + contenteditor HTML).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/storage/StorageFactory.php';
require_once dirname(__DIR__) . '/core/public_read.php';
require_once dirname(__DIR__) . '/core/page_bundle_runner.php';
require_once dirname(__DIR__) . '/core/birkly_template_engine.php';
require_once dirname(__DIR__) . '/core/project_ssr.php';

$failures = 0;
$tmpRoot = sys_get_temp_dir() . '/birkly-sub-ssr-' . bin2hex(random_bytes(4));
$content = $tmpRoot . '/content';

function assert_true(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    echo $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '  ' . $detail . "\n";
        }
    }
}

function delete_dir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            delete_dir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

$sectionsFields = [
    ['name' => 'section_key', 'type' => 'text'],
    ['name' => 'heading', 'type' => 'text'],
    ['name' => 'lead', 'type' => 'textarea'],
    ['name' => 'body', 'type' => 'contenteditor'],
];

mkdir($content . '/collections/pages/entries/contact-id/sub_entries/sections', 0755, true);
file_put_contents($content . '/collections/pages/collection.json', json_encode([
    'slug' => 'pages',
    'title' => 'Pages',
    'allow_public_read' => true,
    'multilanguage' => [
        'enabled' => true,
        'languages' => ['en', 'da'],
    ],
    'sub_collections' => [
        [
            'slug' => 'sections',
            'schema' => ['fields' => $sectionsFields],
        ],
    ],
], JSON_PRETTY_PRINT));
file_put_contents($content . '/collections/pages/entries/contact-id.json', json_encode([
    'id' => 'contact-id',
    'slug' => 'contact',
    'status' => 'published',
    'title' => 'Contact',
], JSON_PRETTY_PRINT));

$subEntry = [
    'id' => 'sub_next',
    'title' => 'Next section',
    'status' => 'published',
    'section_key' => 'next',
    'order' => 10,
    'heading' => 'Base heading',
    'en' => [
        'heading' => 'English heading',
        'lead' => 'English lead',
        'body' => '<ol class="steps"><li>English step</li></ol>',
    ],
    'da' => [
        'heading' => 'Danish heading',
        'lead' => 'Danish lead',
        'body' => '<ol class="steps"><li>Danish step</li></ol>',
    ],
];
file_put_contents(
    $content . '/collections/pages/entries/contact-id/sub_entries/sections/sub_next.json',
    json_encode($subEntry, JSON_PRETTY_PRINT)
);

StorageFactory::reset();
$storage = StorageFactory::createStorage($content);

$template = "{for each entry in 'sections' of 'pages'.'contact' with 'section_key' = 'next' ordered by order}"
    . "<h2>{'heading'}</h2><p>{'lead'}</p><div class=\"body\">{'body'}</div>{endfor}";

$dataEn = birkly_project_ssr_build_data($template, ['lang' => 'en'], 'en', $storage);
$renderedEn = birkly_template_engine_render($template, $dataEn);

assert_true(
    'SSR EN sub loop uses language block heading',
    is_string($renderedEn) && str_contains($renderedEn, 'English heading'),
    'got: ' . var_export($renderedEn, true)
);
assert_true(
    'SSR EN contenteditor body is raw HTML',
    is_string($renderedEn) && str_contains($renderedEn, '<ol class="steps">')
        && !str_contains($renderedEn, '&lt;ol'),
    'got: ' . var_export($renderedEn, true)
);

$dataDa = birkly_project_ssr_build_data($template, ['lang' => 'da'], 'da', $storage);
$renderedDa = birkly_template_engine_render($template, $dataDa);

assert_true(
    'SSR DA sub loop uses Danish heading',
    is_string($renderedDa) && str_contains($renderedDa, 'Danish heading'),
    'got: ' . var_export($renderedDa, true)
);
assert_true(
    'SSR DA contenteditor body is raw HTML',
    is_string($renderedDa) && str_contains($renderedDa, '<ol class="steps">')
        && !str_contains($renderedDa, '&lt;ol'),
    'got: ' . var_export($renderedDa, true)
);

$listDa = birkly_public_list_sub_entries('pages', 'contact-id', 'sections', null, null, $storage, 'da');
$first = $listDa['data']['entries'][0] ?? [];
assert_true(
    'public_list_sub_entries merges da block',
    ($first['heading'] ?? '') === 'Danish heading',
    'got: ' . json_encode($first)
);

delete_dir($tmpRoot);
StorageFactory::reset();

exit($failures > 0 ? 1 : 0);
