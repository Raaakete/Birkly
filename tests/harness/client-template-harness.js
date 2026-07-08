#!/usr/bin/env node
/**
 * P20-M10: Minimal harness for Birkly.render() (no npm).
 * Usage: echo '{"template":"...","data":{...}}' | node tests/harness/client-template-harness.js
 */
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

let payload = {};
try {
    const raw = fs.readFileSync(0, 'utf8');
    payload = raw ? JSON.parse(raw) : {};
} catch (e) {
    console.error(JSON.stringify({ ok: false, error: 'Invalid JSON stdin: ' + e.message }));
    process.exit(2);
}

const template = payload.template || '';
const data = payload.data || {};

const localStorage = {
    getItem: () => null,
    setItem: () => {}
};

const document = {
    readyState: 'complete',
    getElementsByTagName: () => [],
    querySelector: (sel) => {
        if (sel === 'script[data-cms-url]') {
            return { getAttribute: () => 'http://localhost:8080' };
        }
        return null;
    },
    querySelectorAll: () => [],
    body: { innerHTML: '' },
    addEventListener: () => {},
    createElement: () => {
        const el = { _text: '' };
        Object.defineProperty(el, 'textContent', {
            set(v) { el._text = v == null ? '' : String(v); },
            get() { return el._text; },
            configurable: true
        });
        Object.defineProperty(el, 'innerHTML', {
            get() {
                return el._text
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            },
            configurable: true
        });
        return el;
    },
};

const window = {
    location: { hostname: 'localhost', origin: 'http://localhost:8000', pathname: '/', search: '' },
    localStorage,
    URLSearchParams: global.URLSearchParams,
};

const context = { window, document, console, localStorage, URLSearchParams: global.URLSearchParams };
vm.createContext(context);

let src = fs.readFileSync(path.join(__dirname, '../../birkly-client.js'), 'utf8');
src = src.replace(
    /if \(document\.readyState === 'loading'\)\s*\{[\s\S]*?\}\s*else\s*\{[\s\S]*?init\(\);\s*\}/,
    '/* auto-init disabled for harness */'
);

try {
    vm.runInContext(src, context);
    const out = context.window.Birkly.render(template, data);
    console.log(JSON.stringify({ ok: true, output: out }));
} catch (e) {
    console.error(JSON.stringify({ ok: false, error: e.message, stack: e.stack }));
    process.exit(1);
}
