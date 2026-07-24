/**
 * Birkly Template Engine v3
 * String-based renderer — parse → render pipeline (parity with StudioTemplateEngine.php).
 */
(function (root) {
    'use strict';

    const CONTROL_WORDS = new Set([
        'if', 'else', 'elseif', 'endif', 'for', 'endfor', 'foreach', 'endforeach',
        'from', 'get', 'endget', 'end', 'set', 'count', 'each', 'in', 'with',
        'ordered', 'by', 'limit', 'of', 'has', 'exist', 'not', 'does'
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
        return String(format || 'Y-m-d')
            .replace(/F/g, monthsFull[d.getMonth()])
            .replace(/M/g, months[d.getMonth()])
            .replace(/j/g, String(d.getDate()))
            .replace(/d/g, String(d.getDate()).padStart(2, '0'))
            .replace(/Y/g, String(d.getFullYear()))
            .replace(/y/g, String(d.getFullYear()).slice(-2));
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

    function evaluateExistence(ref, data) {
        const value = resolveCollectionEntryRef(ref, data);
        if (value === null || value === undefined) return false;
        if (Array.isArray(value)) return value.length > 0;
        return value !== '' && value !== false;
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

        return false;
    }

    function escapeOutput(pathOrName, value) {
        const str = value == null ? '' : String(value);
        if (!str) return '';
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

    function processVariables(html, data) {
        return html.replace(/\{([^}]+)\}/g, (full, inner) => {
            const trimmed = String(inner || '').trim();
            if (!trimmed) return '';
            if (/^(if|else|elseif|endif|for|endfor|from|get|endget|end|set|count)\b/i.test(trimmed)) {
                return '';
            }
            if (/^count\s+of\b/i.test(trimmed)) return full;

            if (trimmed.includes("'")) {
                const parts = [...trimmed.matchAll(/'([^']+)'|(\d+)/g)];
                if (parts.length) {
                    const value = resolveFieldPathFromParts(parts, data);
                    return escapeOutput(trimmed, value);
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
                return escapeOutput(name, value);
            }

            return '';
        });
    }

    function render(html, data) {
        if (!html || typeof html !== 'string') return html || '';
        if (!data || typeof data !== 'object') return html;

        const renderFn = (tpl, ctx) => render(tpl, ctx);
        let out = processFromGetBlocks(html, data, renderFn);
        out = processForLoops(out, data, renderFn);
        out = processIfBlocks(out, data, renderFn);
        out = processCountVariables(out, data);
        out = processVariables(out, data);
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

        return requests;
    }

    function discoverPageRegions(doc) {
        const regions = [];
        const add = (el) => {
            if (!el || regions.some((r) => r.element === el)) return;
            const template = el.innerHTML || '';
            if (!hasTemplateSyntax(template)) return;
            regions.push({ element: el, template: template });
        };

        const main = doc.querySelector('main');
        if (main) add(main);

        const header = doc.querySelector('header');
        if (header) add(header);

        const footer = doc.querySelector('footer');
        if (footer) add(footer);

        const title = doc.querySelector('title');
        if (title && hasTemplateSyntax(title.textContent || '')) {
            regions.push({ element: title, template: title.textContent || '', isText: true });
        }

        return regions;
    }

    const engine = {
        CONTROL_WORDS,
        render,
        hasTemplateSyntax,
        collectBundleRequests,
        discoverPageRegions,
        resolveLoopItems,
        evaluateCondition,
        escapeHtml
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = engine;
    }

    const Birkly = root.Birkly || (root.Birkly = {});
    Birkly.render = engine;
})(typeof globalThis !== 'undefined' ? globalThis : typeof window !== 'undefined' ? window : global);
