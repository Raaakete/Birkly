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

$requiredField = '<?php if (!empty($field[\'required\'])): ?><span class="required">*</span><?php endif; ?>';
try {
    TemplateSafeRenderer::validate($requiredField);
    test_assert('Allows required field key in generated PHP', true);
} catch (Throwable $e) {
    test_assert('Allows required field key in generated PHP', false, $e->getMessage());
}

$legacyBlock = '<?php require_once __DIR__ . \'/api/storage.php\'; ?>';
$withGenerated = $legacyBlock . '<div><?= htmlspecialchars($title ?? "", ENT_QUOTES, "UTF-8") ?></div>';
try {
    TemplateSafeRenderer::validate($withGenerated, [$legacyBlock]);
    test_assert('Skips validation for trusted legacy PHP blocks', true);
} catch (Throwable $e) {
    test_assert('Skips validation for trusted legacy PHP blocks', false, $e->getMessage());
}

require_once dirname(__DIR__) . '/admin/template_parser.php';

function test_template_compiles(string $path, array $data = []): void
{
    $template = file_get_contents($path);
    $parser = new TemplateParser($data);
    $ref = new ReflectionClass($parser);
    $mask = $ref->getMethod('maskExistingPhpBlocks');
    $mask->setAccessible(true);
    $parse = $ref->getMethod('parseNaturalSyntax');
    $parse->setAccessible(true);
    $unmask = $ref->getMethod('unmaskPhpBlocks');
    $unmask->setAccessible(true);

    [$masked, $blocks] = $mask->invoke($parser, $template);
    $parsed = $parse->invoke($parser, $masked);
    $merged = $unmask->invoke($parser, $parsed, $blocks);
    TemplateSafeRenderer::validate($merged, array_values($blocks));
}

try {
    test_template_compiles(dirname(__DIR__) . '/admin/templates/onboarding.html');
    test_assert('Onboarding template compiles', true);
} catch (Throwable $e) {
    test_assert('Onboarding template compiles', false, $e->getMessage());
}

try {
    test_template_compiles(dirname(__DIR__) . '/admin/templates/entries.html', [
        'entries' => [],
        'fields' => [],
        'collection' => ['slug' => 'blog', 'name' => 'Blog'],
    ]);
    test_assert('Entries template compiles', true);
} catch (Throwable $e) {
    test_assert('Entries template compiles', false, $e->getMessage());
}

try {
    test_template_compiles(dirname(__DIR__) . '/admin/templates/entry-editor.html', [
        'entry' => ['title' => 'Test'],
        'fields' => [['name' => 'body', 'type' => 'content-editor', 'required' => true]],
        'collection' => ['slug' => 'blog'],
    ]);
    test_assert('Entry editor template compiles', true);
} catch (Throwable $e) {
    test_assert('Entry editor template compiles', false, $e->getMessage());
}

exit(test_finish('template_safe_renderer_test'));
