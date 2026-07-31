/**
 * Birkly Template Engine v3 — thin re-export.
 *
 * The canonical implementation now lives in the repo-root shared module
 * `birkly-template-engine.js` (P51-M1). This file remains as a stable entry
 * point so existing tests and any `require('birkly-client-modules/birkly-render.js')`
 * callers keep working. It also mirrors the engine onto `Birkly.render` for
 * browser parity.
 */
(function (root) {
    'use strict';

    let engine = null;

    // Node / CommonJS: load the shared engine module directly.
    if (typeof require === 'function') {
        try {
            engine = require('../birkly-template-engine.js');
        } catch (e) {
            engine = null;
        }
    }

    // Browser fallback: the shared engine script exposes the global.
    if (!engine && root && root.BirklyTemplateEngine) {
        engine = root.BirklyTemplateEngine;
    }

    if (!engine) return;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = engine;
    }

    if (root) {
        root.BirklyTemplateEngine = engine;
        const Birkly = root.Birkly || (root.Birkly = {});
        Birkly.render = engine;
    }
})(typeof globalThis !== 'undefined' ? globalThis : (typeof window !== 'undefined' ? window : this));
