#!/usr/bin/env node
'use strict';

/**
 * P52-M1 — Fixture renderer for the JS engine side of the PHP/JS parity
 * harness (V-PHP-12).
 *
 * Renders every case in tests/template-engine/fixtures/*.json through the
 * canonical JS engine (birkly-template-engine.js) and prints a single JSON
 * document to stdout:
 *
 *   { "ok": true, "results": [ { "file": "...", "name": "...", "output": "..." } ] }
 *
 * The PHP counterpart (tests/template_engine_parity_test.php) renders the
 * same cases with core/birkly_template_engine.php and byte-compares outputs.
 *
 * Usage: node tests/template-engine/render-fixtures.js [fixture.json ...]
 * (no args = all fixture files, sorted by name)
 */

const fs = require('fs');
const path = require('path');

const engine = require(path.join(__dirname, '..', '..', 'birkly-template-engine.js'));
const fixturesDir = path.join(__dirname, 'fixtures');

function listFixtureFiles() {
    const args = process.argv.slice(2);
    if (args.length) {
        return args;
    }
    return fs.readdirSync(fixturesDir)
        .filter((f) => f.endsWith('.json'))
        .sort()
        .map((f) => path.join(fixturesDir, f));
}

const results = [];
try {
    for (const file of listFixtureFiles()) {
        const doc = JSON.parse(fs.readFileSync(file, 'utf8'));
        const cases = Array.isArray(doc.cases) ? doc.cases : [];
        for (const testCase of cases) {
            const output = engine.render(
                String(testCase.html || ''),
                testCase.data || {},
                testCase.options || {}
            );
            results.push({
                file: path.basename(file),
                name: String(testCase.name || ''),
                output: String(output)
            });
        }
    }
    process.stdout.write(JSON.stringify({ ok: true, results: results }));
} catch (e) {
    process.stdout.write(JSON.stringify({ ok: false, error: String(e && e.message ? e.message : e) }));
    process.exit(1);
}
