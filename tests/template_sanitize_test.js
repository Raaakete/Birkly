#!/usr/bin/env node
'use strict';

function sanitizeRuntimeTemplateHtml(html) {
    if (!html || typeof html !== 'string') return html || '';
    return html
        .replace(/\sdata-marsk-bound="[^"]*"/gi, '')
        .replace(/\sdata-marsk-scroll-bound="[^"]*"/gi, '')
        .replace(/\sdata-cms-processed="[^"]*"/gi, '');
}

let failures = 0;
function assertTrue(name, ok) {
    console.log(name + ': ' + (ok ? 'PASS' : 'FAIL'));
    if (!ok) failures++;
}

const dirty = '<button class="menu-toggle" data-marsk-bound="1" data-cms-processed="true">Menu</button>';
const clean = sanitizeRuntimeTemplateHtml(dirty);
assertTrue('strips marsk bound marker', clean.indexOf('data-marsk-bound') === -1);
assertTrue('strips cms processed marker', clean.indexOf('data-cms-processed') === -1);
assertTrue('keeps menu markup', clean.indexOf('menu-toggle') !== -1);

console.log('\ntemplate_sanitize_test: ' + (failures === 0 ? 'PASS' : 'FAIL (' + failures + ')'));
process.exit(failures === 0 ? 0 : 1);
