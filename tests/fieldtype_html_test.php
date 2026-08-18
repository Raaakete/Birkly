<?php
/**
 * HTML field detection for template escaping (Content Editor / editor / wysiwyg).
 *
 * Run: php tests/fieldtype_html_test.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/fieldtype_html.php';
require_once dirname(__DIR__) . '/core/storage/FlatFileStorage.php';

$failures = 0;
$total = 0;

function assert_true(string $name, bool $ok, string $detail = ''): void
{
    global $failures, $total;
    $total++;
    echo '  ' . $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '    ' . $detail . "\n";
        }
    }
}

echo "\n[V-HTML-01] schema.json field definitions drive _html_fields\n";

$tmp = sys_get_temp_dir() . '/birkly-html-fields-' . uniqid('', true);
@mkdir($tmp . '/collections/demo-posts', 0755, true);
file_put_contents(
    $tmp . '/collections/demo-posts/collection.json',
    json_encode(['slug' => 'demo-posts', 'name' => 'Demo Posts'], JSON_THROW_ON_ERROR)
);
file_put_contents(
    $tmp . '/collections/demo-posts/schema.json',
    json_encode([
        'fields' => [
            ['name' => 'title', 'type' => 'text'],
            ['name' => 'body', 'type' => 'content-editor'],
            ['name' => 'excerpt', 'type' => 'textarea'],
        ],
    ], JSON_THROW_ON_ERROR)
);

$storage = new FlatFileStorage($tmp);
$names = birkly_page_bundle_html_field_names($storage, ['demo-posts']);
assert_true('reads editor fields from schema.json', $names === ['body'], 'got: ' . json_encode($names));

echo "\n[V-HTML-02] real blog collection exposes content editor field\n";
require_once dirname(__DIR__) . '/core/storage/StorageFactory.php';
$liveStorage = get_storage(dirname(__DIR__) . '/content');
$blogFields = birkly_page_bundle_html_field_names($liveStorage, ['blog']);
assert_true('blog collection includes content field', in_array('content', $blogFields, true), 'got: ' . json_encode($blogFields));

echo "\n[V-HTML-03] page_bundle attaches _html_fields for list_entries request\n";
require_once dirname(__DIR__) . '/core/page_bundle_runner.php';
$bundle = birkly_run_page_bundle_requests($liveStorage, [
    ['type' => 'list_entries', 'into' => 'blog', 'collection' => 'blog'],
], 'en', ['trusted_ssr' => true]);
$htmlFields = $bundle['data']['_html_fields'] ?? [];
assert_true('page_bundle sets _html_fields', is_array($htmlFields) && in_array('content', $htmlFields, true), 'got: ' . json_encode($htmlFields));

echo "\n[V-HTML-04] SSR detail build_data includes _html_fields for body-like fields\n";
require_once dirname(__DIR__) . '/core/project_ssr.php';
$detailTpl = '<p>{\'body\'}</p>';
$detailData = birkly_project_ssr_build_data($detailTpl, ['collection' => 'demo-posts', 'slug' => 'missing'], 'en', $storage);
$detailHtmlFields = $detailData['_html_fields'] ?? [];
assert_true('SSR build_data merges detail collection html fields', in_array('body', $detailHtmlFields, true), 'got: ' . json_encode($detailHtmlFields));

$rendered = birkly_project_ssr_render("<p>{'body'}</p>", [
    'body' => '<p><b>Okay</b>, this is the actual html test</p>',
    '_html_fields' => ['body'],
]);
assert_true(
    'SSR render outputs raw editor HTML when _html_fields present',
    $rendered === '<p><p><b>Okay</b>, this is the actual html test</p></p>',
    'got: ' . var_export($rendered, true)
);

@exec('rm -rf ' . escapeshellarg($tmp));

echo "\n" . ($failures === 0 ? "All $total tests passed.\n" : "$failures of $total tests FAILED.\n");
exit($failures === 0 ? 0 : 1);
