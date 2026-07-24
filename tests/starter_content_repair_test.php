<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/starter_content_repair.php';

function assert_true(string $label, bool $condition): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: $label\n");
        exit(1);
    }
    echo "OK: $label\n";
}

$tmpRoot = sys_get_temp_dir() . '/birkly-starter-repair-' . getmypid();
@mkdir($tmpRoot . '/content/collections/about/entries', 0755, true);

$bloated = [
    'id' => 'about-page',
    'status' => 'published',
    'slug' => 'about',
    'en' => [
        'title' => "Hello, I'm June.",
        'slug' => 'about',
        'content' => '<p class="statement">Intro</p><div class="principle"><span class="principle-num">01</span></div>',
        'status' => 'published',
    ],
];

$entryPath = $tmpRoot . '/content/collections/about/entries/about-page.json';
file_put_contents($entryPath, json_encode($bloated, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

$first = birkly_run_starter_content_repairs($tmpRoot);
assert_true('repair runs once', in_array('marsk_about_page_v1', $first['repaired'], true));

$repaired = json_decode(file_get_contents($entryPath) ?: '', true);
assert_true('content trimmed', !str_contains((string) ($repaired['en']['content'] ?? ''), 'principle-num'));
assert_true('art_class added', ($repaired['en']['art_class'] ?? '') === 'art--02');
assert_true('caption added', ($repaired['en']['caption'] ?? '') === 'Early morning, studio window');

$second = birkly_run_starter_content_repairs($tmpRoot);
assert_true('repair skipped when already fixed', in_array('marsk_about_page_v1', $second['skipped'], true));

@unlink($entryPath);
@rmdir($tmpRoot . '/content/collections/about/entries');
@rmdir($tmpRoot . '/content/collections/about');
@rmdir($tmpRoot . '/content/collections');
@rmdir($tmpRoot . '/content');
@rmdir($tmpRoot);

echo "starter_content_repair_test: all passed\n";
