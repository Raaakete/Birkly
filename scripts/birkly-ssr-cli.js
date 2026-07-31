#!/usr/bin/env node
'use strict';

/**
 * Birkly SSR CLI bridge (P51-M2)
 * ------------------------------
 * Tiny, dependency-free stdin/stdout bridge between PHP (`core/project_ssr.php`)
 * and the shared template engine (`birkly-template-engine.js`).
 *
 * Reads a single JSON document from stdin and writes a single JSON document to
 * stdout. Two modes:
 *
 *   {"mode":"collect","html":"..."}
 *       -> {"ok":true,"requests":[ ...page_bundle-shaped requests... ]}
 *
 *   {"mode":"render","html":"...","data":{...},"options":{...}}
 *       -> {"ok":true,"html":"..."}
 *
 * On any error it prints {"ok":false,"error":"..."} and exits 0 so the PHP side
 * can decide to fall back to client-side rendering. Process timeout is enforced
 * by the PHP caller (proc_open), so this script stays deliberately minimal.
 */

const path = require('path');

function respond(obj) {
    try {
        process.stdout.write(JSON.stringify(obj));
    } catch (e) {
        process.stdout.write('{"ok":false,"error":"encode failed"}');
    }
}

function fail(message) {
    respond({ ok: false, error: String(message || 'unknown error') });
    process.exit(0);
}

function loadEngine() {
    // Engine lives at repo root, one level up from scripts/.
    const enginePath = path.join(__dirname, '..', 'birkly-template-engine.js');
    // eslint-disable-next-line global-require
    return require(enginePath);
}

function run(raw) {
    let payload;
    try {
        payload = JSON.parse(raw || '{}');
    } catch (e) {
        return fail('invalid JSON on stdin');
    }
    if (!payload || typeof payload !== 'object') {
        return fail('payload must be an object');
    }

    let engine;
    try {
        engine = loadEngine();
    } catch (e) {
        return fail('engine load failed: ' + (e && e.message ? e.message : e));
    }

    const mode = payload.mode || 'render';
    const html = typeof payload.html === 'string' ? payload.html : '';

    if (mode === 'collect') {
        if (typeof engine.collectBundleRequests !== 'function') {
            return fail('engine.collectBundleRequests unavailable');
        }
        try {
            const requests = engine.collectBundleRequests(html) || [];
            return respond({ ok: true, requests: requests });
        } catch (e) {
            return fail('collect failed: ' + (e && e.message ? e.message : e));
        }
    }

    if (mode === 'render') {
        if (typeof engine.render !== 'function') {
            return fail('engine.render unavailable');
        }
        const data = (payload.data && typeof payload.data === 'object') ? payload.data : {};
        const options = (payload.options && typeof payload.options === 'object') ? payload.options : {};
        try {
            const out = engine.render(html, data, options);
            return respond({ ok: true, html: typeof out === 'string' ? out : String(out == null ? '' : out) });
        } catch (e) {
            return fail('render failed: ' + (e && e.message ? e.message : e));
        }
    }

    return fail('unknown mode: ' + mode);
}

let input = '';
process.stdin.setEncoding('utf8');
process.stdin.on('data', (chunk) => { input += chunk; });
process.stdin.on('end', () => { run(input); });
process.stdin.on('error', () => { fail('stdin read error'); });
