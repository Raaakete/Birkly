<?php
/**
 * Studio creative smoke runner (P26-M15).
 */

$root = dirname(__DIR__);
require_once $root . '/tests/bootstrap.php';
require_once $root . '/plugins/studio/init.php';
require_once $root . '/plugins/studio/includes/StudioProjectRepository.php';
require_once $root . '/plugins/studio/includes/StudioCompiler.php';
require_once $root . '/plugins/studio/includes/StudioCreative.php';
require_once $root . '/plugins/studio/includes/StudioFigmaImporter.php';

$tmp = sys_get_temp_dir() . '/studio-creative-smoke-' . getmypid();
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
$project = $repo->createProject('Creative Smoke', 'minimal', 'creative-smoke');

$figma = StudioFigmaImporter::import(json_decode(file_get_contents($root . '/tests/fixtures/studio/figma-sample.json'), true));
$canvas = $repo->getPageCanvas($project['id'], 'index');
foreach ($figma['sections'] as $section) {
    $canvas['sections'][] = $section;
}
$canvas['creative'] = [
    'animations' => [[
        'id' => 'anim-scroll',
        'name' => 'reveal',
        'targetId' => 'el-reveal',
        'trigger' => 'scroll',
        'scrollOffset' => 0.2,
        'duration' => 700,
        'keyframes' => [
            '0%' => ['opacity' => '0', 'transform' => 'translateY(24px)'],
            '100%' => ['opacity' => '1', 'transform' => 'translateY(0)'],
        ],
    ]],
    'popups' => [[
        'id' => 'popup-smoke',
        'title' => 'Smoke popup',
        'body' => '<p>Popup body</p>',
        'trigger' => 'click',
        'clickTargetId' => 'el-open-popup',
        'ariaLabel' => 'Smoke popup dialog',
    ]],
];
$canvas['sections'][] = [
    'id' => 'sec-interact',
    'classes' => ['studio-section'],
    'layout' => ['type' => 'grid', 'columns' => 1, 'gap' => '1rem'],
    'rows' => [[
        'columns' => [[
            'elements' => [
                ['id' => 'el-reveal', 'type' => 'text', 'tag' => 'p', 'content' => 'Scroll reveal'],
                ['id' => 'el-open-popup', 'type' => 'button', 'content' => 'Open popup', 'href' => '#'],
            ],
        ]],
    ]],
];
$repo->savePageCanvas($project['id'], 'index', $canvas);
$repo->addPage($project['id'], 'Step two', 'step-two');

StudioCreative::writeFunnel($repo->projectDir($project['id']), [
    'enabled' => true,
    'title' => 'Smoke funnel',
    'steps' => [
        ['slug' => 'index', 'label' => 'Step 1'],
        ['slug' => 'step-two', 'label' => 'Step 2'],
    ],
]);

$html = $compiler->compilePage($project['id'], 'index');
$assert('scroll animation in config', str_contains($html, 'scroll') && str_contains($html, '__STUDIO_CREATIVE__'));
$assert('popup in HTML', str_contains($html, 'data-studio-popup="popup-smoke"'));
$assert('runtime script linked', str_contains($html, 'studio-creative-runtime.js'));
$assert('funnel stepper on page', str_contains($html, 'studio-funnel-step'));
$assert('figma section class', str_contains($html, 'studio-figma-import'));

$runtimePath = $root . '/plugins/studio/assets/studio-creative-runtime.js';
$runtime = is_file($runtimePath) ? file_get_contents($runtimePath) : '';
$assert('runtime has IntersectionObserver', str_contains($runtime, 'IntersectionObserver'));
$assert('runtime Esc handler', str_contains($runtime, "e.key === 'Escape'"));
$assert('runtime focus trap', str_contains($runtime, 'trapFocus'));

// cleanup
foreach (scandir($tmp) ?: [] as $f) {
    if ($f === '.' || $f === '..') continue;
    $p = $tmp . '/' . $f;
    if (is_dir($p)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($p, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($p);
    }
}
@rmdir($tmp);

if ($fail > 0) {
    fwrite(STDERR, "[studio-creative-smoke] FAIL ($fail assertion(s))\n");
    exit(1);
}

echo "[studio-creative-smoke] All assertions passed\n";
exit(0);
