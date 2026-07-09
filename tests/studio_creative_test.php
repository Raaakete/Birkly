<?php
/**
 * Studio creative suite tests (P26-M14).
 */

$root = dirname(__DIR__);
require_once $root . '/tests/bootstrap.php';
require_once $root . '/plugins/studio/init.php';
require_once $root . '/plugins/studio/includes/StudioCreative.php';
require_once $root . '/plugins/studio/includes/StudioFigmaImporter.php';
require_once $root . '/plugins/studio/includes/StudioProjectRepository.php';
require_once $root . '/plugins/studio/includes/StudioCompiler.php';

$fail = 0;
$assert = static function (string $label, bool $ok) use (&$fail): void {
    if ($ok) {
        echo "  OK: $label\n";
        return;
    }
    echo "  FAIL: $label\n";
    $fail++;
};

// Animation CSS
$css = StudioCreative::buildAnimationCss([[
    'id' => 'a1',
    'name' => 'fadeUp',
    'targetId' => 'el-hero',
    'trigger' => 'scroll',
    'duration' => 500,
    'keyframes' => ['0%' => ['opacity' => '0'], '100%' => ['opacity' => '1']],
]]);
$assert('keyframes emitted', str_contains($css, '@keyframes fadeUp'));
$assert('scroll pending class', str_contains($css, '[data-studio-el="el-hero"].studio-anim-ready'));

// Popups HTML
$popHtml = StudioCreative::buildPopupsHtml([[
    'id' => 'popup-1',
    'title' => 'Welcome',
    'body' => '<p>Hi</p>',
    'trigger' => 'click',
    'clickTargetId' => 'btn-open',
    'ariaLabel' => 'Welcome dialog',
]]);
$assert('popup role=dialog', str_contains($popHtml, 'role="dialog"'));
$assert('popup aria-modal', str_contains($popHtml, 'aria-modal="true"'));
$assert('popup close button', str_contains($popHtml, 'data-popup-close'));

// Funnel
$funnelHtml = StudioCreative::buildFunnelHtml([
    'enabled' => true,
    'title' => 'Checkout',
    'steps' => [
        ['slug' => 'index', 'label' => 'Cart'],
        ['slug' => 'pay', 'label' => 'Pay'],
    ],
], 'demo-shop', 'index');
$assert('funnel nav', str_contains($funnelHtml, 'studio-funnel'));
$assert('funnel active step', str_contains($funnelHtml, 'studio-funnel-step--active'));
$assert('funnel link to pay', str_contains($funnelHtml, '/pay'));

// Figma import
$figma = StudioFigmaImporter::import([
    'frames' => [[
        'name' => 'Hero',
        'type' => 'FRAME',
        'fills' => [['color' => ['r' => 0.05, 'g' => 0.39, 'b' => 0.99, 'a' => 1]]],
        'children' => [[
            'type' => 'TEXT',
            'characters' => 'Hello Figma',
            'style' => ['fontSize' => 32],
        ]],
    ]],
]);
$assert('figma sections', count($figma['sections']) === 1);
$assert('figma text content', str_contains(json_encode($figma['sections']), 'Hello Figma'));
$assert('figma tokens', !empty($figma['tokens']['colors']));

// Compiler integration
$tmp = sys_get_temp_dir() . '/studio-creative-test-' . getmypid();
@mkdir($tmp, 0755, true);
$repo = new StudioProjectRepository($tmp);
$compiler = new StudioCompiler($repo);
$project = $repo->createProject('Creative Test', 'minimal', 'creative-test');
$canvas = $repo->getPageCanvas($project['id'], 'index');
$canvas['creative'] = [
    'animations' => [[
        'id' => 'anim-1',
        'name' => 'slideIn',
        'targetId' => 'el-slide',
        'trigger' => 'scroll',
        'duration' => 400,
        'keyframes' => ['0%' => ['opacity' => '0'], '100%' => ['opacity' => '1']],
    ]],
    'popups' => [[
        'id' => 'pop-1',
        'title' => 'Offer',
        'body' => '<p>Save 10%</p>',
        'trigger' => 'click',
        'clickTargetId' => 'el-cta',
    ]],
];
$canvas['sections'] = [[
    'id' => 'sec-1',
    'classes' => ['studio-section'],
    'layout' => ['type' => 'grid', 'columns' => 1, 'gap' => '1rem'],
    'rows' => [[
        'columns' => [[
            'elements' => [
                ['id' => 'el-slide', 'type' => 'text', 'tag' => 'p', 'content' => 'Animated'],
                ['id' => 'el-cta', 'type' => 'button', 'content' => 'Open', 'href' => '#'],
            ],
        ]],
    ]],
]];
$repo->savePageCanvas($project['id'], 'index', $canvas);
StudioCreative::writeFunnel($repo->projectDir($project['id']), [
    'enabled' => true,
    'steps' => [
        ['slug' => 'index', 'label' => 'Start'],
        ['slug' => 'done', 'label' => 'Done'],
    ],
]);
$html = $compiler->compilePage($project['id'], 'index');
$assert('compiled creative script', str_contains($html, 'studio-creative-runtime.js'));
$assert('compiled creative config', str_contains($html, '__STUDIO_CREATIVE__'));
$assert('compiled data-studio-el', str_contains($html, 'data-studio-el="el-slide"'));
$assert('compiled popup markup', str_contains($html, 'data-studio-popup="pop-1"'));
$assert('compiled funnel stepper', str_contains($html, 'studio-funnel'));

$creativeCssPath = $repo->projectDir($project['id']) . '/styles/creative.css';
$assert('creative.css written', is_file($creativeCssPath));
$creativeCss = is_file($creativeCssPath) ? file_get_contents($creativeCssPath) : '';
$assert('creative.css has keyframes', str_contains($creativeCss, '@keyframes slideIn'));

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
    fwrite(STDERR, "studio_creative_test: FAIL ($fail)\n");
    exit(1);
}
echo "studio_creative_test: PASS\n";
exit(0);
