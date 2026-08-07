/**
 * Birkly WebMCP runtime (P60)
 * ===========================
 * Publishes clear, safe "tools" that an AI helper running in the *visitor's*
 * browser can call on the live public site — submit a public form, add to cart,
 * RSVP, etc. — instead of guessing from the DOM.
 *
 * Hard rules (see internal P60-M0 spec):
 *   - Off by default. Nothing registers unless the site owner enabled WebMCP.
 *   - Feature-detect the browser API and SILENTLY no-op when it is absent:
 *     no console spam, no thrown errors, no layout shift. An unsupported or
 *     disabled site behaves exactly as it does today.
 *   - Public-site actuation only. Never any admin/CMS write tools.
 *   - Mutating tools (submit / add-to-cart / checkout / rsvp / login) ask the
 *     visitor to confirm before acting.
 *   - Lifecycle via AbortSignal — there is no unregisterTool(); aborting the
 *     controller removes the tools.
 *
 * Load order: after birkly-client.js (which owns page context + public APIs).
 *
 *   <script src="/birkly-client.js" data-cms-url="https://cms"></script>
 *   <script src="/birkly-webmcp.js" data-cms-url="https://cms"></script>
 */
(function () {
    'use strict';

    // ---- Feature detection --------------------------------------------------
    // Prefer document.modelContext (current); fall back to navigator.modelContext
    // (early-preview, deprecated). When only document exposes the API, alias it
    // onto navigator so agents / older Lighthouse checks that still probe
    // navigator.modelContext can see the same object (page world only).
    function shimNavigatorModelContext() {
        try {
            if (typeof document === 'undefined' || !document.modelContext) return;
            if (typeof navigator === 'undefined') return;
            if (typeof navigator.modelContext !== 'undefined') return;
            try {
                Object.defineProperty(Navigator.prototype, 'modelContext', {
                    configurable: true,
                    get: function () { return document.modelContext; }
                });
            } catch (e1) {
                try { navigator.modelContext = document.modelContext; } catch (e2) {}
            }
        } catch (e) {}
    }

    function getModelContext() {
        try {
            shimNavigatorModelContext();
            var mc = (typeof document !== 'undefined' && document.modelContext)
                || (typeof navigator !== 'undefined' && navigator.modelContext)
                || null;
            if (!mc) return null;
            if (typeof mc.registerTool !== 'function' && !('registerTool' in mc)) return null;
            return mc;
        } catch (e) {
            return null;
        }
    }

    // ---- CMS base URL -------------------------------------------------------
    function detectCmsUrl() {
        try {
            if (typeof window !== 'undefined' && window.Birkly && window.Birkly.getConfig) {
                var cfg = window.Birkly.getConfig();
                if (cfg && cfg.cmsUrl) return String(cfg.cmsUrl).replace(/\/$/, '');
            }
        } catch (e) { /* ignore */ }
        try {
            var scripts = document.getElementsByTagName('script');
            for (var i = 0; i < scripts.length; i++) {
                var s = scripts[i];
                if (s.src && s.src.indexOf('birkly-webmcp.js') !== -1) {
                    var attr = s.getAttribute('data-cms-url');
                    if (attr) return String(attr).replace(/\/$/, '');
                    return new URL(s.src).origin;
                }
            }
            var tagged = document.querySelector('script[data-cms-url]');
            if (tagged) return String(tagged.getAttribute('data-cms-url')).replace(/\/$/, '');
        } catch (e) { /* ignore */ }
        return (typeof window !== 'undefined' && window.location) ? window.location.origin : '';
    }

    var WebMCP = {
        version: '1.0.0',
        cmsUrl: detectCmsUrl(),
        config: null,           // { enabled, tools:[], form_allowlist:[] }
        mc: null,
        started: false,
        // scoped abort controllers so we can re-register cleanly
        controllers: {},
        registered: {},         // toolName -> true (currently live)
        authorTools: [],        // { descriptor } queued before start
        confirmFn: null,        // owner/app override for consent
        debug: false
    };

    function log() {
        if (!WebMCP.debug) return;
        try { console.log.apply(console, ['[Birkly WebMCP]'].concat([].slice.call(arguments))); } catch (e) {}
    }

    function toolEnabled(name) {
        if (!WebMCP.config || !WebMCP.config.enabled) return false;
        var tools = WebMCP.config.tools || [];
        return tools.indexOf(name) !== -1;
    }

    function collectionAllowed(slug) {
        if (!WebMCP.config) return false;
        var list = WebMCP.config.form_allowlist || [];
        return list.indexOf(String(slug || '').toLowerCase()) !== -1;
    }

    // ---- Consent ------------------------------------------------------------
    // Visitor confirmation for mutating tools. Owners/apps may override via
    // Birkly.webmcp.setConfirm(fn) to show a nicer dialog; default is a plain
    // window.confirm so the gate always exists.
    function confirmAction(message) {
        if (typeof WebMCP.confirmFn === 'function') {
            try { return Promise.resolve(WebMCP.confirmFn(message)); } catch (e) { return Promise.resolve(false); }
        }
        try {
            return Promise.resolve(window.confirm(message));
        } catch (e) {
            return Promise.resolve(false);
        }
    }

    function toolResult(payload, isError) {
        // Shape a tool result that is agent-friendly and never throws.
        var text;
        try { text = typeof payload === 'string' ? payload : JSON.stringify(payload); }
        catch (e) { text = String(payload); }
        return {
            content: [{ type: 'text', text: text }],
            isError: !!isError
        };
    }

    // ---- Public API fetch (published data only) -----------------------------
    function apiGet(action, params) {
        var url = WebMCP.cmsUrl + '/api/public.php?action=' + encodeURIComponent(action);
        if (params) {
            Object.keys(params).forEach(function (k) {
                if (params[k] === undefined || params[k] === null) return;
                url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
            });
        }
        return fetch(url, { credentials: 'include' }).then(function (r) { return r.json(); });
    }

    // ---- Registration wrapper ----------------------------------------------
    function scope(name) {
        if (!WebMCP.controllers[name]) {
            WebMCP.controllers[name] = new AbortController();
        }
        return WebMCP.controllers[name].signal;
    }

    function abortScope(name) {
        var c = WebMCP.controllers[name];
        if (c) {
            try { c.abort(); } catch (e) {}
            delete WebMCP.controllers[name];
        }
    }

    /**
     * Register one tool with the browser, gated by enabled state + consent.
     * spec: { name, description, inputSchema?, annotations?, mutating?, consent?,
     *         gate?, scope?, execute }
     * `gate()` (optional) is a final per-call guard (e.g. allowlist check).
     */
    function register(spec) {
        if (!WebMCP.mc) return false;
        if (!spec || !spec.name || typeof spec.execute !== 'function') return false;
        if (WebMCP.registered[spec.name]) return true; // already live

        var mutating = !!spec.mutating;
        var consentLabel = spec.consent || (mutating ? 'confirm' : 'none');

        var execute = function (args) {
            args = args || {};
            return Promise.resolve()
                .then(function () {
                    if (typeof spec.gate === 'function') {
                        var g = spec.gate(args);
                        if (g === false) {
                            throw new Error('This action is not available on this site.');
                        }
                        if (g && typeof g.then === 'function') return g;
                    }
                })
                .then(function () {
                    if (mutating && consentLabel !== 'none') {
                        var msg = spec.confirmMessage
                            ? spec.confirmMessage(args)
                            : ('Allow the AI assistant to ' + (spec.description || spec.name) + '?');
                        return confirmAction(msg).then(function (ok) {
                            if (!ok) {
                                return { __cancelled: true };
                            }
                            return null;
                        });
                    }
                    return null;
                })
                .then(function (gateResult) {
                    if (gateResult && gateResult.__cancelled) {
                        return toolResult('The visitor did not confirm this action.', false);
                    }
                    return spec.execute(args);
                })
                .then(function (out) {
                    // Allow execute to return a raw result or a plain value.
                    if (out && out.content) return out;
                    return toolResult(out === undefined ? { ok: true } : out, false);
                })
                .catch(function (err) {
                    return toolResult({ error: (err && err.message) || String(err) }, true);
                });
        };

        var annotations = spec.annotations || {};
        if (!mutating && annotations.readOnlyHint === undefined) annotations.readOnlyHint = true;
        if (mutating && annotations.untrustedContentHint === undefined) annotations.untrustedContentHint = true;

        var descriptor = {
            name: spec.name,
            description: spec.description || spec.name,
            execute: execute,
            annotations: annotations
        };
        if (spec.inputSchema) descriptor.inputSchema = spec.inputSchema;

        try {
            WebMCP.mc.registerTool(descriptor, { signal: scope(spec.scope || 'core') });
            WebMCP.registered[spec.name] = true;
            log('registered', spec.name);
            return true;
        } catch (e) {
            log('register failed', spec.name, e);
            return false;
        }
    }

    // ---- Core tools ---------------------------------------------------------
    function pageContext() {
        var ctx = {
            title: document.title || '',
            url: (window.location && window.location.href) || '',
            path: (window.location && window.location.pathname) || '',
            language: 'en'
        };
        try {
            if (window.Birkly && window.Birkly.getConfig) {
                var cfg = window.Birkly.getConfig();
                if (cfg && cfg.currentLanguage) ctx.language = cfg.currentLanguage;
            }
        } catch (e) {}
        try {
            var params = new URLSearchParams(window.location.search);
            var col = params.get('collection');
            var slug = params.get('slug') || params.get('id');
            if (col) ctx.collection = col;
            if (slug) ctx.slug = slug;
            var metaCol = document.querySelector('meta[name="birkly-collection"]');
            if (!ctx.collection && metaCol) ctx.collection = metaCol.getAttribute('content');
        } catch (e) {}
        return ctx;
    }

    // Detect public forms rendered on the page (from {entry for} / data-birkly-*).
    function findPublicForms() {
        var forms = [];
        try {
            var els = document.querySelectorAll('form[data-birkly-collection], form[data-birkly-url]');
            for (var i = 0; i < els.length; i++) {
                var f = els[i];
                var collection = f.getAttribute('data-birkly-collection') || '';
                if (!collection) {
                    var hidden = f.querySelector('input[name="collection"]');
                    if (hidden) collection = hidden.value;
                }
                if (!collection) continue;
                var fields = [];
                var controls = f.querySelectorAll('input[name], textarea[name], select[name]');
                for (var j = 0; j < controls.length; j++) {
                    var c = controls[j];
                    var nm = c.getAttribute('name');
                    if (!nm || nm === 'collection' || nm === '_csrf' || nm === '_honeypot') continue;
                    if (c.type === 'hidden') continue;
                    fields.push({
                        name: nm,
                        type: c.getAttribute('type') || c.tagName.toLowerCase(),
                        required: c.hasAttribute('required'),
                        label: fieldLabel(f, c, nm)
                    });
                }
                forms.push({ el: f, collection: collection, fields: fields,
                    toolName: f.getAttribute('toolname') || '',
                    toolDescription: f.getAttribute('tooldescription') || '' });
            }
        } catch (e) {}
        return forms;
    }

    function fieldLabel(form, control, name) {
        try {
            if (control.id) {
                var lab = form.querySelector('label[for="' + control.id + '"]');
                if (lab) return (lab.textContent || '').trim();
            }
            var ph = control.getAttribute('placeholder');
            if (ph) return ph;
        } catch (e) {}
        return name;
    }

    function submitPublicForm(collection, fields) {
        // Reuse the existing public form submit path (never invent an endpoint).
        var body = { collection: collection, fields: fields || {} };
        return fetch(WebMCP.cmsUrl + '/api/public_submit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(body)
        }).then(function (r) { return r.json(); });
    }

    function registerCoreTools() {
        register({
            name: 'birkly_get_page_context',
            description: 'Get this page\u2019s title, URL, language, and (on an entry page) its collection and slug.',
            execute: function () { return pageContext(); }
        });

        register({
            name: 'birkly_list_published_entries',
            description: 'List published entries for a content collection on this site (public data only).',
            inputSchema: {
                type: 'object',
                properties: {
                    collection: { type: 'string', description: 'Collection slug, e.g. "blog".' },
                    limit: { type: 'number', description: 'Max entries to return (optional).' }
                },
                required: ['collection']
            },
            execute: function (args) {
                return apiGet('list_entries', { collection: args.collection, language: pageContext().language })
                    .then(function (res) {
                        var entries = (res && res.data && res.data.entries) || [];
                        if (args.limit && entries.length > args.limit) entries = entries.slice(0, args.limit);
                        return { collection: args.collection, count: entries.length, entries: entries };
                    });
            }
        });

        register({
            name: 'birkly_get_published_entry',
            description: 'Get one published entry by collection and slug or id (public fields only).',
            inputSchema: {
                type: 'object',
                properties: {
                    collection: { type: 'string' },
                    slug: { type: 'string' },
                    id: { type: 'string' }
                },
                required: ['collection']
            },
            execute: function (args) {
                return apiGet('get_entry', {
                    collection: args.collection, slug: args.slug, id: args.id,
                    language: pageContext().language
                }).then(function (res) {
                    if (!res || res.status !== 'ok') throw new Error('Entry not found');
                    return (res.data && (res.data.entry || res.data)) || res.data;
                });
            }
        });

        register({
            name: 'birkly_describe_form',
            description: 'Describe a public form on this page (its collection, fields, labels, and which are required) so it can be filled correctly.',
            inputSchema: {
                type: 'object',
                properties: { collection: { type: 'string', description: 'Optional collection slug to pick one form.' } }
            },
            execute: function (args) {
                var forms = findPublicForms().filter(function (f) {
                    return !args.collection || f.collection === args.collection;
                });
                if (!forms.length) return { forms: [], note: 'No public forms found on this page.' };
                return {
                    forms: forms.map(function (f) {
                        return {
                            collection: f.collection,
                            submittable: collectionAllowed(f.collection),
                            fields: f.fields
                        };
                    })
                };
            }
        });

        register({
            name: 'birkly_submit_public_form',
            description: 'Submit a public contact/enquiry form the site owner has opened to visitors. Asks the visitor to confirm before sending.',
            mutating: true,
            consent: 'confirm',
            inputSchema: {
                type: 'object',
                properties: {
                    collection: { type: 'string' },
                    fields: { type: 'object', description: 'Field name/value pairs matching the form.' }
                },
                required: ['collection', 'fields']
            },
            confirmMessage: function (args) {
                return 'Allow the AI assistant to submit the "' + args.collection + '" form with the information you provided?';
            },
            gate: function (args) {
                return collectionAllowed(args.collection);
            },
            execute: function (args) {
                return submitPublicForm(args.collection, args.fields).then(function (res) {
                    if (!res || res.status !== 'ok') {
                        throw new Error((res && res.message) || 'Submission failed');
                    }
                    return { ok: true, message: 'Submitted', data: res.data || null };
                });
            }
        });

        register({
            name: 'birkly_set_language',
            description: 'Change the site language, only when this page has a language selector.',
            mutating: true,
            consent: 'none',
            inputSchema: {
                type: 'object',
                properties: { language: { type: 'string', description: 'Language code, e.g. "de".' } },
                required: ['language']
            },
            gate: function () {
                return !!document.querySelector('[data-birkly-language-select]');
            },
            execute: function (args) {
                var sel = document.querySelector('[data-birkly-language-select]');
                if (!sel) throw new Error('No language selector on this page.');
                sel.value = args.language;
                sel.dispatchEvent(new Event('change', { bubbles: true }));
                return { ok: true, language: args.language };
            }
        });
    }

    // ---- Declarative HTML annotations (Chrome WebMCP Declarative API) -------
    // Lighthouse / Chrome discover tools from `toolname` + `tooldescription` on
    // <form> elements. Project footers and hard-coded public forms often omit
    // these; stamp them for every allowlisted public form so PageSpeed-capable
    // browsers and agent Chrome builds can see declarative tools without waiting
    // for authors to hand-edit markup.
    function derivedFormToolName(collection) {
        return 'birkly_form_' + String(collection || '').replace(/[^a-z0-9_]/gi, '_');
    }

    function stampAllowlistedForms() {
        if (!WebMCP.config || !WebMCP.config.enabled) return;
        if (!toolEnabled('birkly_submit_public_form')) return;
        var forms = findPublicForms();
        forms.forEach(function (f) {
            if (!collectionAllowed(f.collection)) return;
            var el = f.el;
            if (!el) return;
            if (!el.getAttribute('toolname')) {
                el.setAttribute('toolname', derivedFormToolName(f.collection));
            }
            if (!el.getAttribute('tooldescription')) {
                el.setAttribute(
                    'tooldescription',
                    'Submit the "' + f.collection + '" form on this page.'
                );
            }
            // Prefer explicit param descriptions when labels are nested/complex.
            try {
                var controls = el.querySelectorAll('input[name], textarea[name], select[name]');
                for (var i = 0; i < controls.length; i++) {
                    var c = controls[i];
                    var nm = c.getAttribute('name');
                    if (!nm || nm === 'collection' || nm === '_csrf' || nm === '_honeypot') continue;
                    if (c.type === 'hidden') continue;
                    if (c.getAttribute('toolparamdescription')) continue;
                    var label = fieldLabel(el, c, nm);
                    if (label) c.setAttribute('toolparamdescription', label);
                }
            } catch (e) {}
        });
    }

    function refreshDeclarativeForms() {
        if (!WebMCP.config || !WebMCP.config.enabled) return;
        stampAllowlistedForms();
        if (WebMCP.mc) {
            registerDeclarativeForms();
            registerAttributeTools();
        }
    }

    function scheduleDeclarativeRefresh() {
        if (WebMCP._stampTimer) {
            try { clearTimeout(WebMCP._stampTimer); } catch (e) {}
        }
        WebMCP._stampTimer = setTimeout(refreshDeclarativeForms, 60);
    }

    function wireFormObservers() {
        if (WebMCP._formObserversWired) return;
        WebMCP._formObserversWired = true;

        document.addEventListener('birkly:templates-processed', refreshDeclarativeForms);
        document.addEventListener('birkly:footer-forms-ready', refreshDeclarativeForms);

        try {
            if (typeof MutationObserver !== 'undefined') {
                var obs = new MutationObserver(function (mutations) {
                    if (!WebMCP.config || !WebMCP.config.enabled) return;
                    for (var i = 0; i < mutations.length; i++) {
                        var m = mutations[i];
                        if (m.addedNodes && m.addedNodes.length) {
                            scheduleDeclarativeRefresh();
                            return;
                        }
                    }
                });
                obs.observe(document.documentElement, { childList: true, subtree: true });
                WebMCP._formObserver = obs;
            }
        } catch (e) {}
    }

    // ---- Declarative forms (author + {entry for}) ---------------------------
    // Any public form on the page whose collection is on the allowlist becomes a
    // per-form submit tool. Forms with explicit toolname/tooldescription use
    // those; otherwise we derive a stable name. Re-scanned on templates-processed.
    function registerDeclarativeForms() {
        if (!toolEnabled('birkly_submit_public_form')) return;
        stampAllowlistedForms();
        abortScope('forms');
        var forms = findPublicForms();
        forms.forEach(function (f) {
            if (!collectionAllowed(f.collection)) return;
            var name = f.toolName || derivedFormToolName(f.collection);
            if (WebMCP.registered[name]) return;
            var props = {};
            var required = [];
            f.fields.forEach(function (fld) {
                props[fld.name] = { type: 'string', description: fld.label || fld.name };
                if (fld.required) required.push(fld.name);
            });
            register({
                name: name,
                scope: 'forms',
                description: f.toolDescription || ('Submit the "' + f.collection + '" form on this page. Asks the visitor to confirm.'),
                mutating: true,
                consent: 'confirm',
                inputSchema: { type: 'object', properties: props, required: required },
                confirmMessage: function () { return 'Submit the "' + f.collection + '" form?'; },
                gate: function () { return collectionAllowed(f.collection); },
                execute: function (args) {
                    return submitPublicForm(f.collection, args).then(function (res) {
                        if (!res || res.status !== 'ok') throw new Error((res && res.message) || 'Submission failed');
                        return { ok: true, data: res.data || null };
                    });
                }
            });
        });
    }

    // ---- Author API (data-birkly-webmcp + imperative) -----------------------
    function registerAttributeTools() {
        try {
            var els = document.querySelectorAll('[data-birkly-webmcp]');
            for (var i = 0; i < els.length; i++) {
                var el = els[i];
                var name = el.getAttribute('data-birkly-webmcp');
                if (!name || WebMCP.registered[name]) continue;
                var desc = el.getAttribute('data-birkly-webmcp-description') || name;
                var mutating = el.getAttribute('data-birkly-webmcp-mutating') === 'true';
                (function (element, toolName, description, isMutating) {
                    register({
                        name: toolName,
                        scope: 'author',
                        description: description,
                        mutating: isMutating,
                        execute: function () {
                            element.dispatchEvent(new CustomEvent('birkly:webmcp-invoke', { bubbles: true }));
                            if (typeof element.click === 'function' && (element.tagName === 'BUTTON' || element.tagName === 'A')) {
                                element.click();
                            }
                            return { ok: true };
                        }
                    });
                })(el, name, desc, mutating);
            }
        } catch (e) {}
    }

    function flushAuthorTools() {
        WebMCP.authorTools.forEach(function (spec) {
            var s = Object.assign({ scope: 'author' }, spec);
            register(s);
        });
    }

    // ---- Plugin packs (M5) --------------------------------------------------
    // Register only when (a) the plugin's public surface is present AND (b) the
    // tool is enabled in the admin catalog. Detection-first so plugins do not
    // need changes.
    function registerCommercePack() {
        var C = window.BirklyCommerce;
        var present = !!(C && typeof C.getCart === 'function');
        if (!present) return;

        if (toolEnabled('birkly_commerce_get_cart')) {
            register({
                name: 'birkly_commerce_get_cart',
                scope: 'commerce',
                description: 'Get the current shopping cart contents and totals.',
                execute: function () { return C.getCart(); }
            });
        }
        if (toolEnabled('birkly_commerce_add_to_cart')) {
            register({
                name: 'birkly_commerce_add_to_cart',
                scope: 'commerce',
                description: 'Add a product (or variant) to the shopping cart. Asks the visitor to confirm.',
                mutating: true,
                consent: 'confirm',
                inputSchema: {
                    type: 'object',
                    properties: {
                        entry_id: { type: 'string', description: 'Product entry id.' },
                        collection: { type: 'string' },
                        variant: { type: 'string' },
                        quantity: { type: 'number' }
                    },
                    required: ['entry_id']
                },
                confirmMessage: function () { return 'Add this item to your cart?'; },
                execute: function (args) {
                    return C.add({
                        entry_id: args.entry_id,
                        collection: args.collection,
                        variant: args.variant,
                        quantity: args.quantity || 1
                    });
                }
            });
        }
        if (toolEnabled('birkly_commerce_checkout')) {
            register({
                name: 'birkly_commerce_checkout',
                scope: 'commerce',
                description: 'Begin or advance checkout. The payment step always asks the visitor to confirm.',
                mutating: true,
                consent: 'pay',
                inputSchema: {
                    type: 'object',
                    properties: {
                        email: { type: 'string' },
                        group_key: { type: 'string' }
                    }
                },
                confirmMessage: function () { return 'Start checkout and proceed to payment?'; },
                execute: function (args) {
                    return C.checkout(args || {});
                }
            });
        }
    }

    function eventsSurfacePresent() {
        return !!(window.BirklyEvents
            || document.querySelector('[data-birkly-events], [data-event-rsvp], form[data-birkly-collection="events"]'));
    }

    function registerEventsPack() {
        if (!eventsSurfacePresent()) return;
        var E = window.BirklyEvents || null;

        if (toolEnabled('birkly_events_list')) {
            register({
                name: 'birkly_events_list',
                scope: 'events',
                description: 'List published events relevant to this page.',
                inputSchema: {
                    type: 'object',
                    properties: { collection: { type: 'string', description: 'Events collection slug (default "events").' } }
                },
                execute: function (args) {
                    if (E && typeof E.list === 'function') return E.list(args || {});
                    var col = (args && args.collection) || 'events';
                    return apiGet('list_entries', { collection: col, language: pageContext().language })
                        .then(function (res) { return (res && res.data && res.data.entries) || []; });
                }
            });
        }
        if (toolEnabled('birkly_events_rsvp')) {
            register({
                name: 'birkly_events_rsvp',
                scope: 'events',
                description: 'RSVP to an event on behalf of the visitor. Asks the visitor to confirm.',
                mutating: true,
                consent: 'confirm',
                inputSchema: {
                    type: 'object',
                    properties: {
                        collection: { type: 'string' },
                        fields: { type: 'object' }
                    },
                    required: ['fields']
                },
                confirmMessage: function () { return 'Send this RSVP?'; },
                gate: function (args) {
                    if (E && typeof E.rsvp === 'function') return true;
                    return collectionAllowed((args && args.collection) || 'events');
                },
                execute: function (args) {
                    if (E && typeof E.rsvp === 'function') return E.rsvp(args || {});
                    var col = (args && args.collection) || 'events';
                    return submitPublicForm(col, (args && args.fields) || {}).then(function (res) {
                        if (!res || res.status !== 'ok') throw new Error((res && res.message) || 'RSVP failed');
                        return { ok: true, data: res.data || null };
                    });
                }
            });
        }
    }

    function userSurfacePresent() {
        return !!(window.BirklyUser
            || document.querySelector('[data-birkly-login], form[data-birkly-login], [data-birkly-user-session]'));
    }

    function registerUserPack() {
        if (!userSurfacePresent()) return;
        var U = window.BirklyUser || null;

        if (toolEnabled('birkly_user_session')) {
            register({
                name: 'birkly_user_session',
                scope: 'user',
                description: 'Report whether the visitor is signed in, plus basic public session state.',
                execute: function () {
                    if (U && typeof U.session === 'function') return U.session();
                    var el = document.querySelector('[data-birkly-user-session]');
                    var loggedIn = el ? el.getAttribute('data-birkly-user-session') === 'in' : false;
                    return { signed_in: loggedIn };
                }
            });
        }
        if (toolEnabled('birkly_user_login')) {
            register({
                name: 'birkly_user_login',
                scope: 'user',
                description: 'Start visitor sign-in. Off unless the owner opts in; always asks the visitor to confirm.',
                mutating: true,
                consent: 'login',
                inputSchema: {
                    type: 'object',
                    properties: { email: { type: 'string' }, password: { type: 'string' } }
                },
                confirmMessage: function () { return 'Allow the AI assistant to sign you in?'; },
                gate: function () {
                    return !!(U && typeof U.login === 'function') || !!document.querySelector('form[data-birkly-login], [data-birkly-login]');
                },
                execute: function (args) {
                    if (U && typeof U.login === 'function') return U.login(args || {});
                    var form = document.querySelector('form[data-birkly-login]');
                    if (form) {
                        if (args && args.email) { var e = form.querySelector('[name="email"]'); if (e) e.value = args.email; }
                        if (args && args.password) { var p = form.querySelector('[name="password"]'); if (p) p.value = args.password; }
                        if (typeof form.requestSubmit === 'function') form.requestSubmit(); else form.submit();
                        return { ok: true, note: 'Login form submitted.' };
                    }
                    throw new Error('No login surface on this page.');
                }
            });
        }
    }

    function registerPluginPacks() {
        try { registerCommercePack(); } catch (e) { log('commerce pack error', e); }
        try { registerEventsPack(); } catch (e) { log('events pack error', e); }
        try { registerUserPack(); } catch (e) { log('user pack error', e); }
    }

    // ---- Lifecycle ----------------------------------------------------------
    function abortAll() {
        Object.keys(WebMCP.controllers).forEach(abortScope);
        WebMCP.registered = {};
    }

    function registerAll() {
        registerCoreTools();
        registerPluginPacks();
        registerDeclarativeForms();
        registerAttributeTools();
        flushAuthorTools();
    }

    function start() {
        if (WebMCP.started) return;
        WebMCP.started = true;

        // Prefer the SSR-inlined config: stamp declarative attrs + register
        // synchronously so agent browsers / Lighthouse see tools without waiting
        // on a network round-trip. Stamping runs even when modelContext is
        // missing so markup is ready if the browser adds the API later.
        if (typeof window !== 'undefined' && window.BIRKLY_WEBMCP_CONFIG) {
            applyConfig(window.BIRKLY_WEBMCP_CONFIG);
            return;
        }

        // Fallback: no inlined config — fetch the public endpoint.
        fetch(WebMCP.cmsUrl + '/api/public/webmcp.php', { credentials: 'include' })
            .then(function (r) { return r.json(); })
            .then(function (res) { return (res && res.data) || { enabled: false }; })
            .catch(function () { return { enabled: false }; })
            .then(applyConfig);
    }

    function applyConfig(cfg) {
        WebMCP.config = cfg || { enabled: false };
        if (!WebMCP.config.enabled) {
            log('disabled — registering nothing');
            return;
        }

        wireFormObservers();
        stampAllowlistedForms();

        WebMCP.mc = getModelContext();
        if (!WebMCP.mc) {
            // Unsupported browser — keep stamped declarative attrs, skip
            // imperative registerTool. Silent: no console spam.
            log('modelContext unavailable — declarative attrs only');
            return;
        }

        // Tear tools down when the page is going away.
        window.addEventListener('pagehide', abortAll);
        window.addEventListener('beforeunload', abortAll);

        registerAll();
    }

    // ---- Public author surface (window.Birkly.webmcp) -----------------------
    var publicApi = {
        version: WebMCP.version,
        /** Imperatively register a project tool (safe wrapper over registerTool). */
        register: function (spec) {
            WebMCP.authorTools.push(spec);
            if (WebMCP.started && WebMCP.config && WebMCP.config.enabled) {
                register(Object.assign({ scope: 'author' }, spec));
            }
            return true;
        },
        /** Provide a custom visitor-confirmation dialog (return boolean/Promise). */
        setConfirm: function (fn) { WebMCP.confirmFn = fn; },
        isEnabled: function () { return !!(WebMCP.config && WebMCP.config.enabled); },
        isSupported: function () { return !!getModelContext(); },
        listRegistered: function () { return Object.keys(WebMCP.registered); },
        _internal: WebMCP
    };

    try {
        if (typeof window !== 'undefined') {
            window.Birkly = window.Birkly || {};
            window.Birkly.webmcp = publicApi;
            window.BirklyWebMCP = publicApi;
        }
    } catch (e) {}

    // ---- Boot ---------------------------------------------------------------
    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', start);
        } else {
            start();
        }
    }

    // Export for Node-based unit tests (no DOM side effects when required).
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { getModelContext: getModelContext, _WebMCP: WebMCP };
    }
})();
