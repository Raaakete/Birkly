#!/usr/bin/env node
'use strict';

const engine = require('../birkly-client-modules/birkly-render.js');

let failures = 0;
function assertTrue(name, ok) {
    console.log(name + ': ' + (ok ? 'PASS' : 'FAIL'));
    if (!ok) failures++;
}

function mockElement(tagName, innerHTML, attrs, children) {
    const el = {
        tagName: String(tagName || '').toUpperCase(),
        innerHTML: innerHTML || '',
        children: children || [],
        contains(other) {
            if (other === this) return true;
            return this.children.some((child) => child === other || child.contains(other));
        },
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

function mockDocument(elements) {
    const all = [];
    const collect = (el) => {
        all.push(el);
        (el.children || []).forEach(collect);
    };
    elements.forEach(collect);

    const body = mockElement('body', '', {}, elements);
    body.querySelectorAll = (selector) => {
        if (selector === '*') {
            return all.filter((el) => el !== body);
        }
        return [];
    };

    return {
        body,
        documentElement: body,
        querySelector(selector) {
            if (selector === 'title') {
                return all.find((el) => el.tagName === 'TITLE') || null;
            }
            return null;
        },
        querySelectorAll(selector) {
            if (selector === '*') {
                return body.querySelectorAll(selector);
            }
            return [];
        }
    };
}

const navTpl = "{for each entry in 'nav-links' ordered by order}<a href=\"{url}\">{label}</a>{endfor}";
const navEl = mockElement('nav', navTpl);
const doc = mockDocument([navEl]);
const regions = engine.discoverPageRegions(doc);

assertTrue('discovers nav with template syntax', regions.length === 1 && regions[0].element === navEl);
assertTrue('nav region keeps template source', regions[0].template === navTpl);

const overlayTpl = "{for each entry in 'nav-links'}<a>{label}</a>{endfor}";
const overlay = mockElement('div', overlayTpl, { 'data-birkly-region': '' });
const overlayDoc = mockDocument([overlay]);
const overlayRegions = engine.discoverPageRegions(overlayDoc);
assertTrue('discovers any element with template syntax', overlayRegions.length === 1);

const mainInner = "{for each entry in 'blog'}<h2>{title}</h2>{endfor}";
const sectionInner = "{for each entry in 'gallery'}<figure>{caption}</figure>{endfor}";
const section = mockElement('section', sectionInner);
const main = mockElement('main', mainInner, {}, [section]);
const nestedDoc = mockDocument([main]);
const nestedRegions = engine.discoverPageRegions(nestedDoc);
assertTrue('uses outermost template region only', nestedRegions.length === 1 && nestedRegions[0].element === main);

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
