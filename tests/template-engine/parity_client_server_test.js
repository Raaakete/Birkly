#!/usr/bin/env node
'use strict';

/**
 * P51-M5 — Client/server render parity suite (matrix V-DEC-01 … V-DEC-08).
 *
 * The whole point of P51 is that there is exactly ONE JS template engine
 * implementation (the browser SSOT). This test proves the decoupling holds:
 *
 *   - `birkly-template-engine.js`                  ← canonical JS engine
 *   - `birkly-client-modules/birkly-render.js`     ← thin re-export (tests)
 *   - the inlined block in `birkly-client.js`      ← production single script tag
 *
 * ...all resolve to the same source. Because the client inlines a byte-for-byte
 * copy of the engine (kept in sync by scripts/sync-template-engine-inline.sh and
 * enforced by ci-check), every browser `Birkly.render(html, data)` call runs
 * the same code by construction.
 *
 * Server-side rendering is PHP-native since P52 (core/birkly_template_engine.php,
 * no Node on the SSR path); PHP↔JS output parity is proven separately by
 * tests/template_engine_parity_test.php against the shared fixture set.
 *
 * Run: node tests/template-engine/parity_client_server_test.js
 */

const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..', '..');
const enginePath = path.join(ROOT, 'birkly-template-engine.js');
const reexportPath = path.join(ROOT, 'birkly-client-modules', 'birkly-render.js');
const clientPath = path.join(ROOT, 'birkly-client.source.js');

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
// V-DEC-01 — Shared module identity: re-export === engine === global.
// ---------------------------------------------------------------------------
section('V-DEC-01', 'Single shared module identity');
const reexport = require(reexportPath);
assert('birkly-render.js re-exports the same engine object', reexport === engine);
assert('globalThis.BirklyTemplateEngine === engine', globalThis.BirklyTemplateEngine === engine);
assert('render is a function', typeof engine.render === 'function');

// ---------------------------------------------------------------------------
// V-DEC-02 — Client inlines a byte-for-byte copy of the engine source.
//            (This is what makes the single <script> tag == SSR engine.)
// ---------------------------------------------------------------------------
section('V-DEC-02', 'Client inlines the engine source verbatim');
const BEGIN = '/* BIRKLY_TEMPLATE_ENGINE_INLINE_BEGIN */';
const END = '/* BIRKLY_TEMPLATE_ENGINE_INLINE_END */';
const engineSrc = fs.readFileSync(enginePath, 'utf8');
const clientSrc = fs.readFileSync(clientPath, 'utf8');
const bi = clientSrc.indexOf(BEGIN);
const ei = clientSrc.indexOf(END);
assert('client has BEGIN marker', bi !== -1);
assert('client has END marker', ei !== -1 && ei > bi);
let inlined = '';
if (bi !== -1 && ei !== -1 && ei > bi) {
    // Content strictly between the two marker lines (drop the marker lines' own
    // newlines the same way the sync script splices them).
    const after = clientSrc.slice(bi + BEGIN.length);
    inlined = after.slice(after.indexOf('\n') + 1, after.indexOf(END));
    // Trim the trailing indentation that precedes the END marker on its line.
    inlined = inlined.replace(/\n[ \t]*$/, '\n');
}
assert('inlined engine equals birkly-template-engine.js', inlined === engineSrc);
assert('client no longer contains the old BIRKLY_RENDER_V3_INLINE block',
    !clientSrc.includes('BIRKLY_RENDER_V3_INLINE'));

// ---------------------------------------------------------------------------
// Fixtures exercised through the shared engine. Because client + server use the
// SAME module, these outputs are the client output AND the SSR output.
// ---------------------------------------------------------------------------
const fixtures = [
    {
        id: 'V-DEC-03',
        title: 'List loop + filter + order + limit',
        html: "{for each entry in 'blog' with status = published ordered by newest limit 2}<li>{title}</li>{endfor}",
        data: {
            blog: [
                { title: 'A', status: 'published', created_at: '2026-01-01' },
                { title: 'B', status: 'draft', created_at: '2026-02-01' },
                { title: 'C', status: 'published', created_at: '2026-03-01' },
                { title: 'D', status: 'published', created_at: '2026-02-15' }
            ]
        },
        expect: '<li>C</li><li>D</li>'
    },
    {
        id: 'V-DEC-04',
        title: 'from…get block + inline value',
        html: "{from 'site_elements' get entry 'hero'}<h1>{'content'.0.'value'}</h1>{endget}|<i>{from 'site_elements'.'hero' get 'content'.1.'value'}</i>",
        data: {
            site_elements: {
                hero: { key: 'hero', content: [{ value: 'Welcome' }, { value: 'Sub' }] }
            }
        },
        expect: '<h1>Welcome</h1>|<i>Sub</i>'
    },
    {
        id: 'V-DEC-05',
        title: 'if / else / comparison + date format',
        html: "{if current.language = 'de'}Hallo {created_at format \"F j, Y\"}{else}Hi{endif}",
        data: { current: { language: 'de' }, entry: { created_at: '2025-01-05' }, created_at: '2025-01-05' },
        expect: 'Hallo January 5, 2025'
    },
    {
        id: 'V-DEC-06',
        title: 'Detail-page current entry fields + escaping',
        html: "<h1>{title}</h1><p>{'summary'}</p>",
        data: { entry: { title: 'Post', summary: '<b>x</b>' }, title: 'Post', summary: '<b>x</b>' },
        expect: '<h1>Post</h1><p>&lt;b&gt;x&lt;/b&gt;</p>'
    },
    {
        id: 'V-DEC-07',
        title: 'Sub-collection of parent.current + count',
        html: "{count of 'comments' of 'blog'.'p1'}:{for each entry in 'comments' of 'blog'.current ordered by newest}<c>{'author'}</c>{endfor}",
        data: (function () {
            const parent = {
                slug: 'p1', id: 'p1',
                comments: [
                    { author: 'Ana', created_at: '2026-01-02' },
                    { author: 'Bo', created_at: '2026-01-05' }
                ]
            };
            return { blog: [parent], entry: parent };
        })(),
        expect: '2:<c>Bo</c><c>Ana</c>'
    },
    {
        id: 'V-DEC-08',
        title: 'entry-for public form placeholder is deterministic',
        // The engine emits a hydration placeholder and resolves any leftover
        // {var} in the encoded body against data (here empty → stripped). This
        // exact behavior is what both client and SSR emit.
        html: "{entry for 'contact'}<label>Name</label>{endentry}",
        data: {},
        expect: '<div class="birkly-entry-block" data-birkly-collection="contact" data-birkly-content="&lt;label&gt;Name&lt;/label&gt;"></div>'
    }
];

for (const fx of fixtures) {
    section(fx.id, fx.title);
    const out = engine.render(fx.html, fx.data);
    assert('renders expected output', out === fx.expect);
    // Determinism: same input → same output across repeated renders.
    const again = engine.render(fx.html, fx.data);
    assert('render is deterministic (stable across calls)', again === out);
    if (out !== fx.expect) {
        console.log('    expected: ' + JSON.stringify(fx.expect));
        console.log('    actual:   ' + JSON.stringify(out));
    }
}

// ---------------------------------------------------------------------------
console.log('\nclient/server parity suite: ' + (failures === 0 ? 'PASS' : 'FAIL') +
    ' (' + (total - failures) + '/' + total + ')');
process.exit(failures === 0 ? 0 : 1);
