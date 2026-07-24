#!/usr/bin/env node
'use strict';

const engine = require('../birkly-client-modules/birkly-render.js');

let failures = 0;
function assertTrue(name, ok) {
    console.log(name + ': ' + (ok ? 'PASS' : 'FAIL'));
    if (!ok) failures++;
}

function mockElement(tagName, innerHTML, attrs) {
    const el = {
        tagName: String(tagName || '').toUpperCase(),
        innerHTML: innerHTML || '',
        getAttribute(name) {
            if (!attrs || !Object.prototype.hasOwnProperty.call(attrs, name)) {
                return null;
            }
            return attrs[name];
        }
    };
    el.textContent = el.innerHTML;
    return el;
}

function mockDocument(spec) {
    const elements = spec.elements || [];
    const byTag = {};
    elements.forEach((el) => {
        const tag = el.tagName.toLowerCase();
        if (!byTag[tag]) byTag[tag] = [];
        byTag[tag].push(el);
    });

    return {
        querySelector(selector) {
            if (selector === 'main') return byTag.main ? byTag.main[0] : null;
            if (selector === 'header') return byTag.header ? byTag.header[0] : null;
            if (selector === 'footer') return byTag.footer ? byTag.footer[0] : null;
            if (selector === 'title') return byTag.title ? byTag.title[0] : null;
            return null;
        },
        querySelectorAll(selector) {
            if (selector === 'nav') return byTag.nav || [];
            if (selector === '[data-birkly-region]') {
                return elements.filter((el) => el.getAttribute('data-birkly-region') !== null);
            }
            return [];
        }
    };
}

const navTpl = "{for each entry in 'nav-links' ordered by order}<a href=\"{url}\">{label}</a>{endfor}";
const navEl = mockElement('nav', navTpl);
const doc = mockDocument({ elements: [navEl] });
const regions = engine.discoverPageRegions(doc);

assertTrue('discovers nav with template syntax', regions.length === 1 && regions[0].element === navEl);
assertTrue('nav region keeps template source', regions[0].template === navTpl);

const overlayTpl = "{for each entry in 'nav-links'}<a>{label}</a>{endfor}";
const overlay = mockElement('div', overlayTpl, { 'data-birkly-region': '' });
const overlayDoc = mockDocument({ elements: [overlay] });
const overlayRegions = engine.discoverPageRegions(overlayDoc);
assertTrue('discovers data-birkly-region elements', overlayRegions.length === 1);

const navData = {
    'nav-links': [
        { label: 'Home', url: '/', order: 1, status: 'published' },
        { label: 'About', url: '/about', order: 2, status: 'draft' }
    ]
};
const navOut = engine.render(navTpl, navData);
assertTrue('hyphenated nav-links collection renders', navOut.includes('Home') && navOut.includes('About'));
assertTrue('hyphenated slug in bundle requests', engine.collectBundleRequests(navTpl).some((r) => r.collection === 'nav-links'));

const filteredTpl = "{for each entry in 'nav-links' with status = published ordered by order}<a>{label}</a>{endfor}";
const filteredOut = engine.render(filteredTpl, navData);
assertTrue('status filter on hyphenated collection', filteredOut.includes('Home') && !filteredOut.includes('About'));

console.log('\ntemplate_regions_test: ' + (failures === 0 ? 'PASS' : 'FAIL (' + failures + ')'));
process.exit(failures === 0 ? 0 : 1);
