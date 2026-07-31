/**
 * Birkly Template Engine (shared)
 * -------------------------------
 * Canonical string-based renderer for the Birkly plain-English template
 * language (see docs/TEMPLATE_LANGUAGE_SPEC.md). This is the single source of
 * truth used by:
 *   - the public Light DOM client (`birkly-client.js`)
 *   - `birkly-client-modules/birkly-render.js` (thin re-export for tests)
 *   - server-side rendering (SSR) added in later P51 milestones
 *
 * UMD/CommonJS + browser-global compatible: works under Node `require()` and
 * as `globalThis.BirklyTemplateEngine` in a browser. No third-party deps.
 *
 * Public API (also exposed on the exported `engine` object):
 *   - render(html, data, options)      main renderer; options.escapeHtml (bool)
 *   - collectBundleRequests(html)      static analysis of a template's data needs
 *   - hasTemplateSyntax(html)          does the string contain template syntax?
 *   - escapeHtml(str)                  HTML-escape a value
 *   - region helpers (discoverPageRegions, buildRegionTemplate, …) for parity
 */
(function (root) {
    'use strict';

    const CONTROL_WORDS = new Set([
        'if', 'else', 'elseif', 'endif', 'for', 'endfor', 'foreach', 'endforeach',
        'from', 'get', 'endget', 'end', 'set', 'count', 'each', 'in', 'with',
        'ordered', 'by', 'limit', 'of', 'has', 'exist', 'not', 'does',
        'entry', 'endentry', 'public_form'
    ]);

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function getNestedValue(obj, path) {
        if (!obj || !path) return undefined;
        const parts = String(path).split('.');
        let cur = obj;
        for (const p of parts) {
            if (cur == null) return undefined;
            cur = cur[p];
        }
        return cur;
    }

    function normalizeOperator(op) {
        op = String(op || '').trim();
        if (op === '=<') return '<=';
        if (op === '=>') return '>=';
        if (op === '==') return '=';
        return op;
    }

    function compareNumeric(left, op, right) {
        switch (op) {
            case '<': return left < right;
            case '>': return left > right;
            case '<=': return left <= right;
            case '>=': return left >= right;
            case '=':
            case '==': return Math.abs(left - right) < 0.00001;
            case '!=': return Math.abs(left - right) >= 0.00001;
            default: return false;
        }
    }

    function entryHasField(entry, field) {
        if (!entry || typeof entry !== 'object' || !Object.prototype.hasOwnProperty.call(entry, field)) {
            return false;
        }
        const val = entry[field];
        return val !== null && val !== undefined && val !== '' && !(Array.isArray(val) && val.length === 0);
    }

    function formatDate(value, format) {
        if (!value) return '';
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return String(value);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const monthsFull = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        const pad = (n) => String(n).padStart(2, '0');
        // PHP-style format codes. Scanned char-by-char so that substituted text
        // (e.g. "January") is never re-scanned for later codes (the trailing
        // "y" in a month name must not become the 2-digit year).
        const codes = {
            Y: String(d.getFullYear()),
            y: String(d.getFullYear()).slice(-2),
            m: pad(d.getMonth() + 1),
            n: String(d.getMonth() + 1),
            F: monthsFull[d.getMonth()],
            M: months[d.getMonth()],
            d: pad(d.getDate()),
            j: String(d.getDate()),
            H: pad(d.getHours()),
            G: String(d.getHours()),
            i: pad(d.getMinutes()),
            s: pad(d.getSeconds())
        };
        const fmt = String(format || 'Y-m-d');
        let out = '';
        for (let i = 0; i < fmt.length; i++) {
            const ch = fmt[i];
            out += Object.prototype.hasOwnProperty.call(codes, ch) ? codes[ch] : ch;
        }
        return out;
    }

    function resolveEntry(collection, slug, data) {
        const key = '__entry__:' + collection + ':' + slug;
        if (data[key] && typeof data[key] === 'object') {
            return data[key];
        }
        if (collection === 'site_elements') {
            const se = data.site_elements || {};
            return se[slug] || null;
        }
        const entries = data[collection];
        if (!Array.isArray(entries)) return null;
        return entries.find((e) => e && (String(e.slug || '') === slug || String(e.id || '') === slug)) || null;
    }

    function resolveParentCollectionEntry(collection, identifier, data) {
        if (collection === 'site_elements') {
            const se = data.site_elements || {};
            return se[identifier] || null;
        }
        if (identifier === 'current') {
            if (data.entry) return data.entry;
            const prefix = '__entry__:' + collection + ':';
            for (const k of Object.keys(data)) {
                if (k.startsWith(prefix) && data[k]) return data[k];
            }
            return null;
        }
        if (identifier === 'newest' || identifier === 'last-updated') {
            const entries = data[collection];
            if (!Array.isArray(entries) || !entries.length) return null;
            const field = identifier === 'last-updated' ? 'updated_at' : 'created_at';
            return entries.slice().sort((a, b) => {
                const da = Date.parse(a && a[field] ? a[field] : 0) || 0;
                const db = Date.parse(b && b[field] ? b[field] : 0) || 0;
                return db - da;
            })[0];
        }
        return resolveEntry(collection, identifier, data);
    }

    function resolveCollectionEntryRef(ref, data) {
        const trimmed = String(ref || '').trim();
        let m = trimmed.match(/^'([^']+)'\.'([^']+)'$/);
        if (m) return resolveParentCollectionEntry(m[1], m[2], data);
        m = trimmed.match(/^'([^']+)'\.'(current|newest|last-updated)'$/);
        if (m) return resolveParentCollectionEntry(m[1], m[2], data);
        return null;
    }

    function resolveSubCollectionItems(subCollection, parentCollection, parentIdentifier, data) {
        const parent = resolveParentCollectionEntry(parentCollection, parentIdentifier, data);
        if (!parent) return [];
        const ids = [parent.id, parent.slug, parentIdentifier].filter((v) => v !== undefined && v !== null && v !== '');
        for (const id of ids) {
            const key = '__sub__:' + parentCollection + ':' + id + ':' + subCollection;
            if (Array.isArray(data[key])) return data[key];
        }
        if (Array.isArray(data[subCollection]) && data.entry) {
            const entry = data.entry;
            if (ids.some((id) => String(entry.id) === String(id) || String(entry.slug) === String(id))) {
                return data[subCollection];
            }
        }
        if (Array.isArray(parent[subCollection])) return parent[subCollection];
        return [];
    }

    function resolveLoopItems(inClause, data) {
        const clause = String(inClause || '').trim();
        let m = clause.match(/^'([^']+)'\s+of\s+'([^']+)'\.'([^']+)'\s*$/i);
        if (m) return resolveSubCollectionItems(m[1], m[2], m[3], data);
        m = clause.match(/^'([^']+)'\s+of\s+'([^']+)'\.\s*(current|newest|last-updated)\s*$/i);
        if (m) return resolveSubCollectionItems(m[1], m[2], m[3], data);

        m = clause.match(/^'([^']+)'/);
        if (m) {
            const collection = m[1];
            if (Array.isArray(data[collection])) return data[collection].slice();
            const entry = data.entry;
            if (entry && Array.isArray(entry[collection])) return entry[collection].slice();
            return [];
        }

        const field = (clause.split(/\s+/)[0] || '').replace(/[^a-zA-Z0-9_]/g, '');
        if (!field) return [];
        if (Array.isArray(data[field])) return data[field].slice();
        const entry = data.entry;
        if (!entry || typeof entry !== 'object') return [];
        const value = entry[field] ?? entry[field + 's'];
        return Array.isArray(value) ? value.slice() : [];
    }

    function parseLoopModifiers(inClause) {
        const result = { inClause: inClause, filter: null, order: null, limit: null };
        let rest = String(inClause || '').trim();

        const limitM = rest.match(/\blimit\s+(\d+)\b/i);
        if (limitM) {
            result.limit = parseInt(limitM[1], 10);
            rest = rest.replace(/\blimit\s+\d+\b/i, '').trim();
        }
        const orderM = rest.match(/\bordered\s+by\s+(\S+)/i);
        if (orderM) {
            result.order = orderM[1].toLowerCase();
            rest = rest.replace(/\bordered\s+by\s+\S+/i, '').trim();
        }
        const withM = rest.match(/\bwith\s+(.+)$/is);
        if (withM) {
            result.filter = withM[1].trim();
            rest = rest.replace(/\bwith\s+.+$/is, '').trim();
        }
        result.inClause = rest.trim();
        return result;
    }

    function applyLoopFilters(items, filter) {
        if (!filter) return items;
        let m = filter.match(/^([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*([a-zA-Z_][a-zA-Z0-9_]*)$/);
        if (m) {
            const field = m[1];
            const value = m[2];
            return items.filter((it) => it && String(it[field]) === value);
        }
        m = filter.match(/^['"]([^'"]+)['"]\s*=\s*['"]([^'"]*)['"]$/);
        if (m) {
            const field = m[1];
            const value = m[2];
            return items.filter((it) => it && String(it[field]) === value);
        }
        m = filter.match(/^['"]([^'"]+)['"]\s*(=|<=|=<|>=|=>|<|>)\s*([0-9]+(?:\.[0-9]+)?)$/);
        if (m) {
            const field = m[1];
            const op = normalizeOperator(m[2]);
            const num = parseFloat(m[3]);
            return items.filter((it) => {
                if (!it) return false;
                const v = parseFloat(it[field]);
                if (Number.isNaN(v)) return false;
                return compareNumeric(v, op, num);
            });
        }
        return items;
    }

    function applyLoopOrder(items, order) {
        if (!order || items.length < 2) return items;
        const list = items.slice();
        if (order === 'newest' || order === 'oldest' || order === 'last-updated') {
            const field = order === 'last-updated' ? 'updated_at' : 'created_at';
            list.sort((a, b) => {
                const da = Date.parse(a && a[field] ? a[field] : 0) || 0;
                const db = Date.parse(b && b[field] ? b[field] : 0) || 0;
                return order === 'oldest' ? da - db : db - da;
            });
            return list;
        }
        if (order === 'order') {
            list.sort((a, b) => {
                const oa = parseFloat(a && a.order != null ? a.order : (a && a.sort_order != null ? a.sort_order : 0)) || 0;
                const ob = parseFloat(b && b.order != null ? b.order : (b && b.sort_order != null ? b.sort_order : 0)) || 0;
                return oa - ob;
            });
        }
        return list;
    }

    function applyLoopLimit(items, limit) {
        if (!limit || limit < 1) return items;
        return items.slice(0, limit);
    }

    function findClosingTag(html, from, tags) {
        let bestPos = null;
        let bestLen = 0;
        for (const tag of tags) {
            const pos = html.toLowerCase().indexOf(tag.toLowerCase(), from);
            if (pos !== -1 && (bestPos === null || pos < bestPos)) {
                bestPos = pos;
                bestLen = tag.length;
            }
        }
        return bestPos === null ? [null, 0] : [bestPos, bestLen];
    }

    function findMatchingLoopEnd(html, from) {
        let pos = from;
        let depth = 1;
        const openRe = /\{for\s+(?:each\s+)?/gi;
        const closeTags = ['{endfor}', '{endforeach}', '{end}'];

        while (pos < html.length && depth > 0) {
            openRe.lastIndex = pos;
            const openM = openRe.exec(html);
            const nextOpen = openM ? openM.index : Infinity;

            let nextClose = Infinity;
            let closeLen = 0;
            for (const tag of closeTags) {
                const p = html.toLowerCase().indexOf(tag.toLowerCase(), pos);
                if (p !== -1 && p < nextClose) {
                    nextClose = p;
                    closeLen = tag.length;
                }
            }

            if (nextClose === Infinity) return null;
            if (nextOpen < nextClose) {
                depth++;
                pos = nextOpen + 1;
                continue;
            }
            depth--;
            if (depth === 0) return [nextClose, closeLen];
            pos = nextClose + closeLen;
        }
        return null;
    }

    function findIfBlockEnd(html, from) {
        let pos = from;
        let depth = 1;
        let elsePos = null;
        let elseLen = 0;
        const re = /\{(if|elseif|else|endif)\}/gi;

        while (pos < html.length && depth > 0) {
            re.lastIndex = pos;
            const m = re.exec(html);
            if (!m) break;
            const tag = m[1].toLowerCase();
            const tagPos = m.index;
            const tagLen = m[0].length;

            if (tag === 'if' || tag === 'elseif') {
                if (depth === 1 && tag === 'elseif' && elsePos === null) {
                    elsePos = tagPos;
                    elseLen = tagLen;
                }
                if (tag === 'if') depth++;
                pos = tagPos + tagLen;
                continue;
            }
            if (tag === 'else' && depth === 1 && elsePos === null) {
                elsePos = tagPos;
                elseLen = tagLen;
                pos = tagPos + tagLen;
                continue;
            }
            if (tag === 'endif') {
                depth--;
                if (depth === 0) return [tagPos, tagLen, elsePos, elseLen];
            }
            pos = tagPos + tagLen;
        }
        return [null, 0, null, 0];
    }

    /**
     * Resolve a bare-headed context/system path such as `current.language`,
     * `query.'lang'`, `query.slug`, or `entry.title`. The head must be a
     * top-level key on `data` that holds an object; the remaining segments
     * (quoted, numeric, or bare) are walked in order.
     * Returns a string, or `undefined` when the expression is not such a path
     * (so the caller can fall through to other resolvers).
     */
    function resolveContextPath(expr, data) {
        const trimmed = String(expr || '').trim();
        const m = trimmed.match(/^([a-zA-Z_][a-zA-Z0-9_]*)((?:\.(?:'[^']+'|"[^"]+"|\d+|[a-zA-Z_][a-zA-Z0-9_]*))+)$/);
        if (!m) return undefined;
        const head = m[1];
        if (!data || typeof data !== 'object' || !Object.prototype.hasOwnProperty.call(data, head)) {
            return undefined;
        }
        let cur = data[head];
        if (cur == null || typeof cur !== 'object') return undefined;
        const segRe = /\.(?:'([^']+)'|"([^"]+)"|(\d+)|([a-zA-Z_][a-zA-Z0-9_]*))/g;
        let s;
        while ((s = segRe.exec(m[2])) !== null) {
            const key = s[1] != null ? s[1] : (s[2] != null ? s[2] : (s[3] != null ? s[3] : s[4]));
            if (cur == null || typeof cur !== 'object') return undefined;
            cur = cur[key];
        }
        if (cur == null || typeof cur === 'object') return undefined;
        return String(cur);
    }

    function evaluateExistence(ref, data) {
        const value = resolveCollectionEntryRef(ref, data);
        if (value === null || value === undefined) return false;
        if (Array.isArray(value)) return value.length > 0;
        return value !== '' && value !== false;
    }

    /** Resolve the left-hand side of a comparison into a comparable string. */
    function resolveComparisonValue(expr, data) {
        const trimmed = String(expr || '').trim();
        const ctx = resolveContextPath(trimmed, data);
        if (ctx !== undefined) return ctx;
        const bare = trimmed.match(/^[a-zA-Z_][a-zA-Z0-9_]*$/);
        if (bare) return resolveVariable(trimmed, data);
        const quoted = trimmed.match(/^'([^']+)'$/);
        if (quoted) {
            const entry = data.entry || data;
            if (entry && typeof entry === 'object' && Object.prototype.hasOwnProperty.call(entry, quoted[1])) {
                const v = entry[quoted[1]];
                return v == null || typeof v === 'object' ? '' : String(v);
            }
        }
        return undefined;
    }

    function evaluateCondition(condition, data) {
        const cond = String(condition || '').trim();
        if (!cond) return false;

        let m = cond.match(/^(.+?)\s+does\s+not\s+exist\s*$/i);
        if (m) return !evaluateExistence(m[1].trim(), data);

        m = cond.match(/^(.+?)\s+exist\s*$/i);
        if (m) return evaluateExistence(m[1].trim(), data);

        m = cond.match(/^entry\s+has\s+['"]([^'"]+)['"]\s*(>=|<=|=<|=>|>|<|=|==|!=)\s*([0-9]+(?:\.[0-9]+)?)\s*$/i);
        if (m) {
            const entry = data.entry || data;
            if (!entry || typeof entry !== 'object') return false;
            const val = parseFloat(entry[m[1]]);
            if (Number.isNaN(val)) return false;
            return compareNumeric(val, normalizeOperator(m[2]), parseFloat(m[3]));
        }

        m = cond.match(/^entry\s+has\s+['"]([^'"]+)['"]\s*$/i);
        if (m) {
            const entry = data.entry || data;
            return entry && typeof entry === 'object' && entryHasField(entry, m[1]);
        }

        m = cond.match(/^entry\s+has\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*$/i);
        if (m) {
            const entry = data.entry || data;
            return entry && typeof entry === 'object' && entryHasField(entry, m[1]);
        }

        m = cond.match(/^has\s+['"]([^'"]+)['"]\s*$/i);
        if (m) {
            const entry = data.entry || data;
            return entry && typeof entry === 'object' && entryHasField(entry, m[1]);
        }

        // entry-field numeric comparison without `has`: {if entry 'price' <= 15}
        m = cond.match(/^entry\s+['"]([^'"]+)['"]\s*(>=|<=|=<|=>|>|<|=|==|!=)\s*([0-9]+(?:\.[0-9]+)?)\s*$/i);
        if (m) {
            const entry = data.entry || data;
            if (!entry || typeof entry !== 'object') return false;
            const val = parseFloat(entry[m[1]]);
            if (Number.isNaN(val)) return false;
            return compareNumeric(val, normalizeOperator(m[2]), parseFloat(m[3]));
        }

        // Generic value comparison against a literal, e.g.
        // {if current.language = 'de'} or {if query.'mode' = 'grid'}
        m = cond.match(/^(.+?)\s*(=|==|!=|<=|>=|=<|=>|<|>)\s*('([^']*)'|"([^"]*)"|[0-9]+(?:\.[0-9]+)?|[a-zA-Z0-9_-]+)\s*$/);
        if (m) {
            const op = normalizeOperator(m[2]);
            const rawRight = m[3];
            const left = resolveComparisonValue(m[1].trim(), data);
            if (left !== undefined) {
                let right;
                if (m[4] != null) right = m[4];
                else if (m[5] != null) right = m[5];
                else right = rawRight;
                const leftNum = parseFloat(left);
                const rightNum = parseFloat(right);
                if (!Number.isNaN(leftNum) && !Number.isNaN(rightNum) && /^[0-9.]+$/.test(String(right))) {
                    return compareNumeric(leftNum, op, rightNum);
                }
                const leftStr = String(left);
                const rightStr = String(right);
                switch (op) {
                    case '=': return leftStr === rightStr;
                    case '!=': return leftStr !== rightStr;
                    case '<': return leftStr < rightStr;
                    case '>': return leftStr > rightStr;
                    case '<=': return leftStr <= rightStr;
                    case '>=': return leftStr >= rightStr;
                    default: return false;
                }
            }
        }

        return false;
    }

    function escapeOutput(pathOrName, value, options) {
        const str = value == null ? '' : String(value);
        if (!str) return '';
        if (options && options.escapeHtml === false) {
            return str;
        }
        // Rich-content fields are treated as trusted HTML by convention.
        if (String(pathOrName).includes('content') || String(pathOrName).includes('html')) {
            return str;
        }
        return escapeHtml(str);
    }

    function resolveVariable(name, data) {
        if (Object.prototype.hasOwnProperty.call(data, name) && !Array.isArray(data[name]) && typeof data[name] !== 'object') {
            return String(data[name] ?? '');
        }
        const entry = data.entry;
        if (entry && typeof entry === 'object' && Object.prototype.hasOwnProperty.call(entry, name) && !Array.isArray(entry[name]) && typeof entry[name] !== 'object') {
            return String(entry[name] ?? '');
        }
        return '';
    }

    function resolveFieldPathFromParts(parts, data) {
        let current = data.entry || data;
        for (const part of parts) {
            const key = part[1] !== '' && part[1] != null ? part[1] : part[2];
            if (!current || typeof current !== 'object' || !Object.prototype.hasOwnProperty.call(current, key)) {
                return '';
            }
            current = current[key];
        }
        if (current == null || typeof current === 'object') return '';
        return String(current);
    }

    function encodeEntryBlockContent(html) {
        return String(html || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    /**
     * `{entry for 'collection'}…{endentry}` — public form scaffold.
     * The engine emits a placeholder `<div>` carrying the inner markup so the
     * client (or SSR post-step) can hydrate a real form. This keeps the string
     * engine framework-free.
     */
    function processEntryForBlocks(html) {
        const pattern = /\{entry\s+for\s+['"]([^'"]+)['"]\}/i;
        let guard = 0;
        while (guard++ < 100) {
            const m = html.match(pattern);
            if (!m) break;
            const start = m.index;
            const openLen = m[0].length;
            const collectionSlug = m[1];
            const contentStart = start + openLen;
            const [closePos, closeLen] = findClosingTag(html, contentStart, ['{endentry}']);
            if (closePos === null) {
                html = html.substring(0, start) + html.substring(contentStart);
                continue;
            }
            const inner = html.substring(contentStart, closePos);
            const placeholder = '<div class="birkly-entry-block" data-birkly-collection="' + escapeHtml(collectionSlug) + '" data-birkly-content="' + encodeEntryBlockContent(inner) + '"></div>';
            html = html.substring(0, start) + placeholder + html.substring(closePos + closeLen);
        }
        return html;
    }

    /** `{public_form 'collection'}` — inline public form placeholder. */
    function processPublicFormBlocks(html) {
        return html.replace(/\{public_form\s+['"]([^'"]+)['"]\}/gi, (_, slug) => {
            return '<div class="birkly-form-placeholder" data-birkly-collection="' + escapeHtml(String(slug || '').trim()) + '"></div>';
        });
    }

    function stringifyInlineItem(item) {
        if (item == null) return '';
        if (typeof item !== 'object') return String(item);
        for (const k of ['value', 'name', 'label', 'title', 'id']) {
            if (item[k] != null && typeof item[k] !== 'object') return String(item[k]);
        }
        return '';
    }

    function resolveArrayInlineItem(arr, accessor) {
        if (!Array.isArray(arr)) return undefined;
        let m = accessor.match(/^item\.(\d+)$/i);
        if (m) return arr[parseInt(m[1], 10) - 1];
        if (/^item\.newest$/i.test(accessor)) return arr[arr.length - 1];
        if (/^item\.oldest$/i.test(accessor)) return arr[0];
        m = accessor.match(/^item\.'([^']+)'$/i);
        if (m) {
            const key = m[1];
            return arr.find((it) => {
                if (it == null) return false;
                if (typeof it === 'object') {
                    return ['value', 'name', 'id', 'label'].some((k) => String(it[k]) === key);
                }
                return String(it) === key;
            });
        }
        return undefined;
    }

    /**
     * Inline single-value `{from … get …}` (no `{endget}`), e.g.
     *   {from 'site_elements'.'headline' get 'content'.0.'value'}
     *   {from 'tags' get item.1}   (array field on the current entry)
     * Block forms (`{from … get entry …}{endget}`) are handled earlier and are
     * already consumed by the time this runs.
     */
    function processInlineFromGet(html, data, options) {
        return html.replace(/\{from\s+([^{}]+?)\s+get\s+([^{}]+?)\}/gi, (_, source, target) => {
            const src = String(source || '').trim();
            const tgt = String(target || '').trim();
            if (/^entry\b/i.test(tgt)) return '';

            // Case A: {from 'collection'.'identifier' get 'field'.0.'value'}
            let m = src.match(/^'([^']+)'\.'([^']+)'$/) || src.match(/^'([^']+)'\.(current|newest|last-updated)$/);
            if (m) {
                const entry = resolveParentCollectionEntry(m[1], m[2], data);
                if (!entry || typeof entry !== 'object') return '';
                const parts = [...tgt.matchAll(/'([^']+)'|(\d+)/g)];
                if (!parts.length) return '';
                const value = resolveFieldPathFromParts(parts, { entry: entry });
                return escapeOutput(tgt, value, options);
            }

            // Case B: {from 'arrayField' get item.N | item.newest | item.'value'}
            m = src.match(/^'([^']+)'$/);
            if (m) {
                const field = m[1];
                const entry = data.entry;
                let arr = null;
                if (entry && typeof entry === 'object' && Array.isArray(entry[field])) arr = entry[field];
                else if (Array.isArray(data[field])) arr = data[field];
                if (!arr) return '';
                const item = resolveArrayInlineItem(arr, tgt);
                return escapeOutput(tgt, stringifyInlineItem(item), options);
            }

            return '';
        });
    }

    function processFromGetBlocks(html, data, render) {
        const pattern = /\{from\s+['"]([^'"]+)['"]\s+get\s+entry\s+['"]([^'"]+)['"]\}/i;
        let guard = 0;
        while (guard++ < 500) {
            const m = html.match(pattern);
            if (!m) break;
            const start = m.index;
            const openLen = m[0].length;
            const collection = m[1];
            const slug = m[2];
            const contentStart = start + openLen;
            const [closePos, closeLen] = findClosingTag(html, contentStart, ['{endget}', '{end}']);
            if (closePos === null) {
                html = html.substring(0, start) + html.substring(contentStart);
                continue;
            }
            const block = html.substring(contentStart, closePos);
            const entry = resolveEntry(collection, slug, data);
            let replacement = '';
            if (entry && typeof entry === 'object') {
                const blockData = Object.assign({}, data, { entry: entry }, entry);
                replacement = render(block, blockData);
            }
            html = html.substring(0, start) + replacement + html.substring(closePos + closeLen);
        }
        return html;
    }

    function processForLoops(html, data, render) {
        const pattern = /\{for\s+(?:each\s+)?([a-zA-Z0-9_]+)\s+in\s+([^}]+)\}/i;
        let guard = 0;
        while (guard++ < 500) {
            const m = html.match(pattern);
            if (!m) break;
            const start = m.index;
            const openLen = m[0].length;
            const itemVar = m[1];
            const inClause = m[2].trim();
            const contentStart = start + openLen;
            const close = findMatchingLoopEnd(html, contentStart);
            if (!close) break;
            const [closePos, closeLen] = close;
            const loopBody = html.substring(contentStart, closePos);
            const header = parseLoopModifiers(inClause);
            let items = resolveLoopItems(header.inClause, data);
            items = applyLoopFilters(items, header.filter);
            items = applyLoopOrder(items, header.order);
            items = applyLoopLimit(items, header.limit);

            let rendered = '';
            for (const item of items) {
                const loopData = Object.assign({}, data, { [itemVar]: item });
                if (item && typeof item === 'object') {
                    if (itemVar === 'entry' || !Object.prototype.hasOwnProperty.call(data, 'entry')) {
                        loopData.entry = item;
                        Object.assign(loopData, item);
                    }
                }
                rendered += render(loopBody, loopData);
            }
            html = html.substring(0, start) + rendered + html.substring(closePos + closeLen);
        }
        return html;
    }

    function processIfBlocks(html, data, render) {
        const pattern = /\{(if|elseif)\s+([^}]+)\}/i;
        let guard = 0;
        while (guard++ < 500) {
            const m = html.match(pattern);
            if (!m) break;
            const start = m.index;
            const openLen = m[0].length;
            const condition = m[2].trim();
            const contentStart = start + openLen;
            const [closePos, closeLen, elsePos, elseLen] = findIfBlockEnd(html, contentStart);
            if (closePos === null) break;

            const trueBlock = elsePos !== null
                ? html.substring(contentStart, elsePos)
                : html.substring(contentStart, closePos);
            const falseBlock = elsePos !== null
                ? html.substring(elsePos + elseLen, closePos)
                : '';

            const replacement = evaluateCondition(condition, data)
                ? render(trueBlock, data)
                : render(falseBlock, data);
            html = html.substring(0, start) + replacement + html.substring(closePos + closeLen);
        }
        return html;
    }

    function processCountVariables(html, data) {
        return html.replace(/\{\s*count\s+of\s+([^}]+)\}/gi, (_, clause) => {
            const trimmed = String(clause || '').trim();
            let items;
            let m = trimmed.match(/^'([^']+)'\s+of\s+'([^']+)'\.'([^']+)'\s*$/i);
            if (m) {
                items = resolveSubCollectionItems(m[1], m[2], m[3], data);
            } else {
                m = trimmed.match(/^'([^']+)'\s*$/);
                items = resolveLoopItems(m ? "'" + m[1] + "'" : trimmed, data);
            }
            return String(Array.isArray(items) ? items.length : 0);
        });
    }

    function processVariables(html, data, options) {
        return html.replace(/\{([^{}]*)\}/g, (full, inner) => {
            const trimmed = String(inner || '').trim();
            if (!trimmed) return '';
            if (/^(if|else|elseif|endif|for|endfor|from|get|endget|endentry|end|set|count|entry)\b/i.test(trimmed)) {
                return '';
            }
            if (/^entry\s+for\s+['"][^'"]+['"]$/i.test(trimmed)) {
                return '';
            }
            if (/^public_form\s+['"][^'"]+['"]$/i.test(trimmed)) {
                return '';
            }
            if (/^count\s+of\b/i.test(trimmed)) return full;

            // System / context paths: current.language, query.'lang', query.slug …
            const ctx = resolveContextPath(trimmed, data);
            if (ctx !== undefined) {
                return escapeOutput(trimmed, ctx, options);
            }

            if (trimmed.includes("'")) {
                const parts = [...trimmed.matchAll(/'([^']+)'|(\d+)/g)];
                if (parts.length) {
                    const value = resolveFieldPathFromParts(parts, data);
                    return escapeOutput(trimmed, value, options);
                }
            }

            const vm = trimmed.match(/^([a-zA-Z_][a-zA-Z0-9_]*)(?:\s+format\s+["']([^"']+)["'])?$/);
            if (vm) {
                const name = vm[1];
                const format = vm[2] || null;
                let value = resolveVariable(name, data);
                if (format && value) {
                    return formatDate(value, format);
                }
                return escapeOutput(name, value, options);
            }

            return '';
        });
    }

    /**
     * Render a template string against data.
     * @param {string} html   template source
     * @param {object} data   render context (collections, entry, site_elements, query, current, …)
     * @param {{ escapeHtml?: boolean }} [options]  escapeHtml defaults to true (safe)
     * @returns {string}
     */
    function render(html, data, options) {
        if (!html || typeof html !== 'string') return html || '';
        if (!data || typeof data !== 'object') return html;

        const opts = (options && typeof options === 'object') ? options : {};
        const ctxOptions = { escapeHtml: opts.escapeHtml === false ? false : true };
        const renderFn = (tpl, ctx) => render(tpl, ctx, ctxOptions);

        let out = processEntryForBlocks(html);
        out = processPublicFormBlocks(out);
        out = processFromGetBlocks(out, data, renderFn);
        out = processInlineFromGet(out, data, ctxOptions);
        out = processForLoops(out, data, renderFn);
        out = processIfBlocks(out, data, renderFn);
        out = processCountVariables(out, data);
        out = processVariables(out, data, ctxOptions);
        return out;
    }

    function hasTemplateSyntax(html) {
        return /\{[a-zA-Z0-9_'"]/.test(String(html || ''));
    }

    function collectBundleRequests(html) {
        const requests = [];
        const seen = new Set();
        const add = (req) => {
            const key = JSON.stringify(req);
            if (seen.has(key)) return;
            seen.add(key);
            requests.push(req);
        };

        const text = String(html || '');
        const forRe = /\{for\s+(?:each\s+)?[a-zA-Z0-9_]+\s+in\s+'([a-z0-9_-]+)'/gi;
        let m;
        while ((m = forRe.exec(text)) !== null) {
            add({ type: 'list_entries', into: m[1], collection: m[1] });
        }

        const fromRe = /\{from\s+'([a-z0-9_-]+)'\s+get\s+entry\s+'([^']+)'\}/gi;
        while ((m = fromRe.exec(text)) !== null) {
            const col = m[1];
            const slug = m[2];
            if (col !== 'site_elements') {
                add({ type: 'list_entries', into: col, collection: col });
            }
            add({
                type: 'get_entry',
                into: '__entry__:' + col + ':' + slug,
                collection: col,
                slug: slug
            });
        }

        if (text.includes('site_elements')) {
            add({ type: 'list_entries', into: 'site_elements', collection: 'site_elements' });
        }

        const subLoopRe = /\{for\s+(?:each\s+)?[a-zA-Z0-9_]+\s+in\s+'([^']+)'\s+of\s+'([^']+)'\.\s*(current|[^'\s{}]+)\s*/gi;
        while ((m = subLoopRe.exec(text)) !== null) {
            add({
                type: 'list_entries',
                into: m[2],
                collection: m[2]
            });
        }

        return requests;
    }

    function isExplicitRegionElement(el) {
        if (!el) return false;
        const tag = String(el.tagName || '').toUpperCase();
        if (tag === 'BIRKLY-REGION') return true;
        if (typeof el.hasAttribute === 'function' && el.hasAttribute('data-birkly-region')) {
            return true;
        }
        if (typeof el.getAttribute === 'function' && el.getAttribute('data-birkly-region') !== null) {
            return true;
        }
        return false;
    }

    function isRegionProcessed(el) {
        if (!el || typeof el.getAttribute !== 'function') return false;
        return el.getAttribute('data-birkly-processed') === '1';
    }

    /**
     * Discover outermost Light DOM template regions.
     * @param {Document} doc
     * @param {Element|null} [rootArg] optional subtree root (if the root itself is a region, include it)
     * @param {{ force?: boolean }} [options]
     */
    function discoverPageRegions(doc, rootArg, options) {
        const regions = [];
        const opts = options && typeof options === 'object' ? options : {};
        const force = !!opts.force;
        const docRoot = (doc && (doc.body || doc.documentElement)) || null;
        const searchRoot = rootArg || docRoot;
        if (!searchRoot) {
            return regions;
        }

        const candidates = [];
        const consider = (el) => {
            if (!el || !el.tagName) return;
            const tag = String(el.tagName || '').toUpperCase();
            if (tag === 'SCRIPT' || tag === 'STYLE') return;
            if (!force && isRegionProcessed(el)) return;
            const template = el.innerHTML || '';
            if (isExplicitRegionElement(el) || hasTemplateSyntax(template)) {
                candidates.push(el);
            }
        };

        // Scoped root: if the root element itself is a region, process it.
        if (rootArg && rootArg.tagName) {
            consider(rootArg);
        }

        if (typeof searchRoot.querySelectorAll === 'function') {
            searchRoot.querySelectorAll('*').forEach(consider);
        }

        const explicit = candidates.filter(isExplicitRegionElement);
        const remaining = candidates.filter((el) => {
            if (isExplicitRegionElement(el)) return true;
            // Inside an explicit region → not a separate region.
            if (explicit.some((ex) => ex !== el && typeof ex.contains === 'function' && ex.contains(el))) {
                return false;
            }
            // Ancestor of an explicit region → demote; the attributed element is the region root.
            if (explicit.some((ex) => ex !== el && typeof el.contains === 'function' && el.contains(ex))) {
                return false;
            }
            return true;
        });

        const outermost = remaining.filter((el) =>
            !remaining.some((other) => other !== el && typeof other.contains === 'function' && other.contains(el))
        );

        outermost.forEach((el) => {
            regions.push({ element: el, template: el.innerHTML || '' });
        });

        // <title> only on full-document discovery (not scoped subtree passes)
        if (!rootArg && doc && typeof doc.querySelector === 'function') {
            const title = doc.querySelector('title');
            if (title && !(!force && isRegionProcessed(title)) && hasTemplateSyntax(title.textContent || '')) {
                regions.push({ element: title, template: title.textContent || '', isText: true });
            }
        }

        return regions;
    }

    /** Minimal default markup for empty <birkly-region> entry mode. */
    const REGION_DEFAULT_TEMPLATES = {
        'site_elements:header':
            "{from 'site_elements' get entry 'header'}" +
            '<header class="birkly-site-header">{\'content\'}</header>' +
            '{endget}',
        'site_elements:footer':
            "{from 'site_elements' get entry 'footer'}" +
            '<footer class="birkly-site-footer">{\'content\'}</footer>' +
            '{endget}',
        'site_elements:navigation':
            "{from 'site_elements' get entry 'navigation'}" +
            '<nav class="birkly-site-nav">{\'content\'}</nav>' +
            '{endget}'
    };

    function getRegionDefaultTemplate(collection, entry) {
        const col = String(collection || '').trim();
        const slug = String(entry || '').trim();
        if (!col || !slug) return '';
        const key = col + ':' + slug;
        if (Object.prototype.hasOwnProperty.call(REGION_DEFAULT_TEMPLATES, key)) {
            return REGION_DEFAULT_TEMPLATES[key];
        }
        return (
            "{from '" + col + "' get entry '" + slug + "'}" +
            "{title}{'content'}" +
            '{endget}'
        );
    }

    /**
     * Build the Light DOM template string for a region from attrs + optional child HTML.
     * Child HTML always wins as the body; missing {from}/{for} wrappers are added when needed.
     */
    function buildRegionTemplate(opts) {
        const options = opts && typeof opts === 'object' ? opts : {};
        const collection = String(options.collection || '').trim();
        const entry = String(options.entry || '').trim();
        const isList = !!options.list;
        const ordered = String(options.ordered || '').trim();
        const childHtml = options.childHtml != null ? String(options.childHtml) : '';
        const trimmedChild = childHtml.trim();

        if (isList) {
            if (!collection) return trimmedChild;
            if (trimmedChild) {
                if (/\{for\s+/i.test(trimmedChild)) return trimmedChild;
                let open = "{for each entry in '" + collection + "'";
                if (ordered) open += ' ordered by ' + ordered;
                open += '}';
                return open + childHtml + '{endfor}';
            }
            let loop = "{for each entry in '" + collection + "'";
            if (ordered) loop += ' ordered by ' + ordered;
            loop += '}<article class="birkly-region-item"><h2>{title}</h2></article>{endfor}';
            return loop;
        }

        if (!collection || !entry) {
            return trimmedChild;
        }

        if (trimmedChild) {
            if (/\{from\s+/i.test(trimmedChild)) return trimmedChild;
            return (
                "{from '" + collection + "' get entry '" + entry + "'}" +
                childHtml +
                '{endget}'
            );
        }

        return getRegionDefaultTemplate(collection, entry);
    }

    const engine = {
        CONTROL_WORDS,
        render,
        hasTemplateSyntax,
        collectBundleRequests,
        discoverPageRegions,
        isExplicitRegionElement,
        isRegionProcessed,
        REGION_DEFAULT_TEMPLATES,
        getRegionDefaultTemplate,
        buildRegionTemplate,
        resolveLoopItems,
        evaluateCondition,
        formatDate,
        getNestedValue,
        escapeHtml
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = engine;
    }

    // Browser / global exposure (framework-free, no bundler required).
    root.BirklyTemplateEngine = engine;
    const Birkly = root.Birkly || (root.Birkly = {});
    if (!Birkly.render) {
        Birkly.render = engine;
    }
})(typeof globalThis !== 'undefined' ? globalThis : (typeof window !== 'undefined' ? window : this));
