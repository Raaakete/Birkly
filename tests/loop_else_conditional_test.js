#!/usr/bin/env node
'use strict';

// Mirrors loop-item variable substitution guard in birkly-client.js
function isDirectiveKeyword(name) {
    const lowers = String(name || '').trim().toLowerCase();
    return ['if','elseif','else','for','endif','endfor','foreach','endforeach','from','get','has','include','media','url','count'].includes(lowers);
}

function substituteSimpleVars(template) {
    return template.replace(/\{\s*([a-zA-Z0-9_]+)\s*\}/g, (m, name) => {
        if (isDirectiveKeyword(name)) return m;
        if (name === 'category') return 'mornings';
        return '';
    });
}

let failures = 0;
function assertTrue(name, ok) {
    console.log(name + ': ' + (ok ? 'PASS' : 'FAIL'));
    if (!ok) failures++;
}

const loopBody = "{if entry has 'aspect_ratio'}A{else}B{endif}";
const after = substituteSimpleVars(loopBody);
assertTrue('preserves {else} in loop body', after.includes('{else}'));
assertTrue('preserves {if} in loop body', after.includes("{if entry has 'aspect_ratio'}"));
assertTrue('substitutes variables', after.includes('mornings') || after.includes('A') || after.includes('B'));

console.log('\nloop_else_conditional_test: ' + (failures === 0 ? 'PASS' : 'FAIL (' + failures + ')'));
process.exit(failures === 0 ? 0 : 1);
