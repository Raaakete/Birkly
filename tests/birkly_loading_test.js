#!/usr/bin/env node
'use strict';

/**
 * P51-M4: client loading / animation / skeleton helpers.
 * Unit tests for birkly-client-modules/birkly-loading.js using minimal DOM
 * mocks (same spirit as template_regions_test.js — no real browser).
 *
 * Matrix: V-CLI-01..06.
 */

const loading = require('../birkly-client-modules/birkly-loading.js');

let failures = 0;
function assertTrue(name, ok) {
    console.log(name + ': ' + (ok ? 'PASS' : 'FAIL'));
    if (!ok) failures++;
}

// --- Minimal DOM mocks -----------------------------------------------------

function mockClassList() {
    const set = new Set();
    return {
        add(c) { set.add(c); },
        remove(c) { set.delete(c); },
        contains(c) { return set.has(c); },
        _set: set
    };
}

function mockElement(tagName, attrs) {
    const attrMap = Object.assign({}, attrs || {});
    const el = {
        tagName: String(tagName || '').toUpperCase(),
        classList: mockClassList(),
        _listeners: {},
        getAttribute(n) {
            return Object.prototype.hasOwnProperty.call(attrMap, n) ? attrMap[n] : null;
        },
        hasAttribute(n) { return Object.prototype.hasOwnProperty.call(attrMap, n); },
        setAttribute(n, v) { attrMap[n] = v == null ? '' : String(v); },
        removeAttribute(n) { delete attrMap[n]; },
        appendChild(child) { (el.children = el.children || []).push(child); return child; },
        addEventListener(type, fn) { (el._listeners[type] = el._listeners[type] || []).push(fn); },
        dispatchEvent(evt) {
            const fns = el._listeners[evt.type] || [];
            fns.forEach((fn) => fn(evt));
            return true;
        }
    };
    el.children = [];
    return el;
}

function mockDocument(config) {
    config = config || {};
    const html = mockElement('html', config.htmlAttrs);
    const body = mockElement('body', config.bodyAttrs);
    const head = mockElement('head');
    const extra = config.extra || [];
    const metas = config.metas || [];
    const listeners = {};
    const byId = {};

    function matches(el, selector) {
        // Extremely small selector engine: supports comma lists of
        // [attr], .class, and meta[name="..."].
        return selector.split(',').some((raw) => {
            const s = raw.trim();
            if (s === '[data-birkly-skeleton]') return el.hasAttribute('data-birkly-skeleton');
            let m = s.match(/^\[([a-z0-9-]+)\]$/i);
            if (m) return el.hasAttribute(m[1]);
            m = s.match(/^\.([a-z0-9-]+)$/i);
            if (m) return el.classList.contains(m[1]);
            m = s.match(/^meta\[name="([^"]+)"\]$/);
            if (m) return el.tagName === 'META' && el.getAttribute('name') === m[1];
            return false;
        });
    }

    const all = [html, body, head].concat(extra, metas);

    const doc = {
        documentElement: html,
        body: body,
        head: head,
        createElement(tag) { return mockElement(tag); },
        getElementById(id) { return byId[id] || null; },
        getElementsByTagName(tag) {
            return all.filter((el) => el.tagName === String(tag).toUpperCase());
        },
        querySelector(selector) {
            const found = all.filter((el) => matches(el, selector));
            return found.length ? found[0] : null;
        },
        querySelectorAll(selector) {
            return all.filter((el) => matches(el, selector));
        },
        addEventListener(type, fn) { (listeners[type] = listeners[type] || []).push(fn); },
        dispatchEvent(evt) {
            (listeners[evt.type] || []).forEach((fn) => fn(evt));
            return true;
        }
    };
    // Track injected style tags by id when appended to head.
    const origAppend = head.appendChild;
    head.appendChild = function (child) {
        if (child && child.id) byId[child.id] = child;
        return origAppend.call(head, child);
    };
    doc._registerExtra = (el) => { all.push(el); };
    return doc;
}

function mockWindow() {
    let nextId = 1;
    const pending = {};
    const microtasks = [];
    const win = {
        _timers: pending,
        setTimeout(fn, ms) { const id = nextId++; pending[id] = { fn, ms }; return id; },
        clearTimeout(id) { delete pending[id]; },
        queueMicrotask(fn) { microtasks.push(fn); },
        CustomEvent: function (type, init) { this.type = type; this.detail = (init && init.detail) || null; },
        flushMicrotasks() { while (microtasks.length) microtasks.shift()(); },
        runTimer(id) { if (pending[id]) { const t = pending[id]; delete pending[id]; t.fn(); } },
        runAllTimers() { Object.keys(pending).forEach((id) => this.runTimer(id)); }
    };
    return win;
}

// --- V-CLI-01: default path — no opt-in, nothing hidden/toggled ------------
(function () {
    const win = mockWindow();
    const doc = mockDocument();
    const inst = loading.create(win, doc);
    inst.setup();
    win.runAllTimers();
    const htmlReady = doc.documentElement.classList.contains('birkly-ready');
    const bodyReady = doc.body.classList.contains('birkly-ready');
    // Even after reveal fires via timeout, no opt-in attrs => no visible effect
    // beyond the (idempotent) class add on html/body. The injected style is inert
    // without opt-in attributes. Assert the style tag is present but page is not
    // "held" (no opt-in selectors match anything).
    const styleInjected = !!doc.getElementById('birkly-loading-styles');
    assertTrue('V-CLI-01 default path injects inert style, no opt-in elements',
        styleInjected && doc.querySelectorAll('[data-birkly-loading]').length === 0);
    // reveal still safe to have run; html/body ready class harmless
    void htmlReady; void bodyReady;
})();

// --- V-CLI-02: reveal on templates-processed -------------------------------
(function () {
    const win = mockWindow();
    const doc = mockDocument({ htmlAttrs: { 'data-birkly-loading': '' } });
    const inst = loading.create(win, doc);
    inst.setup();
    assertTrue('V-CLI-02a not ready before event', inst.isReady() === false);
    assertTrue('V-CLI-02b html not yet revealed',
        doc.documentElement.classList.contains('birkly-ready') === false);
    doc.dispatchEvent(new win.CustomEvent('birkly:templates-processed'));
    assertTrue('V-CLI-02c ready after templates-processed', inst.isReady() === true);
    assertTrue('V-CLI-02d html revealed after event',
        doc.documentElement.classList.contains('birkly-ready') === true);
})();

// --- V-CLI-03: SSR meta short-circuit reveals via microtask ----------------
(function () {
    const win = mockWindow();
    const metaSsr = mockElement('meta', { name: 'birkly-ssr', content: '1' });
    const doc = mockDocument({ htmlAttrs: { 'data-birkly-loading': '' }, metas: [metaSsr] });
    assertTrue('V-CLI-03a isSsrRendered detects meta', loading.isSsrRendered(doc) === true);
    const inst = loading.create(win, doc);
    inst.setup();
    assertTrue('V-CLI-03b not ready before microtask flush', inst.isReady() === false);
    win.flushMicrotasks();
    assertTrue('V-CLI-03c ready after microtask (SSR)', inst.isReady() === true);
    assertTrue('V-CLI-03d no safety timer registered for SSR path',
        Object.keys(win._timers).length === 0);
})();

// --- V-CLI-04: safety timeout guarantees reveal ----------------------------
(function () {
    const win = mockWindow();
    const doc = mockDocument({ bodyAttrs: { 'data-birkly-reveal': '' } });
    const inst = loading.create(win, doc, { timeout: 8000 });
    inst.setup();
    assertTrue('V-CLI-04a not ready before timeout', inst.isReady() === false);
    win.runAllTimers();
    assertTrue('V-CLI-04b ready after safety timeout', inst.isReady() === true);
    assertTrue('V-CLI-04c body revealed by timeout',
        doc.body.classList.contains('birkly-ready') === true);
})();

// --- V-CLI-05: animation holdback target gets ready class ------------------
(function () {
    const win = mockWindow();
    const doc = mockDocument({ htmlAttrs: { 'data-birkly-hold-animations': '' } });
    const inst = loading.create(win, doc);
    inst.setup();
    assertTrue('V-CLI-05a hold element not ready initially',
        doc.documentElement.classList.contains('birkly-ready') === false);
    doc.dispatchEvent(new win.CustomEvent('birkly:templates-processed'));
    assertTrue('V-CLI-05b hold released (ready class added) on process',
        doc.documentElement.classList.contains('birkly-ready') === true);
})();

// --- V-CLI-06: skeleton lifecycle -----------------------------------------
(function () {
    const win = mockWindow();
    const region = mockElement('div', { 'data-birkly-skeleton': '' });
    const doc = mockDocument({ htmlAttrs: { 'data-birkly-loading': '' }, extra: [region] });
    const inst = loading.create(win, doc);
    inst.setup();
    assertTrue('V-CLI-06a declared skeleton gets shimmer class',
        region.classList.contains('birkly-skeleton') === true);

    // region-processed on that element clears its skeleton
    const evt = new win.CustomEvent('birkly:region-processed');
    evt.target = region;
    doc.dispatchEvent(evt);
    assertTrue('V-CLI-06b region-processed clears its skeleton',
        region.classList.contains('birkly-skeleton') === false);

    // Manual API round-trip
    const el = mockElement('section');
    inst.showSkeleton(el);
    assertTrue('V-CLI-06c showSkeleton adds class', el.classList.contains('birkly-skeleton') === true);
    inst.hideSkeleton(el);
    assertTrue('V-CLI-06d hideSkeleton removes class', el.classList.contains('birkly-skeleton') === false);

    // reveal clears any remaining skeletons
    const leftover = mockElement('aside');
    inst.showSkeleton(leftover);
    doc._registerExtra(leftover);
    doc.dispatchEvent(new win.CustomEvent('birkly:templates-processed'));
    assertTrue('V-CLI-06e templates-processed clears leftover skeletons',
        leftover.classList.contains('birkly-skeleton') === false);
})();

console.log('\nbirkly_loading_test: ' + (failures === 0 ? 'PASS' : 'FAIL (' + failures + ')'));
process.exit(failures === 0 ? 0 : 1);
