<?php
/**
 * PHP template engine syntax suite (P52-M1) — V-PHP-01..11.
 *
 * Mirrors tests/template-engine/run.js (V-ENG-01..11) against the pure-PHP
 * engine in core/birkly_template_engine.php. Plain PHP assert script
 * (tests/project_ssr_test.php style).
 *
 * Run: php tests/template_engine_php_test.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/birkly_template_engine.php';

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

function section(string $id, string $title): void
{
    echo "\n[" . $id . '] ' . $title . "\n";
}

// ---------------------------------------------------------------------------
// V-PHP-01 — Public API exists
// ---------------------------------------------------------------------------
section('V-PHP-01', 'Public API exists');
assert_true('birkly_template_engine_render exists', function_exists('birkly_template_engine_render'));
assert_true('birkly_template_engine_escape_html exists', function_exists('birkly_template_engine_escape_html'));
assert_true('birkly_template_engine_has_syntax exists', function_exists('birkly_template_engine_has_syntax'));
assert_true('escape_html escapes markup', birkly_template_engine_escape_html('<a href="x">&\'') === '&lt;a href=&quot;x&quot;&gt;&amp;&#39;');
assert_true('has_syntax true for templates', birkly_template_engine_has_syntax("{for each entry in 'blog'}") === true);
assert_true('has_syntax false for plain html', birkly_template_engine_has_syntax('<p>hi</p>') === false);
assert_true('render accepts non-array data', birkly_template_engine_render('<p>{title}</p>', null) === '<p>{title}</p>');

// ---------------------------------------------------------------------------
// V-PHP-02 — Loops + with filters + limit
// ---------------------------------------------------------------------------
section('V-PHP-02', 'Loops + with filters + limit');
$blogData = [
    'blog' => [
        ['slug' => 'a', 'title' => 'Alpha', 'status' => 'published', 'category' => 'news', 'price' => 10, 'created_at' => '2026-01-01'],
        ['slug' => 'b', 'title' => 'Bravo', 'status' => 'draft', 'category' => 'news', 'price' => 20, 'created_at' => '2026-02-01'],
        ['slug' => 'c', 'title' => 'Charlie', 'status' => 'published', 'category' => 'craft', 'price' => 5, 'created_at' => '2026-03-01'],
    ],
];
$basicLoop = birkly_template_engine_render("{for each entry in 'blog'}<i>{title}</i>{endfor}", $blogData);
assert_true('basic loop renders all entries', substr_count($basicLoop, '<i>') === 3);

$statusFilter = birkly_template_engine_render("{for each entry in 'blog' with status = published}<i>{title}</i>{endfor}", $blogData);
assert_true('with status filter keeps only published', str_contains($statusFilter, 'Alpha') && str_contains($statusFilter, 'Charlie') && !str_contains($statusFilter, 'Bravo'));

$catFilter = birkly_template_engine_render("{for each entry in 'blog' with 'category' = 'craft'}<i>{title}</i>{endfor}", $blogData);
assert_true("with 'field' = 'value' filter", str_contains($catFilter, 'Charlie') && !str_contains($catFilter, 'Alpha'));

$numFilter = birkly_template_engine_render("{for each entry in 'blog' with 'price' <= 10}<i>{title}</i>{endfor}", $blogData);
assert_true("with numeric filter 'price' <= 10", str_contains($numFilter, 'Alpha') && str_contains($numFilter, 'Charlie') && !str_contains($numFilter, 'Bravo'));

$limited = birkly_template_engine_render("{for each entry in 'blog' limit 2}<i>{title}</i>{endfor}", $blogData);
assert_true('limit caps rendered items', substr_count($limited, '<i>') === 2);

$filterOrderLimit = birkly_template_engine_render("{for each entry in 'blog' with status = published ordered by newest limit 1}<i>{title}</i>{endfor}", $blogData);
assert_true('with + ordered + limit combine (newest published)', str_contains($filterOrderLimit, 'Charlie') && !str_contains($filterOrderLimit, 'Alpha'));

// ---------------------------------------------------------------------------
// V-PHP-03 — Ordering: newest / oldest / last-updated / order
// ---------------------------------------------------------------------------
section('V-PHP-03', 'Ordering newest / oldest / last-updated / order');
$orderData = [
    'posts' => [
        ['title' => 'Old', 'created_at' => '2024-01-01', 'updated_at' => '2024-06-01', 'order' => 3],
        ['title' => 'Mid', 'created_at' => '2025-01-01', 'updated_at' => '2026-01-01', 'order' => 1],
        ['title' => 'New', 'created_at' => '2026-01-01', 'updated_at' => '2024-01-01', 'order' => 2],
    ],
];
$newest = birkly_template_engine_render("{for each entry in 'posts' ordered by newest}[{title}]{endfor}", $orderData);
assert_true('ordered by newest → New first', strpos($newest, 'New') < strpos($newest, 'Mid') && strpos($newest, 'Mid') < strpos($newest, 'Old'));

$oldest = birkly_template_engine_render("{for each entry in 'posts' ordered by oldest}[{title}]{endfor}", $orderData);
assert_true('ordered by oldest → Old first', strpos($oldest, 'Old') < strpos($oldest, 'Mid') && strpos($oldest, 'Mid') < strpos($oldest, 'New'));

$lastUpdated = birkly_template_engine_render("{for each entry in 'posts' ordered by last-updated}[{title}]{endfor}", $orderData);
assert_true('ordered by last-updated → Mid first', strpos($lastUpdated, 'Mid') < strpos($lastUpdated, 'Old') && strpos($lastUpdated, 'Old') < strpos($lastUpdated, 'New'));

$byOrder = birkly_template_engine_render("{for each entry in 'posts' ordered by order}[{title}]{endfor}", $orderData);
assert_true('ordered by order → Mid(1), New(2), Old(3)', strpos($byOrder, 'Mid') < strpos($byOrder, 'New') && strpos($byOrder, 'New') < strpos($byOrder, 'Old'));

$sortOrderData = ['nav' => [['title' => 'B', 'sort_order' => 2], ['title' => 'A', 'sort_order' => 1]]];
$bySortOrder = birkly_template_engine_render("{for each entry in 'nav' ordered by order}[{title}]{endfor}", $sortOrderData);
assert_true('ordered by order falls back to sort_order', strpos($bySortOrder, 'A') < strpos($bySortOrder, 'B'));

// ---------------------------------------------------------------------------
// V-PHP-04 — from … get block + inline
// ---------------------------------------------------------------------------
section('V-PHP-04', 'from…get block + inline');
$seData = [
    'site_elements' => [
        'hero' => ['key' => 'hero', 'title' => 'Hero', 'content' => [['value' => 'Welcome'], ['value' => 'Subtitle']]],
    ],
];
$getBlock = birkly_template_engine_render(
    "{from 'site_elements' get entry 'hero'}<h1>{'content'.0.'value'}</h1><p>{'content'.1.'value'}</p>{endget}",
    $seData
);
assert_true('from…get block resolves entry fields', str_contains($getBlock, '<h1>Welcome</h1>') && str_contains($getBlock, '<p>Subtitle</p>'));
assert_true('from…get block leaves no raw tags', !preg_match('/\{from|\{endget\}/', $getBlock));

$missingBlock = birkly_template_engine_render("{from 'site_elements' get entry 'ghost'}<h1>{title}</h1>{endget}", $seData);
assert_true('from…get block for missing entry removed entirely', !str_contains($missingBlock, '<h1>'));

$inlineFrom = birkly_template_engine_render("<b>{from 'site_elements'.'hero' get 'content'.0.'value'}</b>", $seData);
assert_true('inline from…get resolves single value', str_contains($inlineFrom, '<b>Welcome</b>'));

$tagData = ['entry' => ['tags' => ['cms', 'birkly', 'ssr']]];
$inlineFirst = birkly_template_engine_render("{from 'tags' get item.1}", $tagData);
assert_true('inline array item.1 (1-based)', $inlineFirst === 'cms');
$inlineNewest = birkly_template_engine_render("{from 'tags' get item.newest}", $tagData);
assert_true('inline array item.newest = last', $inlineNewest === 'ssr');

// ---------------------------------------------------------------------------
// V-PHP-05 — if / else / endif, has, exist, comparisons
// ---------------------------------------------------------------------------
section('V-PHP-05', 'if/else/endif, has, exist, comparisons');
$condData = [
    'entry' => ['title' => 'Item', 'price' => 12, 'discount' => ''],
    'site_elements' => ['hero' => ['content' => [['value' => 'x']]]],
    'current' => ['language' => 'de'],
];
$hasTrue = birkly_template_engine_render("{if entry has 'price'}has-price{else}no-price{endif}", $condData);
assert_true('if entry has field (present)', str_contains($hasTrue, 'has-price') && !str_contains($hasTrue, 'no-price'));

$hasFalse = birkly_template_engine_render("{if entry has 'discount'}has{else}none{endif}", $condData);
assert_true('if entry has field (empty string → false)', str_contains($hasFalse, 'none'));

$existTrue = birkly_template_engine_render("{if 'site_elements'.'hero' exist}here{endif}", $condData);
assert_true("'collection'.'entry' exist (present)", str_contains($existTrue, 'here'));

$existFalse = birkly_template_engine_render("{if 'site_elements'.'ghost' exist}here{else}gone{endif}", $condData);
assert_true("'collection'.'entry' exist (missing)", str_contains($existFalse, 'gone'));

$cmpNum = birkly_template_engine_render("{if entry has 'price' <= 15}cheap{else}pricey{endif}", $condData);
assert_true('numeric comparison entry has price <= 15', str_contains($cmpNum, 'cheap'));

$cmpNum2 = birkly_template_engine_render("{if entry has 'price' > 20}big{else}small{endif}", $condData);
assert_true('numeric comparison entry has price > 20 (false)', str_contains($cmpNum2, 'small'));

$langCmp = birkly_template_engine_render("{if current.language = 'de'}Willkommen{else}Welcome{endif}", $condData);
assert_true("current.language = 'de' comparison", str_contains($langCmp, 'Willkommen'));

$langCmp2 = birkly_template_engine_render("{if current.language = 'en'}Welcome{else}Andere{endif}", $condData);
assert_true("current.language = 'en' comparison (false)", str_contains($langCmp2, 'Andere'));

// ---------------------------------------------------------------------------
// V-PHP-06 — date format filter
// ---------------------------------------------------------------------------
section('V-PHP-06', 'date format filter');
$dateData = ['entry' => ['created_at' => '2025-01-05T16:45:00']];
$ymd = birkly_template_engine_render('{created_at format "Y-m-d"}', $dateData);
assert_true('format "Y-m-d"', $ymd === '2025-01-05', 'got: ' . $ymd);
$fjy = birkly_template_engine_render('{created_at format "F j, Y"}', $dateData);
assert_true('format "F j, Y"', $fjy === 'January 5, 2025', 'got: ' . $fjy);
$hm = birkly_template_engine_render('{created_at format "Y-m-d H:i"}', $dateData);
assert_true('format "Y-m-d H:i" includes time', $hm === '2025-01-05 16:45', 'got: ' . $hm);
$mdy = birkly_template_engine_render('{created_at format "m/d/Y"}', $dateData);
assert_true('format "m/d/Y"', $mdy === '01/05/2025', 'got: ' . $mdy);

// ---------------------------------------------------------------------------
// V-PHP-07 — sub-collection of parent.slug / .current
// ---------------------------------------------------------------------------
section('V-PHP-07', 'sub-collection of parent.slug / .current');
$parentEntry = [
    'slug' => 'hello-world', 'id' => 'p1', 'title' => 'Hello',
    'comments' => [
        ['author' => 'Ana', 'created_at' => '2026-01-02'],
        ['author' => 'Bo', 'created_at' => '2026-01-05'],
    ],
];
$subData = ['blog' => [$parentEntry], 'entry' => $parentEntry];
$subBySlug = birkly_template_engine_render(
    "{for each entry in 'comments' of 'blog'.'hello-world'}<c>{'author'}</c>{endfor}",
    $subData
);
assert_true("sub-collection of 'blog'.'hello-world'", str_contains($subBySlug, '<c>Ana</c>') && str_contains($subBySlug, '<c>Bo</c>'));

$subCurrent = birkly_template_engine_render(
    "{for each entry in 'comments' of 'blog'.current ordered by newest}<c>{'author'}</c>{endfor}",
    $subData
);
assert_true("sub-collection of 'blog'.current ordered newest → Bo first", strpos($subCurrent, 'Bo') < strpos($subCurrent, 'Ana'));

$subCount = birkly_template_engine_render("{count of 'comments' of 'blog'.'hello-world'}", $subData);
assert_true('count of sub-collection', $subCount === '2', 'got: ' . $subCount);

// ---------------------------------------------------------------------------
// V-PHP-08 — current.language, query.*, current_year
// ---------------------------------------------------------------------------
section('V-PHP-08', 'current.language, query.*, current_year');
$sysData = [
    'current' => ['language' => 'fr'],
    'query' => ['slug' => 'my-post', 'lang' => 'fr'],
    'current_year' => 2026,
];
assert_true('{current.language}', birkly_template_engine_render('{current.language}', $sysData) === 'fr');
assert_true("{query.'slug'}", birkly_template_engine_render("{query.'slug'}", $sysData) === 'my-post');
assert_true('{query.slug} (bare)', birkly_template_engine_render('{query.slug}', $sysData) === 'my-post');
assert_true('{current_year}', birkly_template_engine_render('{current_year}', $sysData) === '2026');

// ---------------------------------------------------------------------------
// V-PHP-09 — escapeHtml prevents script injection in field values
// ---------------------------------------------------------------------------
section('V-PHP-09', 'escapeHtml prevents script injection');
$xssData = ['items' => [['title' => '<script>alert(1)</script>', 'name' => '<b>x</b>']]];
$escaped = birkly_template_engine_render("{for each entry in 'items'}{title}{endfor}", $xssData);
assert_true('script tag in field value is escaped', !str_contains($escaped, '<script>') && str_contains($escaped, '&lt;script&gt;'));

$escapedName = birkly_template_engine_render("{for each entry in 'items'}{'name'}{endfor}", $xssData);
assert_true('non-content field escaped by default', !str_contains($escapedName, '<b>') && str_contains($escapedName, '&lt;b&gt;'));

$rawName = birkly_template_engine_render("{for each entry in 'items'}{'name'}{endfor}", $xssData, ['escapeHtml' => false]);
assert_true('escapeHtml:false yields raw output', str_contains($rawName, '<b>x</b>'));

// ---------------------------------------------------------------------------
// V-PHP-10 — edge cases: missing entry, empty collection, nested loops,
//            malformed braces, form placeholders
// ---------------------------------------------------------------------------
section('V-PHP-10', 'edge cases + form placeholders');
$emptyOut = birkly_template_engine_render("before{for each entry in 'nothing'}row{endfor}after", ['nothing' => []]);
assert_true('empty collection renders nothing between text', $emptyOut === 'beforeafter');

$missingCollection = birkly_template_engine_render("{for each entry in 'undefinedcol'}row{endfor}", []);
assert_true('undefined collection → empty', trim($missingCollection) === '');

$nestedData = [
    'blog' => [
        ['title' => 'P1', 'comments' => [['author' => 'C1'], ['author' => 'C2']]],
        ['title' => 'P2', 'comments' => [['author' => 'C3']]],
    ],
];
$nested = birkly_template_engine_render(
    "{for each entry in 'blog'}<post>{title}{for each entry in 'comments'}<c>{'author'}</c>{endfor}</post>{endfor}",
    $nestedData
);
assert_true('nested loops render parent + children', substr_count($nested, '<post>') === 2 && str_contains($nested, '<c>C1</c>') && str_contains($nested, '<c>C3</c>'));

$malformedOk = true;
$malformedOut = '';
try {
    $malformedOut = birkly_template_engine_render("ok {for each entry in 'blog' <broken title} {endfor} {unclosed", $nestedData);
} catch (Throwable $e) {
    $malformedOk = false;
}
assert_true('malformed braces do not throw', $malformedOk && is_string($malformedOut));
assert_true('empty braces render empty', birkly_template_engine_render('a{}b', []) === 'ab');

$entryFor = birkly_template_engine_render("{entry for 'contact'}<input name=\"{name}\">{endentry}", []);
assert_true('{entry for} becomes placeholder div', str_contains($entryFor, 'birkly-entry-block') && str_contains($entryFor, 'data-birkly-collection="contact"'));
assert_true('{entry for} inner markup is encoded, not executed', str_contains($entryFor, '&lt;input') && !str_contains($entryFor, '<input'));

$publicForm = birkly_template_engine_render("{public_form 'newsletter'}", []);
assert_true('{public_form} becomes placeholder div', $publicForm === '<div class="birkly-form-placeholder" data-birkly-collection="newsletter"></div>');

// ---------------------------------------------------------------------------
// V-PHP-11 — CSS/JS brace bodies must survive full-page render (SSR)
// ---------------------------------------------------------------------------
section('V-PHP-11', 'protect style/script CSS braces');
$cssHtml = <<<'HTML'
<!DOCTYPE html><html><head><style>
.bk-card { transition: transform .35s ease; }
[data-theme="dark"] { background: #262836; color: #E7EAE4; }
@media (max-width: 900px) { [data-feature-col] { margin-top: 0 !important; } }
@keyframes birkly-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.35; } }
</style><script>var x = { a: 1 };</script></head><body>
{for each entry in 'features'}<div>{'heading'}</div>{endfor}
</body></html>
HTML;
$cssOut = birkly_template_engine_render($cssHtml, ['features' => [['heading' => 'Hi']]]);
assert_true('keeps .bk-card rule body', str_contains($cssOut, 'transition: transform .35s ease'));
assert_true('keeps [data-theme=dark] rule body', str_contains($cssOut, 'background: #262836'));
assert_true('keeps nested media query body', str_contains($cssOut, 'margin-top: 0 !important'));
assert_true('keeps keyframes bodies', str_contains($cssOut, 'opacity: 1') && str_contains($cssOut, 'opacity: 0.35'));
assert_true('keeps JS object braces in script', str_contains($cssOut, 'var x = { a: 1 };'));
assert_true('still renders template loops', str_contains($cssOut, '<div>Hi</div>'));

// ---------------------------------------------------------------------------
echo "\ntemplate_engine_php_test: " . ($failures === 0 ? 'PASS' : 'FAIL')
    . ' (' . ($total - $failures) . '/' . $total . ")\n";
exit($failures === 0 ? 0 : 1);
