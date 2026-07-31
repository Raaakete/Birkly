#!/usr/bin/env node
'use strict';

/**
 * P51-M2 — SSR Node CLI bridge smoke tests (collect + render).
 * Plain-assert harness (no jest).
 *
 * Run: node tests/template-engine/ssr_cli_test.js
 */

const path = require('path');
const { spawnSync } = require('child_process');

const cli = path.join(__dirname, '..', '..', 'scripts', 'birkly-ssr-cli.js');

let failures = 0;
let total = 0;
function assert(name, ok) {
    total++;
    console.log('  ' + name + ': ' + (ok ? 'PASS' : 'FAIL'));
    if (!ok) failures++;
}

function runCli(payload) {
    const res = spawnSync(process.execPath, [cli], {
        input: JSON.stringify(payload),
        encoding: 'utf8'
    });
    return { code: res.status, out: (res.stdout || '').trim(), err: (res.stderr || '').trim() };
}

console.log('\n[V-SSR-CLI] Node CLI bridge collect + render');

// render mode
const r1 = runCli({ mode: 'render', html: '<p>{title}</p>', data: { title: 'Hi' } });
let r1json = null;
try { r1json = JSON.parse(r1.out); } catch (e) { /* noop */ }
assert('render exits 0', r1.code === 0);
assert('render returns ok:true', !!(r1json && r1json.ok === true));
assert('render produces expected HTML', !!(r1json && r1json.html === '<p>Hi</p>'));

// render with a loop + escaping
const r2 = runCli({
    mode: 'render',
    html: "{for each entry in 'items'}<i>{title}</i>{endfor}",
    data: { items: [{ title: '<script>x</script>' }, { title: 'Safe' }] }
});
let r2json = null;
try { r2json = JSON.parse(r2.out); } catch (e) { /* noop */ }
assert('render loop ok', !!(r2json && r2json.ok === true));
assert('render loop renders two items', !!(r2json && (r2json.html.match(/<i>/g) || []).length === 2));
assert('render loop escapes untrusted field', !!(r2json && r2json.html.indexOf('<script>') === -1 && r2json.html.indexOf('&lt;script&gt;') !== -1));

// collect mode
const c1 = runCli({
    mode: 'collect',
    html: "{for each entry in 'blog'}{title}{endfor}{from 'site_elements' get entry 'header'}{'content'}{endget}"
});
let c1json = null;
try { c1json = JSON.parse(c1.out); } catch (e) { /* noop */ }
assert('collect exits 0', c1.code === 0);
assert('collect returns ok:true', !!(c1json && c1json.ok === true));
assert('collect returns array of requests', !!(c1json && Array.isArray(c1json.requests)));
assert('collect finds blog list_entries', !!(c1json && c1json.requests.some(
    (r) => r.type === 'list_entries' && r.collection === 'blog'
)));
assert('collect finds site_elements list_entries', !!(c1json && c1json.requests.some(
    (r) => r.type === 'list_entries' && r.collection === 'site_elements'
)));
assert('collect finds header get_entry', !!(c1json && c1json.requests.some(
    (r) => r.type === 'get_entry' && r.collection === 'site_elements' && r.slug === 'header'
)));

// malformed JSON → ok:false, still exit 0 (graceful for PHP fallback)
const badRaw = spawnSync(process.execPath, [cli], { input: 'not json', encoding: 'utf8' });
let badJson = null;
try { badJson = JSON.parse((badRaw.stdout || '').trim()); } catch (e) { /* noop */ }
assert('invalid stdin exits 0 (graceful)', badRaw.status === 0);
assert('invalid stdin returns ok:false', !!(badJson && badJson.ok === false));

console.log('\nssr_cli suite: ' + (failures === 0 ? 'PASS' : 'FAIL') +
    ' (' + (total - failures) + '/' + total + ')');
process.exit(failures === 0 ? 0 : 1);
