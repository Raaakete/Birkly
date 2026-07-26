#!/usr/bin/env node
'use strict';

const engine = require('../birkly-client-modules/birkly-render.js');

let failures = 0;
function assertTrue(name, ok) {
    console.log(name + ': ' + (ok ? 'PASS' : 'FAIL'));
    if (!ok) failures++;
}

function mockElement(tagName, innerHTML, attrs, children) {
    const attrMap = Object.assign({}, attrs || {});
    const el = {
        tagName: String(tagName || '').toUpperCase(),
        innerHTML: innerHTML || '',
        children: children || [],
        contains(other) {
            if (other === this) return true;
            return this.children.some((child) => child === other || child.contains(other));
        },
        getAttribute(name) {
            if (!Object.prototype.hasOwnProperty.call(attrMap, name)) {
                return null;
            }
            return attrMap[name];
        },
        hasAttribute(name) {
            return Object.prototype.hasOwnProperty.call(attrMap, name);
        },
        setAttribute(name, value) {
            attrMap[name] = value == null ? '' : String(value);
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

// --- P45-M2: scoped root discovery ---
const scopedInner = mockElement('div', "{from 'site_elements' get entry 'header'}{title}{endget}");
const scopedOuter = mockElement('section', 'static', {}, [scopedInner]);
const scopedDoc = mockDocument([scopedOuter]);
const scopedRegions = engine.discoverPageRegions(scopedDoc, scopedInner);
assertTrue(
    'scoped root discovers the root element itself',
    scopedRegions.length === 1 && scopedRegions[0].element === scopedInner
);

const siblingA = mockElement('div', "{for each entry in 'blog'}{title}{endfor}");
const siblingB = mockElement('aside', "{for each entry in 'gallery'}{caption}{endfor}");
const wrap = mockElement('div', '', {}, [siblingA, siblingB]);
const wrapDoc = mockDocument([wrap]);
const onlyA = engine.discoverPageRegions(wrapDoc, siblingA);
assertTrue('scoped root does not include sibling regions', onlyA.length === 1 && onlyA[0].element === siblingA);

// --- P45-M4: data-birkly-region as explicit region root ---
const nestedTpl = "{for each entry in 'items'}{title}{endfor}";
const explicitChild = mockElement('div', nestedTpl, { 'data-birkly-region': '' });
const parentWithBoth = mockElement('main', '', {}, [explicitChild]);
// Parent innerHTML includes child templates (browser behavior) — explicit child must win.
parentWithBoth.innerHTML = "{for each entry in 'blog'}{title}{endfor}" + nestedTpl;
const explicitDoc = mockDocument([parentWithBoth]);
const explicitRegions = engine.discoverPageRegions(explicitDoc);
assertTrue(
    'data-birkly-region is its own region even when nested under template parent',
    explicitRegions.length === 1 && explicitRegions[0].element === explicitChild
);
assertTrue(
    'template-bearing ancestor is demoted when it contains data-birkly-region',
    !explicitRegions.some((r) => r.element === parentWithBoth)
);

const sectionA = mockElement('section', "{for each entry in 'blog'}{title}{endfor}", { 'data-birkly-region': '' });
const sectionB = mockElement('aside', "{for each entry in 'gallery'}{caption}{endfor}", { 'data-birkly-region': '' });
const multiParent = mockElement('main', 'x', {}, [sectionA, sectionB]);
multiParent.innerHTML = sectionA.innerHTML + sectionB.innerHTML;
const multiDoc = mockDocument([multiParent]);
const multiRegions = engine.discoverPageRegions(multiDoc);
assertTrue(
    'multiple data-birkly-region siblings are separate regions',
    multiRegions.length === 2 &&
        multiRegions.some((r) => r.element === sectionA) &&
        multiRegions.some((r) => r.element === sectionB)
);

const emptyExplicit = mockElement('div', '', { 'data-birkly-region': '' });
const emptyDoc = mockDocument([emptyExplicit]);
const emptyRegions = engine.discoverPageRegions(emptyDoc);
assertTrue(
    'data-birkly-region without template syntax is still a region',
    emptyRegions.length === 1 && emptyRegions[0].element === emptyExplicit
);

const processed = mockElement('div', "{for each entry in 'blog'}{title}{endfor}", {
    'data-birkly-processed': '1'
});
const processedDoc = mockDocument([processed]);
const skipped = engine.discoverPageRegions(processedDoc);
assertTrue('skips data-birkly-processed regions', skipped.length === 0);
const forced = engine.discoverPageRegions(processedDoc, null, { force: true });
assertTrue('force option includes processed regions', forced.length === 1);

const birklyTag = mockElement('birkly-region', '', { collection: 'site_elements', entry: 'header' });
const tagDoc = mockDocument([birklyTag]);
const tagRegions = engine.discoverPageRegions(tagDoc);
assertTrue('BIRKLY-REGION tag is an explicit region', tagRegions.length === 1 && tagRegions[0].element === birklyTag);

console.log('\ntemplate_regions_test: ' + (failures === 0 ? 'PASS' : 'FAIL (' + failures + ')'));
process.exit(failures === 0 ? 0 : 1);
