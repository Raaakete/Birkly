#!/usr/bin/env node
'use strict';

/**
 * P51-M1 — Shared template engine syntax suite (V-ENG-01 … V-ENG-10).
 * Plain-assert harness (no jest), mirrors tests/template_regions_test.js.
 *
 * Run: node tests/template-engine/run.js
 */

const path = require('path');
const enginePath = path.join(__dirname, '..', '..', 'birkly-template-engine.js');
const engine = require(enginePath);

let failures = 0;
let total = 0;
function assert(name, ok) {
    total++;
    console.log('  ' + name + ': ' + (ok ? 'PASS' : 'FAIL'));
    if (!ok) failures++;
}
function section(id, title) {
    console.log('\n[' + id + '] ' + title);
}

// ---------------------------------------------------------------------------
// V-ENG-01 — Module exports render
// ---------------------------------------------------------------------------
section('V-ENG-01', 'Module exports render');
assert('require returns object', engine && typeof engine === 'object');
assert('render is a function', typeof engine.render === 'function');
assert('collectBundleRequests is a function', typeof engine.collectBundleRequests === 'function');
assert('hasTemplateSyntax is a function', typeof engine.hasTemplateSyntax === 'function');
assert('escapeHtml is a function', typeof engine.escapeHtml === 'function');
assert('globalThis.BirklyTemplateEngine set', globalThis.BirklyTemplateEngine === engine);
assert('render(html, data, options) accepts options arg', engine.render.length >= 2);

// ---------------------------------------------------------------------------
// V-ENG-02 — Loops + with filters + limit
// ---------------------------------------------------------------------------
section('V-ENG-02', 'Loops + with filters + limit');
const blogData = {
    blog: [
        { slug: 'a', title: 'Alpha', status: 'published', category: 'news', price: 10, created_at: '2026-01-01' },
        { slug: 'b', title: 'Bravo', status: 'draft', category: 'news', price: 20, created_at: '2026-02-01' },
        { slug: 'c', title: 'Charlie', status: 'published', category: 'craft', price: 5, created_at: '2026-03-01' }
    ]
};
const basicLoop = engine.render("{for each entry in 'blog'}<i>{title}</i>{endfor}", blogData);
assert('basic loop renders all entries', (basicLoop.match(/<i>/g) || []).length === 3);

const statusFilter = engine.render("{for each entry in 'blog' with status = published}<i>{title}</i>{endfor}", blogData);
assert('with status filter keeps only published', statusFilter.includes('Alpha') && statusFilter.includes('Charlie') && !statusFilter.includes('Bravo'));

const catFilter = engine.render("{for each entry in 'blog' with 'category' = 'craft'}<i>{title}</i>{endfor}", blogData);
assert("with 'field' = 'value' filter", catFilter.includes('Charlie') && !catFilter.includes('Alpha'));

const numFilter = engine.render("{for each entry in 'blog' with 'price' <= 10}<i>{title}</i>{endfor}", blogData);
assert("with numeric filter 'price' <= 10", numFilter.includes('Alpha') && numFilter.includes('Charlie') && !numFilter.includes('Bravo'));

const limited = engine.render("{for each entry in 'blog' limit 2}<i>{title}</i>{endfor}", blogData);
assert('limit caps rendered items', (limited.match(/<i>/g) || []).length === 2);

const filterOrderLimit = engine.render("{for each entry in 'blog' with status = published ordered by newest limit 1}<i>{title}</i>{endfor}", blogData);
assert('with + ordered + limit combine (newest published)', filterOrderLimit.includes('Charlie') && !filterOrderLimit.includes('Alpha'));

// ---------------------------------------------------------------------------
// V-ENG-03 — Ordering: newest / oldest / last-updated / order
// ---------------------------------------------------------------------------
section('V-ENG-03', 'Ordering newest / oldest / last-updated / order');
const orderData = {
    posts: [
        { title: 'Old', created_at: '2024-01-01', updated_at: '2024-06-01', order: 3 },
        { title: 'Mid', created_at: '2025-01-01', updated_at: '2026-01-01', order: 1 },
        { title: 'New', created_at: '2026-01-01', updated_at: '2024-01-01', order: 2 }
    ]
};
const newest = engine.render("{for each entry in 'posts' ordered by newest}[{title}]{endfor}", orderData);
assert('ordered by newest → New first', newest.indexOf('New') < newest.indexOf('Mid') && newest.indexOf('Mid') < newest.indexOf('Old'));

const oldest = engine.render("{for each entry in 'posts' ordered by oldest}[{title}]{endfor}", orderData);
assert('ordered by oldest → Old first', oldest.indexOf('Old') < oldest.indexOf('Mid') && oldest.indexOf('Mid') < oldest.indexOf('New'));

const lastUpdated = engine.render("{for each entry in 'posts' ordered by last-updated}[{title}]{endfor}", orderData);
assert('ordered by last-updated → Mid first', lastUpdated.indexOf('Mid') < lastUpdated.indexOf('Old') && lastUpdated.indexOf('Old') < lastUpdated.indexOf('New'));

const byOrder = engine.render("{for each entry in 'posts' ordered by order}[{title}]{endfor}", orderData);
assert('ordered by order → Mid(1), New(2), Old(3)', byOrder.indexOf('Mid') < byOrder.indexOf('New') && byOrder.indexOf('New') < byOrder.indexOf('Old'));

const sortOrderData = { nav: [{ title: 'B', sort_order: 2 }, { title: 'A', sort_order: 1 }] };
const bySortOrder = engine.render("{for each entry in 'nav' ordered by order}[{title}]{endfor}", sortOrderData);
assert('ordered by order falls back to sort_order', bySortOrder.indexOf('A') < bySortOrder.indexOf('B'));

// ---------------------------------------------------------------------------
// V-ENG-04 — from … get block + inline
// ---------------------------------------------------------------------------
section('V-ENG-04', 'from…get block + inline');
const seData = {
    site_elements: {
        hero: { key: 'hero', title: 'Hero', content: [{ value: 'Welcome' }, { value: 'Subtitle' }] }
    }
};
const getBlock = engine.render(
    "{from 'site_elements' get entry 'hero'}<h1>{'content'.0.'value'}</h1><p>{'content'.1.'value'}</p>{endget}",
    seData
);
assert('from…get block resolves entry fields', getBlock.includes('<h1>Welcome</h1>') && getBlock.includes('<p>Subtitle</p>'));
assert('from…get block leaves no raw tags', !/\{from|\{endget\}/.test(getBlock));

const missingBlock = engine.render("{from 'site_elements' get entry 'ghost'}<h1>{title}</h1>{endget}", seData);
assert('from…get block for missing entry removed entirely', !missingBlock.includes('<h1>'));

const inlineFrom = engine.render("<b>{from 'site_elements'.'hero' get 'content'.0.'value'}</b>", seData);
assert('inline from…get resolves single value', inlineFrom.includes('<b>Welcome</b>'));

const tagData = { entry: { tags: ['cms', 'birkly', 'ssr'] } };
const inlineFirst = engine.render("{from 'tags' get item.1}", tagData);
assert('inline array item.1 (1-based)', inlineFirst === 'cms');
const inlineNewest = engine.render("{from 'tags' get item.newest}", tagData);
assert('inline array item.newest = last', inlineNewest === 'ssr');

// ---------------------------------------------------------------------------
// V-ENG-05 — if / else / endif, has, exist, comparisons
// ---------------------------------------------------------------------------
section('V-ENG-05', 'if/else/endif, has, exist, comparisons');
const condData = {
    entry: { title: 'Item', price: 12, discount: '' },
    site_elements: { hero: { content: [{ value: 'x' }] } },
    current: { language: 'de' }
};
const hasTrue = engine.render("{if entry has 'price'}has-price{else}no-price{endif}", condData);
assert('if entry has field (present)', hasTrue.includes('has-price') && !hasTrue.includes('no-price'));

const hasFalse = engine.render("{if entry has 'discount'}has{else}none{endif}", condData);
assert('if entry has field (empty string → false)', hasFalse.includes('none'));

const existTrue = engine.render("{if 'site_elements'.'hero' exist}here{endif}", condData);
assert("'collection'.'entry' exist (present)", existTrue.includes('here'));

const existFalse = engine.render("{if 'site_elements'.'ghost' exist}here{else}gone{endif}", condData);
assert("'collection'.'entry' exist (missing)", existFalse.includes('gone'));

const cmpNum = engine.render("{if entry has 'price' <= 15}cheap{else}pricey{endif}", condData);
assert('numeric comparison entry has price <= 15', cmpNum.includes('cheap'));

const cmpNum2 = engine.render("{if entry has 'price' > 20}big{else}small{endif}", condData);
assert('numeric comparison entry has price > 20 (false)', cmpNum2.includes('small'));

const langCmp = engine.render("{if current.language = 'de'}Willkommen{else}Welcome{endif}", condData);
assert("current.language = 'de' comparison", langCmp.includes('Willkommen'));

const langCmp2 = engine.render("{if current.language = 'en'}Welcome{else}Andere{endif}", condData);
assert("current.language = 'en' comparison (false)", langCmp2.includes('Andere'));

// ---------------------------------------------------------------------------
// V-ENG-06 — date format filter
// ---------------------------------------------------------------------------
section('V-ENG-06', 'date format filter');
const dateData = { entry: { created_at: '2025-01-05T16:45:00' } };
const ymd = engine.render('{created_at format "Y-m-d"}', dateData);
assert('format "Y-m-d"', ymd === '2025-01-05');
const fjy = engine.render('{created_at format "F j, Y"}', dateData);
assert('format "F j, Y"', fjy === 'January 5, 2025');
const hm = engine.render('{created_at format "Y-m-d H:i"}', dateData);
assert('format "Y-m-d H:i" includes time', hm === '2025-01-05 16:45');
const mdy = engine.render('{created_at format "m/d/Y"}', dateData);
assert('format "m/d/Y"', mdy === '01/05/2025');

// ---------------------------------------------------------------------------
// V-ENG-07 — sub-collection of parent.slug / .current
// ---------------------------------------------------------------------------
section('V-ENG-07', 'sub-collection of parent.slug / .current');
const parentEntry = {
    slug: 'hello-world', id: 'p1', title: 'Hello',
    comments: [
        { author: 'Ana', created_at: '2026-01-02' },
        { author: 'Bo', created_at: '2026-01-05' }
    ]
};
const subData = { blog: [parentEntry], entry: parentEntry };
const subBySlug = engine.render(
    "{for each entry in 'comments' of 'blog'.'hello-world'}<c>{'author'}</c>{endfor}",
    subData
);
assert("sub-collection of 'blog'.'hello-world'", subBySlug.includes('<c>Ana</c>') && subBySlug.includes('<c>Bo</c>'));

const subCurrent = engine.render(
    "{for each entry in 'comments' of 'blog'.current ordered by newest}<c>{'author'}</c>{endfor}",
    subData
);
assert("sub-collection of 'blog'.current ordered newest → Bo first", subCurrent.indexOf('Bo') < subCurrent.indexOf('Ana'));

const subCount = engine.render("{count of 'comments' of 'blog'.'hello-world'}", subData);
assert('count of sub-collection', subCount === '2');

// ---------------------------------------------------------------------------
// V-ENG-08 — current.language, query.*, current_year
// ---------------------------------------------------------------------------
section('V-ENG-08', 'current.language, query.*, current_year');
const sysData = {
    current: { language: 'fr' },
    query: { slug: 'my-post', lang: 'fr' },
    current_year: 2026
};
assert('{current.language}', engine.render('{current.language}', sysData) === 'fr');
assert("{query.'slug'}", engine.render("{query.'slug'}", sysData) === 'my-post');
assert('{query.slug} (bare)', engine.render('{query.slug}', sysData) === 'my-post');
assert('{current_year}', engine.render('{current_year}', sysData) === '2026');

// ---------------------------------------------------------------------------
// V-ENG-09 — escapeHtml prevents script injection in field values
// ---------------------------------------------------------------------------
section('V-ENG-09', 'escapeHtml prevents script injection');
const xssData = { items: [{ title: '<script>alert(1)</script>', name: '<b>x</b>' }] };
const escaped = engine.render("{for each entry in 'items'}{title}{endfor}", xssData);
assert('script tag in field value is escaped', !escaped.includes('<script>') && escaped.includes('&lt;script&gt;'));

const escapedName = engine.render("{for each entry in 'items'}{'name'}{endfor}", xssData);
assert('non-content field escaped by default', !escapedName.includes('<b>') && escapedName.includes('&lt;b&gt;'));

const rawName = engine.render("{for each entry in 'items'}{'name'}{endfor}", xssData, { escapeHtml: false });
assert('escapeHtml:false yields raw output', rawName.includes('<b>x</b>'));

// ---------------------------------------------------------------------------
// V-ENG-10 — edge cases: missing entry, empty collection, nested loops, malformed braces
// ---------------------------------------------------------------------------
section('V-ENG-10', 'edge cases');
const emptyOut = engine.render("before{for each entry in 'nothing'}row{endfor}after", { nothing: [] });
assert('empty collection renders nothing between text', emptyOut === 'beforeafter');

const missingCollection = engine.render("{for each entry in 'undefinedcol'}row{endfor}", {});
assert('undefined collection → empty', missingCollection.trim() === '');

const nestedData = {
    blog: [
        { title: 'P1', comments: [{ author: 'C1' }, { author: 'C2' }] },
        { title: 'P2', comments: [{ author: 'C3' }] }
    ]
};
const nested = engine.render(
    "{for each entry in 'blog'}<post>{title}{for each entry in 'comments'}<c>{'author'}</c>{endfor}</post>{endfor}",
    nestedData
);
assert('nested loops render parent + children', (nested.match(/<post>/g) || []).length === 2 && nested.includes('<c>C1</c>') && nested.includes('<c>C3</c>'));

let malformedOk = true;
let malformedOut = '';
try {
    malformedOut = engine.render("ok {for each entry in 'blog' <broken title} {endfor} {unclosed", nestedData);
} catch (e) {
    malformedOk = false;
}
assert('malformed braces do not throw', malformedOk && typeof malformedOut === 'string');
assert('empty braces render empty', engine.render('a{}b', {}) === 'ab');

let nonStringOk = true;
try {
    engine.render(null, {});
    engine.render('x', null);
} catch (e) {
    nonStringOk = false;
}
assert('null/invalid inputs handled gracefully', nonStringOk);

// ---------------------------------------------------------------------------
// V-ENG-11 — CSS/JS brace bodies must survive full-page render (SSR)
// ---------------------------------------------------------------------------
section('V-ENG-11', 'protect style/script CSS braces');
const cssHtml = `<!DOCTYPE html><html><head><style>
.bk-card { transition: transform .35s ease; }
[data-theme="dark"] { background: #262836; color: #E7EAE4; }
@media (max-width: 900px) { [data-feature-col] { margin-top: 0 !important; } }
@keyframes birkly-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.35; } }
</style><script>var x = { a: 1 };</script></head><body>
{for each entry in 'features'}<div>{'heading'}</div>{endfor}
</body></html>`;
const cssOut = engine.render(cssHtml, { features: [{ heading: 'Hi' }] });
assert('keeps .bk-card rule body', cssOut.includes('transition: transform .35s ease'));
assert('keeps [data-theme=dark] rule body', cssOut.includes('background: #262836'));
assert('keeps nested media query body', cssOut.includes('margin-top: 0 !important'));
assert('keeps keyframes bodies', cssOut.includes('opacity: 1') && cssOut.includes('opacity: 0.35'));
assert('keeps JS object braces in script', cssOut.includes('var x = { a: 1 };'));
assert('still renders template loops', cssOut.includes('<div>Hi</div>'));

// ---------------------------------------------------------------------------
console.log('\ntemplate-engine suite: ' + (failures === 0 ? 'PASS' : 'FAIL') +
    ' (' + (total - failures) + '/' + total + ')');
process.exit(failures === 0 ? 0 : 1);
