#!/usr/bin/env node
'use strict';

function getTemplateFromEntryCollectionSlugs(templateStr) {
    const collections = [];
    const re = /\{from\s+['"]([^'"]+)['"]\s+get\s+entry\s+['"][^'"]+['"]\s*\}/g;
    let match;
    const text = String(templateStr || '');
    while ((match = re.exec(text)) !== null) {
        const name = match[1];
        if (name && name !== 'site_elements' && collections.indexOf(name) === -1) {
            collections.push(name);
        }
    }
    return collections;
}

function getTemplateCollectionSlugs(templateStr) {
    const collections = [];
    const quoted = templateStr.match(/\{for\s+(?:each\s+)?[a-zA-Z0-9_]+\s+in\s+['"]([^'"]+)['"][^}]*\}/g);
    (quoted || []).forEach((match) => {
        const m = match.match(/['"]([^'"]+)['"]/);
        if (m && collections.indexOf(m[1]) === -1) collections.push(m[1]);
    });
    return collections;
}

function getAllTemplateCollectionSlugs(templateStr) {
    const collections = getTemplateCollectionSlugs(templateStr);
    getTemplateFromEntryCollectionSlugs(templateStr).forEach((name) => {
        if (collections.indexOf(name) === -1) collections.push(name);
    });
    return collections;
}

let failures = 0;
function assertTrue(name, ok) {
    console.log(name + ': ' + (ok ? 'PASS' : 'FAIL'));
    if (!ok) failures++;
}

const aboutHtml = "{from 'about' get entry 'about-page'}<h1>{ title }</h1>{endget}";
assertTrue('detects about from-block', getTemplateFromEntryCollectionSlugs(aboutHtml).join(',') === 'about');
assertTrue('skips site_elements from-block', getTemplateFromEntryCollectionSlugs("{from 'site_elements' get entry 'hero'}{endget}").length === 0);

const homeHtml = "{from 'site_elements' get entry 'hero'}{endget}{for each entry in 'blog' limit 3}{endfor}{for each entry in 'gallery' limit 3}{endfor}";
const all = getAllTemplateCollectionSlugs(homeHtml);
assertTrue('home includes blog and gallery', all.indexOf('blog') !== -1 && all.indexOf('gallery') !== -1);
assertTrue('home excludes site_elements from from-block', all.indexOf('site_elements') === -1);

const headerHtml = "{from 'site_elements' get entry 'navigation'}<a>{'content'.0.'value'}</a>{endget}";
assertTrue('header from-block only site_elements', getAllTemplateCollectionSlugs(headerHtml).length === 0);

console.log('\nfrom_block_prefetch_test: ' + (failures === 0 ? 'PASS' : 'FAIL (' + failures + ')'));
process.exit(failures === 0 ? 0 : 1);
