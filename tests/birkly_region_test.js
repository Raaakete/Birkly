#!/usr/bin/env node
'use strict';

const engine = require('../birkly-client-modules/birkly-render.js');
const components = require('../birkly-components.js');

let failures = 0;
function assertTrue(name, ok) {
    console.log(name + ': ' + (ok ? 'PASS' : 'FAIL'));
    if (!ok) failures++;
}

// --- Default registry ---
const headerTpl = engine.getRegionDefaultTemplate('site_elements', 'header');
assertTrue('header default includes from/get', /\{from\s+'site_elements'\s+get\s+entry\s+'header'\}/i.test(headerTpl));
assertTrue('header default includes endget', /\{endget\}/i.test(headerTpl));

const footerTpl = engine.getRegionDefaultTemplate('site_elements', 'footer');
assertTrue('footer default includes footer slug', footerTpl.includes("get entry 'footer'"));

const navTpl = engine.getRegionDefaultTemplate('site_elements', 'navigation');
assertTrue('navigation default includes nav wrapper', navTpl.includes('birkly-site-nav'));

const generic = engine.getRegionDefaultTemplate('beta_hero', 'main');
assertTrue('generic entry default wraps from/get', /\{from\s+'beta_hero'\s+get\s+entry\s+'main'\}/i.test(generic));

// --- buildRegionTemplate: entry mode ---
const emptyEntry = engine.buildRegionTemplate({
    collection: 'site_elements',
    entry: 'header',
    childHtml: ''
});
assertTrue('empty entry uses registry default', emptyEntry === headerTpl);

const childEntry = engine.buildRegionTemplate({
    collection: 'beta_hero',
    entry: 'main',
    childHtml: '<h1>{h1}</h1><p>{subtitle}</p>'
});
assertTrue('child template wrapped with from/get', childEntry.startsWith("{from 'beta_hero' get entry 'main'}"));
assertTrue('child template preserves markup', childEntry.includes('<h1>{h1}</h1>'));
assertTrue('child template ends with endget', childEntry.endsWith('{endget}'));

const alreadyWrapped = engine.buildRegionTemplate({
    collection: 'blog',
    entry: 'x',
    childHtml: "{from 'blog' get entry 'x'}{title}{endget}"
});
assertTrue('does not double-wrap existing from block', alreadyWrapped === "{from 'blog' get entry 'x'}{title}{endget}");

// --- buildRegionTemplate: list mode ---
const listDefault = engine.buildRegionTemplate({
    collection: 'blog',
    list: true,
    ordered: 'order',
    childHtml: ''
});
assertTrue('list default has for each', /\{for each entry in 'blog' ordered by order\}/i.test(listDefault));
assertTrue('list default has endfor', /\{endfor\}/i.test(listDefault));

const listChild = engine.buildRegionTemplate({
    collection: 'blog',
    list: true,
    ordered: 'order',
    childHtml: '<h2>{title}</h2>'
});
assertTrue('list child wrapped with ordered for', listChild.includes("ordered by order"));
assertTrue('list child keeps item markup', listChild.includes('<h2>{title}</h2>'));

const listNoOrder = engine.buildRegionTemplate({
    collection: 'gallery',
    list: true,
    childHtml: '<figure>{caption}</figure>'
});
assertTrue('list without ordered attr omits ordered by', !/ordered by/i.test(listNoOrder));

// --- Render entry default with mock data ---
const headerData = {
    site_elements: {
        header: { slug: 'header', title: 'Site', content: '<nav>Home</nav>' }
    },
    '__entry__:site_elements:header': { slug: 'header', title: 'Site', content: '<nav>Home</nav>' }
};
const headerOut = engine.render(headerTpl, headerData);
assertTrue('header default renders content', headerOut.includes('<nav>Home</nav>'));
assertTrue('header default drops control tags', !headerOut.includes('{from') && !headerOut.includes('{endget}'));

// --- Render list with ordered ---
const listData = {
    blog: [
        { title: 'Second', order: 2 },
        { title: 'First', order: 1 }
    ]
};
const listOut = engine.render(listDefault, listData);
assertTrue('ordered list renders First before Second', listOut.indexOf('First') < listOut.indexOf('Second'));

const childListOut = engine.render(listChild, listData);
assertTrue('child list template renders titles', childListOut.includes('<h2>First</h2>') && childListOut.includes('<h2>Second</h2>'));

// --- Bundle requests from built templates ---
const entryReqs = engine.collectBundleRequests(emptyEntry);
assertTrue(
    'entry default requests get_entry',
    entryReqs.some((r) => r.type === 'get_entry' && r.collection === 'site_elements' && r.slug === 'header')
);
const listReqs = engine.collectBundleRequests(listDefault);
assertTrue(
    'list default requests list_entries',
    listReqs.some((r) => r.type === 'list_entries' && r.collection === 'blog')
);

// --- Child entry render ---
const heroData = {
    '__entry__:beta_hero:main': { h1: 'Hello', subtitle: 'World' },
    beta_hero: [{ slug: 'main', h1: 'Hello', subtitle: 'World' }]
};
const heroOut = engine.render(childEntry, heroData);
assertTrue('child entry template fills fields', heroOut.includes('<h1>Hello</h1>') && heroOut.includes('<p>World</p>'));

// --- Enhance-only nav helper (M5) ---
function mockLink(href, className) {
    const classes = new Set((className || '').split(/\s+/).filter(Boolean));
    const attrs = { href: href };
    return {
        getAttribute(name) { return Object.prototype.hasOwnProperty.call(attrs, name) ? attrs[name] : null; },
        setAttribute(name, value) { attrs[name] = String(value); },
        removeAttribute(name) { delete attrs[name]; },
        classList: {
            toggle(name, on) {
                if (on) classes.add(name);
                else classes.delete(name);
            },
            contains(name) { return classes.has(name); }
        },
        _classes: classes,
        _attrs: attrs
    };
}

const home = mockLink('/');
const about = mockLink('/about');
const root = {
    querySelectorAll() { return [home, about]; }
};
const n = components.setActiveNav(root, '/about');
assertTrue('setActiveNav returns link count', n === 2);
assertTrue('active class on matching link', about.classList.contains('active'));
assertTrue('aria-current on matching link', about._attrs['aria-current'] === 'page');
assertTrue('no active on other link', !home.classList.contains('active'));
assertTrue('aria-current removed from other link', !Object.prototype.hasOwnProperty.call(home._attrs, 'aria-current'));

console.log('\nbirkly_region_test: ' + (failures === 0 ? 'PASS' : 'FAIL (' + failures + ')'));
process.exit(failures === 0 ? 0 : 1);
