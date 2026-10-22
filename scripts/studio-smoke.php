<?php
/**
 * Studio smoke runner (P25-M17).
 */

$root = dirname(__DIR__);
$engine = $root . '/plugins/studio/includes/StudioTemplateEngine.php';
if (!is_file($engine)) {
    echo "[studio-smoke] SKIP: Studio is not installed in plugins/studio\n";
    exit(0);
}
$bootstrap = $root . '/plugins/studio/tests/bootstrap.php';
if (!is_file($bootstrap)) {
    $bootstrap = $root . '/tests/bootstrap.php';
}
if (!is_file($bootstrap)) {
    fwrite(STDERR, "[studio-smoke] FAIL: test bootstrap not found\n");
    exit(1);
}
require_once $bootstrap;
require_once $root . '/plugins/studio/init.php';
require_once $root . '/plugins/studio/includes/StudioProjectRepository.php';
require_once $root . '/plugins/studio/includes/StudioCompiler.php';
require_once $root . '/plugins/studio/includes/StudioTemplateEngine.php';

$tmp = sys_get_temp_dir() . '/studio-smoke-' . getmypid();
@mkdir($tmp, 0755, true);

$fail = 0;
$assert = static function (string $label, bool $ok) use (&$fail): void {
    if ($ok) {
        echo "  OK: $label\n";
        return;
    }
    echo "  FAIL: $label\n";
    $fail++;
};

$repo = new StudioProjectRepository($tmp);
$compiler = new StudioCompiler($repo);
$engine = new StudioTemplateEngine();

$project = $repo->createProject('Smoke Test', 'minimal', 'smoke-test');
$canvas = $repo->getPageCanvas($project['id'], 'index');
$canvas['sections'] = [[
    'id' => 'section-smoke',
    'classes' => ['studio-section', 'blog-section'],
    'layout' => ['type' => 'grid', 'columns' => 1, 'gap' => '1rem'],
    'rows' => [[
        'id' => 'row-smoke',
        'columns' => [[
            'id' => 'col-smoke',
            'width' => '100%',
            'elements' => [[
                'id' => 'bind-smoke',
                'type' => 'binding',
                'bindingType' => 'loop',
                'collection' => 'blog',
                'itemTemplate' => '<article class="post-card"><h3>{ title }</h3></article>',
            ]],
        ]],
    ]],
]];
$canvas['page']['meta'] = [
    'title' => 'Smoke Page',
    'description' => 'Studio smoke test page',
];
$repo->savePageCanvas($project['id'], 'index', $canvas);

$html = $compiler->compilePage($project['id'], 'index');
$assert('compiler returns html', $html !== '');
$assert('compiled head has meta description', str_contains($html, '<meta name="description"'));
$assert('compiled body has studio-section', str_contains($html, 'studio-section'));

$data = [
    'blog' => [
        ['title' => 'Smoke Post', 'slug' => 'smoke-post', 'status' => 'published'],
    ],
];
$rendered = $engine->render($html, $data);
$assert('rendered title present', str_contains($rendered, 'Smoke Post'));
$assert('no raw for loop', !str_contains($rendered, '{for each'));
$assert('no raw endfor', !str_contains($rendered, '{endfor'));
$assert('studio-section class in output', str_contains($rendered, 'studio-section'));

// cleanup
foreach (scandir($tmp) ?: [] as $f) {
    if ($f === '.' || $f === '..') {
        continue;
    }
    $p = $tmp . '/' . $f;
    if (is_dir($p)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($p, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($p);
    }
}
@rmdir($tmp);

if ($fail > 0) {
    fwrite(STDERR, "[studio-smoke] FAIL ($fail assertion(s))\n");
    exit(1);
}

echo "[studio-smoke] All assertions passed\n";
exit(0);
