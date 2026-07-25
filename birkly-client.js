/**
 * Birkly Client Library
 * 
 * Automatically connects external websites to Birkly
 * Handles domain authentication, API calls, and template rendering
 * 
 * Usage:
 *   <script src="https://your-cms-domain.com/birkly-client.js"></script>
 */

(function() {
    'use strict';

    // Configuration
    const Birkly = {
        // Auto-detect CMS URL from script src
        cmsUrl: (function() {
            const scripts = document.getElementsByTagName('script');
            for (let script of scripts) {
                if (script.src && script.src.includes('birkly-client.js')) {
                    const url = new URL(script.src);
                    return url.origin;
                }
            }
            // Fallback: try to detect from data attribute
            const script = document.querySelector('script[data-cms-url]');
            if (script) {
                return script.getAttribute('data-cms-url');
            }
            // Last resort: current origin
            return window.location.origin;
        })(),
        
        // Current domain
        domain: window.location.hostname,
        
        // Is localhost?
        isLocalhost: (function() {
            const hostname = window.location.hostname.toLowerCase();
            return hostname === 'localhost' || 
                   hostname === '127.0.0.1' ||
                   hostname === '::1' ||
                   hostname === '0.0.0.0' ||
                   hostname.includes('.local') ||
                   hostname.includes('.test') ||
                   hostname.includes('.dev');
        })(),
        
        // API endpoints
        publicApi: '/api/public.php',
        secureApi: '/api/secure_public.php',
        
        // Current API endpoint (will be determined)
        apiEndpoint: null,
        
        // CSRF token cache
        csrfToken: null,
        
        // Current language
        currentLanguage: (function() {
            // Get from URL parameter
            const urlParams = new URLSearchParams(window.location.search);
            const langFromUrl = urlParams.get('lang');
            if (langFromUrl) {
                localStorage.setItem('birkly_language', langFromUrl);
                return langFromUrl;
            }
            // Get from localStorage
            const langFromStorage = localStorage.getItem('birkly_language');
            if (langFromStorage) {
                return langFromStorage;
            }
            // Default to 'en'
            return 'en';
        })(),
        
        // Initialization flag
        initialized: false,

        // Page bundle cache (M4 — batched public reads)
        pageBundle: {
            loaded: false,
            data: {},
            keys: new Set(),
            failed: new Set(),
            subIndex: {},
            revision: null
        },
        
        // Tracking ID and analytics (from data-tracking-id / data-enable-analytics on birkly-client.js)
        trackingId: null,
        enableAnalytics: false,
        
        // Debug logging helper - only logs when debug mode is enabled
        log: function(...args) {
            if (this.debug) console.log(...args);
        },
        warn: function(...args) {
            if (this.debug) console.warn(...args);
        },
        error: function(...args) {
            // Errors are always logged, but can be suppressed in production if needed
            console.error(...args);
        }
    };
    
    // Resolve analytics config after Birkly object is created (needs helpers below)
    (function configureAnalytics() {
        const clientScript = document.querySelector('script[data-tracking-id], script[src*="birkly-client"]');
        const hostname = window.location.hostname.toLowerCase();
        const isLocalhost = hostname === 'localhost' ||
            hostname === '127.0.0.1' ||
            hostname === '::1' ||
            hostname === '0.0.0.0' ||
            hostname.includes('.local') ||
            hostname.includes('.test') ||
            hostname.includes('.dev');

        let trackingId = null;
        let sameOriginClient = false;

        if (clientScript) {
            const explicitId = clientScript.getAttribute('data-tracking-id');
            if (explicitId !== null && explicitId !== '') {
                trackingId = explicitId;
            } else if (clientScript.src) {
                try {
                    const scriptUrl = new URL(clientScript.src, window.location.href);
                    sameOriginClient = scriptUrl.origin === window.location.origin;
                } catch (_) {
                    sameOriginClient = false;
                }
            }
        }

        if (!trackingId && isLocalhost) {
            trackingId = 'localhost';
        } else if (!trackingId && sameOriginClient) {
            // Bundled project/ on the CMS host (e.g. test.birkly.cloud/project/index.html)
            trackingId = hostname;
        }

        let enableAnalytics = false;
        if (clientScript) {
            const flag = (clientScript.getAttribute('data-enable-analytics') || '').toLowerCase();
            if (flag === 'false' || flag === '0') {
                enableAnalytics = false;
            } else if (flag === 'true' || flag === '1') {
                enableAnalytics = true;
            } else if (clientScript.hasAttribute('data-tracking-id')) {
                enableAnalytics = true;
            } else if (trackingId && sameOriginClient && !isLocalhost) {
                enableAnalytics = true;
            }
        }

        Birkly.trackingId = trackingId;
        Birkly.enableAnalytics = enableAnalytics;
    })();

    // Enable verbose debug logs when URL contains ?__dbg=1 or when on localhost (dev server)
    Birkly.debug = (function() {
        try {
            if (new URLSearchParams(window.location.search).has('__dbg')) return true;
            const host = window.location.hostname || '';
            if (host === 'localhost' || host === '127.0.0.1') return true;
            return false;
        } catch (_) { return false; }
    })();

    // Feature flags
    Birkly.enableTemplateFunctions = true;
    console.log('[Birkly] script loaded');
    if (Birkly.debug) console.warn('Birkly client build: dev8 (debug on)');

    // ---------------------------------------------------------------------
    // MODULES - Core functionality extracted into modules for better organization
    // ---------------------------------------------------------------------
    
    // Initialize modules namespace
    if (!Birkly.utils) Birkly.utils = {};
    if (!Birkly.dateFormatter) Birkly.dateFormatter = {};
    if (!Birkly.conditionals) Birkly.conditionals = {};
    if (!Birkly.loops) Birkly.loops = {};
    
    // =====================================================================
    // MODULE: Utilities (birkly-utils.js)
    // =====================================================================
    (function() {
        Birkly.utils.getNestedValue = function(obj, path) {
            const parts = path.split('.');
            let value = obj;
            for (let part of parts) {
                if (value && typeof value === 'object') {
                    const numIndex = parseInt(part, 10);
                    if (!isNaN(numIndex) && Array.isArray(value)) {
                        value = value[numIndex];
                    } else {
                        value = value[part];
                    }
                } else {
                    return null;
                }
            }
            return value;
        };
        
        Birkly.utils.escapeHtml = function(text) {
            if (text == null) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        };
        
        Birkly.utils.simpleHash = function(str) {
            let h = 0;
            for (let i = 0; i < str.length; i++) {
                h = (h * 31 + str.charCodeAt(i)) >>> 0;
            }
            return h.toString(16);
        };
        
        Birkly.utils.parseQuotedArgs = function(raw) {
            const args = [];
            const s = String(raw || '').trim();
            let i = 0;
            while (i < s.length) {
                while (i < s.length && /\s/.test(s[i])) i++;
                if (i >= s.length) break;
                const ch = s[i];
                if (ch === '\'' || ch === '"') {
                    const quote = ch;
                    i++;
                    let buf = '';
                    while (i < s.length && s[i] !== quote) {
                        buf += s[i];
                        i++;
                    }
                    if (i < s.length && s[i] === quote) i++;
                    args.push(buf);
                    continue;
                }
                let buf = '';
                while (i < s.length && !/\s/.test(s[i])) {
                    buf += s[i];
                    i++;
                }
                if (buf) args.push(buf);
            }
            return args;
        };
        
        Birkly.utils.tfCacheKey = function(name, args) {
            return name + '\0' + JSON.stringify(args || []);
        };
    })();
    
    // =====================================================================
    // MODULE: Date Formatter (birkly-date.js)
    // =====================================================================
    (function() {
        Birkly.dateFormatter.formatDate = function(dateString, format) {
            if (!dateString) return '';
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString;
            
            const map = {
                'Y': date.getFullYear(),
                'y': String(date.getFullYear()).slice(-2),
                'm': String(date.getMonth() + 1).padStart(2, '0'),
                'n': date.getMonth() + 1,
                'd': String(date.getDate()).padStart(2, '0'),
                'j': date.getDate(),
                'F': date.toLocaleString('en-US', { month: 'long' }),
                'M': date.toLocaleString('en-US', { month: 'short' }),
                'H': String(date.getHours()).padStart(2, '0'),
                'h': String(date.getHours() % 12 || 12).padStart(2, '0'),
                'G': date.getHours(),
                'g': date.getHours() % 12 || 12,
                'i': String(date.getMinutes()).padStart(2, '0'),
                's': String(date.getSeconds()).padStart(2, '0'),
                'A': date.getHours() >= 12 ? 'PM' : 'AM',
                'a': date.getHours() >= 12 ? 'pm' : 'am'
            };
            
            return format.replace(/([YymdnjFMHhGgisAa])/g, (match) => map[match] !== undefined ? map[match] : match);
        };
        
        Birkly.dateFormatter.isDateField = function(fieldName) {
            return fieldName === 'created_at' || 
                   fieldName === 'updated_at' || 
                   fieldName === 'published_at' || 
                   fieldName === 'date' ||
                   fieldName.endsWith('.created_at') || 
                   fieldName.endsWith('.updated_at') || 
                   fieldName.endsWith('.published_at') ||
                   fieldName.endsWith('.date');
        };
        
        Birkly.dateFormatter.formatValueForOutput = function(value, fieldName) {
            if (value === null || value === undefined || value === '') {
                return '';
            }
            
            const isDateField = Birkly.dateFormatter.isDateField(fieldName);
            
            if (isDateField) {
                const valueStr = String(value);
                if (valueStr.match(/^\d{4}-\d{2}-\d{2}$/)) {
                    return Birkly.utils.escapeHtml(valueStr);
                }
                if (valueStr.match(/^\d{4}-\d{2}-\d{2}T/)) {
                    return Birkly.utils.escapeHtml(valueStr.substring(0, 10));
                }
                const date = new Date(valueStr);
                if (!isNaN(date.getTime())) {
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    return Birkly.utils.escapeHtml(`${year}-${month}-${day}`);
                }
            }
            
            return Birkly.utils.escapeHtml(String(value));
        };
    })();
    
    // =====================================================================
    // MODULE: Conditionals (birkly-conditionals.js)
    // =====================================================================
    (function() {
        const getNestedValue = Birkly.utils.getNestedValue;
        
        Birkly.conditionals.isTruthyTemplateValue = function(value) {
            if (value === null || value === undefined) return false;
            if (value === false) return false;
            if (value === 0) return false;
            if (typeof value === 'string') {
                const v = value.trim().toLowerCase();
                if (v === '' || v === 'false' || v === '0' || v === 'null' || v === 'undefined') return false;
            }
            return !!value;
        };
        
        Birkly.conditionals.resolveCollectionEntryRef = function(ref, data) {
            const trimmed = String(ref || '').trim();
            const special = trimmed.match(/^'([^']+)'\.'(current|newest|last-updated)'$/);
            if (special) {
                return Birkly.conditionals.resolveParentCollectionEntry(special[1], special[2], data);
            }
            const m = trimmed.match(/^'([^']+)'\.'([^']+)'$/);
            if (!m) return undefined;
            return Birkly.conditionals.resolveParentCollectionEntry(m[1], m[2], data);
        };

        Birkly.conditionals.resolveParentCollectionEntry = function(collection, identifier, data) {
            if (collection === 'site_elements') {
                const se = data.site_elements || {};
                return se[identifier];
            }

            if (identifier === 'current') {
                if (data.entry) {
                    return data.entry;
                }
                const prefix = '__entry__:' + collection + ':';
                for (const key of Object.keys(data)) {
                    if (key.startsWith(prefix) && data[key]) {
                        return data[key];
                    }
                }
                return undefined;
            }

            if (identifier === 'newest' || identifier === 'last-updated') {
                const entries = data[collection];
                if (!Array.isArray(entries) || !entries.length) return undefined;
                const field = identifier === 'last-updated' ? 'updated_at' : 'created_at';
                return entries.slice().sort((a, b) => {
                    const da = Date.parse(a && a[field] ? a[field] : 0) || 0;
                    const db = Date.parse(b && b[field] ? b[field] : 0) || 0;
                    return db - da;
                })[0];
            }

            const entries = data[collection];
            if (Array.isArray(entries)) {
                return entries.find(e =>
                    e &&
                    (String(e.slug || '') === identifier || String(e.id || '') === identifier)
                );
            }

            return undefined;
        };

        Birkly.conditionals.parseLoopInClause = function(inClause) {
            const raw = String(inClause || '').trim();
            const ofSub = raw.match(/^['"]([^'"]+)['"]\s+of\s+['"]([^'"]+)['"]\.(?:['"]([^'"]+)['"]|(current|newest|last-updated))\s*$/);
            if (ofSub) {
                return {
                    kind: 'sub_of',
                    subCollection: ofSub[1],
                    parentCollection: ofSub[2],
                    parentIdentifier: ofSub[3] || ofSub[4]
                };
            }
            const quoted = raw.match(/^['"]([^'"]+)['"]\s*$/);
            if (quoted) return { kind: 'collection', name: quoted[1] };
            if (/^[a-zA-Z0-9_\.]+$/.test(raw)) return { kind: 'path', path: raw };
            return { kind: 'raw', raw };
        };

        Birkly.conditionals.resolveSubCollectionItems = function(subCollection, parentCollection, parentIdentifier, data) {
            const parent = Birkly.conditionals.resolveParentCollectionEntry(parentCollection, parentIdentifier, data);
            if (!parent) return [];

            const ids = [parent.id, parent.slug, parentIdentifier].filter(v => v !== undefined && v !== null && v !== '');
            for (const id of ids) {
                const key = '__sub__:' + parentCollection + ':' + id + ':' + subCollection;
                if (Array.isArray(data[key])) return data[key];
            }

            if (Array.isArray(data[subCollection]) && data.entry) {
                const entry = data.entry;
                if (ids.some(id => String(entry.id) === String(id) || String(entry.slug) === String(id))) {
                    return data[subCollection];
                }
            }

            if (Array.isArray(parent[subCollection])) return parent[subCollection];
            return [];
        };

        Birkly.conditionals.resolveLoopItems = function(inClause, data) {
            const parsed = Birkly.conditionals.parseLoopInClause(inClause);
            if (parsed.kind === 'sub_of') {
                return Birkly.conditionals.resolveSubCollectionItems(
                    parsed.subCollection,
                    parsed.parentCollection,
                    parsed.parentIdentifier,
                    data
                );
            }
            if (parsed.kind === 'collection') {
                const name = parsed.name;
                if (data[name] !== undefined && data[name] !== null && Array.isArray(data[name])) {
                    return data[name];
                }
                if (data.entry && Array.isArray(data.entry[name])) {
                    return data.entry[name];
                }
                return Array.isArray(data[name]) ? data[name] : [];
            }
            if (parsed.kind === 'path') {
                const value = getNestedValue(data, parsed.path);
                return Array.isArray(value) ? value : [];
            }
            return [];
        };

        Birkly.conditionals.parseForLoopHeader = function(str) {
            const m = String(str || '').match(/\{for\s+(?:each\s+)?([a-zA-Z0-9_]+)\s+in\s+(.+?)\}/);
            if (!m) return null;
            let rest = m[2].trim();
            let filterExpr = null;
            let orderExpr = null;
            let limitExpr = null;
            const orderMatch = rest.match(/\s+ordered\s+by\s+(.+)$/i);
            if (orderMatch) {
                orderExpr = orderMatch[1].trim();
                const limitInOrder = orderExpr.match(/^(.+?)\s+limit\s+(\d+)\s*$/i);
                if (limitInOrder) {
                    orderExpr = limitInOrder[1].trim();
                    limitExpr = parseInt(limitInOrder[2], 10);
                }
                rest = rest.substring(0, orderMatch.index).trim();
            }
            const withMatch = rest.match(/\s+with\s+(.+)$/i);
            if (withMatch) {
                filterExpr = withMatch[1].trim();
                rest = rest.substring(0, withMatch.index).trim();
            }
            const limitMatch = rest.match(/\s+limit\s+(\d+)\s*$/i);
            if (limitMatch) {
                limitExpr = parseInt(limitMatch[1], 10);
                rest = rest.substring(0, limitMatch.index).trim();
            }
            return { full: m[0], itemVar: m[1], inClause: rest, filterExpr, orderExpr, limitExpr };
        };
        
        Birkly.conditionals.renderConditionals = function(template, data, itemPrefix = '') {
            const evalCondition = (raw) => {
                const cond = String(raw || '').trim();
                if (!cond) return false;

                // Support canonical '=' plus common aliases and compatibility operators.
                // Allowed: =, ==, !=, <, >, <=, >=, =<, =>
                const comparison = cond.match(/^(.+?)\s*(>=|<=|=<|=>|>|<|==|!=|=)\s*(.+?)\s*$/);
                if (comparison) {
                    const leftPath = comparison[1].trim();
                    let operator = comparison[2];
                    // Normalize aliases
                    if (operator === '=') operator = '==';
                    if (operator === '=<') operator = '<=';
                    if (operator === '=>') operator = '>=';
                    const rightValue = comparison[3].trim();
                    
                    let leftValue = getNestedValue(data, leftPath);
                    if (leftPath.endsWith('.length')) {
                        const basePath = leftPath.slice(0, -7);
                        const baseValue = getNestedValue(data, basePath);
                        if (Array.isArray(baseValue)) {
                            leftValue = baseValue.length;
                        } else if (baseValue && typeof baseValue === 'object' && 'length' in baseValue) {
                            leftValue = baseValue.length;
                        } else {
                            leftValue = 0;
                        }
                    }
                    // Normalize relation-like objects for string comparison (e.g. entry.visibility = "public")
                    if (leftValue && typeof leftValue === 'object' && !Array.isArray(leftValue)) {
                        if (typeof leftValue.slug === 'string') leftValue = leftValue.slug;
                        else if (typeof leftValue.name === 'string') leftValue = leftValue.name;
                        else if (typeof leftValue.value === 'string') leftValue = leftValue.value;
                        else leftValue = String(leftValue);
                    }
                    
                    let right = rightValue;
                    if ((rightValue.startsWith('"') && rightValue.endsWith('"')) || 
                        (rightValue.startsWith("'") && rightValue.endsWith("'"))) {
                        right = rightValue.slice(1, -1);
                    } else if (!isNaN(rightValue)) {
                        right = parseFloat(rightValue);
                    } else {
                        right = getNestedValue(data, rightValue);
                        if (rightValue.endsWith('.length')) {
                            const basePath = rightValue.slice(0, -7);
                            const baseValue = getNestedValue(data, basePath);
                            if (Array.isArray(baseValue)) {
                                right = baseValue.length;
                            } else if (baseValue && typeof baseValue === 'object' && 'length' in baseValue) {
                                right = baseValue.length;
                            } else {
                                right = 0;
                            }
                        }
                        // Normalize relation-like objects for string comparison
                        if (right && typeof right === 'object' && !Array.isArray(right)) {
                            if (typeof right.slug === 'string') right = right.slug;
                            else if (typeof right.name === 'string') right = right.name;
                            else if (typeof right.value === 'string') right = right.value;
                            else right = String(right);
                        }
                    }
                    
                    const leftNum = typeof leftValue === 'number' ? leftValue : parseFloat(leftValue);
                    const rightNum = typeof right === 'number' ? right : parseFloat(right);
                    const bothNumeric = !isNaN(leftNum) && !isNaN(rightNum);
                    
                    if (bothNumeric) {
                        switch (operator) {
                            case '>': return leftNum > rightNum;
                            case '<': return leftNum < rightNum;
                            case '>=': return leftNum >= rightNum;
                            case '<=': return leftNum <= rightNum;
                            case '==': return leftNum === rightNum;
                            case '!=': return leftNum !== rightNum;
                        }
                    } else {
                        const leftStr = String(leftValue ?? '');
                        const rightStr = String(right ?? '');
                        switch (operator) {
                            case '==': return leftStr === rightStr;
                            case '!=': return leftStr !== rightStr;
                            default: return false;
                        }
                    }
                }

                const eq = cond.match(/^(.+?)\s*==\s*['"]([^'"]*)['"]\s*$/);
                if (eq) {
                    const varPath = eq[1].trim();
                    const expected = eq[2];
                    const value = getNestedValue(data, varPath);
                    return String(value ?? '') === expected;
                }

                // Bare "has 'field'" in loop context: object is current entry (data.entry or data[itemPrefix])
                const bareHas = cond.match(/^has\s+(.+)\s*$/);
                if (bareHas) {
                    let prop = bareHas[1].trim();
                    if ((prop.startsWith("'") && prop.endsWith("'")) || (prop.startsWith("\"") && prop.endsWith("\""))) {
                        prop = prop.slice(1, -1);
                    }
                    const obj = (itemPrefix && data[itemPrefix]) || data.entry || data;
                    return !!(obj && typeof obj === 'object' && obj[prop] !== undefined && obj[prop] !== null && obj[prop] !== '');
                }

                const has = cond.match(/^([^\s]+)\s+has\s+([^\s]+)\s*$/);
                if (has) {
                    const obj = getNestedValue(data, has[1]);
                    let prop = has[2];
                    if ((prop.startsWith("'") && prop.endsWith("'")) || (prop.startsWith("\"") && prop.endsWith("\""))) {
                        prop = prop.slice(1, -1);
                    }
                    return !!(obj && typeof obj === 'object' && obj[prop] !== undefined && obj[prop] !== null && obj[prop] !== '');
                }

                const exist = cond.match(/^(.+?)\s+exist\s*$/);
                if (exist) {
                    const varPath = exist[1].trim();
                    let value = Birkly.conditionals.resolveCollectionEntryRef(varPath, data);
                    if (value === undefined) {
                        value = getNestedValue(data, varPath);
                    }
                    if (Array.isArray(value)) {
                        return value.length > 0;
                    }
                    if (value && typeof value === 'object') {
                        return Object.keys(value).length > 0;
                    }
                    return value !== null && value !== undefined && value !== '';
                }

                const value = getNestedValue(data, cond);
                return Birkly.conditionals.isTruthyTemplateValue(value);
            };

            const directiveRe = /\{\s*(if\s+[^}]+|elseif\s+[^}]+|else|end\s*if|endif)\s*\}/gi;
            let out = '';
            let lastIdx = 0;

            const stack = [];
            const recomputeInclude = () => {
                if (stack.length === 0) return true;
                for (let i = stack.length - 1; i >= 0; i--) {
                    if (!stack[i].matched) return false;
                }
                return true;
            };

            // Only treat as directive when the current line has only whitespace before the match
            // (allows indented {if}/{else}/{endif}; prevents "Use {if you want}" from breaking the stack)
            const isAtLineStart = (idx) => {
                const before = template.slice(0, idx);
                const lineSoFar = before.split(/\r?\n/).pop() || '';
                return before.length === 0 || /^\s*$/.test(lineSoFar);
            };

            let include = true;
            let m;
            while ((m = directiveRe.exec(template)) !== null) {
                const text = template.slice(lastIdx, m.index);
                if (include && text) out += text;

                let token = (m[1] || '').trim();
                if (token.toLowerCase() === 'end if') token = 'endif';
                const atLineStart = isAtLineStart(m.index);
                const isConditional = token === 'endif' || token === 'else' ||
                    token.toLowerCase().startsWith('elseif ') || token.toLowerCase().startsWith('if ');
                // When stack has an open block, treat ALL conditionals as directives so stack stays in sync.
                const forceDirective = stack.length > 0 && isConditional;
                // Always treat {if single_identifier} (e.g. {if user_logged_in}) as directive so it works
                // even when not at line start (e.g. after inline HTML).
                const ifSingleWord = token.toLowerCase().startsWith('if ') && /^\w+$/.test(token.slice(3).trim());
                const treatAsDirective = atLineStart || forceDirective || ifSingleWord;
                if (!treatAsDirective) {
                    if (include) out += m[0];
                    lastIdx = m.index + m[0].length;
                    continue;
                }
                if (token.toLowerCase().startsWith('if ')) {
                    const condStr = token.slice(3).trim();
                    const condVal = evalCondition(condStr);
                    stack.push({ cond: condVal, inElse: false, matched: condVal });
                    include = recomputeInclude();
                } else if (token.toLowerCase().startsWith('elseif ')) {
                    if (stack.length) {
                        const condStr = token.slice(7).trim();
                        const condVal = evalCondition(condStr);
                        const last = stack[stack.length - 1];
                        if (!last.matched && condVal) {
                            last.cond = condVal;
                            last.inElse = true;
                            last.matched = true;
                        } else {
                            last.inElse = true;
                        }
                        include = recomputeInclude();
                    }
                } else if (token === 'else') {
                    if (stack.length) {
                        const last = stack[stack.length - 1];
                        const hadMatched = last.matched;  // already output the if branch?
                        last.inElse = true;
                        if (!last.matched) last.matched = true;
                        // If we already output the if branch, exclude the else branch
                        include = hadMatched ? false : recomputeInclude();
                    }
                } else if (token === 'endif') {
                    if (stack.length) {
                        stack.pop();
                        include = recomputeInclude();
                    }
                }

                lastIdx = m.index + m[0].length;
            }

            const tail = template.slice(lastIdx);
            if (include && tail) {
                out += tail;
            }

            // Strip any remaining conditional markers (e.g. unprocessed directives)
            // Do NOT early-return on "collapsed" output — that triggered fallback to marker-stripping
            // only, which showed BOTH branches. Trust the conditional result (e.g. only else branch).
            out = out.replace(/\{\s*if\s+[^}]+\s*\}/gi, '');
            out = out.replace(/\{\s*elseif\s+[^}]+\s*\}/gi, '');
            out = out.replace(/\{\s*else\s*\}/gi, '');
            out = out.replace(/\{\s*end\s*if\s*\}/gi, '').replace(/\{\s*endif\s*\}/gi, '');
            // IMPORTANT: do not strip loop markers here (loops are handled by the loop renderer)
            
            return out;
        };
    })();
    
    // =====================================================================
    // MODULE: Loops (birkly-loops.js) - Partial implementation
    // Note: Full loops module is complex and will be integrated separately
    // =====================================================================

    // =====================================================================
    // MODULE: Page bundle (birkly-bundle.js) — M4 batched public reads
    // =====================================================================
    if (!Birkly.bundle) Birkly.bundle = {};
    (function () {
        Birkly.bundle.getTemplateCollectionSlugs = function (templateStr) {
            const collections = [];
            const quoted = templateStr.match(/\{for\s+(?:each\s+)?[a-zA-Z0-9_]+\s+in\s+['"]([^'"]+)['"][^}]*\}/g);
            (quoted || []).forEach((match) => {
                const m = match.match(/['"]([^'"]+)['"]/);
                if (m && collections.indexOf(m[1]) === -1) collections.push(m[1]);
            });
            const unquoted = templateStr.match(/\{for\s+(?:each\s+)?[a-zA-Z0-9_]+\s+in\s+([a-zA-Z0-9_\.]+)(?:\s+with\s+[^{}]+?)?(?:\s+ordered\s+by\s+[^{}]+?)?\s*\}/g);
            (unquoted || []).forEach((match) => {
                const m = match.match(/\s+in\s+([a-zA-Z0-9_\.]+)/);
                if (!m) return;
                const name = m[1];
                if (name.includes('.')) return;
                if (collections.indexOf(name) === -1) collections.push(name);
            });
            return collections;
        };

        // Collections referenced by {from 'blog' get entry 'slug'} blocks (not only {for ... in 'blog'} loops).
        Birkly.bundle.getTemplateFromEntryCollectionSlugs = function (templateStr) {
            const collections = [];
            const re = /\{from\s+['"]([^'"]+)['"]\s+get\s+entry\s+['"][^'"]+['"]\s*\}/g;
            let match;
            const text = String(templateStr || '');
            while ((match = re.exec(text)) !== null) {
                const name = match[1];
                if (name && name !== 'site_elements' && collections.indexOf(name) === -1) {
                    collections.push(name);
                }
            }
            return collections;
        };

        Birkly.bundle.getAllTemplateCollectionSlugs = function (templateStr) {
            const collections = Birkly.bundle.getTemplateCollectionSlugs(templateStr);
            Birkly.bundle.getTemplateFromEntryCollectionSlugs(templateStr).forEach((name) => {
                if (collections.indexOf(name) === -1) collections.push(name);
            });
            return collections;
        };

        Birkly.bundle.getPageTemplateHtml = function () {
            if (!document.body) return '';
            return document.body.innerHTML || '';
        };

        Birkly.bundle.collectBundleRequests = function () {
            const requests = [];
            const seen = new Set();
            const add = (req) => {
                if (!req || !req.type || !req.into) return;
                const key = JSON.stringify(req);
                if (seen.has(key)) return;
                seen.add(key);
                requests.push(req);
            };

            const combined = Birkly.bundle.getPageTemplateHtml();

            Birkly.bundle.getAllTemplateCollectionSlugs(combined).forEach((collection) => {
                add({ type: 'list_entries', into: collection, collection });
            });
            add({ type: 'list_entries', into: 'site_elements', collection: 'site_elements' });

            document.querySelectorAll('[data-birkly-prefetch]').forEach((el) => {
                const type = el.getAttribute('data-birkly-prefetch');
                const into = el.getAttribute('data-birkly-into');
                if (!type || !into) return;
                if (type === 'sub_entries') {
                    add({
                        type: 'sub_entries',
                        into,
                        parent_collection: el.getAttribute('data-parent-collection') || '',
                        parent_entry_id: el.getAttribute('data-parent-entry-id') || '',
                        sub_collection: el.getAttribute('data-sub-collection') || ''
                    });
                } else if (type === 'entries') {
                    add({
                        type: 'list_entries',
                        into,
                        collection: el.getAttribute('data-collection') || '',
                        filter_field: el.getAttribute('data-filter-field') || '',
                        filter_value: el.getAttribute('data-filter-value') || ''
                    });
                } else if (type === 'template_function') {
                    const name = el.getAttribute('data-name') || '';
                    let args = [];
                    try {
                        args = JSON.parse(el.getAttribute('data-args') || '[]');
                    } catch (e) { args = []; }
                    if (name) {
                        add({
                            type: 'template_function',
                            into,
                            name,
                            args: Array.isArray(args) ? args : []
                        });
                    }
                }
            });

            if (Birkly.enableTemplateFunctions !== false) {
                add({ type: 'template_functions_meta', into: '__tf_meta__' });
            }

            const route = Birkly.bundle.detectPageRoute();
            if (route && route.slug) {
                const collection = Birkly.bundle.resolveSlugPageCollection(combined, route);
                if (collection) {
                    const into = '__entry__:' + collection + ':' + route.slug;
                    add({ type: 'get_entry', into, collection, slug: route.slug });
                }
            }

            return requests;
        };

        Birkly.bundle.resolveSlugPageCollection = function (templateHtml, route) {
            const collMeta = document.querySelector('meta[name="birkly-collection"]');
            let collection = collMeta && collMeta.content ? collMeta.content.trim() : '';
            if (collection && /^[a-zA-Z0-9_-]+$/.test(collection)) {
                return collection;
            }
            try {
                const qp = new URLSearchParams(window.location.search);
                collection = qp.get('collection') || '';
                if (collection && /^[a-zA-Z0-9_-]+$/.test(collection)) {
                    return collection;
                }
            } catch (e) { /* ignore */ }
            const page = route && route.page ? route.page : '';
            if (page === 'post') return 'blog';
            if (page === 'service') return 'services';
            if (page === 'product') return 'products';
            if (page === 'event') return 'events';
            const slugs = Birkly.bundle.getTemplateCollectionSlugs(templateHtml || '');
            if (slugs.length === 1) return slugs[0];
            return null;
        };

        Birkly.bundle.ingestTemplateFunctionsMeta = function (list) {
            const meta = Array.isArray(list) ? list : [];
            Birkly.templateFunctions.meta = meta;
            Birkly.templateFunctions.names = new Set(meta.map((f) => f.name).filter(Boolean));
            Birkly.templateFunctions.zeroArg = new Set(
                meta
                    .filter((f) => Array.isArray(f.arguments) && f.arguments.length === 0)
                    .map((f) => f.name)
            );
            Birkly.templateFunctions.loaded = true;
        };

        Birkly.bundle.shouldProbeSnapshot = function () {
            const meta = document.querySelector('meta[name="birkly-snapshot"]');
            if (meta && String(meta.content || '').trim() === '1') {
                return true;
            }
            if (document.documentElement.getAttribute('data-birkly-snapshot') === '1') {
                return true;
            }
            return false;
        };

        Birkly.bundle.getBundleCacheKey = function (requests) {
            const route = Birkly.bundle.detectPageRoute();
            const page = route && route.page ? route.page : 'index';
            const slug = route && route.slug ? route.slug : '';
            let h = 0;
            const s = JSON.stringify(requests || []);
            for (let i = 0; i < s.length; i++) {
                h = (h * 31 + s.charCodeAt(i)) >>> 0;
            }
            // v5: never trust empty bundled arrays (refetch via API)
            return 'birkly_bundle_v5:' + page + ':' + slug + ':' + (Birkly.currentLanguage || 'en') + ':' + h.toString(16);
        };

        Birkly.bundle.entryPayloadLooksStripped = function (entry) {
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
        };

        Birkly.bundle.bundlePayloadIsValid = function (data) {
            if (!data || typeof data !== 'object') {
                return false;
            }
            for (const [key, rows] of Object.entries(data)) {
                if (key === '__tf_meta__' || !Array.isArray(rows) || rows.length === 0) {
                    continue;
                }
                if (Birkly.bundle.entryPayloadLooksStripped(rows[0])) {
                    return false;
                }
            }
            return true;
        };

        Birkly.bundle.applyBundleResponse = function (requests, json) {
            if (!Birkly.pageBundle.data) Birkly.pageBundle.data = {};
            Object.assign(Birkly.pageBundle.data, json.data);
            if (json.revision) {
                Birkly.pageBundle.revision = json.revision;
            }
            if (json.data && json.data.__tf_meta__) {
                Birkly.bundle.ingestTemplateFunctionsMeta(json.data.__tf_meta__);
            }
            const errorList = Array.isArray(json.errors) ? json.errors : [];
            requests.forEach((req) => {
                const into = req.into || (req.type === 'template_functions_meta' ? '__tf_meta__' : '');
                if (!into) return;
                Birkly.pageBundle.keys.add(into);
                const failed = errorList.some((err) => String(err).indexOf(into) !== -1);
                const rows = json.data[into];
                if (req.type === 'template_function' && req.name) {
                    const tfKey = req.name + '|' + JSON.stringify(req.args || []);
                    Birkly.templateFunctions.cache.set(tfKey, rows);
                    return;
                }
                if (req.type === 'template_functions_meta') {
                    return;
                }
                if (failed || (req.type !== 'get_entry' && req.type !== 'template_function' && !Array.isArray(rows))) {
                    Birkly.pageBundle.failed.add(into);
                }
            });
            Birkly.pageBundle.loaded = true;
        };

        Birkly.bundle.loadPageBundle = async function (requests, options = {}) {
            if (!requests || !requests.length) return false;
            const label = options.label || 'page bundle';
            const url = Birkly.cmsUrl + '/api/page_bundle.php';
            const language = Birkly.currentLanguage || 'en';
            const cacheKey = Birkly.bundle.getBundleCacheKey(requests);
            let cached = null;
            try {
                const raw = sessionStorage.getItem(cacheKey);
                if (raw) cached = JSON.parse(raw);
            } catch (e) { /* ignore */ }

            if (cached && cached.data && cached.revision) {
                if (Birkly.bundle.bundlePayloadIsValid(cached.data)) {
                    Birkly.bundle.applyBundleResponse(requests, cached);
                    Birkly.log('Birkly: loadPageBundle from session cache', { revision: cached.revision });
                } else {
                    try { sessionStorage.removeItem(cacheKey); } catch (e) { /* ignore */ }
                    cached = null;
                    Birkly.warn('Birkly: Discarded invalid session page bundle cache');
                }
            }

            try {
                Birkly.log('Birkly: loadPageBundle', { count: requests.length, label });
                const headers = {};
                if (cached && cached.revision) {
                    headers['If-None-Match'] = cached.revision;
                }
                let json = null;
                try {
                    const encoded = btoa(JSON.stringify(requests))
                        .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
                    const getUrl = url + '?language=' + encodeURIComponent(language)
                        + '&requests=' + encodeURIComponent(encoded) + '&lean=1';
                    const getRes = await fetch(getUrl, { method: 'GET', headers, credentials: 'include' });
                    if (getRes.status === 304 && cached) {
                        if (Birkly.bundle.bundlePayloadIsValid(cached.data)) {
                            return true;
                        }
                        try { sessionStorage.removeItem(cacheKey); } catch (e) { /* ignore */ }
                        cached = null;
                        delete headers['If-None-Match'];
                    }
                    if (getRes.ok) {
                        json = await getRes.json().catch(() => ({}));
                    }
                } catch (getErr) {
                    Birkly.warn('Birkly: GET page bundle failed, falling back to POST', getErr);
                }
                if (!json || json.status !== 'ok' || !json.data) {
                    const postRes = await fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', ...headers },
                        body: JSON.stringify({ language, requests, lean: true }),
                        credentials: 'include'
                    });
                    json = await postRes.json().catch(() => ({}));
                    if (!postRes.ok || json.status !== 'ok' || !json.data) {
                        if (cached && cached.data && Birkly.bundle.bundlePayloadIsValid(cached.data)) {
                            return true;
                        }
                        Birkly.warn('Birkly: page bundle failed', json);
                        return false;
                    }
                }
                Birkly.bundle.applyBundleResponse(requests, json);
                try {
                    sessionStorage.setItem(cacheKey, JSON.stringify({
                        revision: json.revision || null,
                        data: json.data,
                        errors: json.errors || []
                    }));
                } catch (e) { /* quota */ }
                return true;
            } catch (err) {
                if (cached && cached.data) {
                    return true;
                }
                Birkly.warn('Birkly: page bundle error', err);
                return false;
            }
        };

        Birkly.bundle.detectPageRoute = function () {
            const pageMeta = document.querySelector('meta[name="birkly-page"]');
            const slugMeta = document.querySelector('meta[name="birkly-slug"]');
            if (pageMeta && pageMeta.content) {
                const page = pageMeta.content.trim();
                if (!/^[a-zA-Z0-9_-]+$/.test(page)) return null;
                const slug = slugMeta && slugMeta.content ? slugMeta.content.trim() : '';
                if (slug && !/^[a-zA-Z0-9_-]+$/.test(slug)) return null;
                return { page, slug: slug || null };
            }
            const path = (window.location.pathname || '').toLowerCase();
            const file = path.split('/').pop() || 'index.html';
            if (file === '' || file === 'index.html') return { page: 'index', slug: null };
            const base = file.replace(/\.html$/, '');
            if (!/^[a-z0-9_-]+$/.test(base)) return null;
            const slugParam = new URLSearchParams(window.location.search).get('slug');
            if (slugParam && /^[a-zA-Z0-9_-]+$/.test(slugParam)) {
                return { page: base, slug: slugParam };
            }
            return { page: base, slug: null };
        };

        Birkly.bundle.consumeInlinePageSnapshot = function (route) {
            const inline = window.BIRKLY_PAGE_SNAPSHOT || window.KEKABA_PAGE_SNAPSHOT;
            if (!inline || !inline.data) return null;
            if (inline.page !== route.page) return null;
            const inlineSlug = inline.slug || null;
            if ((route.slug || null) !== inlineSlug) return null;
            return {
                requests: Array.isArray(inline.requests) ? inline.requests : [],
                data: inline.data,
                errors: Array.isArray(inline.errors) ? inline.errors : [],
                rendered: inline.rendered || null,
                source: 'inline'
            };
        };

        Birkly.bundle.tryLoadPageSnapshot = async function () {
            const route = Birkly.bundle.detectPageRoute();
            if (!route) return false;

            try {
                let json = Birkly.bundle.consumeInlinePageSnapshot(route);
                let source = json ? json.source : 'network';

                if (!json) {
                    let url = Birkly.cmsUrl + '/api/page_snapshot.php?page=' + encodeURIComponent(route.page);
                    if (route.slug) {
                        url += '&slug=' + encodeURIComponent(route.slug);
                    }
                    const res = await fetch(url, { credentials: 'include' });
                    const body = await res.json().catch(() => ({}));
                    if (!res.ok || body.status !== 'ok' || !body.data) {
                        return false;
                    }
                    json = {
                        requests: Array.isArray(body.requests) ? body.requests : [],
                        data: body.data,
                        errors: Array.isArray(body.errors) ? body.errors : [],
                        rendered: body.rendered || null
                    };
                }

                Birkly.bundle.applyBundleResponse(json.requests, {
                    data: json.data,
                    errors: json.errors
                });
                document.documentElement.classList.add('birkly-snapshot-page');
                Birkly.log('Birkly: Page snapshot loaded:', route.page, route.slug || '', Object.keys(json.data).length, 'keys', '(' + source + ')');
                return true;
            } catch (err) {
                Birkly.warn('Birkly: page snapshot failed', err);
                return false;
            }
        };
    })();
    
    // Create aliases for backward compatibility (except renderConditionals which is a function below)
    const getNestedValue = Birkly.utils.getNestedValue;
    const escapeHtml = Birkly.utils.escapeHtml;
    const formatDate = Birkly.dateFormatter.formatDate;
    const formatValueForOutput = Birkly.dateFormatter.formatValueForOutput;
    // renderConditionals is defined as a function below to maintain backward compatibility
    const isTruthyTemplateValue = Birkly.conditionals.isTruthyTemplateValue.bind(Birkly.conditionals);
    const resolveCollectionEntryRef = Birkly.conditionals.resolveCollectionEntryRef.bind(Birkly.conditionals);
    const resolveLoopItems = Birkly.conditionals.resolveLoopItems.bind(Birkly.conditionals);
    const parseForLoopHeader = Birkly.conditionals.parseForLoopHeader.bind(Birkly.conditionals);
    const getTemplateCollectionSlugs = Birkly.bundle.getTemplateCollectionSlugs;
    const collectBundleRequests = Birkly.bundle.collectBundleRequests;
    const applyBundleResponse = Birkly.bundle.applyBundleResponse;
    const loadPageBundle = Birkly.bundle.loadPageBundle;
    const tryLoadPageSnapshot = Birkly.bundle.tryLoadPageSnapshot;

    // ---------------------------------------------------------------------
    // Plugin Template Functions (template-first, plugin self-contained)
    //
    // Plugins register PHP template functions via register_template_function().
    // This client library discovers and resolves them via the public API,
    // so websites only need this one script tag.
    // ---------------------------------------------------------------------

    Birkly.templateFunctions = {
        loaded: false,
        meta: [],
        names: new Set(),
        zeroArg: new Set(),
        cache: new Map() // key -> result
    };

    function mergePageBundleIntoData(data) {
        if (!Birkly.pageBundle.loaded || !Birkly.pageBundle.data) {
            return data;
        }
        Object.keys(Birkly.pageBundle.data).forEach((key) => {
            if (key === '__tf_meta__') return;
            if (Birkly.pageBundle.failed.has(key)) return;
            const bundled = Birkly.pageBundle.data[key];
            if (Array.isArray(bundled) && bundled.length === 0) {
                return;
            }
            if (data[key] !== undefined) {
                return;
            }
            if (Array.isArray(bundled)) {
                data[key] = bundled.map((row) => flattenLocalizedEntry(row));
            } else if (bundled && typeof bundled === 'object') {
                data[key] = flattenLocalizedEntry(bundled);
            } else {
                data[key] = bundled;
            }
        });
        return data;
    }

    async function loadTemplateFunctionsMeta() {
        // Short‑circuit when template functions are disabled.
        if (!Birkly.enableTemplateFunctions) {
            Birkly.templateFunctions.loaded = true;
            Birkly.templateFunctions.meta = [];
            Birkly.templateFunctions.names = new Set();
            Birkly.templateFunctions.zeroArg = new Set();
            return Birkly.templateFunctions.meta;
        }

        if (Birkly.templateFunctions.loaded) return Birkly.templateFunctions.meta;

        if (Birkly.pageBundle.data && Birkly.pageBundle.data.__tf_meta__) {
            Birkly.bundle.ingestTemplateFunctionsMeta(Birkly.pageBundle.data.__tf_meta__);
            return Birkly.templateFunctions.meta;
        }

        try {
            const res = await fetch(Birkly.apiEndpoint + '?action=template_functions_list', { credentials: 'include' });
            
            // Handle non-OK responses gracefully
            if (!res.ok) {
                // If 500 or other error, just return empty list - don't break the page
                Birkly.templateFunctions.loaded = true;
                Birkly.templateFunctions.meta = [];
                Birkly.templateFunctions.names = new Set();
                Birkly.templateFunctions.zeroArg = new Set();
                Birkly.warn('Birkly: Template functions endpoint returned', res.status, res.statusText);
                return Birkly.templateFunctions.meta;
            }
            
            const json = await res.json().catch(() => ({}));
            Birkly.log('Birkly: Template functions API response:', json);
            const list = json?.data?.functions || [];
            Birkly.log('Birkly: Template functions list:', list, 'count:', list.length);

            Birkly.templateFunctions.meta = Array.isArray(list) ? list : [];
            Birkly.templateFunctions.names = new Set(Birkly.templateFunctions.meta.map(f => f.name).filter(Boolean));
            Birkly.templateFunctions.zeroArg = new Set(
                Birkly.templateFunctions.meta
                    .filter(f => Array.isArray(f.arguments) && f.arguments.length === 0)
                    .map(f => f.name)
            );
            Birkly.log('Birkly: Template functions parsed - names:', Array.from(Birkly.templateFunctions.names), 'zeroArg:', Array.from(Birkly.templateFunctions.zeroArg));
            Birkly.templateFunctions.loaded = true;
        } catch (e) {
            // Silently fail - template functions are optional
            Birkly.templateFunctions.loaded = true;
            Birkly.templateFunctions.meta = [];
            Birkly.templateFunctions.names = new Set();
            Birkly.templateFunctions.zeroArg = new Set();
            if (Birkly.debug) console.debug('Birkly: Template functions not available', e);
        }
        
        return Birkly.templateFunctions.meta;

        Birkly.templateFunctions.loaded = true;
        return Birkly.templateFunctions.meta;
    }

    // Use module function
    const tfCacheKey = Birkly.utils.tfCacheKey;

    async function callTemplateFunction(name, args = []) {
        const key = tfCacheKey(name, args);
        if (Birkly.templateFunctions.cache.has(key)) return Birkly.templateFunctions.cache.get(key);

        try {
            const params = new URLSearchParams();
            params.set('action', 'template_function_call');
            params.set('name', name);
            params.set('args', JSON.stringify(args || []));

            const res = await fetch(Birkly.apiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'include',
                body: params.toString()
            });
            
            // Handle non-OK responses gracefully
            if (!res.ok) {
                if (Birkly.debug) console.debug('Birkly: Template function call failed:', name, 'status:', res.status);
                // Cache null to prevent repeated failed calls
                Birkly.templateFunctions.cache.set(key, null);
                return null;
            }
            
            const json = await res.json().catch(() => ({}));
            if (json.status !== 'ok') {
                if (Birkly.debug) console.debug('Birkly: Template function call error:', name, json.message);
                Birkly.templateFunctions.cache.set(key, null);
                return null;
            }

            const result = json?.data?.result;
            Birkly.templateFunctions.cache.set(key, result);
            return result;
        } catch (e) {
            // Silently fail - template functions are optional
            if (Birkly.debug) console.debug('Birkly: Template function call exception:', name, e);
            Birkly.templateFunctions.cache.set(key, null);
            return null;
        }
    }

    function parseQuotedArgs(raw) {
        const args = [];
        const s = String(raw || '').trim();
        let i = 0;
        while (i < s.length) {
            while (i < s.length && /\s/.test(s[i])) i++;
            if (i >= s.length) break;
            const ch = s[i];
            if (ch === '\'' || ch === '"') {
                const quote = ch;
                i++;
                let buf = '';
                while (i < s.length && s[i] !== quote) {
                    buf += s[i];
                    i++;
                }
                if (i < s.length && s[i] === quote) i++;
                args.push(buf);
                continue;
            }
            let buf = '';
            while (i < s.length && !/\s/.test(s[i])) {
                buf += s[i];
                i++;
            }
            if (buf) args.push(buf);
        }
        return args;
    }

    function simpleHash(str) {
        let h = 0;
        for (let i = 0; i < str.length; i++) {
            h = (h * 31 + str.charCodeAt(i)) >>> 0;
        }
        return h.toString(16);
    }

    async function hydrateTemplateFunctionsIntoData(data, templateString = null) {
        await loadTemplateFunctionsMeta();
        // Check both the provided template string and document.body.innerHTML
        const html = templateString || (document.body ? (document.body.innerHTML || '') : '');
        
        // Hydrate only template functions that are registered (by plugins) and used in the template.
        if (Birkly.templateFunctions.zeroArg.size > 0) {
            const namesToFetch = [];
            for (const name of Birkly.templateFunctions.zeroArg) {
                if ((html.includes('{' + name) || html.includes('{if ' + name)) && !(name in data)) {
                    namesToFetch.push(name);
                }
            }
            await Promise.all(namesToFetch.map(async (name) => {
                try {
                    const result = await callTemplateFunction(name, []);
                    data[name] = result !== null && result !== undefined ? result : false;
                    Birkly.log('Birkly: Hydrated template function:', name, '=', data[name]);
                } catch (e) {
                    data[name] = false;
                    if (Birkly.debug) Birkly.warn('Birkly: Failed to call template function:', name, e);
                }
            }));
        }
        
        return data;
    }

    async function preprocessTemplateFunctionCalls(template, data) {
        await loadTemplateFunctionsMeta();
        if (Birkly.templateFunctions.names.size === 0) return template;

        let out = template;

        const tfPending = [];
        const scheduleTemplateCall = (funcName, args, key, nullValue = false) => {
            if (key in data) return;
            tfPending.push(
                callTemplateFunction(funcName, args)
                    .then((result) => {
                        data[key] = result !== null && result !== undefined ? result : nullValue;
                    })
                    .catch(() => { data[key] = nullValue; })
            );
        };

        // Replace {if func ...} with {if __tf_x}
        const ifRegex = /\{if\s+([^}]+)\}/g;
        const ifMatches = Array.from(out.matchAll(ifRegex));
        for (const m of ifMatches) {
            const rawCond = (m[1] || '').trim();
            if (!rawCond) continue;
            const funcName = rawCond.split(/\s+/)[0];
            if (!Birkly.templateFunctions.names.has(funcName)) continue;

            const rawArgs = rawCond.slice(funcName.length).trim();
            const args = rawArgs ? parseQuotedArgs(rawArgs) : [];

            // If this is a zero-arg template function and it's already hydrated into `data`,
            // don't create a hashed __tf_ key (avoids duplicate calls and keeps truthiness stable).
            if (args.length === 0 && (funcName in data)) {
                out = out.replace(m[0], `{if ${funcName}}`);
                continue;
            }
            const keyStr = funcName + '|' + JSON.stringify(args);
            const key = '__tf_' + funcName + '_' + simpleHash(keyStr);

            if (!(key in data)) {
                scheduleTemplateCall(funcName, args, key, false);
            }

            out = out.replace(m[0], `{if ${key}}`);
        }

        await Promise.all(tfPending);
        tfPending.length = 0;

        // Handle {set variable = {template_function args}} directives
        // Process these BEFORE other template function calls so variables are available
        const setRegex = /\{set\s+([a-zA-Z0-9_]+)\s*=\s*\{([^}]+)\}\}/g;
        const setMatches = Array.from(out.matchAll(setRegex));
        for (const m of setMatches) {
            const varName = m[1];
            const funcCall = m[2].trim();
            const funcMatch = funcCall.match(/^([a-zA-Z0-9_]+)\s+(.+)$/);
            
            if (funcMatch && Birkly.templateFunctions.names.has(funcMatch[1])) {
                const funcName = funcMatch[1];
                const args = parseQuotedArgs(funcMatch[2]);
                
                scheduleTemplateCall(funcName, args, varName, {});
                
                // Remove the {set} directive
                out = out.replace(m[0], '');
            }
        }

        await Promise.all(tfPending);
        tfPending.length = 0;

        // Replace direct template function calls like {function_name 'arg1' 'arg2'} with { __tf_x }
        const controlStarts = new Set(['if', 'else', 'endif', 'for', 'endfor', 'foreach', 'endforeach', 'from', 'get', 'has', 'exist']);
        const callRegex = /\{([a-zA-Z0-9_]+)\s+([^}]+)\}/g;
        const callMatches = Array.from(out.matchAll(callRegex));
        for (const m of callMatches) {
            const funcName = m[1];
            if (!funcName || controlStarts.has(funcName)) continue;
            if (!Birkly.templateFunctions.names.has(funcName)) continue;

            const args = parseQuotedArgs(m[2] || '');

            if (args.length === 0 && (funcName in data)) {
                out = out.replace(m[0], `{ ${funcName} }`);
                continue;
            }
            const keyStr = funcName + '|' + JSON.stringify(args);
            const key = '__tf_' + funcName + '_' + simpleHash(keyStr);

            if (!(key in data)) {
                scheduleTemplateCall(funcName, args, key, null);
            }

            out = out.replace(m[0], `{ ${key} }`);
        }

        await Promise.all(tfPending);

        return out;
    }
    
    /**
     * Detect which API endpoint to use
     */
    async function detectApiEndpoint() {
        if (Birkly.isLocalhost) {
            // Localhost always uses public API
            Birkly.apiEndpoint = Birkly.cmsUrl + Birkly.publicApi;
            return Birkly.apiEndpoint;
        }
        
        // For production, try secure API first
        // If it fails with domain not licensed, show error
        Birkly.apiEndpoint = Birkly.cmsUrl + Birkly.secureApi;
        return Birkly.apiEndpoint;
    }
    
    /**
     * Get CSRF token from CMS
     */
    async function getCSRFToken() {
        if (Birkly.csrfToken) {
            return Birkly.csrfToken;
        }
        
        try {
            const response = await fetch(Birkly.cmsUrl + '/api/csrf.php', { credentials: 'include' });
            const data = await response.json();
            
            if (data.status === 'ok' && data.data.csrf_token) {
                Birkly.csrfToken = data.data.csrf_token;
                return Birkly.csrfToken;
            }
        } catch (error) {
            Birkly.warn('Birkly: Could not fetch CSRF token:', error);
        }
        
        return null;
    }
    
    /**
     * Fetch content from CMS API
     */
    async function fetchContent(action, params = {}) {
        if (!Birkly.apiEndpoint) {
            await detectApiEndpoint();
        }

        if (Birkly.pageBundle.loaded && action === 'list_entries' && params.collection) {
            const key = params.collection;
            if (Birkly.pageBundle.keys.has(key) && !Birkly.pageBundle.failed.has(key)) {
                const bundled = Birkly.pageBundle.data[key];
                if (Array.isArray(bundled) && bundled.length > 0) {
                    return { status: 'ok', data: { entries: bundled } };
                }
            }
        }
        if (Birkly.pageBundle.loaded && action === 'get_entry' && params.collection && params.slug) {
            const bundleKey = '__entry__:' + params.collection + ':' + params.slug;
            if (Birkly.pageBundle.keys.has(bundleKey) && !Birkly.pageBundle.failed.has(bundleKey)) {
                const direct = Birkly.pageBundle.data[bundleKey];
                if (direct) {
                    return { status: 'ok', data: { entry: direct } };
                }
            }
        }
        
        // Add language parameter for list_entries and get_entry actions
        if ((action === 'list_entries' || action === 'get_entry') && Birkly.currentLanguage) {
            params.language = Birkly.currentLanguage;
        }
        
        const queryParams = new URLSearchParams({
            action: action,
            ...params
        });
        
        const url = `${Birkly.apiEndpoint}?${queryParams.toString()}`;
        
        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include'
            });
            
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `API request failed: ${response.status}`);
            }
            
            const data = await response.json();
            
            // Handle domain not licensed error
            if (data.status === 'error' && data.code === 'DOMAIN_NOT_LICENSED') {
                showDomainError(data.message);
                return null;
            }
            
            return data;
        } catch (error) {
            console.error('Birkly API Error:', error);
            
            // If secure API fails and not localhost, try public API as fallback
            if (!Birkly.isLocalhost && Birkly.apiEndpoint.includes('secure_public')) {
                Birkly.warn('Birkly: Secure API failed, trying public API...');
                Birkly.apiEndpoint = Birkly.cmsUrl + Birkly.publicApi;
                return fetchContent(action, params);
            }
            
            return null;
        }
    }
    
    /**
     * Submit data to CMS (for forms)
     */
    async function submitData(action, data = {}) {
        if (!Birkly.apiEndpoint) {
            await detectApiEndpoint();
        }
        
        // Use secure forms API for writes
        const apiUrl = Birkly.cmsUrl + '/api/secure_forms.php';
        
        // Get CSRF token
        const csrfToken = await getCSRFToken();
        
        const payload = {
            action: action,
            ...data,
            _csrf: csrfToken
        };
        
        try {
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify(payload)
            });
            
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `Submission failed: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Birkly Submit Error:', error);
            return {
                status: 'error',
                message: error.message
            };
        }
    }
    
    /**
     * Show error message when domain is not registered
     */
    function showDomainError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #f44336;
            color: white;
            padding: 16px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 10000;
            max-width: 400px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
        `;
        errorDiv.innerHTML = `
            <strong>Birkly Domain Error</strong><br>
            ${message}<br>
            <small style="opacity: 0.9;">Please register your domain in the Birkly admin panel.</small>
        `;
        document.body.appendChild(errorDiv);
        
        // Remove after 10 seconds
        setTimeout(() => {
            errorDiv.remove();
        }, 10000);
    }
    
    /**
     * Get site element by key
     */
    async function getSiteElement(key) {
        try {
            const data = await fetchContent('list_entries', { collection: 'site_elements' });
            if (data && data.status === 'ok' && data.data.entries) {
                const element = data.data.entries
                    .map(flattenLocalizedEntry)
                    .find(entry => (entry.key || entry.slug || entry.id) === key);
                return element || null;
            }
        } catch (error) {
            Birkly.warn('Birkly: Error fetching site element:', error);
        }
        return null;
    }
    
    /**
     * Get all site elements
     */
    async function getAllSiteElements() {
        try {
            Birkly.log('Birkly: Fetching site elements with language:', Birkly.currentLanguage);
            const data = await fetchContent('list_entries', { 
                collection: 'site_elements',
                language: Birkly.currentLanguage 
            });
            if (data && data.status === 'ok' && data.data.entries) {
                Birkly.log('Birkly: Fetched', data.data.entries.length, 'site elements');
                const elements = {};
                data.data.entries.forEach(entry => {
                    const normalized = flattenLocalizedEntry(entry);
                    const key = normalized.key || normalized.slug || normalized.id;
                    if (key) {
                        elements[key] = normalized;
                        Birkly.log('Birkly: Added site element:', key, 'with content:', normalized.content ? 'yes' : 'no', 'content type:', typeof normalized.content, 'is array:', Array.isArray(normalized.content));
                        if (normalized.content && Array.isArray(normalized.content) && normalized.content.length > 0) {
                            Birkly.log('Birkly: First content item:', normalized.content[0]);
                        }
                    } else {
                        Birkly.warn('Birkly: Site element missing key:', normalized);
                    }
                });
                Birkly.log('Birkly: Site elements object:', Object.keys(elements));
                return elements;
            } else {
                Birkly.warn('Birkly: Failed to fetch site elements:', data);
            }
        } catch (error) {
            Birkly.warn('Birkly: Error fetching site elements:', error);
        }
        return {};
    }
    
    async function ensureCollectionsInData(data, collections) {
        if (!data || !Array.isArray(collections) || !collections.length) {
            return data;
        }
        mergePageBundleIntoData(data);
        await Promise.all(collections.map(async (collectionName) => {
            if (!collectionName || collectionName === 'site_elements') {
                return;
            }
            const existing = data[collectionName];
            if (Array.isArray(existing) && existing.length > 0) {
                return;
            }
            Birkly.log('Birkly: ensureCollectionsInData fetching:', collectionName);
            const apiData = await fetchContent('list_entries', { collection: collectionName });
            if (apiData && apiData.status === 'ok' && apiData.data.entries) {
                data[collectionName] = apiData.data.entries.map(flattenLocalizedEntry);
                Birkly.log('Birkly: ensureCollectionsInData loaded', data[collectionName].length, 'entries for', collectionName);
            } else if (!Array.isArray(data[collectionName])) {
                data[collectionName] = [];
            }
        }));
        return data;
    }


    /* BIRKLY_RENDER_V3_INLINE */
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
        const root = doc.body || doc.documentElement;
        if (!root || !root.querySelectorAll) {
            return regions;
        }

        const candidates = [];
        root.querySelectorAll('*').forEach((el) => {
            const tag = String(el.tagName || '').toUpperCase();
            if (tag === 'SCRIPT' || tag === 'STYLE') return;
            const template = el.innerHTML || '';
            if (hasTemplateSyntax(template)) {
                candidates.push(el);
            }
        });

        const outermost = candidates.filter((el) =>
            !candidates.some((other) => other !== el && other.contains(el))
        );

        outermost.forEach((el) => {
            regions.push({ element: el, template: el.innerHTML || '' });
        });

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

    const BirklyRoot = root.Birkly || (root.Birkly = {});
    BirklyRoot.render = engine;
})(typeof globalThis !== 'undefined' ? globalThis : (typeof window !== 'undefined' ? window : {}));

    /* END BIRKLY_RENDER_V3_INLINE */

    if (typeof globalThis !== 'undefined' && globalThis.Birkly && globalThis.Birkly.render) {
        Birkly.render = globalThis.Birkly.render;
    }

    let birklyRevealObserver = null;

    /**
     * Marsk templates hide .reveal / .reveal-stagger content until .is-visible is added.
     * Birkly injects CMS HTML after page load, so Marsk's one-time observer often misses
     * below-fold sections. Re-run reveal setup after every template render.
     */
    function setupPostRenderReveals() {
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const revealSelector = '.reveal:not(.is-visible), .reveal-stagger:not(.is-visible)';

        function revealIfInView(el) {
            const rect = el.getBoundingClientRect();
            const margin = 160;
            if (rect.top < window.innerHeight + margin && rect.bottom > -margin) {
                el.classList.add('is-visible');
                if (birklyRevealObserver) {
                    birklyRevealObserver.unobserve(el);
                }
                return true;
            }
            return false;
        }

        if (prefersReducedMotion) {
            document.querySelectorAll('.reveal, .reveal-stagger').forEach((el) => {
                el.classList.add('is-visible');
            });
            return;
        }

        const pending = document.querySelectorAll(revealSelector);
        if (!pending.length) {
            return;
        }

        if ('IntersectionObserver' in window) {
            if (!birklyRevealObserver) {
                birklyRevealObserver = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            birklyRevealObserver.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.01, rootMargin: '160px 0px 160px 0px' });
            }

            pending.forEach((el) => {
                if (!revealIfInView(el)) {
                    birklyRevealObserver.observe(el);
                }
            });
        } else {
            pending.forEach((el) => el.classList.add('is-visible'));
        }

        if (!window.__birklyRevealScrollBound) {
            window.__birklyRevealScrollBound = true;
            let scrollTick = false;
            window.addEventListener('scroll', () => {
                if (scrollTick) {
                    return;
                }
                scrollTick = true;
                requestAnimationFrame(() => {
                    document.querySelectorAll(revealSelector).forEach(revealIfInView);
                    scrollTick = false;
                });
            }, { passive: true });
        }
    }

    function normalizeSiteElementsMap(raw) {
        if (!raw) {
            return {};
        }
        if (!Array.isArray(raw)) {
            return raw;
        }
        const map = {};
        raw.forEach((entry) => {
            const normalized = flattenLocalizedEntry(entry);
            const key = normalized.key || normalized.slug || normalized.id;
            if (key) {
                map[key] = normalized;
            }
        });
        return map;
    }

    function normalizeRenderData(data) {
        if (!data || typeof data !== 'object') {
            return data;
        }
        if (data.site_elements) {
            data.site_elements = normalizeSiteElementsMap(data.site_elements);
        }
        Object.keys(data).forEach((key) => {
            if (key === 'site_elements' || key === 'query' || key === 'current') {
                return;
            }
            const value = data[key];
            if (Array.isArray(value)) {
                data[key] = value.map((row) => flattenLocalizedEntry(row));
            } else if (key.indexOf('__entry__:') === 0 && value && typeof value === 'object') {
                data[key] = flattenLocalizedEntry(value);
            }
        });
        return data;
    }

    async function processTemplatesV3() {
        const siteElements = await getAllSiteElements();
        urlParams = new URLSearchParams(window.location.search);
        const slug = urlParams.get('slug');
        let collection = urlParams.get('collection');
        const query = {};
        for (const [k, v] of urlParams.entries()) query[k] = v;

        if (!collection) {
            collection = detectCollectionFromTemplate();
        }
        if (window.location.pathname.includes('/project/')) {
            if (collection === 'project') collection = 'blog';
            if (!collection) collection = 'blog';
        }
        if (slug && !collection) {
            collection = 'blog';
        }

        const regions = Birkly.render.discoverPageRegions(document);
        if (!regions.length) {
            console.log('[Birkly] v3: no template regions found');
            return;
        }

        const combinedHtml = regions.map((r) => r.template).join('\n');
        const bundleReqs = Birkly.render.collectBundleRequests(combinedHtml);
        if (bundleReqs.length) {
            await loadPageBundle(bundleReqs);
        } else if (typeof collectBundleRequests === 'function') {
            const fallbackReqs = collectBundleRequests();
            if (fallbackReqs.length) await loadPageBundle(fallbackReqs);
        }

        const data = {
            current_year: new Date().getFullYear(),
            site_elements: siteElements,
            query: query,
            current: { language: Birkly.currentLanguage || 'en' }
        };
        mergePageBundleIntoData(data);
        normalizeRenderData(data);

        if (slug && collection) {
            try {
                const entry = await getEntryBySlug(collection, slug);
                if (entry) {
                    const flat = flattenLocalizedEntry(entry);
                    Object.assign(data, flat, { entry: flat });
                    data['__entry__:' + collection + ':' + slug] = flat;
                }
            } catch (e) {
                Birkly.warn('Birkly v3: could not load entry', collection, slug, e);
            }
        }

        const collections = [];
        bundleReqs.forEach((req) => {
            if (req.type === 'list_entries' && req.collection && collections.indexOf(req.collection) === -1) {
                collections.push(req.collection);
            }
        });
        await ensureCollectionsInData(data, collections);

        for (const region of regions) {
            const rendered = Birkly.render.render(region.template, data);
            if (region.isText) {
                region.element.textContent = rendered;
            } else {
                region.element.innerHTML = rendered;
            }
        }

        if (data.title) {
            const titleEl = document.querySelector('title');
            if (titleEl && /\{/.test(titleEl.textContent || '')) {
                titleEl.textContent = Birkly.render.render(titleEl.textContent, data);
            }
            if (/\{/.test(document.title)) {
                document.title = Birkly.render.render(document.title, data);
            }
        }

        setupPostRenderReveals();
        try {
            document.dispatchEvent(new CustomEvent('birkly:reveals-refresh'));
        } catch (e) { /* ignore */ }

        console.log('[Birkly] v3: rendered', regions.length, 'regions');
    }

    async function processTemplates() {
        await processTemplatesV3();
    }

    
    // Store urlParams for use in detectCollectionFromTemplate
    let urlParams = null;
    
    /**
     * Detect collection name from template or URL
     * IMPORTANT: Ignore /project/ path - that's just the website folder, not a collection
     */
    function detectCollectionFromTemplate() {
        // Try to extract from template
        const bodyText = document.body.innerHTML;
        const match = bodyText.match(/\{for\s+(?:each\s+)?[^\s]+\s+in\s+['"]([^'"]+)['"]\}/);
        if (match && match[1] !== 'project') {
            return match[1];
        }
        
        // Try to extract from URL path (but ignore project prefix)
        const path = window.location.pathname;
        let cleanPath = path.replace(/^\/project/, '');
        
        const pathMatch = cleanPath.match(/\/([^\/]+)\/(?:post|entry|article)/i);
        if (pathMatch && pathMatch[1] !== 'project') {
            return pathMatch[1];
        }
        
        // Check if we're on a post/entry/article page
        const filename = cleanPath.split('/').pop() || '';
        if (filename.includes('post') || filename.includes('entry') || filename.includes('article')) {
            // Default to 'blog' for post pages
            return 'blog';
        }
        
        // Common defaults based on path (but exclude project)
        if (cleanPath.includes('/blog') || cleanPath.includes('blog')) return 'blog';
        if (cleanPath.includes('/post')) return 'blog';
        
        // If we're on a project page, default to 'blog' collection
        if (path.includes('/project/')) {
            return 'blog';
        }
        
        // Last resort: if we're on a page with slug parameter, default to 'blog'
        if (urlParams && urlParams.get('slug')) {
            return 'blog';
        }
        
        return null;
    }
    
    /**
     * Get entry by slug (helper function)
     */
    // Helper: flatten localized entry (e.g. { en: {title,...}, de: {...} })
    function flattenLocalizedEntry(entry) {
        if (!entry || typeof entry !== 'object') return entry;
        const lang = Birkly.currentLanguage || 'en';
        const localized = entry[lang] || entry.en;
        if (localized && typeof localized === 'object') {
            return { ...entry, ...localized };
        }
        return entry;
    }

    async function getEntryBySlug(collection, slug) {
        try {
            Birkly.log('Birkly: Fetching entry by slug', { collection, slug });
            const data = await fetchContent('get_entry', { collection: collection, slug: slug });

            if (!data) {
                Birkly.warn('Birkly: No data returned from API');
                return null;
            }

            if (data.status !== 'ok') {
                Birkly.warn('Birkly: API returned error:', data.message || data.status);
                return null;
            }

            const entry = data.data && data.data.entry;
            if (entry) {
                Birkly.log('Birkly: Found entry:', entry.title || entry.id);
                return flattenLocalizedEntry(entry);
            }

            Birkly.warn('Birkly: Entry not found', { collection, slug });
        } catch (error) {
            console.error('Birkly: Error fetching entry by slug:', error);
            console.error('Birkly: Error stack:', error.stack);
        }
        return null;
    }

    async function fetchSubEntries(parentCollection, parentEntryId, subCollection) {
        const url = new URL(Birkly.cmsUrl + '/api/sub_entries.php');
        url.searchParams.set('action', 'list');
        url.searchParams.set('parent_collection', parentCollection);
        url.searchParams.set('parent_entry_id', parentEntryId);
        url.searchParams.set('sub_collection', subCollection);

        Birkly.log('Birkly: fetchSubEntries called with:', { parentCollection, parentEntryId, subCollection, url: url.toString() });
        const res = await fetch(url.toString(), { credentials: 'include' });
        const json = await res.json().catch((e) => {
            console.error('Birkly: Error parsing JSON response:', e);
            return {};
        });
        Birkly.log('Birkly: fetchSubEntries response:', { status: res.status, ok: res.ok, json });
        if (!res.ok || json.status !== 'ok') {
            Birkly.warn('Birkly: fetchSubEntries failed:', json);
            return [];
        }
        const entries = json?.data?.entries || [];
        Birkly.log('Birkly: fetchSubEntries returning', entries.length, 'entries');
        return entries;
    }

    // Generic prefetch mechanism (NO project-specific logic):
    // Templates can request extra data via HTML attributes, e.g.
    //   <meta data-birkly-prefetch="sub_entries" data-birkly-into="comments"
    //         data-parent-collection="social_posts" data-parent-entry-id="{entry.id}" data-sub-collection="comments">
    async function performPrefetches(data) {
        const nodes = document.querySelectorAll('[data-birkly-prefetch]');
        if (!nodes || nodes.length === 0) return data;

        const resolve = (s) => {
            if (s == null) return '';
            const str = String(s);
            return str.replace(/\{([^}]+)\}/g, (_, expr) => {
                const key = String(expr || '').trim().replace(/\s+/g, ' ');
                // support "entry.id" and also " entry.id "
                const normalized = key.replace(/^(\w+)\s*\.\s*/,'$1.').replace(/\s*\.\s*/g,'.');
                const v = getNestedValue(data, normalized);
                return v == null ? '' : String(v);
            });
        };

        const tasks = Array.from(nodes).map(async (el) => {
            const type = el.getAttribute('data-birkly-prefetch');
            const into = el.getAttribute('data-birkly-into');
            if (!type || !into) return;

            if (into in data && data[into] !== undefined && data[into] !== null) {
                return;
            }
            if (Birkly.pageBundle.loaded && Birkly.pageBundle.data[into] !== undefined) {
                data[into] = Birkly.pageBundle.data[into];
                return;
            }

            try {
                if (type === 'sub_entries') {
                    const parentCollection = resolve(el.getAttribute('data-parent-collection'));
                    const parentEntryId = resolve(el.getAttribute('data-parent-entry-id'));
                    const subCollection = resolve(el.getAttribute('data-sub-collection'));
                    if (!parentCollection || !parentEntryId || !subCollection) {
                        Birkly.warn('Birkly: Prefetch sub_entries skipped - missing params:', { parentCollection, parentEntryId, subCollection });
                        return;
                    }
                    Birkly.log('Birkly: Prefetching sub_entries:', { parentCollection, parentEntryId, subCollection, into });
                    const subEntries = await fetchSubEntries(parentCollection, parentEntryId, subCollection);
                    data[into] = Array.isArray(subEntries) ? subEntries : [];
                    Birkly.log('Birkly: Prefetched', data[into].length, 'sub-entries into', into);
                } else if (type === 'template_function') {
                    const name = resolve(el.getAttribute('data-name'));
                    const argsJson = resolve(el.getAttribute('data-args')) || '[]';
                    let args = [];
                    try { args = JSON.parse(argsJson); } catch (_) { args = []; }
                    if (!name) return;
                    try {
                        const result = await callTemplateFunction(name, Array.isArray(args) ? args : []);
                        data[into] = result !== null && result !== undefined ? result : [];
                    } catch (e) {
                        data[into] = [];
                        if (Birkly.debug) Birkly.warn('Birkly: Prefetch template function failed:', name, e);
                    }
                } else if (type === 'entries') {
                    const collection = resolve(el.getAttribute('data-collection'));
                    if (!collection) return;
                    const apiData = await fetchContent('list_entries', { collection });
                    let entries = (apiData && apiData.status === 'ok' && apiData.data.entries) ? apiData.data.entries.map(flattenLocalizedEntry) : [];

                    const filterField = resolve(el.getAttribute('data-filter-field'));
                    const filterValue = resolve(el.getAttribute('data-filter-value'));
                    if (filterField && filterValue) {
                        entries = entries.filter(e => String(e?.[filterField] ?? '') === String(filterValue));
                    }
                    data[into] = entries;
                }
            } catch (e) {
                // Ignore prefetch failures; template should still render
            }
        });

        await Promise.all(tasks);
        return data;
    }
    
    /**
     * Fetch collection schema and API key
     */
    async function fetchCollectionInfo(collectionSlug) {
        try {
            // Try to get collection info from a new API endpoint
            const response = await fetch(Birkly.cmsUrl + '/api/public.php?action=get_collection_info&collection=' + encodeURIComponent(collectionSlug));
            const data = await response.json();
            if (data && data.status === 'ok' && data.data) {
                return data.data;
            }
        } catch (error) {
            Birkly.warn('Birkly: Could not fetch collection info:', error);
        }
        return null;
    }
    
    /**
     * Render public form based on collection
     */
    async function renderPublicForm(collectionSlug) {
        const collectionInfo = await fetchCollectionInfo(collectionSlug);
        // Public get_collection_info no longer returns api_key; we only need schema
        // to render the form. Submissions use /api/public_submit.php with the
        // collection slug and CSRF, not a client-visible API key.
        if (!collectionInfo || !collectionInfo.schema) {
            return '<p style="color: red;">Error: Collection not found or Public API not enabled.</p>';
        }
        
        let formHtml = '<form class="haru-form" data-birkly-collection="' + escapeHtml(collectionSlug) + '" data-birkly-url="/api/public_submit.php">\n';
        
        const fields = collectionInfo.schema.fields || [];
        for (const field of fields) {
            const fieldName = field.name || '';
            const fieldLabel = field.label || fieldName;
            const fieldType = field.type || 'text';
            const required = field.required || false;
            
            // Skip status field (handled automatically)
            if (fieldName === 'status') continue;
            
            formHtml += '  <div class="form-group">\n';
            formHtml += '    <label for="' + escapeHtml(fieldName) + '">' + escapeHtml(fieldLabel);
            if (required) {
                formHtml += ' <span class="required">*</span>';
            }
            formHtml += '</label>\n';
            
            if (fieldType === 'textarea' || fieldType === 'editor') {
                formHtml += '    <textarea name="' + escapeHtml(fieldName) + '" id="' + escapeHtml(fieldName) + '" class="form-control"';
                if (required) formHtml += ' required';
                formHtml += ' rows="5"></textarea>\n';
            } else if (fieldType === 'select') {
                formHtml += '    <select name="' + escapeHtml(fieldName) + '" id="' + escapeHtml(fieldName) + '" class="form-control"';
                if (required) formHtml += ' required';
                formHtml += '>\n';
                formHtml += '      <option value="">Select...</option>\n';
                const options = field.options || [];
                for (const option of options) {
                    const optLabel = option.label || option.value || '';
                    const optValue = option.value || optLabel;
                    formHtml += '      <option value="' + escapeHtml(optValue) + '">' + escapeHtml(optLabel) + '</option>\n';
                }
                formHtml += '    </select>\n';
            } else if (fieldType === 'boolean') {
                formHtml += '    <label class="checkbox-label">\n';
                formHtml += '      <input type="checkbox" name="' + escapeHtml(fieldName) + '" id="' + escapeHtml(fieldName) + '" value="1">\n';
                formHtml += '      <span>' + escapeHtml(fieldLabel) + '</span>\n';
                formHtml += '    </label>\n';
            } else {
                const inputType = (fieldType === 'email') ? 'email' : (fieldType === 'number') ? 'number' : 'text';
                formHtml += '    <input type="' + inputType + '" name="' + escapeHtml(fieldName) + '" id="' + escapeHtml(fieldName) + '" class="form-control"';
                if (required) formHtml += ' required';
                formHtml += '>\n';
            }
            formHtml += '  </div>\n';
        }
        
        // Add hidden collection field (required for /api/public_submit.php)
        formHtml += '  <input type="hidden" name="collection" value="' + escapeHtml(collectionSlug) + '">\n';
        
        // Add honeypot field
        formHtml += '  <div style="display: none;">\n';
        formHtml += '    <input type="text" name="_honeypot" tabindex="-1" autocomplete="off">\n';
        formHtml += '  </div>\n';
        
        // Add submit button
        formHtml += '  <button type="submit" class="btn btn-primary">Submit</button>\n';
        formHtml += '</form>\n';
        
        return formHtml;
    }
    
    /**
     * Setup auto-form submission for Birkly forms
     */
    function setupFormSubmission() {
        const forms = document.querySelectorAll('form[data-birkly-collection]');
        for (const form of forms) {
            if (form.dataset.haruFormInitialized) continue;
            form.dataset.haruFormInitialized = 'true';
            
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const form = this;
                const collectionSlug = form.dataset.birklyCollection;
                const apiKey = form.dataset.haruApiKey;
                
                if (!collectionSlug || !apiKey) {
                    alert('Error: Form configuration missing. Please check the form setup.');
                    return;
                }
                
                const submitBtn = form.querySelector('button[type=submit]');
                const originalText = submitBtn ? submitBtn.textContent : 'Submit';
                
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Sending...';
                }
                
                const formData = new FormData(form);
                const data = {};
                for (const [key, value] of formData.entries()) {
                    data[key] = value;
                }
                
                try {
                    const apiUrl = Birkly.cmsUrl + '/api/public/collections.php?collection=' + encodeURIComponent(collectionSlug);
                    const response = await fetch(apiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-API-Key': apiKey
                        },
                        body: JSON.stringify(data)
                    });
                    
                    const result = await response.json();
                    
                    if (result.status === 'ok') {
                        form.reset();
                        alert('Thank you! Your message has been received. We\'ll get back to you soon.');
                    } else {
                        alert('Error: ' + (result.message || 'Something went wrong'));
                    }
                } catch (error) {
                    console.error('Birkly: Form submission error:', error);
                    alert('Error submitting form. Please try again.');
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                    }
                }
            });
        }
    }
    
    /**
     * Initialize Birkly
     */
    /**
     * Create language selector UI
     */
    function createLanguageSelector() {
        // Prefer template-defined selectors: any <select data-birkly-language-select>
        const selects = document.querySelectorAll('select[data-birkly-language-select]');
        if (selects.length) {
            const current = Birkly.currentLanguage || 'en';
            selects.forEach(sel => {
                try {
                    sel.value = current;
                } catch (_) {
                    // ignore invalid values
                }
                sel.addEventListener('change', function () {
                    const newLang = this.value || 'en';
                    Birkly.currentLanguage = newLang;
                    localStorage.setItem('birkly_language', newLang);
                    // Update URL with language parameter
                    const url = new URL(window.location);
                    url.searchParams.set('lang', newLang);
                    window.location.href = url.toString();
                });
            });
            return;
        }

        // Sites opt in to the floating widget explicitly; otherwise only template hooks are used.
        const clientScript = document.querySelector('script[src*="birkly-client"]');
        if (clientScript && clientScript.getAttribute('data-birkly-no-floating-language-selector') === 'true') {
            return;
        }
        if (!clientScript || clientScript.getAttribute('data-birkly-floating-language-selector') !== 'true') {
            return;
        }

        if (document.getElementById('birkly-language-selector')) {
            return;
        }

        const selector = document.createElement('div');
        selector.id = 'birkly-language-selector';
        selector.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; background: white; border: 1px solid #ddd; border-radius: 4px; padding: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);';

        const select = document.createElement('select');
        select.style.cssText = 'border: none; outline: none; font-size: 14px; padding: 4px 8px; cursor: pointer;';
        select.innerHTML = `
            <option value="en" ${Birkly.currentLanguage === 'en' ? 'selected' : ''}>English</option>
            <option value="de" ${Birkly.currentLanguage === 'de' ? 'selected' : ''}>Deutsch</option>
        `;

        select.addEventListener('change', function() {
            const newLang = this.value;
            Birkly.currentLanguage = newLang;
            localStorage.setItem('birkly_language', newLang);

            const url = new URL(window.location);
            url.searchParams.set('lang', newLang);
            window.location.href = url.toString();
        });

        selector.appendChild(select);
        document.body.appendChild(selector);
    }
    
    async function init() {
        if (Birkly.initialized) return;
        
        Birkly.initialized = true;
        
        try {
        Birkly.log('Birkly: Initializing...');
        
        // Create language selector
        createLanguageSelector();
        
        // Detect API endpoint first
        await detectApiEndpoint();
        Birkly.log('Birkly: API endpoint detected:', Birkly.apiEndpoint);

        const bundleRequests = collectBundleRequests();
        const snapshotLoaded = bundleRequests.length > 0 && Birkly.bundle.shouldProbeSnapshot()
            ? await tryLoadPageSnapshot() : false;
        if (!snapshotLoaded && bundleRequests.length > 0) {
            await loadPageBundle(bundleRequests);
        }

        // Bind generic action forms ASAP (templates can take time to process)
        bindActionForms();
        
        // Process templates immediately (don't wait for DOMContentLoaded)
        console.log('[Birkly] init: processing templates...');
        Birkly.log('Birkly: Processing templates...');
        await processTemplates();
        console.log('[Birkly] init: templates processed');
        Birkly.log('Birkly: Templates processed');
        try {
            document.dispatchEvent(new CustomEvent('birkly:templates-processed'));
        } catch (e) { /* ignore */ }
        } catch (err) {
            console.error('[Birkly] init error:', err);
            Birkly.error('Birkly: init error:', err);
        }

        // Templates can replace DOM nodes (including forms); rebind after processing
        if (typeof bindActionForms.refresh === 'function') {
            bindActionForms.refresh();
        }
        
        // Process {entry for 'collection'} blocks (wait a bit for DOM to update)
        // Use setTimeout to ensure DOM is fully updated after template processing
        setTimeout(() => {
            let entryBlocks = document.querySelectorAll('.birkly-entry-block');
            Birkly.log('Birkly: Found', entryBlocks.length, 'entry blocks to process');
            
            if (entryBlocks.length === 0) {
                if (Birkly.debug) Birkly.warn('Birkly: No entry blocks found. They may have been removed during template processing.');
                // Try to find them by data attribute instead
                const byDataAttr = document.querySelectorAll('[data-birkly-collection]');
                Birkly.log('Birkly: Found', byDataAttr.length, 'elements with data-birkly-collection attribute');
                for (const el of byDataAttr) {
                    if (el.dataset.birklyCollection && !el.classList.contains('birkly-entry-block')) {
                        Birkly.log('Birkly: Found element with collection but missing class:', el.id, el.dataset.birklyCollection);
                        // Add the class and process it
                        el.classList.add('birkly-entry-block');
                    }
                }
                // Re-query after adding classes
                entryBlocks = document.querySelectorAll('.birkly-entry-block');
                Birkly.log('Birkly: Now found', entryBlocks.length, 'entry blocks after adding classes');
                
                if (entryBlocks.length === 0) {
                    return;
                }
            }
            
            for (const block of entryBlocks) {
                const collectionSlug = block.dataset.birklyCollection;
                let formContent = block.dataset.birklyContent;
                Birkly.log('Birkly: Processing entry block for collection:', collectionSlug, 'Content length:', formContent ? formContent.length : 0);
                
                if (collectionSlug && formContent) {
                    // Decode HTML-encoded content
                    // The content is stored as HTML-encoded (e.g., &lt;div&gt; becomes <div>)
                    // We need to decode HTML entities properly
                    // Use a temporary element to decode HTML entities
                    const tempDiv = document.createElement('div');
                    // Decode HTML entities - this handles all HTML entities including numeric ones
                    tempDiv.innerHTML = formContent;
                    // Get the text content (which will have HTML entities decoded)
                    // But we need the actual HTML, so we need to decode entities manually
                    // Actually, if we set innerHTML and then get innerHTML, it should decode entities
                    // But we need to be careful - let's use a different approach
                    // Create a textarea to decode HTML entities properly
                    const textarea = document.createElement('textarea');
                    textarea.innerHTML = formContent;
                    formContent = textarea.value;
                    
                    // Clean up any malformed HTML patterns that might have been included
                    // The decoded content shows patterns like: class=" form-group">="" and <label=""
                    // These need to be fixed to proper HTML
                    
                    // Remove any trailing content that looks like attributes (data-birkly-preserved, etc.)
                    // This should have been removed during extraction, but clean it up here too
                    formContent = formContent.replace(/\s*"\s*data-birkly-preserved.*$/s, '');
                    formContent = formContent.replace(/\s*data-birkly-preserved.*$/s, '');
                    
                    // Fix malformed attribute patterns: class=" form-group">="" -> class="form-group">
                    formContent = formContent.replace(/(\w+)="\s+([^"]+)"\s*>\s*=""/g, '$1="$2">');
                    formContent = formContent.replace(/(\w+)="\s+([^"]+)"\s*>\s*="/g, '$1="$2">');
                    
                    // Fix malformed patterns like: >name="" -> >name
                    formContent = formContent.replace(/>([^<]+)=""/g, '>$1');
                    formContent = formContent.replace(/>([^<]+)="/g, '>$1');
                    
                    // Fix patterns like: <label="" for="title" -> <label for="title"
                    formContent = formContent.replace(/(<\w+)=""/g, '$1');
                    
                    // Fix patterns like: <span="" class=" -> <span class="
                    formContent = formContent.replace(/(<\w+)=""\s+(\w+)=""/g, '$1 $2=""');
                    formContent = formContent.replace(/(<\w+)=""\s+(\w+)=/g, '$1 $2=');
                    
                    // Remove any remaining malformed patterns
                    formContent = formContent.replace(/\s*="">""\s*/g, '">');
                    formContent = formContent.replace(/\s*="">"\s*/g, '">');
                    formContent = formContent.replace(/\s*="">\s*/g, '">');
                    formContent = formContent.replace(/\s*="\s*$/gm, '');
                    formContent = formContent.replace(/\s*=""\s*$/gm, '');
                    
                    // Also remove any remaining malformed quote patterns at the very end
                    formContent = formContent.replace(/\s*"\s*=\s*"\s*$/s, '');
                    formContent = formContent.replace(/\s*=\s*"\s*$/s, '');
                    
                    Birkly.log('Birkly: Decoded form content, length:', formContent.length);
                    Birkly.log('Birkly: First 200 chars:', formContent.substring(0, 200));
                    Birkly.log('Birkly: Last 100 chars:', formContent.substring(Math.max(0, formContent.length - 100)));
                    
                    fetchCollectionInfo(collectionSlug).then(collectionInfo => {
                        Birkly.log('Birkly: Fetched collection info for:', collectionSlug, collectionInfo ? 'OK' : 'FAILED', collectionInfo);
                        // After security hardening, the public get_collection_info endpoint
                        // no longer returns api_key – it only exposes schema + public metadata.
                        // For template-first forms we only need the schema to expand {field}
                        // placeholders; submissions go through the generic /api/public_submit.php
                        // endpoint and do NOT require a client-visible API key.
                        if (!collectionInfo || !collectionInfo.schema) {
                            block.innerHTML = '<p style="color: red;">Error: Collection not found or Public API not enabled.</p>';
                            return;
                        }
                        
                        // Create a data object with field names as variables
                        const fieldData = {};
                        const fields = collectionInfo.schema.fields || [];
                        for (const field of fields) {
                            // Make field names available as template variables
                            fieldData[field.name] = field.name; // Use field name as both key and initial value
                        }
                        
                        Birkly.log('Birkly: Rendering form content with', fields.length, 'fields');
                        
                        // Render the form content with field data
                        const renderedContent = Birkly.render.render(formContent, fieldData);
                        
                        Birkly.log('Birkly: Form content rendered, length:', renderedContent.length);
                        
                        // Wrap in form with auto-submission attributes.
                        // Note: api_key is no longer exposed via public API; submissions use
                        // /api/public_submit.php and the collection slug instead.
                        const formHtml = '<form class="haru-form" data-birkly-collection="' + escapeHtml(collectionSlug) + '" data-birkly-url="/api/public_submit.php">\n' +
                            '<input type="hidden" name="collection" value="' + escapeHtml(collectionSlug) + '">\n' +
                            renderedContent + '\n' +
                            '</form>';
                        
                        // Replace the block with the form
                        const parent = block.parentElement;
                        if (parent) {
                            // Create a temporary container to parse the form HTML
                            const tempDiv = document.createElement('div');
                            tempDiv.innerHTML = formHtml;
                            const formElement = tempDiv.firstElementChild;
                            
                            // Replace the block with the form element
                            parent.replaceChild(formElement, block);
                            Birkly.log('Birkly: Form HTML inserted via replaceChild, setting up submission');
                            
                            // Setup form submission after a brief delay to ensure DOM is updated
                            setTimeout(() => {
                                setupFormSubmission();
                            }, 50);
                        } else {
                            // Fallback to outerHTML if no parent
                            block.outerHTML = formHtml;
                            Birkly.log('Birkly: Form HTML inserted via outerHTML, setting up submission');
                            setTimeout(() => {
                                setupFormSubmission();
                            }, 50);
                        }
                    }).catch(error => {
                        console.error('Birkly: Error rendering entry form:', error);
                        block.innerHTML = '<p style="color: red;">Error loading form. Please try again later.</p>';
                    });
                } else {
                    Birkly.warn('Birkly: Entry block missing collection slug or form content:', { collectionSlug, hasContent: !!formContent });
                }
            }
        }, 100);
        
        // Process {public_form} placeholders after templates are rendered
        const formPlaceholders = document.querySelectorAll('.birkly-form-placeholder');
        for (const placeholder of formPlaceholders) {
            const collectionSlug = placeholder.dataset.birklyCollection;
            if (collectionSlug) {
                renderPublicForm(collectionSlug).then(formHtml => {
                    placeholder.outerHTML = formHtml;
                    setupFormSubmission();
                }).catch(error => {
                    console.error('Birkly: Error rendering form:', error);
                    placeholder.innerHTML = '<p style="color: red;">Error loading form. Please try again later.</p>';
                });
            }
        }
        
        // Setup form auto-submission after templates are processed
        setupFormSubmission();
        
        // Initialize analytics if tracking ID is present
        if (Birkly.trackingId) {
            initAnalytics();
        }
    }

    /**
     * Generic action form handler (template-first apps)
     *
     * HTML usage:
     *   <form data-birkly-url="/api/public_submit.php"> ... </form>
     *
     * - No custom JS needed on the website.
     * - Works on localhost/same-origin out of the box.
     * - For cross-domain (registered domains), we can evolve this to token-based auth later.
     */
    function bindActionForms() {
        // Avoid double-binding if init() is called multiple times
        if (bindActionForms._bound) return;
        bindActionForms._bound = true;
        Birkly.warn('Birkly: bindActionForms() bound');

        const bound = new WeakSet();

        async function handleSubmit(form, e) {
            if (form.dataset.haruSubmitting === '1') return;
            form.dataset.haruSubmitting = '1';
            Birkly.warn('Birkly: handleSubmit()', {
                eventType: e?.type,
                hasDirectUrl: !!form.getAttribute('data-birkly-url'),
                route: form.getAttribute('data-birkly-route'),
                action: form.getAttribute('data-birkly-action')
            });
            const route = form.getAttribute('data-birkly-route');
            const action = form.getAttribute('data-birkly-action');
            const directUrl = form.getAttribute('data-birkly-url');
            // For direct URL forms, action is OPTIONAL (we won't inject it).
            if (!directUrl && (!route || !action)) return;

            e.preventDefault();

            // Collect form data
            const fd = new FormData(form);
            if (action) fd.set('action', action);
            if (route && action) fd.set('route', route);

            // Attach CSRF token for routed (plugin) actions that go through /api/index.php
            // Many admin+plugin endpoints require it (including user_auth login/logout).
            if (!directUrl) {
                try {
                    const csrf = await getCSRFToken();
                    if (csrf) fd.set('csrf_token', csrf);
                } catch (_) {
                    // ignore
                }
            }

            // Optional: allow redirect behavior
            // - reload (default): reload current page
            // - none: do nothing
            // - redirect: redirect to `data-birkly-redirect` (or "self") and optionally set ok/error query params
            const onSuccess = form.getAttribute('data-birkly-success') || 'reload'; // reload | none | redirect
            const redirectTo = form.getAttribute('data-birkly-redirect') || 'self';
            const okParam = form.getAttribute('data-birkly-ok-param') || 'ok';
            const errParam = form.getAttribute('data-birkly-error-param') || 'error';

            // Disable submit button(s)
            const buttons = Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]'));
            const prev = buttons.map(b => ({ b, disabled: b.disabled, text: b.tagName === 'BUTTON' ? b.textContent : null }));
            buttons.forEach(b => { b.disabled = true; if (b.tagName === 'BUTTON') b.textContent = 'Working…'; });

            try {
                const params = new URLSearchParams();
                for (const [k, v] of fd.entries()) {
                    if (typeof v === 'string') params.append(k, v);
                }

                const endpoint = directUrl
                    ? (directUrl.startsWith('http') ? directUrl : (Birkly.cmsUrl + directUrl))
                    : (Birkly.cmsUrl + '/api/index.php');

                Birkly.warn('Birkly: action form submit ->', {
                    endpoint,
                    route,
                    action,
                    onSuccess,
                    body: params.toString().slice(0, 500)
                });

                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params.toString(),
                    credentials: 'include'
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok || (json.status && json.status !== 'ok') || (json.success === false)) {
                    throw new Error(json.message || json.error || 'Action failed');
                }

                // Special-case public collection submissions (contact form, etc.)
                // Always show a friendly confirmation before applying onSuccess behaviour.
                if (directUrl && /public_submit\.php(?:\?|$)/.test(directUrl)) {
                    const msg = (json && (json.message || json.ok))
                        ? String(json.message || json.ok)
                        : 'Thank you! Your message has been received.';
                    try {
                        // Basic feedback – can later be replaced with inline UI or modal.
                        alert(msg);
                    } catch (_) {
                        // Ignore if alerts are blocked
                    }
                }

                // Clear textareas after success
                form.querySelectorAll('textarea').forEach(t => { if (t instanceof HTMLTextAreaElement) t.value = ''; });

                if (onSuccess === 'reload') {
                    window.location.reload();
                } else if (onSuccess === 'redirect') {
                    const msg = (json && (json.message || json.ok)) ? String(json.message || json.ok) : 'OK';
                    const target = (redirectTo === 'self') ? new URL(window.location.href) : new URL(redirectTo, window.location.href);
                    // Remove previous flash params
                    target.searchParams.delete(okParam);
                    target.searchParams.delete(errParam);
                    target.searchParams.set(okParam, msg);
                    window.location.href = target.toString();
                }
            } catch (err) {
                console.error('Birkly: action form submit failed:', err);
                if (onSuccess === 'redirect') {
                    const msg = String(err?.message || 'Action failed');
                    const target = (redirectTo === 'self') ? new URL(window.location.href) : new URL(redirectTo, window.location.href);
                    target.searchParams.delete(okParam);
                    target.searchParams.delete(errParam);
                    target.searchParams.set(errParam, msg);
                    window.location.href = target.toString();
                } else {
                    alert(err?.message || 'Action failed');
                }
            } finally {
                delete form.dataset.haruSubmitting;
                prev.forEach(({ b, disabled, text }) => {
                    b.disabled = disabled;
                    if (b.tagName === 'BUTTON' && text != null) b.textContent = text;
                });
            }
        }

        // Global capture (covers dynamically inserted forms)
        document.addEventListener('submit', async (e) => {
            const form = e.target;
            if (!form || !(form instanceof HTMLFormElement)) return;
            Birkly.warn('Birkly: document submit capture', {
                route: form.getAttribute('data-birkly-route'),
                action: form.getAttribute('data-birkly-action'),
                url: form.getAttribute('data-birkly-url')
            });
            return handleSubmit(form, e);
        }, true);

        // Some browsers / setups can be flaky with submit capture; also intercept submit-button clicks.
        document.addEventListener('click', (e) => {
            const target = e.target;
            if (!(target instanceof HTMLElement)) return;
            const isSubmitBtn =
                (target.tagName === 'BUTTON' && (target.getAttribute('type') || 'submit') === 'submit') ||
                (target.tagName === 'INPUT' && (target.getAttribute('type') || '').toLowerCase() === 'submit');
            if (!isSubmitBtn) return;
            const form = target.closest('form');
            if (!(form instanceof HTMLFormElement)) return;
            // Only handle Haru action forms
            if (!form.hasAttribute('data-birkly-url') && !(form.hasAttribute('data-birkly-route') && form.hasAttribute('data-birkly-action'))) return;
            Birkly.warn('Birkly: document click capture (submit btn)', {
                route: form.getAttribute('data-birkly-route'),
                action: form.getAttribute('data-birkly-action'),
                url: form.getAttribute('data-birkly-url')
            });
            handleSubmit(form, e);
        }, true);

        // Even earlier than click: pointerdown catches cases where click/submit are prevented.
        document.addEventListener('pointerdown', (e) => {
            const target = e.target;
            if (!(target instanceof HTMLElement)) return;
            const isSubmitBtn =
                (target.tagName === 'BUTTON' && (target.getAttribute('type') || 'submit') === 'submit') ||
                (target.tagName === 'INPUT' && (target.getAttribute('type') || '').toLowerCase() === 'submit');
            if (!isSubmitBtn) return;
            const form = target.closest('form');
            if (!(form instanceof HTMLFormElement)) return;
            if (!form.hasAttribute('data-birkly-url') && !(form.hasAttribute('data-birkly-route') && form.hasAttribute('data-birkly-action'))) return;
            Birkly.warn('Birkly: document pointerdown capture (submit btn)', {
                route: form.getAttribute('data-birkly-route'),
                action: form.getAttribute('data-birkly-action'),
                url: form.getAttribute('data-birkly-url')
            });
            handleSubmit(form, e);
        }, true);

        // Catch Enter key inside Haru forms (some setups submit without firing capture submit handlers reliably)
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') return;
            const target = e.target;
            if (!(target instanceof HTMLElement)) return;
            const form = target.closest('form');
            if (!(form instanceof HTMLFormElement)) return;
            if (!form.hasAttribute('data-birkly-url') && !(form.hasAttribute('data-birkly-route') && form.hasAttribute('data-birkly-action'))) return;
            Birkly.warn('Birkly: document keydown capture (Enter)', {
                route: form.getAttribute('data-birkly-route'),
                action: form.getAttribute('data-birkly-action'),
                url: form.getAttribute('data-birkly-url')
            });
            handleSubmit(form, e);
        }, true);

        // Direct binding (some environments miss document-level submit)
        function bindExistingForms() {
            const forms = document.querySelectorAll('form[data-birkly-url], form[data-birkly-route][data-birkly-action]');
            Birkly.warn('Birkly: bindExistingForms()', { found: forms.length });
            for (const form of forms) {
                if (!(form instanceof HTMLFormElement)) continue;
                if (bound.has(form)) continue;
                bound.add(form);
                Birkly.warn('Birkly: bound form', {
                    route: form.getAttribute('data-birkly-route'),
                    action: form.getAttribute('data-birkly-action'),
                    url: form.getAttribute('data-birkly-url')
                });
                form.addEventListener('submit', (e) => handleSubmit(form, e));

                // Also bind submit-button clicks directly (more robust if submit events are blocked)
                const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
                submitButtons.forEach((btn) => {
                    if (!(btn instanceof HTMLElement)) return;
                    btn.addEventListener('click', (e) => handleSubmit(form, e), true);
                    btn.addEventListener('pointerdown', (e) => handleSubmit(form, e), true);
                });
            }
        }

        bindExistingForms();
        // Re-run once after templates mutate the DOM
        setTimeout(bindExistingForms, 0);

        // Expose refresh so init() can re-bind after template processing finishes
        bindActionForms.refresh = bindExistingForms;
    }
    
    /**
     * Initialize analytics tracking
     */
    function initAnalytics() {
        const trackingId = Birkly.trackingId;
        const cmsUrl = Birkly.cmsUrl.replace(/\/$/, '');
        
        if (!trackingId || !cmsUrl) {
            return;
        }
        
        // Generate or get session ID
        function getSessionId() {
            const key = 'birkly_session_' + trackingId;
            let sessionId = sessionStorage.getItem(key);
            if (!sessionId) {
                sessionId = 'sess_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                sessionStorage.setItem(key, sessionId);
            }
            return sessionId;
        }
        
        // Track page view
        function trackPageView() {
            // Analytics is optional and disabled by default for the demo/public setup.
            // Avoid hitting /api/analytics.php when not explicitly enabled.
            if (!Birkly.enableAnalytics) return;

            const data = {
                tracking_id: trackingId,
                domain: window.location.hostname,
                path: window.location.pathname + window.location.search,
                referrer: document.referrer || '',
                screen_width: window.screen.width || 0,
                screen_height: window.screen.height || 0,
                language: navigator.language || navigator.userLanguage || '',
                session_id: getSessionId()
            };
            
            const url = cmsUrl + '/api/analytics.php?action=track';
            
            // Use sendBeacon if available (more reliable)
            if (navigator.sendBeacon) {
                const blob = new Blob([JSON.stringify(data)], { type: 'application/json' });
                navigator.sendBeacon(url, blob);
            } else {
                // Fallback to fetch
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data),
                    keepalive: true
                }).catch(function(err) {
                    // Silently fail - don't interrupt user experience
                    if (Birkly.debug) console.debug('Birkly Analytics: Tracking failed', err);
                });
            }
        }
        
        // Track page view on load
        if (document.readyState === 'complete') {
            trackPageView();
        } else {
            window.addEventListener('load', trackPageView);
        }
        
        // Track page views on history changes (SPA support)
        let lastUrl = location.href;
        new MutationObserver(function() {
            const url = location.href;
            if (url !== lastUrl) {
                lastUrl = url;
                trackPageView();
            }
        }).observe(document, { subtree: true, childList: true });
        
        // Also listen to popstate for back/forward navigation
        window.addEventListener('popstate', function() {
            setTimeout(trackPageView, 100);
        });
        
        // Expose API for manual tracking
        window.HaruAnalyticsTrack = function(eventName, eventData) {
            const data = {
                tracking_id: trackingId,
                domain: window.location.hostname,
                path: window.location.pathname,
                referrer: document.referrer || '',
                screen_width: window.screen.width || 0,
                screen_height: window.screen.height || 0,
                language: navigator.language || '',
                session_id: getSessionId(),
                event_name: eventName,
                event_data: eventData || {}
            };
            
            const url = cmsUrl + '/api/analytics.php?action=track';
            
            if (navigator.sendBeacon) {
                const blob = new Blob([JSON.stringify(data)], { type: 'application/json' });
                navigator.sendBeacon(url, blob);
            } else {
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data),
                    keepalive: true
                }).catch(function(err) {
                    if (Birkly.debug) console.debug('Birkly Analytics: Event tracking failed', err);
                });
            }
        };
    }
    
    
    /**
     * Public API
     */
    window.Birkly = {
        /**
         * Initialize (called automatically, but can be called manually)
         */
        init: init,
        
        /**
         * Fetch content from CMS
         */
        fetch: fetchContent,
        
        /**
         * Submit data to CMS (for forms)
         */
        submit: submitData,
        
        /**
         * Get entry by slug
         */
        async getEntryBySlug(collection, slug) {
            const data = await fetchContent('list_entries', { collection: collection });
            if (data && data.status === 'ok' && data.data.entries) {
                const entries = data.data.entries.map(flattenLocalizedEntry);
                return entries.find(entry => entry.slug === slug);
            }
            return null;
        },
        
        /**
         * Render template manually
         */
        render: function renderTemplate(template, data) {
            if (Birkly.render && typeof Birkly.render.render === 'function') {
                return Birkly.render.render(template, data);
            }
            return template;
        },
        
        /**
         * Process all templates in page
         */
        processTemplates: processTemplates,
        
        /**
         * Get current configuration
         */
        getConfig: () => ({
            cmsUrl: Birkly.cmsUrl,
            domain: Birkly.domain,
            isLocalhost: Birkly.isLocalhost,
            apiEndpoint: Birkly.apiEndpoint
        })
    };
    
    // Auto-initialize - start immediately (don't wait for DOMContentLoaded for inline scripts)
    // But check if DOM is ready first
    if (document.readyState === 'loading') {
        // Wait for DOM to be ready before processing templates
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // DOM already ready, initialize immediately
        init();
    }
})();
