#!/usr/bin/env node
/**
 * P20-M1: Minimal DOM harness for Birkly.bundle collection helpers (no npm).
 * Usage: node tests/harness/client-bundle-harness.js <fixture-html-path>
 */
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const fixturePath = process.argv[2];
if (!fixturePath) {
    console.error('Usage: node client-bundle-harness.js <fixture.html>');
    process.exit(2);
}

const html = fs.readFileSync(fixturePath, 'utf8');
const mainMatch = html.match(/<main[^>]*>([\s\S]*?)<\/main>/i);
const mainHtml = mainMatch ? mainMatch[1] : html;

const metas = {};
(html.match(/<meta\s+name="([^"]+)"\s+content="([^"]*)"/gi) || []).forEach((tag) => {
    const m = tag.match(/name="([^"]+)"\s+content="([^"]*)"/i);
    if (m) metas[m[1]] = m[2];
});

let pathname = '/reference-projects/blog/post.html';
let search = '';
const pathMatch = html.match(/data-test-pathname="([^"]+)"/);
const searchMatch = html.match(/data-test-search="([^"]*)"/);
if (pathMatch) pathname = pathMatch[1];
if (searchMatch) search = searchMatch[1];

const document = {
    querySelector(sel) {
        if (sel === 'main') {
            return { innerHTML: mainHtml };
        }
        if (sel === 'meta[name="birkly-page"]' && metas['birkly-page']) {
            return { content: metas['birkly-page'] };
        }
        if (sel === 'meta[name="birkly-slug"]' && metas['birkly-slug']) {
            return { content: metas['birkly-slug'] };
        }
        if (sel === 'meta[name="birkly-collection"]' && metas['birkly-collection']) {
            return { content: metas['birkly-collection'] };
        }
        return null;
    },
    querySelectorAll() {
        return [];
    },
    body: { innerHTML: html },
};

const window = {
    location: { pathname, search },
    URLSearchParams: global.URLSearchParams,
};

const Birkly = { bundle: {} };
const context = { Birkly, document, window, console };
vm.createContext(context);

const clientSrc = fs.readFileSync(path.join(__dirname, '../../birkly-client.js'), 'utf8');
const bundleBlock = clientSrc.match(
    /Birkly\.bundle\.getTemplateCollectionSlugs[\s\S]*?Birkly\.bundle\.tryLoadPageSnapshot/
);
if (!bundleBlock) {
    console.error('Could not extract Birkly.bundle block from birkly-client.js');
    process.exit(2);
}

vm.runInContext(
    '(function () {\n' + bundleBlock[0].replace(/Birkly\.bundle\.tryLoadPageSnapshot[\s\S]*$/, '') + '\n})();',
    context
);

const route = Birkly.bundle.detectPageRoute();
const requests = Birkly.bundle.collectBundleRequests();
const collection = Birkly.bundle.resolveSlugPageCollection(mainHtml, route);

console.log(JSON.stringify({ route, collection, requests }, null, 2));
