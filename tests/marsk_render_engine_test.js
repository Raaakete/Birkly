#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');
const engine = require('../birkly-client-modules/birkly-render.js');

const marskRoot = path.join(__dirname, '../internal/cms/packages/starter-packs/marsk/project');
// Fallback when marketplace not sibling — use inline snippets
const marketplaceRoot = path.join(__dirname, '../../Birkly-Marketplace/internal/cms/packages/starter-packs/marsk/project');

function readPage(name) {
    for (const root of [marskRoot, marketplaceRoot]) {
        const p = path.join(root, name);
        if (fs.existsSync(p)) return fs.readFileSync(p, 'utf8');
    }
    return '';
}

const mockData = {
    current_year: 2026,
    site_elements: {
        navigation: {
            key: 'navigation',
            content: [{ type: 'text', value: 'Marsk' }]
        },
        hero: {
            key: 'hero',
            content: [
                { type: 'text', value: 'Notes' },
                { type: 'text', value: 'on' },
                { type: 'text', value: 'slow' },
                { type: 'text', value: 'living' },
                { type: 'text', value: 'A journal of craft and the everyday.' }
            ]
        },
        marquee: {
            key: 'marquee',
            content: [
                { value: 'Bread' },
                { value: 'Mending' },
                { value: 'Walking' }
            ]
        }
    },
    blog: [
        {
            slug: 'quiet-hour-before-city-wakes',
            title: 'The quiet hour before the city wakes',
            category: 'Rituals',
            excerpt: 'Morning notes.',
            art_class: 'art--01',
            status: 'published',
            date: '2026-07-12',
            created_at: '2026-07-12T00:00:00+00:00'
        },
        {
            slug: 'bread-patience-slow-proofs',
            title: 'Bread, patience, slow proofs',
            category: 'Craft',
            excerpt: 'On sourdough.',
            art_class: 'art--02',
            status: 'published',
            date: '2026-07-05',
            created_at: '2026-07-05T00:00:00+00:00'
        }
    ],
    gallery: [
        {
            slug: 'five-am-kitchen-table',
            caption: 'Five a.m., kitchen table',
            category: 'mornings',
            art_class: 'art--01',
            status: 'published',
            order: 1
        },
        {
            slug: 'harbour-low-tide',
            caption: 'Harbour at low tide',
            category: 'places',
            art_class: 'art--03',
            status: 'published',
            order: 2
        }
    ],
    about: [
        {
            id: 'about-page',
            slug: 'about-page',
            title: "Hello, I'm June.",
            art_class: 'art--02',
            content: '<p class="statement">Marsk started as notebooks.</p>',
            status: 'published'
        }
    ]
};

let failures = 0;
function assertTrue(name, ok) {
    console.log(name + ': ' + (ok ? 'PASS' : 'FAIL'));
    if (!ok) failures++;
}

function assertNoRawTags(name, html) {
    const bad = /\{from\s+|\{for\s+|\{if\s+|\{endget\}|\{'content'/.test(html);
    assertTrue(name + ' has no raw template tags', !bad);
}

// Gallery if/else loop (the bug that broke gallery)
const galleryLoop = `{for each entry in 'gallery' with status = published ordered by order}
{if entry has 'aspect_ratio'}
<figure data-cat="{ category }" style="aspect-ratio:{ aspect_ratio }"><div class="art { art_class }"></div></figure>
{else}
<figure data-cat="{ category }"><div class="art { art_class }"></div><figcaption>{ caption }</figcaption></figure>
{endif}
{endfor}`;
const galleryOut = engine.render(galleryLoop, mockData);
assertTrue('gallery loop renders 2 figures', (galleryOut.match(/<figure/g) || []).length === 2);
assertTrue('gallery else branch shows caption', galleryOut.includes('Five a.m., kitchen table'));
assertNoRawTags('gallery loop', galleryOut);

// Header from block
const headerTpl = `{from 'site_elements' get entry 'navigation'}
<a class="brand">{'content'.0.'value'}</a>
{endget}`;
const headerOut = engine.render(headerTpl, mockData);
assertTrue('header shows brand', headerOut.includes('Marsk'));
assertNoRawTags('header', headerOut);

// Blog loop with limit
const blogLoop = `{for each entry in 'blog' with status = published ordered by newest limit 1}
<h2>{ title }</h2>
{endfor}`;
const blogOut = engine.render(blogLoop, mockData);
assertTrue('blog limit 1 shows newest', blogOut.includes('quiet hour'));
assertTrue('blog limit 1 excludes second', !blogOut.includes('Bread, patience'));

// About from block
const aboutTpl = `{from 'about' get entry 'about-page'}
<h1>{ title }</h1>
<div class="art { art_class }"></div>
{ content }
{endget}`;
const aboutData = Object.assign({}, mockData, { about: mockData.about });
const aboutOut = engine.render(aboutTpl, aboutData);
assertTrue('about title renders', aboutOut.includes('Hello'));
assertTrue('about art_class renders', aboutOut.includes('art--02'));
assertNoRawTags('about', aboutOut);

// Marquee field loop
const marqueeTpl = `{from 'site_elements' get entry 'marquee'}
{for each entry in content}<span>{ value }</span>{endfor}
{endget}`;
const marqueeOut = engine.render(marqueeTpl, mockData);
assertTrue('marquee loops content field', marqueeOut.includes('Bread') && marqueeOut.includes('Mending'));

// collectBundleRequests
const reqs = engine.collectBundleRequests(readPage('index.html') || galleryLoop + aboutTpl);
const into = reqs.map((r) => r.into);
assertTrue('bundle requests include blog', into.includes('blog'));
assertTrue('bundle requests include gallery', into.includes('gallery'));
assertTrue('bundle requests include site_elements', into.includes('site_elements'));

// Full main excerpt from index if available
const indexHtml = readPage('index.html');
if (indexHtml) {
    const mainMatch = indexHtml.match(/<main[^>]*>([\s\S]*)<\/main>/i);
    if (mainMatch) {
        const mainOut = engine.render(mainMatch[1], mockData);
        assertTrue('index main has featured post link', mainOut.includes('quiet-hour-before-city-wakes') || mainOut.includes('quiet hour'));
        assertTrue('index main has post-grid cards', (mainOut.match(/post-card/g) || []).length >= 2);
        assertNoRawTags('index main', mainOut);
    }
}

console.log('\nmarsk_render_engine_test: ' + (failures === 0 ? 'PASS' : 'FAIL (' + failures + ')'));
process.exit(failures === 0 ? 0 : 1);
