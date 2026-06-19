<?php
/**
 * P2-M6-T1: Template safe renderer validation tests.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/admin/template_safe_renderer.php';

test_reset_failures();
echo "Template safe renderer tests\n\n";

$ok = '<div><?= htmlspecialchars($title ?? "", ENT_QUOTES, "UTF-8") ?></div>';
try {
    TemplateSafeRenderer::validate($ok);
    test_assert('Allows compiled echo', true);
} catch (Throwable $e) {
    test_assert('Allows compiled echo', false, $e->getMessage());
}

$bad = '<div><?php eval("evil"); ?></div>';
$caught = false;
try {
    TemplateSafeRenderer::validate($bad);
} catch (InvalidArgumentException $e) {
    $caught = true;
}
test_assert('Rejects eval in PHP block', $caught);

$badInclude = '<div><?php include "/etc/passwd"; ?></div>';
$caught = false;
try {
    TemplateSafeRenderer::validate($badInclude);
} catch (InvalidArgumentException $e) {
    $caught = true;
}
test_assert('Rejects include in PHP block', $caught);

$htmlWithNew = '<p>something new here</p><?= $x ?>';
try {
    TemplateSafeRenderer::validate($htmlWithNew);
    test_assert('Allows "new" in HTML text', true);
} catch (Throwable $e) {
    test_assert('Allows "new" in HTML text', false, $e->getMessage());
}

class TemplateSafeRendererStub
{
    public function renderFieldtype($name, $field)
    {
        return '<span>' . htmlspecialchars($name) . '</span>';
    }
}

$fieldTemplate = '<?= $this->renderFieldtype("text", $field) ?>';
$stub = new TemplateSafeRendererStub();
$output = TemplateSafeRenderer::render($fieldTemplate, $stub, ['field' => []]);
test_assert('Renders $this->renderFieldtype', $output === '<span>text</span>', "got: $output");

exit(test_finish('template_safe_renderer_test'));
