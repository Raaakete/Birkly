<?php
/**
 * DynamicItems template engine: loops, nested blocks, direct access, shelf helpers.
 *
 * Run: php tests/dynamicitems_template_engine_test.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/birkly_template_engine.php';
require_once dirname(__DIR__) . '/core/fieldtype_presentation.php';
birkly_fieldtype_presentation_boot();

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

$contentBlocks = [
    ['type' => 'text', 'name' => 'heading', 'label' => 'Heading', 'value' => 'EN heading'],
    ['type' => 'contenteditor', 'name' => 'body', 'label' => 'Body', 'value' => '<p>EN <strong>rich</strong></p>'],
    ['type' => 'text', 'name' => 'plain', 'label' => 'Plain', 'value' => '<b>not bold</b>'],
    ['type' => 'file', 'name' => 'photo', 'label' => 'Photo', 'value' => '6ae3af8581f3c'],
    [
        'type' => 'dynamicitems',
        'name' => 'q1',
        'label' => 'Question 1',
        'value' => [
            ['type' => 'text', 'name' => 'question', 'value' => 'EN Q1?'],
            ['type' => 'contenteditor', 'name' => 'answer', 'value' => '<p>EN A1</p>'],
        ],
    ],
];

$faqBlocks = [
    [
        'type' => 'dynamicitems',
        'name' => 'q1',
        'label' => 'Question 1',
        'value' => [
            ['type' => 'text', 'name' => 'question', 'value' => 'Q?'],
            ['type' => 'contenteditor', 'name' => 'answer', 'value' => '<p>A</p>'],
        ],
    ],
];

$entry = [
    'title' => 'Hero',
    'content' => $contentBlocks,
];

$ctx = ['entry' => $entry];

echo "\n[DI-01] contenteditor in block loop outputs raw HTML\n";
$tplD = "{for each b in 'content'}{if b.name = 'body'}{b.value}{endif}{endfor}";
$outD = birkly_template_engine_render($tplD, $ctx);
assert_true('body html not escaped', str_contains($outD, '<strong>rich</strong>') && !str_contains($outD, '&lt;strong&gt;'), 'got: ' . $outD);

echo "\n[DI-02] text block value is escaped\n";
$tplText = "{for each b in 'content'}{if b.name = 'plain'}{b.value}{endif}{endfor}";
$outText = birkly_template_engine_render($tplText, $ctx);
assert_true('angle brackets escaped', str_contains($outText, '&lt;b&gt;') && !str_contains($outText, '<b>not bold</b>'), 'got: ' . $outText);

echo "\n[DI-03] nested dynamicitems loop\n";
$tplF = "{for each b in 'content'}{if b.type = 'dynamicitems'}{for each c in b.value}{c.name}={c.value};{endfor}{endif}{endfor}";
$outF = birkly_template_engine_render($tplF, $ctx);
assert_true('nested question present', str_contains($outF, 'question=EN Q1?'), 'got: ' . $outF);
assert_true('nested answer html', str_contains($outF, 'answer=<p>EN A1</p>'), 'got: ' . $outF);

echo "\n[DI-04] dynamicitems_html renders nested children\n";
birkly_fieldtype_presentation_boot();
$tplG = "{dynamicitems_html('content')}";
$outG = birkly_template_engine_render($tplG, $ctx);
assert_true('nested answer in shelf html', str_contains($outG, 'EN A1') && str_contains($outG, 'EN Q1?'), 'got: ' . substr($outG, 0, 400));

echo "\n[DI-05] direct field path by block name\n";
$tplA = "{'content'.'heading'}";
$outA = birkly_template_engine_render($tplA, $ctx);
assert_true('heading direct access', $outA === 'EN heading', 'got: ' . var_export($outA, true));

echo "\n[DI-06] nested direct path\n";
$tplNested = "{'content'.'q1'.'answer'}";
$outNested = birkly_template_engine_render($tplNested, $ctx);
assert_true('nested answer path', str_contains($outNested, '<p>EN A1</p>'), 'got: ' . $outNested);

echo "\n[DI-07] dynamicitem() shelf helper\n";
$tplB = "{dynamicitem('content', 'heading')}";
$outB = birkly_template_engine_render($tplB, $ctx);
assert_true('dynamicitem heading', $outB === 'EN heading', 'got: ' . var_export($outB, true));

echo "\n[DI-08] exist check for block path\n";
$existYes = birkly_template_engine_render("{if 'content'.'heading' exist}yes{endif}", $ctx);
$existNo = birkly_template_engine_render("{if 'content'.'missing' exist}yes{endif}", $ctx);
assert_true('exist true', $existYes === 'yes', 'got: ' . var_export($existYes, true));
assert_true('exist false', $existNo === '', 'got: ' . var_export($existNo, true));

echo "\n[DI-09] media_url resolves block.value path (callable)\n";
$mediaFn = get_template_function('media_url');
$resolved = $mediaFn !== null ? (string) $mediaFn('6ae3af8581f3c') : '';
assert_true('media_url registered and returns string', $mediaFn !== null && is_string($resolved));

$tplE = "{for each b in 'content'}{if b.name = 'photo'}{media_url(b.value)}{endif}{endfor}";
$outE = birkly_template_engine_render($tplE, $ctx);
assert_true('media_url in loop uses id', $outE === '' || str_contains($outE, '/media/6ae3af8581f3c'), 'got: ' . $outE);

echo "\nSummary: {$total} checks, {$failures} failures\n";
exit($failures > 0 ? 1 : 0);
