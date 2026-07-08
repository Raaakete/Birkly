<?php
/**
 * Studio compiler v2 styles tests (P23-M15).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/plugins/studio/init.php';
require_once dirname(__DIR__) . '/plugins/studio/includes/StudioProjectRepository.php';
require_once dirname(__DIR__) . '/plugins/studio/includes/StudioCompiler.php';
require_once dirname(__DIR__) . '/plugins/studio/includes/StudioCssOptimizer.php';

$tmp = sys_get_temp_dir() . '/studio-compiler-v2-' . getmypid();
@mkdir($tmp, 0755, true);

test_reset_failures();
echo "Studio compiler v2 tests\n\n";

$repo = new StudioProjectRepository($tmp);
$compiler = new StudioCompiler($repo);

$project = $repo->createProject('Compiler V2', 'minimal', 'compiler-v2');
$id = $project['id'];

$canvas = $repo->getPageCanvas($id, 'index');
$canvas['version'] = 2;
$canvas['sections'][0]['id'] = 'hero-main';
$canvas['sections'][0]['responsive'] = [
    'default' => ['columns' => 1, 'gap' => '1rem'],
    'desktop' => ['columns' => 3, 'gap' => '1.5rem'],
];
$repo->savePageCanvas($id, 'index', $canvas);

$tokensCss = $compiler->buildTokensCss($id);
test_assert('tokens css has root', str_contains($tokensCss, ':root'));
test_assert('tokens css has primary', str_contains($tokensCss, '--studio-colors-primary'));

$responsiveCss = $compiler->buildResponsiveCss($id);
test_assert('responsive has section selector', str_contains($responsiveCss, 'data-studio-section="hero-main"'));
test_assert('responsive has media query', str_contains($responsiveCss, '@media'));

$html = $compiler->compilePage($id, 'index');
test_assert('compile has data-studio-section', str_contains($html, 'data-studio-section="hero-main"'));

$genPath = $repo->projectDir($id) . '/styles/generated.css';
test_assert('generated.css written', is_file($genPath));

$min = StudioCssOptimizer::minify("/* comment */\n.foo { color: red; }\n");
test_assert('css optimizer strips comments', !str_contains($min, 'comment'));

$repo->deleteProject($id);

function studio_cv2_test_rm($dir) {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $dir . '/' . $f;
        is_dir($p) ? studio_cv2_test_rm($p) : unlink($p);
    }
    rmdir($dir);
}
studio_cv2_test_rm($tmp);

exit(test_finish('studio_compiler_v2_test'));
