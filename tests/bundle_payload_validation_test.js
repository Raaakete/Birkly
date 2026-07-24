#!/usr/bin/env node
'use strict';

function entryPayloadLooksStripped(entry) {
    if (!entry || typeof entry !== 'object') {
        return true;
    }
    if (entry.content || entry.excerpt || entry.slug || entry.key) {
        return false;
    }
    for (const key of Object.keys(entry)) {
        if (typeof key === 'string' && key.length === 2 && entry[key] && typeof entry[key] === 'object') {
            return false;
        }
    }
    const allowed = new Set(['id', 'title', 'status', 'updated_at', 'created_at', 'published_at']);
    const keys = Object.keys(entry);
    return keys.length > 0 && keys.every((key) => allowed.has(key));
}

function bundlePayloadIsValid(data) {
    if (!data || typeof data !== 'object') {
        return false;
    }
    for (const [key, rows] of Object.entries(data)) {
        if (key === '__tf_meta__' || !Array.isArray(rows) || rows.length === 0) {
            continue;
        }
        if (entryPayloadLooksStripped(rows[0])) {
            return false;
        }
    }
    return true;
}

let failures = 0;
function assertTrue(name, ok) {
    console.log(name + ': ' + (ok ? 'PASS' : 'FAIL'));
    if (!ok) failures++;
}

const stripped = [{ id: 'hero', title: 'Hero', status: 'published', updated_at: 'x', created_at: 'x' }];
const full = [{ id: 'hero', slug: 'hero', key: 'hero', content: [{ type: 'text', value: 'Notes' }] }];

assertTrue('detects stripped site_elements', !bundlePayloadIsValid({ site_elements: stripped }));
assertTrue('accepts full site_elements', bundlePayloadIsValid({ site_elements: full }));
assertTrue('accepts empty bundle sections', bundlePayloadIsValid({ blog: [] }));

console.log('\nbundle_payload_validation_test: ' + (failures === 0 ? 'PASS' : 'FAIL (' + failures + ')'));
process.exit(failures === 0 ? 0 : 1);
