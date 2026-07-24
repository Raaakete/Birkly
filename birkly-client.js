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
        
        // Tracking ID (from data-tracking-id attribute)
        trackingId: (function() {
            const script = document.querySelector('script[data-tracking-id]');
            if (script) {
                return script.getAttribute('data-tracking-id');
            }
            // Default to localhost for localhost domains
            const hostname = window.location.hostname.toLowerCase();
            const isLocalhost = hostname === 'localhost' || 
                   hostname === '127.0.0.1' ||
                   hostname === '::1' ||
                   hostname === '0.0.0.0' ||
                   hostname.includes('.local') ||
                   hostname.includes('.test') ||
                   hostname.includes('.dev');
            if (isLocalhost) {
                return 'localhost';
            }
            return null;
        })()
        ,
        // Enable verbose debug logs when URL contains ?__dbg=1 or when on localhost (dev server)
        debug: (function() {
            try {
                if (new URLSearchParams(window.location.search).has('__dbg')) return true;
                const host = window.location.hostname || '';
                if (host === 'localhost' || host === '127.0.0.1') return true;
                return false;
            } catch (_) { return false; }
        })(),

        // Feature flags (can be wired to data-attributes later)
        // - Template functions: Enable to use plugin-provided template functions (e.g., from user_management plugin)
        // - Analytics is optional and currently returns 403 on many setups.
        enableTemplateFunctions: true,
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
    
    // Unconditional so we can confirm script loaded (even without ?__dbg)
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

            const parts = [];
            const mainEl = document.querySelector('main');
            if (mainEl) parts.push(mainEl.innerHTML || '');
            else if (document.body) parts.push(document.body.innerHTML || '');
            const combined = parts.join('\n');

            Birkly.bundle.getTemplateCollectionSlugs(combined).forEach((collection) => {
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
            // v3: invalidates session caches poisoned by pre-#233 admin entry summaries
            return 'birkly_bundle_v3:' + page + ':' + slug + ':' + (Birkly.currentLanguage || 'en') + ':' + h.toString(16);
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
            if (data[key] === undefined) {
                data[key] = Birkly.pageBundle.data[key];
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
                if (Array.isArray(bundled)) {
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
     * Helper function to process nested loops recursively
     */
    function processNestedLoops(template, data, depth = 0) {
        // Only process if there are actually loops in the template
        if (!template.includes('{for each') && !template.includes('{for ')) {
            return template;
        }
        
        // Limit recursion depth to prevent infinite loops
        if (depth > 2) {
            Birkly.warn('Birkly: processNestedLoops reached max depth', depth, ', stopping');
            return template;
        }
        
        // Process loops one at a time
        const loopHeader = parseForLoopHeader(template);
        if (!loopHeader) return template;
        const match = [loopHeader.full, loopHeader.itemVar, loopHeader.inClause];
        match.filterExpr = loopHeader.filterExpr;
        match.orderExpr = loopHeader.orderExpr;
        
        const itemVar = loopHeader.itemVar;
        const inClause = loopHeader.inClause;
        
        const items = resolveLoopItems(inClause, data);
        
        if (inClause === "'tags'" || inClause === '"tags"') {
            Birkly.log('Birkly: processNestedLoops - resolving tags:', {
                inClause,
                itemsLength: items.length,
                dataKeys: Object.keys(data).slice(0, 20),
                hasEntry: !!data.entry,
                hasTagsInData: 'tags' in data,
                tagsInData: data.tags
            });
        }
        
        Birkly.log('Birkly: processNestedLoops - calling extractLoopContent with template length:', template.length, 'startMatch:', match[0]);
        Birkly.log('Birkly: processNestedLoops - template sample around match:', template.substring(Math.max(0, template.indexOf(match[0]) - 50), template.indexOf(match[0]) + match[0].length + 200));
        const loopContent = extractLoopContent(template, match[0]);
        if (!loopContent) {
            // Couldn't find endfor - remove the loop directive to prevent it from appearing in output
            Birkly.warn('Birkly: processNestedLoops - failed to extract loop content for:', match[0], 'template length:', template.length, 'removing loop directive');
            const startPos = template.indexOf(match[0]);
            // Try to find the next {endfor} or {endforeach} after the loop start
            const nextEndFor = template.indexOf('{endfor', startPos);
            const nextEndForEach = template.indexOf('{endforeach', startPos);
            let endPos = -1;
            if (nextEndFor !== -1 && (nextEndForEach === -1 || nextEndFor < nextEndForEach)) {
                endPos = template.indexOf('}', nextEndFor) + 1;
            } else if (nextEndForEach !== -1) {
                endPos = template.indexOf('}', nextEndForEach) + 1;
            }
            if (endPos !== -1) {
                return template.substring(0, startPos) + template.substring(endPos);
            }
            // If we can't find endfor, just remove the loop start directive
            return template.substring(0, startPos) + template.substring(startPos + match[0].length);
        }
        
        if (!Array.isArray(items) || items.length === 0) {
            // Remove the loop if no items
            const startPos = template.indexOf(match[0]);
            const endPos = startPos + match[0].length + loopContent.length + 7;
            return template.substring(0, startPos) + template.substring(endPos);
        }
        
        const output = items.map(item => {
            const itemData = { ...data, [itemVar]: item };
            let itemOutput = loopContent;
            
            // Handle simple unquoted variables first: {item}
            itemOutput = itemOutput.replace(/\{\s*([a-zA-Z0-9_]+)\s*\}/g, (m, varName) => {
                // Skip if it's a directive keyword
                const directiveKeywords = ['if','for','endif','endfor','foreach','endforeach','from','get','has','include','media','url','count','endget','else','elseif'];
                if (directiveKeywords.includes(varName.toLowerCase())) return m;
                
                // If it's the loop variable (e.g., 'item'), output the current item
                if (varName === itemVar) {
                    if (typeof item === 'string') return escapeHtml(item);
                    if (item && typeof item === 'object') {
                        const v = item.value ?? item.name ?? item.id ?? item.label ?? item;
                        return typeof v === 'string' ? escapeHtml(v) : String(v);
                    }
                    return escapeHtml(String(item));
                }
                
                // Otherwise, check if it's in the data context
                if (itemData[varName] !== undefined) {
                    const value = itemData[varName];
                    return escapeHtml(String(value));
                }
                
                return m; // Don't replace if not found
            });
            
            // Handle quoted fields: {'field'} or {'field'.0.'subfield'}
            itemOutput = itemOutput.replace(/\{\s*((?:['"][^'"]+['"]|\.?\d+\.?)+)\s*\}/g, (m, pathStr) => {
                const directiveKeywords = ['if','for','endif','endfor','foreach','endforeach','from','get','has','include','media','url','count','endget'];
                const firstPartMatch = pathStr.match(/^['"]([^'"]+)['"]/);
                if (firstPartMatch && directiveKeywords.includes(firstPartMatch[1].toLowerCase())) return m;
                
                const pathParts = [];
                const pathMatch = pathStr.matchAll(/['"]([^'"]+)['"]|(\d+)/g);
                for (const pm of pathMatch) {
                    if (pm[1]) pathParts.push(pm[1]);
                    else if (pm[2]) pathParts.push(pm[2]);
                }
                
                if (pathParts.length === 0) return m;
                
                const value = getNestedValue(itemData, pathParts.join('.'));
                if (value === null || value === undefined) return '';
                if (pathParts.includes('content') || pathParts[pathParts.length - 1] === 'content') {
                    return String(value);
                }
                return escapeHtml(String(value));
            });
            
            // Replace variables (same logic as main loop)
            itemOutput = itemOutput.replace(/\{\s*([a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*(?:\.[0-9]+(?:\.[a-zA-Z0-9_]+)*)?)\s*(?:\|\|\s*([^}]+))?\s*(?:"([^"]+)")?\s*\}/g, (m, varPath, fallback, arg) => {
                const rawPath = String(varPath || '').trim();
                const lowers = rawPath.toLowerCase();
                const directiveKeywords = ['if','for','endif','endfor','foreach','endforeach','from','get','has','include','media','url','count','public_form','entry','endentry','else'];
                if (directiveKeywords.includes(lowers)) return m;
                
                let value = getNestedValue(itemData, rawPath);
                
                if (rawPath.endsWith('.length')) {
                    const basePath = rawPath.slice(0, -7);
                    const baseValue = getNestedValue(itemData, basePath);
                    if (Array.isArray(baseValue)) {
                        value = baseValue.length;
                    } else if (baseValue && typeof baseValue === 'object' && 'length' in baseValue) {
                        value = baseValue.length;
                    } else {
                        value = null;
                    }
                }
                
                if ((value === null || value === undefined || value === '') && fallback) {
                    const fallbackTrimmed = fallback.trim();
                    if (fallbackTrimmed === '0' || /^\d+$/.test(fallbackTrimmed)) {
                        value = parseInt(fallbackTrimmed, 10);
                    } else {
                        const fallbackValue = getNestedValue(itemData, fallbackTrimmed);
                        value = fallbackValue !== null && fallbackValue !== undefined ? fallbackValue : fallbackTrimmed;
                    }
                }
                
                if (value === null || value === undefined) return '';
                
                const lastSegment = rawPath.split('.').pop();
                if (lastSegment === 'content' || /\.content$/.test(rawPath)) {
                    return String(value);
                }
                if (rawPath === 'date' && arg) {
                    return formatDate(String(value), arg);
                }
                return escapeHtml(String(value));
            });
            
            // Process conditionals
            itemOutput = renderConditionals(itemOutput, itemData);
            
            // Recursively process deeper nested loops (limit depth)
            // This processes nested loops like {for each reply_check in comments} within each comment item
            if (depth < 2 && (itemOutput.includes('{for each') || itemOutput.includes('{for '))) {
                const beforeNested = itemOutput;
                itemOutput = processNestedLoops(itemOutput, itemData, depth + 1);
                // Verify that nested loops were actually processed and removed
                if (itemOutput === beforeNested && itemOutput.includes('{for each')) {
                    Birkly.warn('Birkly: processNestedLoops did not process nested loop in itemOutput, item:', item.id || item.title || 'unknown');
                }
            }
            
            return itemOutput;
        }).join('');
        
        // Replace the loop with its output
        const startPos = template.indexOf(match[0]);
        // Find the actual {endfor} or {endforeach} position to ensure accurate replacement
        let endPos = -1;
        const searchStart = startPos + match[0].length + loopContent.length;
        const endforPos = template.indexOf('{endfor}', searchStart);
        const endforeachPos = template.indexOf('{endforeach}', searchStart);
        
        if (endforPos !== -1 && (endforeachPos === -1 || endforPos < endforeachPos)) {
            endPos = endforPos + 8; // {endfor} is 8 chars
        } else if (endforeachPos !== -1) {
            endPos = endforeachPos + 12; // {endforeach} is 12 chars
        } else {
            // Fallback to old calculation if we can't find endfor
            endPos = startPos + match[0].length + loopContent.length + 7;
        }
        
        const result = template.substring(0, startPos) + output + template.substring(endPos);
        
        // Process any remaining nested loops in the result (but limit depth)
        // IMPORTANT: Only process if we're at depth 0 (top level)
        // Nested loops within items are already processed at line 617 within each item's context,
        // so they should be completely removed from the output. If there are still loop directives
        // in the result, they must be top-level loops that weren't part of the main loop content.
        if (depth === 0 && (result.includes('{for each') || result.includes('{for '))) {
            // Only process if there are actually unprocessed loop directives
            // Nested loops that were processed within items should have been replaced with content
            const hasUnprocessedLoops = /\{for\s+(?:each\s+)?[a-zA-Z0-9_]+\s+in\s+/.test(result);
            if (hasUnprocessedLoops) {
                Birkly.log('Birkly: processNestedLoops - processing remaining top-level loops at depth', depth);
                return processNestedLoops(result, data, depth + 1);
            }
        }
        
        return result;
    }
    
    /**
     * Parse template syntax and render with data
     */
    function renderTemplate(template, data) {
        if (!template || typeof template !== 'string') {
            Birkly.error('Birkly: renderTemplate called with invalid template:', typeof template, template);
            return template || '';
        }
        if (!data || typeof data !== 'object') {
            Birkly.error('Birkly: renderTemplate called with invalid data:', typeof data, data);
            return template;
        }
        let output = template;
        Birkly.log('Birkly: renderTemplate called, template length:', template.length, 'data keys:', Object.keys(data || {}), 'template sample:', template.substring(0, 100));
        
        // Preserve entry block placeholders - extract and restore them after processing
        const entryBlockPlaceholders = [];
        const entryBlockRegex = /<div[^>]*class="birkly-entry-block"[^>]*>.*?<\/div>/g;
        let match;
        while ((match = entryBlockRegex.exec(output)) !== null) {
            entryBlockPlaceholders.push({
                placeholder: match[0],
                index: match.index,
                uniqueId: 'ENTRY_BLOCK_PLACEHOLDER_' + entryBlockPlaceholders.length
            });
        }
        
        // Replace entry blocks with unique placeholders that won't be processed
        entryBlockPlaceholders.forEach((block, index) => {
            output = output.replace(block.placeholder, block.uniqueId);
        });
        
        // Handle {from 'fieldName' get item.selector} for array fields on current entry
        // Examples:
        // - {from 'tags' get item.1}
        // - {from 'tags' get item.newest}
        // - {from 'tags' get item.'cms'}
        output = output.replace(/\{from\s+['"]([^'"]+)['"]\s+get\s+item\.(newest|oldest|\d+|'[^']+')\s*\}/g, (match, fieldName, selector) => {
            // Prefer entry[fieldName], fallback to top-level data[fieldName]
            let arr = null;
            if (data && data.entry && Array.isArray(data.entry[fieldName])) {
                arr = data.entry[fieldName];
            } else if (data && Array.isArray(data[fieldName])) {
                arr = data[fieldName];
            }
            if (!arr || arr.length === 0) return '';

            let element = null;
            if (/^\d+$/.test(selector)) {
                const idx = parseInt(selector, 10) - 1; // 1-based index
                if (idx >= 0 && idx < arr.length) element = arr[idx];
            } else if (selector === 'newest') {
                element = arr[arr.length - 1];
            } else if (selector === 'oldest') {
                element = arr[0];
            } else if (/^'[^']+'$/.test(selector)) {
                const key = selector.slice(1, -1);
                element = arr.find(it => {
                    if (typeof it === 'string') return it === key;
                    if (it && typeof it === 'object') {
                        if (it.value === key) return true;
                        if (it.name === key) return true;
                        if (it.id === key) return true;
                        if (it.label === key) return true;
                    }
                    return false;
                }) || null;
            }

            if (element == null) return '';
            if (typeof element === 'string') return escapeHtml(element);
            if (element && typeof element === 'object') {
                const v = element.value ?? element.name ?? element.id ?? element.label;
                return v != null ? escapeHtml(String(v)) : '';
            }
            return escapeHtml(String(element));
        });

        // Handle {from 'collection'.'id' get 'field'.'subfield'} inline form
        // Supports paths like: 'content'.0.'value' or 'content'.'text'
        output = output.replace(/\{from\s+['"]([^'"]+)['"]\.['"]([^'"]+)['"]\s+get\s+((?:['"][^'"]+['"]\.?)+)\}/g, (match, collection, identifier, fieldPathStr) => {
            const entry = resolveCollectionEntryRef(`'${collection}'.'${identifier}'`, data);
            if (!entry) return '';
            
            // Parse field path: 'content'.0.'value' → ['content', '0', 'value']
            const pathParts = [];
            const pathMatch = fieldPathStr.matchAll(/['"]([^'"]+)['"]|(\d+)/g);
            for (const m of pathMatch) {
                pathParts.push(m[1] || m[2]); // m[1] for quoted strings, m[2] for numbers
            }
            
            const value = getNestedValue(entry, pathParts.join('.'));
            if (value === null || value === undefined) return '';
            // Auto-raw for content fields
            if (pathParts.includes('content') || pathParts[pathParts.length - 1] === 'content') {
                return String(value);
            }
            return escapeHtml(String(value));
        });
        
        // Handle {from 'collection' get entry 'id'} ... {endget} block form
        let fromProcessed = true;
        while (fromProcessed) {
            fromProcessed = false;
            const fromBlockRegex = /\{from\s+['"]([^'"]+)['"]\s+get\s+entry\s+['"]([^'"]+)['"]\}/;
            const match = output.match(fromBlockRegex);
            if (match) {
                fromProcessed = true;
                const fullMatch = match[0];
                const collection = match[1];
                const identifier = match[2];
                const entry = resolveCollectionEntryRef(`'${collection}'.'${identifier}'`, data);
                
                const startPos = output.indexOf(fullMatch);
                const endPos = output.indexOf('{endget}', startPos);
                
                if (endPos === -1) {
                    // No matching {endget}, remove the directive
                    output = output.replace(fullMatch, '');
                    continue;
                }
                
                const blockContent = output.substring(startPos + fullMatch.length, endPos);
                
                if (!entry) {
                    // Entry not found, remove the entire block
                    output = output.substring(0, startPos) + output.substring(endPos + 8);
                    continue;
                }
                
                // Render block content with entry properties spread into context
                // This allows {'content'.0.'value'} to resolve from entry.content[0].value
                const blockData = { ...data, entry: entry, ...entry };
                let rendered = blockContent;
                
                // Process variables in block context (entry properties are available at top level)
                rendered = renderTemplate(rendered, blockData);
                
                output = output.substring(0, startPos) + rendered + output.substring(endPos + 8);
            }
        }
        
        // Handle {foreach array as item} loops (for arrays like tags)
        // We need to process these BEFORE the collection loops to avoid conflicts
        // Use a while loop to process all foreach loops
        let foreachProcessed = true;
        while (foreachProcessed) {
            foreachProcessed = false;
            const foreachRegex = /\{foreach\s+([^\s]+)\s+as\s+([^\s]+)\}/;
            const match = output.match(foreachRegex);
            if (match) {
                foreachProcessed = true;
                const fullMatch = match[0];
                const arrayName = match[1];
                const itemVar = match[2];
                const array = getNestedValue(data, arrayName) || [];
                
                if (!Array.isArray(array)) {
                    // Remove the entire loop (including endforeach)
                    const startPos = output.indexOf(fullMatch);
                    const endPos = output.indexOf('{endforeach', startPos);
                    if (endPos !== -1) {
                        output = output.substring(0, startPos) + output.substring(endPos + 11);
                    } else {
                        output = output.replace(fullMatch, '');
                    }
                    continue;
                }
                
                // Find the matching {endforeach}
                const startPos = output.indexOf(fullMatch);
                let pos = startPos + fullMatch.length;
                let depth = 1;
                let endPos = -1;
                
                while (pos < output.length && depth > 0) {
                    const nextForeach = output.indexOf('{foreach', pos);
                    const nextEndForeach = output.indexOf('{endforeach', pos);
                    
                    if (nextForeach !== -1 && nextForeach < nextEndForeach) {
                        depth++;
                        pos = nextForeach + 8;
                    } else if (nextEndForeach !== -1) {
                        depth--;
                        if (depth === 0) {
                            endPos = nextEndForeach;
                            break;
                        }
                        pos = nextEndForeach + 11;
                    } else {
                        break; // No matching endforeach
                    }
                }
                
                if (endPos === -1) {
                    // No matching endforeach, remove the loop start
                    output = output.replace(fullMatch, '');
                    continue;
                }
                
                const loopContent = output.substring(startPos + fullMatch.length, endPos);
                
                // Render each item
                const rendered = array.map(item => {
                    let itemOutput = loopContent;
                    
                    // Replace {{ itemVar }} with actual item value
                    itemOutput = itemOutput.replace(new RegExp(`\\{\\{\\s*${itemVar}\\s*\\}\\}`, 'g'), () => {
                        return typeof item === 'string' ? escapeHtml(item) : item;
                    });
                    
                    // Handle conditionals inside loop
                    itemOutput = renderConditionals(itemOutput, { [itemVar]: item }, itemVar);
                    
                    return itemOutput;
                }).join('');
                
                // Replace the entire loop (including endforeach) with rendered content
                output = output.substring(0, startPos) + rendered + output.substring(endPos + 11);
            }
        }
        
        // Handle {count of 'collection'} and {count of 'sub' of 'parent'.'slug'}
        output = output.replace(/\{\s*count\s+of\s+([^}]+)\}/gi, (m, clause) => {
            const items = resolveLoopItems(String(clause || '').trim(), data);
            return String(Array.isArray(items) ? items.length : 0);
        });
        
        // Handle {for each item in 'collection'} loops (quoted) AND {for each item in collectionVar} loops (unquoted)
        // Extended syntax supports optional filtering and ordering:
        // - {for each entry in 'blog'}
        // - {for each entry in 'blog' with status = published}
        // - {for each entry in 'blog' ordered by newest}
        // - {for each entry in 'comments' of 'blog'.'slug-here'}
        // - {for each entry in 'comments' of 'blog'.current ordered by newest}
        const __loopPlaceholders = [];
        let loopHeader = parseForLoopHeader(output);
        while (loopHeader) {
            const match = loopHeader.full;
            const itemVar = loopHeader.itemVar;
            const inClause = loopHeader.inClause;
            const filterExpr = loopHeader.filterExpr;
            const orderExpr = loopHeader.orderExpr;
            const limitExpr = loopHeader.limitExpr;
            const __token = '__BIRKLY_LOOP__' + Math.random().toString(36).slice(2) + '__';
            let items = resolveLoopItems(inClause, data);
            if (!Array.isArray(items)) items = [];
            
            // Apply simple filters: currently supports single condition:
            // - status = published
            // - 'fieldName' = 'value'
            // - 'fieldName' <= 15  (and corresponding <, >, >=, =>, <=, =<)
            if (filterExpr && typeof filterExpr === 'string') {
                const expr = filterExpr.trim();
                if (expr.length) {
                    // Match status = published (unquoted value)
                    let m = expr.match(/^([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*([a-zA-Z_][a-zA-Z0-9_]*)$/);
                    if (m) {
                        const field = m[1];
                        const value = m[2];
                        items = items.filter(it => (it && String(it[field]) === value));
                    } else {
                        // Match 'fieldName' <op> value
                        m = expr.match(/^['"]([^'"]+)['"]\s*(=|<=|=<|>=|=>|<|>)\s*([0-9]+(?:\.[0-9]+)?)$/);
                        if (m) {
                            const field = m[1];
                            const opRaw = m[2];
                            const num = parseFloat(m[3]);
                            const op = opRaw === '=<'
                                ? '<='
                                : opRaw === '=>'
                                    ? '>='
                                    : opRaw;
                            items = items.filter(it => {
                                if (!it) return false;
                                const v = parseFloat(it[field]);
                                if (Number.isNaN(v)) return false;
                                switch (op) {
                                    case '=':  return v === num;
                                    case '<':  return v < num;
                                    case '>':  return v > num;
                                    case '<=': return v <= num;
                                    case '>=': return v >= num;
                                    default:   return true;
                                }
                            });
                        }
                    }
                }
            }

            // Apply ordering: newest / oldest / last-updated
            if (orderExpr && typeof orderExpr === 'string' && items.length > 1) {
                const order = orderExpr.trim().toLowerCase();
                const sortByDateField = (field, direction) => {
                    items = items.slice().sort((a, b) => {
                        const da = a && a[field] ? Date.parse(a[field]) : 0;
                        const db = b && b[field] ? Date.parse(b[field]) : 0;
                        return direction * (db - da);
                    });
                };
                if (order === 'newest') {
                    sortByDateField('created_at', +1);
                } else if (order === 'oldest') {
                    // Reverse newest
                    sortByDateField('created_at', -1);
                } else if (order === 'last-updated') {
                    sortByDateField('updated_at', +1);
                } else if (order === 'order') {
                    items = items.slice().sort((a, b) => {
                        const oa = parseFloat(a && a.order != null ? a.order : (a && a.sort_order != null ? a.sort_order : 0)) || 0;
                        const ob = parseFloat(b && b.order != null ? b.order : (b && b.sort_order != null ? b.sort_order : 0)) || 0;
                        return oa - ob;
                    });
                }
            }

            if (limitExpr && limitExpr > 0) {
                items = items.slice(0, limitExpr);
            }

            // Find the matching {endfor} or {endforeach}
            const loopContent = extractLoopContent(output, match);
            if (!loopContent) {
                Birkly.warn('Birkly: extractLoopContent failed for loop:', match, 'in template sample:', output.substring(Math.max(0, output.indexOf(match) - 50), Math.min(output.length, output.indexOf(match) + 200)));
                break;
            }
            
            Birkly.log('Birkly: Processing loop:', itemVar, 'in', inClause, 'with', items.length, 'items');
            Birkly.log('Birkly: Outer loop extracted content length:', loopContent.length, 'contains {for each item:', loopContent.includes('{for each item'), 'contains {endfor}:', loopContent.includes('{endfor}'));
            if (loopContent.includes('{for each item')) {
                const nestedLoopIndex = loopContent.indexOf('{for each item');
                Birkly.log('Birkly: Outer loop content around nested loop:', loopContent.substring(Math.max(0, nestedLoopIndex - 50), nestedLoopIndex + 200));
            }
            
            // Render each item
            const __rendered = items.map(item => {
                let itemOutput = loopContent;
                Birkly.log('Birkly: Processing item in collection loop:', item.title || item.id, 'itemOutput length:', itemOutput.length);
                Birkly.log('Birkly: itemOutput contains {for each:', itemOutput.includes('{for each'), 'contains {for each tag:', itemOutput.includes('{for each tag'));
                
                // Create loopData early so it can be used for nested loop processing
                // Create a combined data object for this loop iteration.
                // Important: only alias the current item to `entry` if:
                // - the loop variable is already `entry`, OR
                // - the outer data has no `entry` (so we don't clobber single-entry pages that already have `entry`)
                const loopData = {
                    ...data,
                    [itemVar]: item
                };
                // For entry loops, alias and spread entry fields so nested loops and field access
                // like {for each item in 'tags'} and {'tags'} can see fields at top level.
                if (itemVar === 'entry' || !Object.prototype.hasOwnProperty.call(data, 'entry')) {
                    loopData.entry = item;
                    if (item && typeof item === 'object') {
                        Object.assign(loopData, item);
                    }
                }
                
                // Process nested loops FIRST (loops with 'in' clause like {for each reply_check in comments})
                // This must happen BEFORE the old {for each tag} handler
                const hasNestedLoopWithIn = /\{for\s+(?:each\s+)?[a-zA-Z0-9_]+\s+in\s+/.test(itemOutput);
                if (hasNestedLoopWithIn) {
                    itemOutput = processNestedLoops(itemOutput, loopData, 0);
                }
                
                // Handle nested {for each tag} loops (without 'in' clause) - refers to item's property
                let nestedLoopProcessed = true;
                while (nestedLoopProcessed) {
                    nestedLoopProcessed = false;
                    // Match {for each tag} or {for each tags} - handle optional spaces
                    // Must match: {for each tag} or {for each tags} (with optional spaces)
                    const nestedLoopRegex = /\{for\s+each\s+([a-zA-Z0-9_]+)\s*\}/;
                    // Reset regex lastIndex to ensure fresh search
                    nestedLoopRegex.lastIndex = 0;
                    const nestedMatch = nestedLoopRegex.exec(itemOutput);
                    Birkly.log('Birkly: Checking for nested loops in itemOutput, length:', itemOutput.length, 'match:', nestedMatch ? 'found' : 'not found', nestedMatch ? 'matched:' + nestedMatch[0] : '');
                    if (nestedMatch) {
                        nestedLoopProcessed = true;
                        const fullMatch = nestedMatch[0];
                        const propName = nestedMatch[1];
                        Birkly.log('Birkly: Processing nested loop for property:', propName, 'in item:', item.title || item.id);
                        
                        // Check if it's a property of the current item (e.g., tags)
                        // Try singular form first, then plural form (e.g., 'tag' -> 'tags')
                        let array = item[propName];
                        Birkly.log('Birkly: Checking property:', propName, 'value:', array, 'type:', typeof array, 'isArray:', Array.isArray(array));
                        // If property doesn't exist or is not an array, try plural form
                        if (!array || !Array.isArray(array)) {
                            const pluralForm = propName + 's';
                            array = item[pluralForm];
                            Birkly.log('Birkly: Trying plural form:', pluralForm, 'value:', array, 'isArray:', Array.isArray(array));
                        }
                        // If still not an array, default to empty array
                        if (!array || !Array.isArray(array)) {
                            array = [];
                            Birkly.log('Birkly: Property not found or not an array, defaulting to empty array');
                        } else {
                            Birkly.log('Birkly: Found array with', array.length, 'items:', array);
                        }
                        
                        // Only remove the loop if it's not an array (empty arrays should still process)
                        if (!Array.isArray(array)) {
                            // Remove the entire loop
                            const startPos = itemOutput.indexOf(fullMatch);
                            const endPos = itemOutput.indexOf('{endfor', startPos);
                            if (endPos !== -1) {
                                itemOutput = itemOutput.substring(0, startPos) + itemOutput.substring(endPos + 7);
                            } else {
                                itemOutput = itemOutput.replace(fullMatch, '');
                            }
                            nestedLoopRegex.lastIndex = 0; // Reset regex
                            continue;
                        }
                        
                        // Find the matching {endfor}
                        const startPos = itemOutput.indexOf(fullMatch);
                        let pos = startPos + fullMatch.length;
                        let depth = 1;
                        let endPos = -1;
                        let endPosClosingBrace = -1;
                        
                        while (pos < itemOutput.length && depth > 0) {
                            const nextFor = itemOutput.indexOf('{for', pos);
                            const nextEndFor = itemOutput.indexOf('{endfor', pos);
                            
                            if (nextFor !== -1 && nextEndFor !== -1 && nextFor < nextEndFor) {
                                depth++;
                                pos = nextFor + 4;
                            } else if (nextEndFor !== -1) {
                                // Find the closing brace of {endfor}
                                const closingBracePos = itemOutput.indexOf('}', nextEndFor + 7);
                                if (closingBracePos !== -1) {
                                    depth--;
                                    if (depth === 0) {
                                        endPos = nextEndFor; // Position of {endfor (before the closing brace)
                                        endPosClosingBrace = closingBracePos; // Position of the closing brace
                                        break;
                                    }
                                    pos = closingBracePos + 1;
                                } else {
                                    // No closing brace found, treat {endfor as the end
                                    depth--;
                                    if (depth === 0) {
                                        endPos = nextEndFor;
                                        endPosClosingBrace = nextEndFor + 7; // Assume {endfor is 7 chars
                                        break;
                                    }
                                    pos = nextEndFor + 7;
                                }
                            } else {
                                break;
                            }
                        }
                        
                        if (endPos === -1) {
                            itemOutput = itemOutput.replace(fullMatch, '');
                            nestedLoopRegex.lastIndex = 0;
                            continue;
                        }
                        
                        // Extract content between {for each tag} and {endfor} (not including {endfor})
                        const nestedLoopContent = itemOutput.substring(startPos + fullMatch.length, endPos);
                        Birkly.log('Birkly: Nested loop content:', nestedLoopContent.substring(0, 100));
                        
                        // Render each array item
                        const rendered = array.map(tagItem => {
                            Birkly.log('Birkly: Processing tag item:', tagItem);
                            let tagOutput = nestedLoopContent;
                            
                            // Replace the loop variable first ({ tag } or { tags })
                            // Match both singular and plural forms
                            const tagVarRegex = new RegExp(`\\{\\s*${propName}\\s*\\}`, 'g');
                            const tagVarPluralRegex = new RegExp(`\\{\\s*${propName}s\\s*\\}`, 'g');
                            
                            tagOutput = tagOutput.replace(tagVarRegex, () => {
                                const replaced = typeof tagItem === 'string' ? escapeHtml(tagItem) : tagItem;
                                Birkly.log('Birkly: Replaced {', propName, '} with:', replaced);
                                return replaced;
                            });
                            tagOutput = tagOutput.replace(tagVarPluralRegex, () => {
                                const replaced = typeof tagItem === 'string' ? escapeHtml(tagItem) : tagItem;
                                Birkly.log('Birkly: Replaced {', propName, 's} with:', replaced);
                                return replaced;
                            });
                            
                            // Then replace all other variables that refer to the outer loop item (e.g., { title }, { slug })
                            // This allows variables like { title } to work inside nested loops
                            tagOutput = tagOutput.replace(/\{\s*([a-zA-Z0-9_]+)\s*\}/g, (m, name) => {
                                name = name.trim();
                                const lowers = name.toLowerCase();
                                // Skip directive keywords and the loop variable (already replaced)
                                if (['if','for','endif','endfor','foreach','endforeach','from','get','has','include','media','url','count'].includes(lowers)) return m;
                                if (name === propName || name === propName + 's') return m; // Already replaced above
                                
                                // Otherwise, try to get from the outer loop item
                                if (item && Object.prototype.hasOwnProperty.call(item, name)) {
                                    if (name === 'content') return (item[name] || '');
                                    return escapeHtml(item[name] || '');
                                }
                                
                                // Fallback to outer data
                                if (data && Object.prototype.hasOwnProperty.call(data, name)) {
                                    if (name === 'content') return (data[name] || '');
                                    return escapeHtml(data[name] || '');
                                }
                                
                                return '';
                            });
                            
                            Birkly.log('Birkly: Final tag output:', tagOutput);
                            return tagOutput;
                        }).join('');
                        
                        Birkly.log('Birkly: Rendered nested loop content, length:', rendered.length, 'first 100 chars:', rendered.substring(0, 100));
                        
                        // Replace the nested loop with rendered content
                        // endPos is the position of {endfor, endPosClosingBrace is the position of the closing brace
                        const replaceEndPos = endPosClosingBrace !== -1 ? endPosClosingBrace + 1 : endPos + 8;
                        itemOutput = itemOutput.substring(0, startPos) + rendered + itemOutput.substring(replaceEndPos);
                        nestedLoopRegex.lastIndex = 0; // Reset regex
                    }
                }
                
                // First, handle nested foreach loops (like {foreach entry.tags as tag})
                let foreachProcessed = true;
                while (foreachProcessed) {
                    foreachProcessed = false;
                    const foreachRegex = /\{foreach\s+([a-zA-Z0-9_\.]+)\s+as\s+([a-zA-Z0-9_]+)\}/;
                    const match = itemOutput.match(foreachRegex);
                    if (match) {
                        foreachProcessed = true;
                        const fullMatch = match[0];
                        const arrayPath = match[1];
                        const tagVar = match[2];
                        
                        // Check if it's a property of the current item (e.g., entry.tags)
                        if (arrayPath.startsWith(itemVar + '.')) {
                            const propName = arrayPath.substring(itemVar.length + 1);
                            const array = item[propName] || [];
                            if (!Array.isArray(array)) {
                                // Remove the entire loop
                                const startPos = itemOutput.indexOf(fullMatch);
                                const endPos = itemOutput.indexOf('{endforeach', startPos);
                                if (endPos !== -1) {
                                    itemOutput = itemOutput.substring(0, startPos) + itemOutput.substring(endPos + 11);
                                } else {
                                    itemOutput = itemOutput.replace(fullMatch, '');
                                }
                                continue;
                            }
                            
                            // Find the matching {endforeach}
                            const startPos = itemOutput.indexOf(fullMatch);
                            let pos = startPos + fullMatch.length;
                            let depth = 1;
                            let endPos = -1;
                            
                            while (pos < itemOutput.length && depth > 0) {
                                const nextForeach = itemOutput.indexOf('{foreach', pos);
                                const nextEndForeach = itemOutput.indexOf('{endforeach', pos);
                                
                                if (nextForeach !== -1 && nextForeach < nextEndForeach) {
                                    depth++;
                                    pos = nextForeach + 8;
                                } else if (nextEndForeach !== -1) {
                                    depth--;
                                    if (depth === 0) {
                                        endPos = nextEndForeach;
                                        break;
                                    }
                                    pos = nextEndForeach + 11;
                                } else {
                                    break;
                                }
                            }
                            
                            if (endPos === -1) {
                                itemOutput = itemOutput.replace(fullMatch, '');
                                continue;
                            }
                            
                            const foreachContent = itemOutput.substring(startPos + fullMatch.length, endPos);
                            
                            // Render each tag/item
                            const rendered = array.map(tagItem => {
                                let tagOutput = foreachContent;
                                // Replace {{ tagVar }} with actual tag value
                                tagOutput = tagOutput.replace(new RegExp(`\\{\\{\\s*${tagVar}\\s*\\}\\}`, 'g'), () => {
                                    return typeof tagItem === 'string' ? escapeHtml(tagItem) : tagItem;
                                });
                                return tagOutput;
                            }).join('');
                            
                            // Replace the foreach loop with rendered content
                            itemOutput = itemOutput.substring(0, startPos) + rendered + itemOutput.substring(endPos + 11);
                        } else {
                            // Not for this item, skip it
                            break;
                        }
                    }
                }
                
                // Replace {{ item.property }} with actual values
                // Fixed regex: variable and property names should not include pipe character
                itemOutput = itemOutput.replace(/\{\{\s*([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)(?:\s*\|\s*([a-zA-Z0-9_]+)(?:\s*"([^"]+)")?)?\s*\}\}/g, (varMatch, itemName, prop, filter, filterValue) => {
                    if (itemName !== itemVar) return varMatch;
                    
                    let value = item[prop] || '';
                    
                    // Apply filters
                    if (filter === 'date' && filterValue) {
                        value = formatDate(value, filterValue);
                    } else if (filter === 'raw') {
                        return value; // Don't escape
                    } else if (filter === 'escape' || !filter) {
                        value = escapeHtml(value);
                    }
                    
                    return value;
                });
                
                // Support single-brace variables for current item: { title }, { slug }, etc.
                // Also support quoted field names: {'excerpt'}, {'price'}
                // Skip control structures like {if, {for, {endfor}
                // Handle variables with spaces: { title } -> title
                // NOTE: This regex matches simple variables like { created_at } but NOT { created_at format "Y-m-d" }
                // Format filters are handled by the more complex regex later (which matches the full pattern)
                itemOutput = itemOutput.replace(/\{\s*([a-zA-Z0-9_]+)\s*\}/g, (m, name) => {
                    name = name.trim(); // Trim whitespace
                    const lowers = name.toLowerCase();
                    if (['if','for','endif','endfor','foreach','endforeach','from','get','has','include','media','url','count'].includes(lowers)) return m;
                    
                    // Check if this is a date field
                    const isDateField = name === 'created_at' || name === 'updated_at' || name === 'published_at' || name === 'date';
                    
                    // Prefer current item property (in loop context, item is the entry)
                    if (item && Object.prototype.hasOwnProperty.call(item, name)) {
                        if (name === 'content') return (item[name] || ''); // auto-raw for editor content
                        if (isDateField) {
                            // Format date fields to show just the date (YYYY-MM-DD) by default
                            return formatValueForOutput(item[name], name);
                        }
                        return escapeHtml(item[name] || '');
                    }
                    // Fallback to outer data (loopData)
                    if (data && Object.prototype.hasOwnProperty.call(data, name)) {
                        if (name === 'content') return (data[name] || '');
                        if (isDateField) {
                            // Format date fields to show just the date (YYYY-MM-DD) by default
                            return formatValueForOutput(data[name], name);
                        }
                        return escapeHtml(data[name] || '');
                    }
                    return '';
                });
                // Quoted field names: {'excerpt'} or {'content'.0.'value'} → resolve nested paths
                // Match pattern: {'part1'.'part2'} or {'part1'.0.'part2'} or {'part1'.0.1.'part2'}
                itemOutput = itemOutput.replace(/\{\s*((?:['"][^'"]+['"]|\.?\d+\.?)+)\s*\}/g, (m, pathStr) => {
                    // Parse path like: 'content'.0.'value' → ['content', '0', 'value']
                    const pathParts = [];
                    const pathMatch = pathStr.matchAll(/['"]([^'"]+)['"]|(\d+)/g);
                    for (const pm of pathMatch) {
                        if (pm[1]) pathParts.push(pm[1]); // quoted string
                        else if (pm[2]) pathParts.push(pm[2]); // number
                    }
                    
                    if (pathParts.length === 0) return m; // Don't replace if we can't parse
                    
                    const firstPart = pathParts[0].toLowerCase();
                    if (['if','for','endif','endfor','foreach','endforeach','from','get','has','include','media','url','count','endget'].includes(firstPart)) return m;
                    
                    // Try item first, then loopData
                    let value = null;
                    if (item) {
                        value = getNestedValue(item, pathParts.join('.'));
                    }
                    if (value === null || value === undefined) {
                        value = getNestedValue(loopData, pathParts.join('.'));
                    }
                    
                    if (value === null || value === undefined) return '';
                    
                    // Auto-raw for content fields
                    if (pathParts.includes('content') || pathParts[pathParts.length - 1] === 'content') {
                        return String(value);
                    }
                    return escapeHtml(String(value));
                });
                
                // Replace {{ variable }} (non-item variables)
                itemOutput = itemOutput.replace(/\{\{\s*([a-zA-Z0-9_]+)(?:\s*\|\s*([a-zA-Z0-9_]+)(?::\s*"([^"]+)")?)?\s*\}\}/g, (varMatch, varName, filter, filterValue) => {
                    // Skip if it contains a dot (it's an item property)
                    if (varMatch.includes('.')) return varMatch;
                    
                    let value = data[varName] || '';
                    
                    if (filter === 'date' && filterValue) {
                        value = formatDate(value, filterValue);
                    } else if (filter === 'raw') {
                        return value;
                    } else if (filter === 'escape' || !filter) {
                        value = escapeHtml(value);
                    }
                    
                    return value;
                });
                
                // IMPORTANT: Replace variables FIRST so conditionals can evaluate them
                // (loopData is already created above for nested loop processing)
                // Support dotted variables inside loops, e.g.:
                // - { entry.slug }
                // - { comment.user_name }
                // - { entry.likes_count || 0 }
                // - { profile_posts.length || 0 }
                // - { created_at format "Y-m-d" }
                // The global variable pass later does not know per-item loop scope, so we must resolve them here.
                itemOutput = itemOutput.replace(/\{\s*([a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*(?:\.[0-9]+(?:\.[a-zA-Z0-9_]+)*)?)\s*(?:format\s+(["'])([^"']+)\2)?\s*(?:\|\|\s*([^}]+))?\s*(?:"([^"]+)")?\s*\}/g, (m, varPath, quote, formatFilter, fallback, arg) => {
                    const rawPath = String(varPath || '').trim();
                    const lowers = rawPath.toLowerCase();
                    const directiveKeywords = ['if','for','endif','endfor','foreach','endforeach','from','get','has','include','media','url','count','public_form','entry','endentry','else'];
                    if (directiveKeywords.includes(lowers)) return m;
                    
                    let value = getNestedValue(loopData, rawPath);
                    
                    // Handle .length property
                    if (rawPath.endsWith('.length')) {
                        const basePath = rawPath.slice(0, -7); // Remove '.length'
                        const baseValue = getNestedValue(loopData, basePath);
                        if (Array.isArray(baseValue)) {
                            value = baseValue.length;
                        } else if (baseValue && typeof baseValue === 'object' && 'length' in baseValue) {
                            value = baseValue.length;
                        } else {
                            value = null;
                        }
                    }
                    
                    // Handle fallback operator (||)
                    if ((value === null || value === undefined || value === '') && fallback) {
                        const fallbackTrimmed = fallback.trim();
                        if (fallbackTrimmed === '0' || /^\d+$/.test(fallbackTrimmed)) {
                            value = parseInt(fallbackTrimmed, 10);
                        } else if (fallbackTrimmed === 'true') {
                            value = true;
                        } else if (fallbackTrimmed === 'false') {
                            value = false;
                        } else if (fallbackTrimmed.startsWith('"') && fallbackTrimmed.endsWith('"')) {
                            value = fallbackTrimmed.slice(1, -1);
                        } else if (fallbackTrimmed.startsWith("'") && fallbackTrimmed.endsWith("'")) {
                            value = fallbackTrimmed.slice(1, -1);
                        } else {
                            const fallbackValue = getNestedValue(loopData, fallbackTrimmed);
                            value = fallbackValue !== null && fallbackValue !== undefined ? fallbackValue : fallbackTrimmed;
                        }
                    }
                    
                    if (value === null || value === undefined) return '';

                    // Check if this is a date field
                    const isDateField = rawPath === 'created_at' || 
                                       rawPath === 'updated_at' || 
                                       rawPath === 'published_at' || 
                                       rawPath === 'date' ||
                                       rawPath.endsWith('.created_at') || 
                                       rawPath.endsWith('.updated_at') || 
                                       rawPath.endsWith('.published_at') ||
                                       rawPath.endsWith('.date');
                    
                    // Auto-raw only for content fields (entry.content, content, etc.)
                    const lastSegment = rawPath.split('.').pop();
                    if (lastSegment === 'content' || /\.content$/.test(rawPath)) {
                        return String(value);
                    }
                    
                    // Handle date formatting
                    if (isDateField) {
                        if (formatFilter) {
                            // Use custom format filter
                            return formatDate(value, formatFilter);
                        } else {
                            // Default to date-only format for date fields
                            return formatValueForOutput(value, rawPath);
                        }
                    } else if (rawPath === 'date' && (arg || formatFilter)) {
                        // Legacy support: { date "format" } or { date format "format" }
                        return formatDate(value, arg || formatFilter);
                    }
                    
                    return escapeHtml(String(value));
                });
                
                // NOW apply conditionals after variables are replaced
                const beforeConditionals = itemOutput.length;
                if (beforeConditionals > 0 && itemOutput.includes('{if ')) {
                    Birkly.log('Birkly: Before conditionals - length:', beforeConditionals, 'loopData keys:', Object.keys(loopData));
                }
                itemOutput = renderConditionals(itemOutput, loopData, itemVar);
                // Clean up any remaining conditional markers from the loop item (safety)
                // This ensures that when the loop is replaced and renderConditionals is called on the full template,
                // we don't have leftover conditional markers from the loop items
                // Use more comprehensive regex patterns to catch all variations
                itemOutput = itemOutput.replace(/\{if\s+[^}]+\}/gi, '');
                itemOutput = itemOutput.replace(/\{elseif\s+[^}]+\}/gi, '');
                itemOutput = itemOutput.replace(/\{else\}/gi, '');
                itemOutput = itemOutput.replace(/\{endif\}/gi, '');
                // Also handle any whitespace variations
                itemOutput = itemOutput.replace(/\{\s*if\s+[^}]+\s*\}/gi, '');
                itemOutput = itemOutput.replace(/\{\s*elseif\s+[^}]+\s*\}/gi, '');
                itemOutput = itemOutput.replace(/\{\s*else\s*\}/gi, '');
                itemOutput = itemOutput.replace(/\{\s*endif\s*\}/gi, '');
                // Also strip any leftover template-function conditional keys (safety)
                itemOutput = itemOutput.replace(/\{if\s+__tf_[^}]+\}/gi, '');
                itemOutput = itemOutput.replace(/\{elseif\s+__tf_[^}]+\}/gi, '');
                const afterConditionals = itemOutput.length;
                if (beforeConditionals > 0 && afterConditionals === 0) {
                    Birkly.warn('Birkly: Warning - itemOutput became empty after conditionals. Before:', beforeConditionals, 'After:', afterConditionals, 'loopData keys:', Object.keys(loopData));
                } else if (beforeConditionals > 1000 && afterConditionals < 100) {
                    Birkly.warn('Birkly: Warning - itemOutput shrunk significantly after conditionals. Before:', beforeConditionals, 'After:', afterConditionals, 'loopData keys:', Object.keys(loopData));
                }
                
                // Nested loops with 'in' clause are already processed above in the main loop handler
                // This section only handles loops without 'in' clause (like {for each tag})
                
                if (itemOutput.length === 0 && loopContent.length > 0) {
                    Birkly.warn('Birkly: itemOutput is empty after processing, but loopContent was not empty. This suggests conditionals evaluated to false. loopData keys:', Object.keys(loopData));
                }
                return itemOutput;
            }).join('');
            
            // IMPORTANT: String.replace() only replaces the opening {for ...} tag.
            // We return a token here, and after replace() we remove the original loopContent + matching {endfor}.
            Birkly.log('Birkly: Loop placeholder pushed, rendered length:', __rendered.length, 'items:', items.length, 'sample:', __rendered.substring(0, 120));
            __loopPlaceholders.push({ token: __token, loopContent, rendered: __rendered });
            const matchPos = output.indexOf(match);
            output = output.substring(0, matchPos) + __token + output.substring(matchPos + match.length);
            loopHeader = parseForLoopHeader(output);
        }

        // Post-process: replace each token + original loopContent + matching {endfor}/{endforeach}
        // with the already-rendered HTML. Process in REVERSE order (innermost loops first) so that
        // when we replace an outer loop, the content after its token hasn't been altered by inner replacements.
        for (let pi = __loopPlaceholders.length - 1; pi >= 0; pi--) {
            const ph = __loopPlaceholders[pi];
            const idx = output.indexOf(ph.token);
            if (idx === -1) continue;
            const afterTokenIdx = idx + ph.token.length;
            const contentStart = afterTokenIdx;
            const contentEnd = contentStart + ph.loopContent.length;
            let replaceEnd = contentEnd;
            let endLen = 0;
            const afterContent = output.substring(contentEnd);
            const endM = afterContent.match(/^\s*\{\s*(endfor|endforeach)\s*\}/);
            if (endM) {
                endLen = endM[0].length;
                replaceEnd = contentEnd + endLen;
            }
            // If the expected loopContent is present and we found the end marker, do full replacement.
            if (output.substring(contentStart, contentEnd) === ph.loopContent && endLen > 0) {
                output = output.substring(0, idx) + ph.rendered + output.substring(replaceEnd);
                Birkly.log('Birkly: Post-process replaced full loop block, rendered length:', ph.rendered.length);
                continue;
            }
            // Content mismatch or no end marker: find matching {endfor} by counting nested loops, then replace full block.
            let pos = contentStart;
            let depth = 1;
            while (pos < output.length && depth > 0) {
                const fromHere = output.substring(pos);
                const nextFor = fromHere.search(/\{for\s+(?:each\s+)?/);
                const nextEnd = fromHere.search(/\{\s*(?:endfor|endforeach)\s*\}/);
                const iFor = nextFor >= 0 ? pos + nextFor : output.length;
                const iEnd = nextEnd >= 0 ? pos + nextEnd : output.length;
                if (iEnd < iFor) {
                    const endMatch = output.substring(iEnd).match(/^\{\s*(endfor|endforeach)\s*\}/);
                    const skip = endMatch ? endMatch[0].length : 7;
                    depth--;
                    if (depth === 0) {
                        endLen = skip;
                        replaceEnd = iEnd + endLen;
                        break;
                    }
                    pos = iEnd + skip;
                } else if (iFor < output.length) {
                    depth++;
                    pos = iFor + 1;
                } else break;
            }
            if (replaceEnd > contentEnd) {
                output = output.substring(0, idx) + ph.rendered + output.substring(replaceEnd);
                Birkly.log('Birkly: Post-process replaced loop block (by scan), rendered length:', ph.rendered.length);
            } else {
                output = output.replace(ph.token, ph.rendered);
            }
        }
        
        // NOTE: {entry for} blocks are already processed in processTemplates() BEFORE renderTemplate is called
        // They are replaced with placeholders in the DOM, so we don't need to process them here
        
        // Handle {public_form 'collection'} directive - replace with placeholder
        // We'll process these after template rendering is complete
        output = output.replace(/\{public_form\s+['"]([^'"]+)['"]\}/g, (match, collectionSlug) => {
            const placeholderId = 'birkly-form-placeholder-' + Math.random().toString(36).substr(2, 9);
            // Store form info in data attribute for later processing
            return '<div id="' + placeholderId + '" data-birkly-collection="' + escapeHtml(collectionSlug) + '" class="birkly-form-placeholder">Loading form...</div>';
        });
        
        // Handle nested loops for single entry pages (e.g., {for each tag} when rendering a single entry)
        // IMPORTANT: Process this BEFORE single-brace variable replacement to prevent { tag } from being replaced prematurely
        // This processes nested loops that reference properties of the current entry (like tags)
        let nestedLoopProcessed = true;
        while (nestedLoopProcessed) {
            nestedLoopProcessed = false;
            // Match {for each tag} or {for each tags} - handle optional spaces
            const nestedLoopRegex = /\{for\s+each\s+([a-zA-Z0-9_]+)\s*\}/;
            const nestedMatch = nestedLoopRegex.exec(output);
            if (nestedMatch) {
                nestedLoopProcessed = true;
                const fullMatch = nestedMatch[0];
                const propName = nestedMatch[1];
                Birkly.log('Birkly: Processing nested loop for property:', propName, 'in single entry context');
                
                // Check if it's a property in the data object (e.g., tags)
                // Try singular form first, then plural form (e.g., 'tag' -> 'tags')
                let array = data[propName];
                Birkly.log('Birkly: Checking property:', propName, 'value:', array, 'type:', typeof array, 'isArray:', Array.isArray(array));
                // If property doesn't exist or is not an array, try plural form
                if (!array || !Array.isArray(array)) {
                    const pluralForm = propName + 's';
                    array = data[pluralForm];
                    Birkly.log('Birkly: Trying plural form:', pluralForm, 'value:', array, 'isArray:', Array.isArray(array));
                }
                // If still not an array, default to empty array
                if (!array || !Array.isArray(array)) {
                    array = [];
                    Birkly.log('Birkly: Property not found or not an array, defaulting to empty array');
                } else {
                    Birkly.log('Birkly: Found array with', array.length, 'items:', array);
                }
                
                // Only remove the loop if it's not an array (empty arrays should still process)
                if (!Array.isArray(array)) {
                    // Remove the entire loop
                    const startPos = output.indexOf(fullMatch);
                    const endPos = output.indexOf('{endfor', startPos);
                    if (endPos !== -1) {
                        output = output.substring(0, startPos) + output.substring(endPos + 7);
                    } else {
                        output = output.replace(fullMatch, '');
                    }
                    continue;
                }
                
                // Find the matching {endfor}
                const startPos = output.indexOf(fullMatch);
                let pos = startPos + fullMatch.length;
                let depth = 1;
                let endPos = -1;
                let endPosClosingBrace = -1;
                
                while (pos < output.length && depth > 0) {
                    const nextFor = output.indexOf('{for', pos);
                    const nextEndFor = output.indexOf('{endfor', pos);
                    
                    if (nextFor !== -1 && nextEndFor !== -1 && nextFor < nextEndFor) {
                        depth++;
                        pos = nextFor + 4;
                    } else if (nextEndFor !== -1) {
                        // Find the closing brace of {endfor}
                        const closingBracePos = output.indexOf('}', nextEndFor + 7);
                        if (closingBracePos !== -1) {
                            depth--;
                            if (depth === 0) {
                                endPos = nextEndFor; // Position of {endfor (before the closing brace)
                                endPosClosingBrace = closingBracePos; // Position of the closing brace
                                break;
                            }
                            pos = closingBracePos + 1;
                        } else {
                            // No closing brace found, treat {endfor as the end
                            depth--;
                            if (depth === 0) {
                                endPos = nextEndFor;
                                endPosClosingBrace = nextEndFor + 7; // Assume {endfor is 7 chars
                                break;
                            }
                            pos = nextEndFor + 7;
                        }
                    } else {
                        break;
                    }
                }
                
                if (endPos === -1) {
                    output = output.replace(fullMatch, '');
                    continue;
                }
                
                // Extract content between {for each tag} and {endfor} (not including {endfor})
                const nestedLoopContent = output.substring(startPos + fullMatch.length, endPos);
                Birkly.log('Birkly: Nested loop content:', nestedLoopContent.substring(0, 100));
                
                // Render each array item
                const rendered = array.map(tagItem => {
                    Birkly.log('Birkly: Processing tag item:', tagItem);
                    let tagOutput = nestedLoopContent;
                    
                    // Replace the loop variable first ({ tag } or { tags })
                    // Match both singular and plural forms
                    const tagVarRegex = new RegExp(`\\{\\s*${propName}\\s*\\}`, 'g');
                    const tagVarPluralRegex = new RegExp(`\\{\\s*${propName}s\\s*\\}`, 'g');
                    
                    tagOutput = tagOutput.replace(tagVarRegex, () => {
                        const replaced = typeof tagItem === 'string' ? escapeHtml(tagItem) : tagItem;
                        Birkly.log('Birkly: Replaced {', propName, '} with:', replaced);
                        return replaced;
                    });
                    tagOutput = tagOutput.replace(tagVarPluralRegex, () => {
                        const replaced = typeof tagItem === 'string' ? escapeHtml(tagItem) : tagItem;
                        Birkly.log('Birkly: Replaced {', propName, 's} with:', replaced);
                        return replaced;
                    });
                    
                    // Then replace all other variables that refer to the entry data (e.g., { title }, { slug })
                    // This allows variables like { title } to work inside nested loops
                    tagOutput = tagOutput.replace(/\{\s*([a-zA-Z0-9_]+)\s*\}/g, (m, name) => {
                        name = name.trim();
                        const lowers = name.toLowerCase();
                        // Skip directive keywords and the loop variable (already replaced)
                        if (['if','for','endif','endfor','foreach','endforeach','from','get','has','include','media','url','count'].includes(lowers)) return m;
                        if (name === propName || name === propName + 's') return m; // Already replaced above
                        
                        // Otherwise, try to get from the data object
                        if (data && Object.prototype.hasOwnProperty.call(data, name)) {
                            if (name === 'content') return (data[name] || '');
                            return escapeHtml(data[name] || '');
                        }
                        
                        return '';
                    });
                    
                    Birkly.log('Birkly: Final tag output:', tagOutput);
                    return tagOutput;
                }).join('');
                
                Birkly.log('Birkly: Rendered nested loop content, length:', rendered.length, 'first 100 chars:', rendered.substring(0, 100));
                
                // Replace the nested loop with rendered content
                // endPos is the position of {endfor, endPosClosingBrace is the position of the closing brace
                const replaceEndPos = endPosClosingBrace !== -1 ? endPosClosingBrace + 1 : endPos + 8;
                output = output.substring(0, startPos) + rendered + output.substring(replaceEndPos);
            }
        }
        
        // Replace {{ variable }} outside loops
        output = output.replace(/\{\{\s*([a-zA-Z0-9_\.]+)(?:\s*\|\s*([a-zA-Z0-9_]+)(?::\s*"([^"]+)")?)?\s*\}\}/g, (match, varName, filter, filterValue) => {
            let value = getNestedValue(data, varName) || '';
            
            if (filter === 'date' && filterValue) {
                value = formatDate(value, filterValue);
            } else if (filter === 'raw') {
                return value;
            } else if (filter === 'escape' || !filter) {
                value = escapeHtml(value);
            }
            
            return value;
        });
        
        // Support quoted paths outside loops: {'content'.0.'value'} or {'excerpt'} or {'created_at'}
        // Match pattern: {'part1'.'part2'} or {'part1'.0.'part2'} or {'part1'.0.1.'part2'}
        output = output.replace(/\{\s*((?:['"][^'"]+['"]|\.?\d+\.?)+)\s*\}/g, (m, pathStr) => {
            // Skip if it's part of a directive (check if it starts with a directive keyword)
            const directiveKeywords = ['if','for','endif','endfor','foreach','endforeach','from','get','has','include','media','url','count','endget'];
            const firstPartMatch = pathStr.match(/^['"]([^'"]+)['"]/);
            if (firstPartMatch && directiveKeywords.includes(firstPartMatch[1].toLowerCase())) return m;
            
            // Parse path like: 'content'.0.'value' → ['content', '0', 'value']
            // Handle both quoted strings and numbers
            const pathParts = [];
            // Match quoted strings OR numbers (with optional dots)
            const pathMatch = pathStr.matchAll(/['"]([^'"]+)['"]|(\d+)/g);
            for (const pm of pathMatch) {
                if (pm[1]) pathParts.push(pm[1]); // quoted string
                else if (pm[2]) pathParts.push(pm[2]); // number
            }
            
            if (pathParts.length === 0) return m; // Don't replace if we can't parse
            
            const value = getNestedValue(data, pathParts.join('.'));
            if (value === null || value === undefined) return '';
            
            // Auto-raw for content fields
            if (pathParts.includes('content') || pathParts[pathParts.length - 1] === 'content') {
                return String(value);
            }
            
            // Use the helper function to format the value (handles dates automatically)
            const fieldName = pathParts.join('.');
            return formatValueForOutput(value, fieldName);
        });
        
        // Support single-brace variables outside loops: { content }, { site_title }, etc.
        // Handle variables with spaces: { site_title } -> site_title
        // IMPORTANT: Process this AFTER nested loops to prevent { tag } from being replaced prematurely
        // Use a while loop to track positions correctly
        let lastIndex = 0;
        let result = '';
        // Updated regex to handle:
        // - Dot notation: entry.title, profile.connections
        // - Array indices: content.0.value
        // - Property access: array.length
        // - Fallback operator: value || 0, value || 'default'
        // - Format filter: { created_at format "Y-m-d" } or { created_at format 'Y-m-d' }
        // Match: { varName } or { varName format "format" } or { varName || fallback }
        const varRegex = /\{\s*([a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*(?:\.[0-9]+(?:\.[a-zA-Z0-9_]+)*)?)\s*(?:format\s+(["'])([^"']+)\2)?\s*(?:\|\|\s*([^}]+))?\s*\}/g;
        let varMatch;
        
        while ((varMatch = varRegex.exec(output)) !== null) {
            const matchStart = varMatch.index;
            const matchEnd = varMatch.index + varMatch[0].length;
            const varName = varMatch[1].trim();
            const formatFilter = varMatch[3] || null; // format string (without quotes)
            const fallback = varMatch[4] ? varMatch[4].trim() : null;
            
            // Add everything before this match
            result += output.substring(lastIndex, matchStart);
            
            const lowers = varName.toLowerCase();
            
            // Skip if it's a directive keyword (these should already be processed)
            // Check context to see if it's part of a directive
            const before = output.substring(Math.max(0, matchStart - 20), matchStart);
            const after = output.substring(matchEnd, Math.min(output.length, matchEnd + 20));
            
            // Check if this is part of a directive (if, for, etc.)
            // For variables like "entry" in "{if entry exist}", we need to check the context
            const directiveKeywords = ['if','for','endif','endfor','foreach','endforeach','from','get','has','include','media','url','count','public_form','entry','endentry','else'];
            const isDirectiveKeyword = directiveKeywords.includes(lowers);
            
            // Check if it's in a directive context
            // Examples: "{if entry exist}" - "entry" should NOT be replaced, "exist" should NOT be replaced
            // Examples: "{ entry.author_name }" - "entry" is NOT in a directive, should be replaced
            const isInDirectiveContext = after.match(/^\s+(for|each|in|has|exist|==|!=|<=|>=|>|<)/) || 
                                        (before.match(/if\s+$/) && (lowers === 'exist' || after.match(/^\s+exist/))) ||
                                        (before.match(/for\s+(?:each\s+)?$/) && after.match(/^\s+in/));
            
            // Special case: "entry" after "if" and before "exist" should NOT be replaced
            // But "entry.author_name" should ALWAYS be replaced (it's a variable with dots, not a directive)
            // Variables with dots (like "entry.author_name") are NEVER directives
            if (varName.includes('.')) {
                // It's a dotted variable - always replace it (never a directive)
                // Fall through to normal variable replacement
            } else if (isDirectiveKeyword && isInDirectiveContext) {
                // It's a standalone directive keyword without dots in a directive context - keep as-is
                result += varMatch[0];
                lastIndex = matchEnd;
                continue;
            } else if (isInDirectiveContext && !varName.includes('.')) {
                // It's in a directive context and has no dots - keep as-is
                result += varMatch[0];
                lastIndex = matchEnd;
                continue;
            }
            
            // Normal variable replacement (for dotted variables or non-directive variables)
            {
                // It's a regular variable - replace it
                let value = getNestedValue(data, varName);
                
                // Handle .length property
                if (varName.endsWith('.length')) {
                    const basePath = varName.slice(0, -7); // Remove '.length'
                    const baseValue = getNestedValue(data, basePath);
                    if (Array.isArray(baseValue)) {
                        value = baseValue.length;
                    } else if (baseValue && typeof baseValue === 'object' && 'length' in baseValue) {
                        value = baseValue.length;
                    } else {
                        value = null;
                    }
                }
                
                // Handle fallback operator (||)
                if ((value === null || value === undefined || value === '') && fallback) {
                    // Try to parse fallback as number, boolean, or string
                    const fallbackTrimmed = fallback.trim();
                    if (fallbackTrimmed === '0' || /^\d+$/.test(fallbackTrimmed)) {
                        value = parseInt(fallbackTrimmed, 10);
                    } else if (fallbackTrimmed === 'true') {
                        value = true;
                    } else if (fallbackTrimmed === 'false') {
                        value = false;
                    } else if (fallbackTrimmed.startsWith('"') && fallbackTrimmed.endsWith('"')) {
                        value = fallbackTrimmed.slice(1, -1);
                    } else if (fallbackTrimmed.startsWith("'") && fallbackTrimmed.endsWith("'")) {
                        value = fallbackTrimmed.slice(1, -1);
                    } else {
                        // Try to evaluate as nested value
                        const fallbackValue = getNestedValue(data, fallbackTrimmed);
                        value = fallbackValue !== null && fallbackValue !== undefined ? fallbackValue : fallbackTrimmed;
                    }
                }
                
                // Default to empty string if still null/undefined
                if (value === null || value === undefined) {
                    value = '';
                }
                
                if (varName.startsWith('site_elements.')) {
                    // Log site element access for debugging
                    Birkly.log('Birkly: Accessing site element:', varName, 'value:', value, 'type:', typeof value);
                }
                if (varName === 'content' || /\.content$/.test(varName)) {
                    result += value; // auto-raw
                } else {
                    // Check if this is a date field
                    const isDateField = varName === 'created_at' || 
                                       varName === 'updated_at' || 
                                       varName === 'published_at' || 
                                       varName === 'date' ||
                                       varName.endsWith('.created_at') || 
                                       varName.endsWith('.updated_at') || 
                                       varName.endsWith('.published_at') ||
                                       varName.endsWith('.date');
                    
                    if (isDateField && formatFilter) {
                        // Use custom format filter
                        result += formatDate(value, formatFilter);
                    } else if (isDateField) {
                        // Default to date-only format for date fields
                        result += formatValueForOutput(value, varName);
                    } else if (varName === 'date' && (arg || formatFilter)) {
                        // Legacy support: { date "format" } or { date format "format" }
                        result += formatDate(value, arg || formatFilter);
                    } else {
                        // Use the helper function to format the value (handles dates automatically)
                        result += formatValueForOutput(value, varName);
                    }
                }
            }
            
            lastIndex = matchEnd;
        }
        
        // Add remaining content
        result += output.substring(lastIndex);
        output = result;

        // Safety: ensure common raw content placeholder renders even if earlier
        // directive/variable heuristics kept it intact.
        // (Single-entry pages rely heavily on `{ content }`.)
        if (data && typeof data === 'object' && typeof data.content === 'string' && data.content.length) {
            output = output.replace(/\{\s*content\s*\}/g, data.content);
        }
        
        // {set} directives are already processed in preprocessTemplateFunctionCalls
        // Just remove any remaining {set} directives that weren't processed
        output = output.replace(/\{set\s+[^}]+\}/g, '');
        
        // Handle conditionals (variables should already be replaced above)
        // IMPORTANT: The loop replacement should have already processed conditionals for each loop item.
        // But the template may still have conditionals OUTSIDE the loop (like {if query.error exist}).
        // We need to process those, but we should NOT process conditionals that were already processed in the loop.
        // The loop replacement should have removed all conditional markers from the loop content.
        // IMPORTANT:
        // Do NOT aggressively remove `entry.*` / `__tf_*` conditionals here.
        // Loop items already run `renderConditionals()` and then strip remaining markers per-item.
        // Removing markers at the full-template level can erase large chunks of valid output.
        //
        // Robustness guard:
        // If output still contains unexpanded loop markers, running full conditionals would evaluate
        // per-entry conditionals (e.g. visibility = 'public') with global data and exclude almost
        // everything, collapsing output to a few characters. Skip full conditionals and only strip
        // markers so the page at least renders (loop expansion is a separate failure to fix).
        const hasUnexpandedLoop = output.includes('{for each') && output.includes('{endfor');
        const stripConditionalMarkersOnly = () => {
            return output
                .replace(/\{\s*if\s+[^}]+\s*\}/gi, '')
                .replace(/\{\s*elseif\s+[^}]+\s*\}/gi, '')
                .replace(/\{\s*else\s*\}/gi, '')
                .replace(/\{\s*end\s*if\s*\}/gi, '')
                .replace(/\{\s*endif\s*\}/gi, '');
        };
        const beforeConditionalsLength = output.length;
        let conditioned;
        if (hasUnexpandedLoop) {
            Birkly.warn('Birkly: output still has unexpanded loop markers; skipping full conditionals to avoid collapse');
            conditioned = stripConditionalMarkersOnly();
        } else {
            conditioned = renderConditionals(output, data);
            // Fall back when output is broken: empty, very short, or collapsed to a tiny fraction of original
            // (e.g. 55 chars from 25k = conditional logic excluded almost everything incorrectly)
            const collapsed = conditioned.length === 0 ||
                conditioned.length < 50 ||
                (beforeConditionalsLength > 2000 && conditioned.length < beforeConditionalsLength * 0.02);
            if (collapsed) {
                Birkly.error('Birkly: renderConditionals produced collapsed output; falling back to marker-stripping only', {
                    before: beforeConditionalsLength,
                    after: conditioned.length
                });
                conditioned = stripConditionalMarkersOnly();
            }
        }
        output = conditioned;
        if (output.length > 0 && output.length < 100 && beforeConditionalsLength > 1000) {
            Birkly.warn('Birkly: After renderConditionals (with fallback), output is very short:', output.length, 'original length was', beforeConditionalsLength);
        }
        
        // Restore entry block placeholders
        entryBlockPlaceholders.forEach((block) => {
            output = output.replace(block.uniqueId, block.placeholder);
        });
        
        // Final check: if output still contains template tags, log a warning
        if (output.includes('{for') || output.includes('{if') || output.includes('{entry') || output.includes('{endif}') || output.includes('{endfor}')) {
            Birkly.warn('Birkly: renderTemplate finished but output still contains template tags! Output length:', output.length, 'Sample:', output.substring(0, 300));
        } else {
            Birkly.log('Birkly: renderTemplate completed successfully, output length:', output.length);
        }
        
        return output;
    }
    
    /**
     * Extract loop content between {for} and {endfor}
     */
    function extractLoopContent(template, startMatch) {
        const startPos = template.indexOf(startMatch);
        if (startPos === -1) {
            Birkly.warn('Birkly: extractLoopContent - startMatch not found:', startMatch);
            return null;
        }
        
        const afterStart = template.substring(startPos + startMatch.length);
        Birkly.log('Birkly: extractLoopContent - startMatch:', startMatch, 'afterStart length:', afterStart.length);
        Birkly.log('Birkly: extractLoopContent - afterStart sample:', afterStart.substring(0, 300));
        
        // Direct search for {endfor} to debug
        const directEndforIndex = afterStart.indexOf('{endfor');
        if (directEndforIndex !== -1) {
            Birkly.log('Birkly: extractLoopContent - FOUND {endfor at index:', directEndforIndex, 'context:', afterStart.substring(Math.max(0, directEndforIndex - 20), directEndforIndex + 30));
        } else {
            Birkly.log('Birkly: extractLoopContent - {endfor NOT FOUND in afterStart. Full afterStart:', JSON.stringify(afterStart.substring(0, 500)));
        }
        
        // Count how many {endfor} we should find - try multiple patterns
        const endforPatterns = [
            /\{endfor\}/g,
            /\{\s*endfor\s*\}/g,
            /\{endfor\s*\}/g,
            /\{\s*endfor\}/g
        ];
        let endforCount = 0;
        for (const pattern of endforPatterns) {
            const matches = afterStart.match(pattern);
            if (matches) {
                endforCount = matches.length;
                Birkly.log('Birkly: extractLoopContent - found', endforCount, '{endfor} with pattern:', pattern);
                break;
            }
        }
        const endforeachCount = (afterStart.match(/\{endforeach\}/g) || []).length;
        const forCount = (afterStart.match(/\{for\s+(?:each\s+)?[^}]+\}/g) || []).length;
        Birkly.log('Birkly: extractLoopContent - found', endforCount, '{endfor},', endforeachCount, '{endforeach},', forCount, 'nested {for}');
        
        // Also check for {endfor} with different whitespace
        if (endforCount === 0) {
            const endforIndex = afterStart.indexOf('{endfor');
            if (endforIndex !== -1) {
                Birkly.log('Birkly: extractLoopContent - found {endfor at index:', endforIndex, 'context:', afterStart.substring(Math.max(0, endforIndex - 20), endforIndex + 30));
            }
        }
        
        let depth = 1;
        let pos = 0;
        let content = '';
        
        // Use a simpler approach: find all {for} and {endfor} markers and track depth
        const markers = [];
        let searchPos = 0;
        
        // Find all {for...} directives
        const forRegex = /\{for\s+(?:each\s+)?[^}]+\}/g;
        let forMatch;
        while ((forMatch = forRegex.exec(afterStart)) !== null) {
            markers.push({ type: 'for', pos: forMatch.index, end: forMatch.index + forMatch[0].length });
        }
        
        // Find all {endfor} and {endforeach} - handle optional whitespace
        const endforRegex = /\{\s*endfor\s*\}/g;
        let endforMatch;
        while ((endforMatch = endforRegex.exec(afterStart)) !== null) {
            markers.push({ type: 'endfor', pos: endforMatch.index, end: endforMatch.index + endforMatch[0].length });
        }
        
        const endforeachRegex = /\{\s*endforeach\s*\}/g;
        let endforeachMatch;
        while ((endforeachMatch = endforeachRegex.exec(afterStart)) !== null) {
            markers.push({ type: 'endfor', pos: endforeachMatch.index, end: endforeachMatch.index + endforeachMatch[0].length });
        }
        
        // Sort markers by position
        markers.sort((a, b) => a.pos - b.pos);
        
        Birkly.log('Birkly: extractLoopContent - found', markers.length, 'markers:', markers.map(m => m.type + '@' + m.pos));
        
        // Process markers to find matching {endfor}
        // Only add to content when at depth 1 so that sibling loops (e.g. posts then users)
        // are not included in the outer loop's content.
        let currentDepth = 1;
        let lastPos = 0;
        
        for (const marker of markers) {
            // Add content before this marker only when at top level (depth 1)
            if (marker.pos > lastPos && currentDepth === 1) {
                content += afterStart.substring(lastPos, marker.pos);
            }
            
            if (marker.type === 'for') {
                // Nested/sibling {for} - do not include in this loop's content
                currentDepth++;
                lastPos = marker.end;
            } else if (marker.type === 'endfor') {
                currentDepth--;
                lastPos = marker.end;
                // Return when we close a nested loop (depth 2->1) so this loop's content
                // is only up to the first {endfor} that closes a child, not the whole block.
                if (currentDepth === 1) {
                    Birkly.log('Birkly: extractLoopContent - extracted content length (depth 2->1):', content.length);
                    Birkly.log('Birkly: extractLoopContent - extracted content sample:', content.substring(0, 300));
                    return content;
                }
                if (currentDepth === 0) {
                    Birkly.log('Birkly: extractLoopContent - extracted content length (depth 1->0):', content.length);
                    Birkly.log('Birkly: extractLoopContent - extracted content sample:', content.substring(0, 300));
                    return content;
                }
            }
        }
        
        // If we get here, we didn't find a matching {endfor}
        // Try a simpler fallback: find the first {endfor} if depth tracking failed
        if (currentDepth > 0 && markers.length === 0) {
            // No markers found at all - try direct search for {endfor}
            const directEndfor = afterStart.search(/\{\s*endfor\s*\}/);
            if (directEndfor !== -1) {
                Birkly.log('Birkly: extractLoopContent - using fallback, found {endfor at:', directEndfor);
                return afterStart.substring(0, directEndfor);
            }
        }
        
        // Additional fallback: if we're looking for a nested loop (indicated by 'item' variable)
        // and no {endfor} was found, try to find it by looking for the closing </span> tag
        // This handles the case where the outer loop extraction removed the {endfor}
        if (markers.length === 0 && startMatch.includes('item')) {
            // Look for </span> which should close the <span class="tag"> tag
            const spanClose = afterStart.indexOf('</span>');
            if (spanClose !== -1) {
                // Include the </span> tag and everything up to it
                // The loop content should be: <span class="tag">{item}</span>
                const endPos = spanClose + 7; // Length of '</span>'
                const extracted = afterStart.substring(0, endPos);
                Birkly.log('Birkly: extractLoopContent - using nested loop fallback, found </span> at:', spanClose, 'extracted:', JSON.stringify(extracted));
                return extracted;
            }
        }
        
        // Add any remaining content
        if (lastPos < afterStart.length) {
            content += afterStart.substring(lastPos);
        }
        
        // Final fallback: use first occurrence of "{endfor" or "{ endfor" or "{endforeach" so we never leave loop unexpanded
        const endforFallback = afterStart.match(/\{\s*endfor\s*\}/);
        const endforeachFallback = afterStart.match(/\{\s*endforeach\s*\}/);
        let fallbackEnd = -1;
        if (endforFallback && endforFallback.index !== undefined) {
            fallbackEnd = endforFallback.index;
        }
        if (endforeachFallback && endforeachFallback.index !== undefined) {
            const idx = endforeachFallback.index;
            if (fallbackEnd === -1 || idx < fallbackEnd) fallbackEnd = idx;
        }
        if (fallbackEnd !== -1) {
            Birkly.log('Birkly: extractLoopContent - using final fallback, content up to index:', fallbackEnd);
            return afterStart.substring(0, fallbackEnd);
        }
        
        Birkly.log('Birkly: extractLoopContent - failed to extract content, depth:', currentDepth, 'markers found:', markers.length);
        return null;
    }
    
    /**
     * Render conditionals {if} ... {endif}
     * Now uses module function for better organization
     */
    function renderConditionals(template, data, itemPrefix = '') {
        return Birkly.conditionals.renderConditionals(template, data, itemPrefix);
    }
    
    // Legacy function body removed - using module implementation above
    // Original implementation preserved in birkly-client-modules/birkly-conditionals.js
    // Old implementation commented out below for reference (can be removed later)
    /*
    function renderConditionals_OLD(template, data, itemPrefix = '') {
        // Supports:
        // - {if x}
        // - {if x exist}
        // - {if x has y}
        // - {if x == 'value'}
        // - {else}
        // - {endif}
        const evalCondition = (raw) => {
            const cond = String(raw || '').trim();
            if (!cond) return false;

            // Comparison operators: canonical '=' plus common variants/compat
            // Allowed: =, ==, !=, <, >, <=, >=, =<, =>
            const comparison = cond.match(/^(.+?)\s*(>=|<=|=<|=>|>|<|==|!=|=)\s*(.+?)\s*$/);
            if (comparison) {
                const leftPath = comparison[1].trim();
                let operator = comparison[2];
                const rightValue = comparison[3].trim();

                // Normalize aliases for clarity
                if (operator === '=') operator = '==';
                if (operator === '=<') operator = '<=';
                if (operator === '=>') operator = '>=';
                
                // Get left value (handle .length property)
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
                
                // Get right value (could be a number or variable)
                let right = rightValue;
                // Remove quotes if present
                if ((rightValue.startsWith('"') && rightValue.endsWith('"')) || 
                    (rightValue.startsWith("'") && rightValue.endsWith("'"))) {
                    right = rightValue.slice(1, -1);
                } else if (!isNaN(rightValue)) {
                    // It's a number
                    right = parseFloat(rightValue);
                } else {
                    // It's a variable path
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
                }
                
                // Convert to numbers if both are numeric
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
                    // String comparison
                    const leftStr = String(leftValue ?? '');
                    const rightStr = String(right ?? '');
                    switch (operator) {
                        case '==': return leftStr === rightStr;
                        case '!=': return leftStr !== rightStr;
                        default: return false; // >, <, >=, <= don't make sense for strings
                    }
                }
            }

            // == comparison (string equality)
            const eq = cond.match(/^(.+?)\s*==\s*['"]([^'"]*)['"]\s*$/);
            if (eq) {
                const varPath = eq[1].trim();
                const expected = eq[2];
                const value = getNestedValue(data, varPath);
                return String(value ?? '') === expected;
            }

            // "has" property
            const has = cond.match(/^([^\s]+)\s+has\s+([^\s]+)\s*$/);
            if (has) {
                const obj = getNestedValue(data, has[1]);
                let prop = has[2];
                // Strip quotes from property name if present: 'excerpt' → excerpt
                if ((prop.startsWith("'") && prop.endsWith("'")) || (prop.startsWith("\"") && prop.endsWith("\""))) {
                    prop = prop.slice(1, -1);
                }
                return !!(obj && typeof obj === 'object' && obj[prop] !== undefined && obj[prop] !== null && obj[prop] !== '');
            }

            // "exist" check - must come before simple truthy check
            const exist = cond.match(/^(.+?)\s+exist\s*$/);
            if (exist) {
                const varPath = exist[1].trim();
                // Support "'collection'.'identifier' exist" syntax
                let value = resolveCollectionEntryRef(varPath, data);
                if (value === undefined) {
                    value = getNestedValue(data, varPath);
                }
                // For arrays, check if it exists and has length > 0
                if (Array.isArray(value)) {
                    return value.length > 0;
                }
                // For objects, check if it exists and is not empty
                if (value && typeof value === 'object') {
                    return Object.keys(value).length > 0;
                }
                return value !== null && value !== undefined && value !== '';
            }

            // simple truthy
            const value = getNestedValue(data, cond);
            return isTruthyTemplateValue(value);
        };

        const directiveRe = /\{(if\s+[^}]+|elseif\s+[^}]+|else|endif)\}/g;
        let out = '';
        let lastIdx = 0;

        const stack = []; // {cond: boolean, inElse: boolean, matched: boolean}
        const recomputeInclude = () => {
            if (stack.length === 0) return true;
            // Check all conditionals in the stack - all must be "matched" for content to be included
            // This handles nested conditionals correctly
            // IMPORTANT: We check from the top of the stack down, so that nested conditionals
            // that are false don't prevent content outside their block from rendering
            for (let i = stack.length - 1; i >= 0; i--) {
                if (!stack[i].matched) {
                    if (Birkly.debug && template.length > 500) {
                        Birkly.log('Birkly: recomputeInclude returning false, stack:', stack.map(s => ({matched: s.matched, inElse: s.inElse})));
                    }
                    return false;
                }
            }
            return true;
        };

        let include = true;
        let m;
        while ((m = directiveRe.exec(template)) !== null) {
            const text = template.slice(lastIdx, m.index);
            if (include && text) {
                out += text;
                if (Birkly.debug && text.length > 100 && template.length > 500) {
                    Birkly.log('Birkly: Adding text to output, length:', text.length, 'include:', include, 'stack length:', stack.length, 'stack matched:', stack.map(s => s.matched));
                }
            } else if (Birkly.debug && text.length > 100 && template.length > 500) {
                Birkly.log('Birkly: Skipping text, length:', text.length, 'include:', include, 'stack length:', stack.length, 'stack matched:', stack.map(s => s.matched));
            }

            const token = m[1];
            if (token.startsWith('if ')) {
                const condStr = token.slice(3);
                const condVal = evalCondition(condStr);
                // Log conditionals when debugging or when output is suspiciously short
                if (Birkly.debug || condStr.includes('query.') || (out.length < 100 && template.length > 1000)) {
                    Birkly.log('Birkly: Evaluating conditional:', condStr, 'result:', condVal, 'data keys:', Object.keys(data), 'stack length:', stack.length);
                }
                stack.push({ cond: condVal, inElse: false, matched: condVal });
                include = recomputeInclude();
                if (condStr.includes('query.') && !condVal && template.length > 1000) {
                    Birkly.log('Birkly: Query conditional is false, include:', include, 'stack:', stack.map(s => ({matched: s.matched})));
                }
            } else if (token.startsWith('elseif ')) {
                if (stack.length) {
                    const condStr = token.slice(7);
                    const condVal = evalCondition(condStr);
                    const last = stack[stack.length - 1];
                    // elseif is only true if previous conditions (if/elseif) were false AND this condition is true
                    // If we already matched a previous branch, this elseif should not match
                    if (!last.matched && condVal) {
                        last.cond = condVal;
                        last.inElse = true;
                        last.matched = true; // This elseif matches
                    } else {
                        last.inElse = true;
                        // Keep matched as false if we already matched or this condition is false
                    }
                    include = recomputeInclude();
                }
            } else if (token === 'else') {
                if (stack.length) {
                    const last = stack[stack.length - 1];
                    last.inElse = true;
                    // else matches only if no previous branch (if/elseif) matched
                    if (!last.matched) {
                        last.matched = true; // else matches
                    }
                    include = recomputeInclude();
                }
            } else if (token === 'endif') {
                if (stack.length) {
                    stack.pop();
                    include = recomputeInclude();
                    // Log when stack becomes empty after endif (important for debugging)
                    if (stack.length === 0 && template.length > 1000 && out.length < 100) {
                        Birkly.log('Birkly: Stack became empty after endif, include:', include, 'remaining template length:', template.length - lastIdx);
                    }
                } else {
                    // Orphaned endif - log warning but don't break
                    if (Birkly.debug || template.length > 1000) {
                        Birkly.warn('Birkly: Orphaned {endif} found - no matching {if} in stack');
                    }
                }
            }

            lastIdx = m.index + m[0].length;
        }

        const tail = template.slice(lastIdx);
        if (include && tail) {
            out += tail;
            if (Birkly.debug && tail.length > 100 && template.length > 500) {
                Birkly.log('Birkly: Adding tail text, length:', tail.length, 'include:', include, 'stack length:', stack.length);
            }
        } else if (Birkly.debug && tail.length > 100 && template.length > 500) {
            Birkly.log('Birkly: Skipping tail text, length:', tail.length, 'include:', include, 'stack length:', stack.length, 'stack:', stack.map(s => ({matched: s.matched, inElse: s.inElse})));
        }
        
        // Check if output is suspiciously short compared to template
        // This can happen if conditionals are incorrectly filtering content
        if (out.length < 100 && template.length > 1000) {
            Birkly.warn('Birkly: renderConditionals output is very short:', out.length, 'template length:', template.length, 'final stack length:', stack.length, 'final include:', include);
            Birkly.warn('Birkly: Stack details:', stack.map((s, i) => ({index: i, matched: s.matched, inElse: s.inElse, cond: s.cond})));
            
            // Check if there are unmatched conditionals
            const unmatchedCount = stack.filter(s => !s.matched).length;
            if (unmatchedCount > 0) {
                const firstUnmatched = stack.findIndex(s => !s.matched);
                Birkly.warn('Birkly: Found', unmatchedCount, 'unmatched conditionals in stack. First unmatched at index:', firstUnmatched);
                // Check what conditional is at that position by looking at the template
                const conditionalMatches = Array.from(template.matchAll(/\{(if\s+[^}]+|elseif\s+[^}]+|else|endif)\}/g));
                if (firstUnmatched < conditionalMatches.length) {
                    Birkly.warn('Birkly: First unmatched conditional text:', conditionalMatches[firstUnmatched][0]);
                }
            }
            
            // Also check if there are unclosed conditionals (stack not empty at end)
            if (stack.length > 0) {
                Birkly.warn('Birkly: Warning - stack is not empty at end of renderConditionals. This suggests unclosed conditionals.');
                // Try to find what conditionals are still open
                const remainingConditionals = template.match(/\{(if\s+[^}]+|elseif\s+[^}]+|else|endif)\}/g);
                if (remainingConditionals) {
                    Birkly.warn('Birkly: Remaining conditional markers in template:', remainingConditionals.slice(0, 20));
                }
            }
            
            // Safety: If output is very short, try to recover by rendering the tail
            // This is a fallback to prevent complete rendering failure
            Birkly.warn('Birkly: Output is very short (', out.length, ') but template is large (', template.length, '). Attempting recovery.');
            Birkly.warn('Birkly: Stack state:', stack.map((s, i) => ({index: i, matched: s.matched, inElse: s.inElse})));
            Birkly.warn('Birkly: Recovery - lastIdx:', lastIdx, 'template length:', template.length, 'tail length:', template.slice(lastIdx).length);
            
            // If stack has unmatched items, try to close them
            if (unmatchedCount > 0) {
                Birkly.warn('Birkly: Found', unmatchedCount, 'unmatched conditionals. Closing them to allow content to render.');
                // Pop all unmatched conditionals from the stack
                while (stack.length > 0 && !stack[stack.length - 1].matched) {
                    stack.pop();
                }
            }
            
            // Now try to render the tail - this is critical for recovery
            const recoveryTail = template.slice(lastIdx);
            if (recoveryTail && recoveryTail.length > 0) {
                Birkly.warn('Birkly: Recovery - processing tail, length:', recoveryTail.length, 'first 200 chars:', recoveryTail.substring(0, 200));
                // Remove any conditional and loop markers from the tail and add it
                // This allows content that was skipped due to false conditionals to be rendered
                // But we need to remove unprocessed loop directives to prevent duplication
                const cleanedTail = recoveryTail
                    .replace(/\{if\s+[^}]+\}/g, '')
                    .replace(/\{elseif\s+[^}]+\}/g, '')
                    .replace(/\{else\}/g, '')
                    .replace(/\{endif\}/g, '')
                    .replace(/\{for\s+(?:each\s+)?[^}]+\}/g, '') // Remove unprocessed loop directives
                    .replace(/\{endfor\}/g, '')
                    .replace(/\{endforeach\}/g, '');
                if (cleanedTail.length > 0) {
                    out += cleanedTail;
                    Birkly.warn('Birkly: Recovery - added', cleanedTail.length, 'characters from tail. New output length:', out.length);
                } else {
                    Birkly.warn('Birkly: Recovery - tail was empty after cleaning conditional markers. Original tail length:', recoveryTail.length);
                }
            } else {
                Birkly.warn('Birkly: Recovery - no tail to process (lastIdx:', lastIdx, 'template length:', template.length, ')');
                // If there's no tail but the template is much longer, something went wrong
                if (lastIdx >= template.length - 10 && template.length > 1000) {
                    Birkly.warn('Birkly: Recovery - lastIdx is at end of template, but output is very short. This suggests all content was skipped.');
                }
            }
        }

        // Remove any remaining conditional markers that weren't processed (safety cleanup)
        out = out.replace(/\{if\s+[^}]+\}/g, '');
        out = out.replace(/\{elseif\s+[^}]+\}/g, '');
        out = out.replace(/\{else\}/g, '');
        out = out.replace(/\{endif\}/g, '');
        
        // Remove any remaining loop markers that weren't processed (safety cleanup)
        // This prevents unprocessed loop directives from appearing in the output
        out = out.replace(/\{for\s+(?:each\s+)?[^}]+\}/g, '');
        out = out.replace(/\{endfor\}/g, '');
        out = out.replace(/\{endforeach\}/g, '');
        return out;
    }
    */
    
    /**
     * Get nested value from object (e.g., "entry.title" or "content.0.value")
     * Now uses module function
     */
    // Functions now use module references (defined above as aliases)
    
    /**
     * Fetch site settings from API
     * Note: This is for backward compatibility only.
     * Websites should define their own site_title in their templates.
     * The CMS doesn't manage site titles - it just provides content.
     */
    async function fetchSiteSettings() {
        try {
            const apiData = await fetchContent('get_site_settings', {});
            if (apiData && apiData.status === 'ok' && apiData.data.settings) {
                return apiData.data.settings;
            }
        } catch (error) {
            Birkly.warn('Birkly: Could not fetch site settings:', error);
        }
        // Return empty settings - websites should define their own site_title
        return {
            title: '',
            name: '',
            description: ''
        };
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
    
    /**
     * Process all templates in the page
     */
    // Strip remaining template directives and variable placeholders so we can still update the DOM
    // when renderTemplate left tags in place (avoids showing raw {if}, {{ x }}, etc.)
    function stripRemainingTemplateTags(html) {
        if (!html || typeof html !== 'string') return html || '';
        let out = html;
        // Remove conditional and loop directives (with optional whitespace)
        out = out.replace(/\{\s*if\s+[^}]+\s*\}/gi, '');
        out = out.replace(/\{\s*elseif\s+[^}]+\s*\}/gi, '');
        out = out.replace(/\{\s*else\s*\}/gi, '');
        out = out.replace(/\{\s*endif\s*\}/gi, '');
        out = out.replace(/\{\s*for\s+[^}]+\s*\}/gi, '');
        out = out.replace(/\{\s*endfor\s*\}/gi, '');
        out = out.replace(/\{\s*endforeach\s*\}/gi, '');
        // Remove {{ variable }} and { variable } (only template-like: word chars, dots, pipes)
        out = out.replace(/\{\{\s*[^}]+\s*\}\}/g, '');
        out = out.replace(/\{\s*[a-zA-Z0-9_.]+(?:\s*\|[^}]*)?\s*\}/g, (m) => {
            // Don't strip if it looks like CSS/JS (e.g. { color: red })
            if (/^\{\s*[a-zA-Z0-9_.]+\s*:\s*/.test(m)) return m;
            return '';
        });
        return out;
    }

    // Helper to safely set innerHTML - prevents clearing content if rendered output is suspiciously short
    function safeSetInnerHTML(element, rendered, template, context = '') {
        const originalLength = element.innerHTML.length;
        const templateLength = template ? template.length : 0;
        
        // If rendered output is empty or very short, but original content was substantial, don't replace
        if (rendered.length < 100 && originalLength > 500 && templateLength > 500) {
            Birkly.warn('Birkly: WARNING - Refusing to replace content: rendered too short', {
                context,
                renderedLength: rendered.length,
                originalLength: originalLength,
                templateLength: templateLength,
                renderedSample: rendered.substring(0, 200)
            });
            return false; // Indicate that we didn't replace
        }
        
        // If rendered is empty but template had content, something went wrong
        if (rendered.length === 0 && templateLength > 100) {
            Birkly.warn('Birkly: WARNING - Rendered output is empty but template had content!', {
                context,
                templateSample: template.substring(0, 200)
            });
            return false; // Don't clear existing content
        }
        
        element.innerHTML = rendered;
        return true; // Indicate success
    }
    
    async function processTemplates() {
        // Note: We no longer fetch site_title from CMS settings.
        // Websites should define their own site_title in their templates.
        // The CMS doesn't manage site titles - it just provides content.
        
        // Fetch site elements for use in templates
        const siteElements = await getAllSiteElements();
        
        // Check for URL parameters that might indicate we need a specific entry
        urlParams = new URLSearchParams(window.location.search);
        const slug = urlParams.get('slug');
        let collection = urlParams.get('collection');
        // Expose query params to templates as `query.*` (generic)
        const query = {};
        for (const [k, v] of urlParams.entries()) query[k] = v;
        
        // If no collection specified, detect from template or URL
        if (!collection) {
            collection = detectCollectionFromTemplate();
        }
        
        // IMPORTANT: For project website, the collection is always 'blog', not 'project'
        // The '/project/' path is just the website folder, not the collection name
        if (window.location.pathname.includes('/project/')) {
            // If collection was detected as 'project', change it to 'blog'
            if (collection === 'project') {
                collection = 'blog';
            }
            // If no collection detected, default to 'blog' for demo blog
            if (!collection) {
                collection = 'blog';
            }
        }
        
        // If no collection detected but we have a slug, default to 'blog'
        if (slug && !collection) {
            collection = 'blog';
        }
        
        // If we have a slug parameter, fetch that specific entry first
        if (slug && collection) {
            try {
                const entry = await getEntryBySlug(collection, slug);
                if (entry) {
                    // Prepare data with the specific entry
                    // Note: site_title is not provided by CMS - websites should define their own
                    const data = {
                        current_year: new Date().getFullYear(),
                        title: entry.title || '',
                        content: entry.content || '',
                        slug: entry.slug || slug,
                        created_at: entry.created_at || '',
                        excerpt: entry.excerpt || '',
                        tags: entry.tags || [],
                        entry: entry, // Add entry object for conditionals
                        site_elements: siteElements, // Add site elements for use in templates
                        query: query,
                        current: {
                            language: Birkly.currentLanguage || 'en'
                        },
                        comments: [], // Initialize comments array for prefetch
                        ...entry // Spread all entry properties for direct access
                    };

                    // Note: Template hydration will happen later when templates are collected
                    // We can't hydrate here because templates haven't been extracted from DOM yet
                    // hydrateTemplateFunctionsIntoData will be called for each template individually
                    
                    // NOTE: no project-specific behavior here.
                    // Any additional data should be loaded via generic mechanisms (template functions or generic endpoints).
                    
                    // Single entry pages are easiest/most robust if we render ONE top-level container.
                    // Rendering multiple nested elements can cause partial updates (e.g. only the <h1> updates),
                    // or DOM replacement invalidating later element references.
                    const templates = [];
                    const mainEl = document.querySelector('main');
                    if (mainEl && mainEl.innerHTML && (/\{\s*['"]?[a-zA-Z0-9_]/.test(mainEl.innerHTML) || /\{\{/.test(mainEl.innerHTML))) {
                        templates.push({
                            element: mainEl,
                            originalHtml: mainEl.innerHTML,
                            template: mainEl.innerHTML
                        });
                    } else {
                        // Fallback: render whole body if main isn't present (rare)
                        const bodyEl = document.body;
                        templates.push({
                            element: bodyEl,
                            originalHtml: bodyEl.innerHTML,
                            template: bodyEl.innerHTML
                        });
                    }
                    
                    Birkly.log('Birkly: Found', templates.length, 'templates for single entry page (main container mode)');
                    
                    // Render each template with the entry data
                    for (let template of templates) {
                        try {
                            // Extract prefetch directives from template string BEFORE rendering
                            const prefetchMatches = template.template.matchAll(/<meta\s+[^>]*data-birkly-prefetch[^>]*>/gi);
                            for (const match of prefetchMatches) {
                                const metaHtml = match[0];
                                // Create a temporary element to parse attributes
                                const tempDiv = document.createElement('div');
                                tempDiv.innerHTML = metaHtml;
                                const metaEl = tempDiv.querySelector('meta');
                                if (metaEl) {
                                    const type = metaEl.getAttribute('data-birkly-prefetch');
                                    const into = metaEl.getAttribute('data-birkly-into');
                                    if (type === 'sub_entries' && into) {
                                        const resolve = (s) => {
                                            if (s == null) return '';
                                            const str = String(s);
                                            return str.replace(/\{([^}]+)\}/g, (_, expr) => {
                                                const key = String(expr || '').trim().replace(/\s+/g, ' ');
                                                const normalized = key.replace(/^(\w+)\s*\.\s*/,'$1.').replace(/\s*\.\s*/g,'.');
                                                const v = getNestedValue(data, normalized);
                                                return v == null ? '' : String(v);
                                            });
                                        };
                                        
                                        const parentCollection = resolve(metaEl.getAttribute('data-parent-collection'));
                                        const parentEntryId = resolve(metaEl.getAttribute('data-parent-entry-id'));
                                        const subCollection = resolve(metaEl.getAttribute('data-sub-collection'));
                                        
                                        if (parentCollection && parentEntryId && subCollection) {
                                            Birkly.log('Birkly: Prefetching sub_entries from template:', { parentCollection, parentEntryId, subCollection, into, dataId: data.id, dataKeys: Object.keys(data) });
                                            const subEntries = await fetchSubEntries(parentCollection, parentEntryId, subCollection);
                                            data[into] = Array.isArray(subEntries) ? subEntries : [];
                                            Birkly.log('Birkly: Prefetched', data[into].length, 'sub-entries into', into);
                                            if (data[into].length > 0) {
                                                Birkly.log('Birkly: First comment sample:', JSON.stringify(data[into][0]));
                                            }
                                        } else {
                                            Birkly.warn('Birkly: Prefetch skipped - missing params:', { parentCollection, parentEntryId, subCollection, dataId: data.id });
                                        }
                                    }
                                }
                            }
                            
                            Birkly.log('Birkly: About to render template, data keys:', Object.keys(data), 'data.comments:', data.comments, 'length:', data.comments?.length, 'data.entry:', data.entry);
                            // Load template functions metadata first
                            await loadTemplateFunctionsMeta();
                            await hydrateTemplateFunctionsIntoData(data, template.template);
                            const pre = await preprocessTemplateFunctionCalls(template.template, data);
                            // First pass: core templating (if/for/from/etc.)
                            let rendered = renderTemplate(pre, data);
                            // Second pass: nested field-type loops like {for each item in 'tags'}
                            // Reuse the dedicated nested-loop processor so behaviour matches list pages.
                            rendered = processNestedLoops(rendered, data);
                            Birkly.log('Birkly: Template rendered, checking for comments in output:', rendered.includes('comment'), 'checking for "Post not found":', rendered.includes('Post not found'));
                            if (!safeSetInnerHTML(template.element, rendered, template.template, 'single-entry')) {
                                // Content was not replaced - skip further processing for this template
                                continue;
                            }
                            // As a final safety pass for single-entry pages, ensure tags
                            // are rendered even if the template loop was skipped.
                            try {
                                if (Array.isArray(data.tags) && data.tags.length) {
                                    document.querySelectorAll('.tags').forEach(container => {
                                        // If container has no visible text content, hydrate it from data.tags
                                        if (!container.textContent.trim()) {
                                            container.innerHTML = data.tags
                                                .map(t => `<span class="tag">${t}</span>`)
                                                .join('');
                                        }
                                    });
                                }
                            } catch (e) {
                                Birkly.warn('Birkly: Failed to hydrate tags fallback on single entry page:', e);
                            }
                            // Update page title if title variable exists
                            if (entry.title && (template.template.includes('{ title }') || template.template.includes('{{ title }}'))) {
                                document.title = entry.title + (document.title.includes(' - ') ? document.title.substring(document.title.indexOf(' - ')) : '');
                            }
                        } catch (error) {
                            console.error('Birkly: Error processing template:', error);
                        }
                    }
                    
                    return; // Done processing single entry
                } else {
                    // Entry not found - show error message and process any remaining templates
                    const main = document.querySelector('main');
                    if (main) {
                        main.innerHTML = `
                            <div style="text-align: center; padding: 3rem;">
                                <h1 style="font-size: 2rem; margin-bottom: 1rem;">Post Not Found</h1>
                                <p style="color: #6b7280; margin-bottom: 2rem;">The post you're looking for doesn't exist.</p>
                                <a href="/project/index.html" style="color: #3b82f6; text-decoration: none; font-weight: 600;">← Back to Home</a>
                            </div>
                        `;
                    }
                    
                    // Process remaining templates (header, footer) with default data
                    // Note: site_title is not provided by CMS - websites should define their own
                    const defaultData = {
                        current_year: new Date().getFullYear(),
                        site_elements: siteElements // Add site elements for use in templates
                    };

                    // Process header and footer templates
                    const walker = document.createTreeWalker(
                        document.body,
                        NodeFilter.SHOW_TEXT,
                        null,
                        false
                    );
                    
                    const templates = [];
                    let node;
                    
                    while (node = walker.nextNode()) {
                        if (node.nodeValue && /\{[a-zA-Z0-9_\.\s'"]+\}/.test(node.nodeValue)) {
                            const parent = node.parentElement;
                            if (!parent || parent.dataset.cmsProcessed) continue;
                            
                            parent.dataset.cmsProcessed = 'true';
                            templates.push({
                                element: parent,
                                template: parent.innerHTML
                            });
                        }
                    }
                    
                    // Render templates with default data
                    // Load template functions metadata first
                    await loadTemplateFunctionsMeta();
                    for (let template of templates) {
                        try {
                            await hydrateTemplateFunctionsIntoData(defaultData, template.template);
                            await performPrefetches(defaultData);
                            const pre = await preprocessTemplateFunctionCalls(template.template, defaultData);
                            const rendered = renderTemplate(pre, defaultData);
                            if (!safeSetInnerHTML(template.element, rendered, template.template, 'single-entry')) {
                                // Content was not replaced - skip further processing for this template
                                continue;
                            }
                        } catch (error) {
                            console.error('Birkly: Error processing template:', error);
                        }
                    }
                    
                    return;
                }
            } catch (error) {
                console.error('Birkly: Could not fetch entry by slug:', error);
                console.error('Birkly: Error details:', {
                    slug: slug,
                    collection: collection,
                    error: error.message || error
                });
                
                // Show error message and process remaining templates
                const main = document.querySelector('main');
                if (main) {
                    main.innerHTML = `
                        <div style="text-align: center; padding: 3rem;">
                            <h1 style="font-size: 2rem; margin-bottom: 1rem;">Error Loading Post</h1>
                            <p style="color: #6b7280; margin-bottom: 2rem;">Could not load the post. Please try again later.</p>
                            <a href="/project/index.html" style="color: #3b82f6; text-decoration: none; font-weight: 600;">← Back to Home</a>
                        </div>
                    `;
                }
                
                // Process remaining templates (header, footer) with default data
                // Note: site_title is not provided by CMS - websites should define their own
                const defaultData = {
                    current_year: new Date().getFullYear(),
                    site_elements: siteElements // Add site elements for use in templates
                };
                mergePageBundleIntoData(defaultData);
                
                const walker = document.createTreeWalker(
                    document.body,
                    NodeFilter.SHOW_TEXT,
                    null,
                    false
                );
                
                const templates = [];
                let node;
                
                while (node = walker.nextNode()) {
                    if (node.nodeValue && (/\{\{|\{for|\{if|\{[a-zA-Z0-9_\.\s'"]+\}/.test(node.nodeValue))) {
                        const parent = node.parentElement;
                        if (!parent || parent.dataset.cmsProcessed) continue;
                        
                        parent.dataset.cmsProcessed = 'true';
                        templates.push({
                            element: parent,
                            template: parent.innerHTML
                        });
                    }
                }
                
                // Render templates with default data
                for (let template of templates) {
                    try {
                        await hydrateTemplateFunctionsIntoData(defaultData, template.template);
                        await performPrefetches(defaultData);
                        const pre = await preprocessTemplateFunctionCalls(template.template, defaultData);
                        const rendered = renderTemplate(pre, defaultData);
                        template.element.innerHTML = rendered;
                    } catch (error) {
                        console.error('Birkly: Error processing template:', error);
                    }
                }
                
                return;
            }
        }

        // NOTE: no hardcoded per-page behaviors (like "profile") in the core client.
        
        // STEP 1: Process {entry for 'collection'} blocks FIRST before template detection
        // This prevents the form HTML inside from being detected as separate templates
        const entryBlocks = document.querySelectorAll('*');
        for (const el of entryBlocks) {
            const innerHTML = el.innerHTML || '';
            // Check if this element contains {entry for} directive
            if (innerHTML.includes('{entry for') && innerHTML.includes('{endentry}')) {
                const entryForRegex = /\{entry\s+for\s+['"]([^'"]+)['"]\}/;
                const entryForMatch = innerHTML.match(entryForRegex);
                if (entryForMatch) {
                    const collectionSlug = entryForMatch[1];
                    const startPos = innerHTML.indexOf(entryForMatch[0]);
                    const afterStart = innerHTML.substring(startPos + entryForMatch[0].length);
                    const endPos = afterStart.indexOf('{endentry}');
                    
                    if (endPos !== -1) {
                        const formContent = afterStart.substring(0, endPos);
                        const placeholderId = 'birkly-entry-block-' + Math.random().toString(36).substr(2, 9);
                        // Escape the form content for use in HTML attribute
                        // We need to encode quotes, <, >, &, and newlines properly
                        let escapedContent = escapeHtml(formContent);
                        // Also encode quotes and apostrophes for use in attribute
                        escapedContent = escapedContent.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                        // Encode newlines as spaces to keep it on one line
                        escapedContent = escapedContent.replace(/\n/g, ' ').replace(/\r/g, '');
                        
                        const placeholder = '<div id="' + placeholderId + '" data-birkly-collection="' + escapeHtml(collectionSlug) + '" data-birkly-content="' + escapedContent + '" class="birkly-entry-block" data-birkly-preserved="false">Loading form...</div>';
                        
                        // Replace the entire {entry for}...{endentry} block with placeholder
                        const beforeBlock = innerHTML.substring(0, startPos);
                        // "{endentry}" is 10 characters long; include the full token so that no trailing brace remains
                        const afterBlock = innerHTML.substring(startPos + entryForMatch[0].length + endPos + 10);
                        el.innerHTML = beforeBlock + placeholder + afterBlock;
                        
                        // Mark this element as processed to avoid detecting it again
                        el.dataset.cmsProcessed = 'true';
                        Birkly.log('Birkly: Processed {entry for} block for collection:', collectionSlug);
                    }
                }
            }
        }
        
        // STEP 2: Normal template processing for lists/loops
        // Find ALL elements with template syntax (including in attributes)
        // We need to check both text nodes AND attribute values
        const templates = [];
        const markTemplateSubtreeProcessed = (el) => {
            if (!el) return;
            el.dataset.cmsProcessed = 'true';
            el.querySelectorAll('*').forEach((child) => {
                child.dataset.cmsProcessed = 'true';
            });
        };

        // Prefer a single <main> pass when it contains CMS collection loops (avoids fragmenting
        // hero/marquee/blog into separate templates that abort or strip each other).
        const mainTemplateRoot = document.querySelector('main');
        if (mainTemplateRoot && /\{for\s+(?:each\s+)?[a-zA-Z0-9_]+\s+in\s+['"][^'"]+['"]/.test(mainTemplateRoot.innerHTML || '')) {
            markTemplateSubtreeProcessed(mainTemplateRoot);
            templates.push({
                element: mainTemplateRoot,
                originalHtml: mainTemplateRoot.innerHTML,
                template: mainTemplateRoot.innerHTML
            });
            Birkly.log('Birkly: Registered primary main template root');
        }
        
        // Method 1: Check text nodes (for content)
        const walker = document.createTreeWalker(
            document.body,
            NodeFilter.SHOW_TEXT,
            null,
            false
        );
        
        let node;
        while (node = walker.nextNode()) {
            const nodeValue = node.nodeValue || '';
            // Check for template syntax - look for { or {{ followed by alphanumeric
            // Skip if inside .birkly-entry-block (already processed)
            const parent = node.parentElement;
            if (parent && parent.classList && parent.classList.contains('birkly-entry-block')) {
                continue; // Skip - already processed as entry block
            }
            
            if (nodeValue && (/\{\s*['"]?[a-zA-Z0-9_]/.test(nodeValue) || /\{\{/.test(nodeValue))) {
                // Found template syntax in text (single or double braces)
                if (!parent || parent.dataset.cmsProcessed) continue;
                
                // Skip if parent contains an entry block (it will be processed separately)
                if (parent.querySelector('.birkly-entry-block')) {
                    Birkly.log('Birkly: Skipping template with entry block:', parent.tagName, parent.className);
                    continue;
                }
                
                // Check if this element is inside a collection loop (e.g., {for each entry in 'blog'})
                // If so, skip it - it will be processed as part of the collection loop
                // BUT: Don't skip if this element itself contains a collection loop (it's the parent template)
                const parentText = parent.textContent || parent.innerHTML || '';
                const parentHasCollectionLoop = parentText.match(/\{for\s+(?:each\s+)?[a-zA-Z0-9_]+\s+in\s+['"][^'"]+['"]\}/);
                
                if (!parentHasCollectionLoop) {
                    // Only check ancestors if this element doesn't contain a collection loop itself
                    // We need to check if this element is actually INSIDE a collection loop's content
                    // (i.e., between {for each entry in 'collection'} and {endfor})
                    // Not just if there's a collection loop somewhere in an ancestor
                    let ancestor = parent.parentElement;
                    let isInsideCollectionLoop = false;
                    while (ancestor && ancestor !== document.body) {
                        const ancestorText = ancestor.textContent || ancestor.innerHTML || '';
                        // Check if this ancestor contains a collection loop AND this element is inside its content
                        const collectionLoopMatch = ancestorText.match(/\{for\s+(?:each\s+)?[a-zA-Z0-9_]+\s+in\s+['"]([^'"]+)['"]\}/);
                        if (collectionLoopMatch) {
                            // Found a collection loop in ancestor - check if this element is inside its loop content
                            // The loop content is between the {for} tag and {endfor}
                            const loopStartIndex = ancestorText.indexOf(collectionLoopMatch[0]);
                            const loopEndIndex = ancestorText.indexOf('{endfor}', loopStartIndex);
                            if (loopEndIndex !== -1) {
                                // Get the position of this element's content in the ancestor's text
                                const elementIndex = ancestorText.indexOf(parent.innerHTML);
                                if (elementIndex !== -1 && elementIndex > loopStartIndex && elementIndex < loopEndIndex) {
                                    // This element is inside the collection loop's content
                                    isInsideCollectionLoop = true;
                                    Birkly.log('Birkly: Skipping nested template inside collection loop:', parent.tagName, parent.className);
                                    break;
                                }
                            }
                        }
                        ancestor = ancestor.parentElement;
                    }
                    
                    if (isInsideCollectionLoop) {
                        continue; // Skip - will be processed as part of collection loop
                    }
                }
                
                // Check if this element is a child of an already-detected template
                // If so, skip it - it will be processed as part of the parent template
                let ancestor = parent.parentElement;
                let isChildOfTemplate = false;
                while (ancestor && ancestor !== document.body) {
                    // Check if this ancestor is already in the templates array
                    if (templates.some(t => t.element === ancestor)) {
                        isChildOfTemplate = true;
                        break;
                    }
                    ancestor = ancestor.parentElement;
                }
                
                if (isChildOfTemplate) {
                    continue; // Skip - will be processed as part of parent template
                }
                
                // Prefer top-level container (main or body) when it contains collection loops,
                // so the full page is rendered in one pass (fixes social feed and similar pages).
                let templateRoot = parent;
                let el = parent;
                while (el && el !== document.body) {
                    if (el.tagName === 'MAIN' || el.tagName === 'BODY') {
                        const rootHtml = el.innerHTML || '';
                        if (/\{\s*['"]?[a-zA-Z0-9_]/.test(rootHtml) || /\{\{/.test(rootHtml)) {
                            templateRoot = el;
                            break;
                        }
                    }
                    el = el.parentElement;
                }
                if (templateRoot !== parent && !templates.some(t => t.element === templateRoot)) {
                    templateRoot.dataset.cmsProcessed = 'true';
                    templates.push({
                        element: templateRoot,
                        originalHtml: templateRoot.innerHTML,
                        template: templateRoot.innerHTML
                    });
                    Birkly.log('Birkly: Found template in top-level container:', templateRoot.tagName, templateRoot.className, 'length:', templateRoot.innerHTML.length);
                } else if (templateRoot === parent) {
                    parent.dataset.cmsProcessed = 'true';
                    templates.push({
                        element: parent,
                        originalHtml: parent.innerHTML,
                        template: parent.innerHTML
                    });
                    Birkly.log('Birkly: Found template in element:', parent.tagName, parent.className, 'Content:', nodeValue.substring(0, 50));
                }
            }
        }
        
            Birkly.log('Birkly: Found', templates.length, 'templates to process');
        
        // If no templates found, try a different approach - check all elements
        if (templates.length === 0) {
            Birkly.warn('Birkly: No templates found via text nodes, trying alternative detection...');
            const allElementsAlt = document.body.querySelectorAll('*');
            for (const el of allElementsAlt) {
                if (el.dataset.cmsProcessed || el.classList.contains('birkly-entry-block')) continue;
                
                const innerHTML = el.innerHTML || '';
                if (innerHTML && (/\{\s*['"]?[a-zA-Z0-9_]/.test(innerHTML) || /\{\{/.test(innerHTML))) {
                    // Check if this element is inside a collection loop
                    // If so, skip it - it will be processed as part of the collection loop
                    // BUT: Don't skip if this element itself contains a collection loop (it's the parent template)
                    const elText = el.textContent || el.innerHTML || '';
                    const elHasCollectionLoop = elText.match(/\{for\s+(?:each\s+)?[a-zA-Z0-9_]+\s+in\s+['"][^'"]+['"]\}/);
                    
                    if (!elHasCollectionLoop) {
                        // Only check ancestors if this element doesn't contain a collection loop itself
                        // We need to check if this element is actually INSIDE a collection loop's content
                        // (i.e., between {for each entry in 'collection'} and {endfor})
                        // Not just if there's a collection loop somewhere in an ancestor
                        let ancestor = el.parentElement;
                        let isInsideCollectionLoop = false;
                        while (ancestor && ancestor !== document.body) {
                            const ancestorText = ancestor.textContent || ancestor.innerHTML || '';
                            // Check if this ancestor contains a collection loop AND this element is inside its content
                            const collectionLoopMatch = ancestorText.match(/\{for\s+(?:each\s+)?[a-zA-Z0-9_]+\s+in\s+['"]([^'"]+)['"]\}/);
                            if (collectionLoopMatch) {
                                // Found a collection loop in ancestor - check if this element is inside its loop content
                                // The loop content is between the {for} tag and {endfor}
                                const loopStartIndex = ancestorText.indexOf(collectionLoopMatch[0]);
                                const loopEndIndex = ancestorText.indexOf('{endfor}', loopStartIndex);
                                if (loopEndIndex !== -1) {
                                    // Get the position of this element's content in the ancestor's text
                                    const elementIndex = ancestorText.indexOf(el.innerHTML);
                                    if (elementIndex !== -1 && elementIndex > loopStartIndex && elementIndex < loopEndIndex) {
                                        // This element is inside the collection loop's content
                                        isInsideCollectionLoop = true;
                                        Birkly.log('Birkly: Skipping nested template inside collection loop (alt detection):', el.tagName, el.className);
                                        break;
                                    }
                                }
                            }
                            ancestor = ancestor.parentElement;
                        }
                        
                        if (isInsideCollectionLoop) {
                            continue; // Skip - will be processed as part of collection loop
                        }
                    }
                    
                    // Check if this element is a child of an already-detected template
                    // If so, skip it - it will be processed as part of the parent template
                    let ancestor = el.parentElement;
                    let isChildOfTemplate = false;
                    while (ancestor && ancestor !== document.body) {
                        // Check if this ancestor is already in the templates array
                        if (templates.some(t => t.element === ancestor)) {
                            isChildOfTemplate = true;
                            break;
                        }
                        ancestor = ancestor.parentElement;
                    }
                    
                    if (isChildOfTemplate) {
                        continue; // Skip - will be processed as part of parent template
                    }
                    
                    // Prefer main/body when it contains template (same as text-node path)
                    let templateRoot = el;
                    let walk = el;
                    while (walk && walk !== document.body) {
                        if (walk.tagName === 'MAIN' || walk.tagName === 'BODY') {
                            const rootHtml = walk.innerHTML || '';
                            if (/\{\s*['"]?[a-zA-Z0-9_]/.test(rootHtml) || /\{\{/.test(rootHtml)) {
                                templateRoot = walk;
                                break;
                            }
                        }
                        walk = walk.parentElement;
                    }
                    if (templateRoot !== el && !templates.some(t => t.element === templateRoot)) {
                        templateRoot.dataset.cmsProcessed = 'true';
                        templates.push({
                            element: templateRoot,
                            originalHtml: templateRoot.innerHTML,
                            template: templateRoot.innerHTML
                        });
                        Birkly.log('Birkly: Found template via alt (top-level):', templateRoot.tagName, templateRoot.className);
                    } else if (templateRoot === el) {
                        el.dataset.cmsProcessed = 'true';
                        templates.push({
                            element: el,
                            originalHtml: innerHTML,
                            template: innerHTML
                        });
                        Birkly.log('Birkly: Found template via alternative detection:', el.tagName, el.className);
                    }
                }
            }
            Birkly.log('Birkly: Found', templates.length, 'templates via alternative detection');
        }
        
        // Method 2: Check attributes (for href, src, etc.)
        // Find all elements that might have template syntax in attributes
        const allElements = document.body.querySelectorAll('*');
        for (let el of allElements) {
            if (el.dataset.cmsProcessed) continue;
            
            // Check all attributes for template syntax
            for (let attr of el.attributes) {
                if (/\{[a-zA-Z0-9_\.\s'"]+\}/.test(attr.value)) {
                    // Found template syntax in attribute
                    // Add parent element to templates list
                    let parent = el.parentElement;
                    if (!parent) parent = el;
                    
                    // Skip if parent is inside an already-tracked template (avoid duplicate/nested processing)
                    let ancestor = parent.parentElement;
                    let isChildOfTemplate = false;
                    while (ancestor && ancestor !== document.body) {
                        if (templates.some(t => t.element === ancestor)) {
                            isChildOfTemplate = true;
                            break;
                        }
                        ancestor = ancestor.parentElement;
                    }
                    if (isChildOfTemplate) {
                        break;
                    }

                    if (!parent.dataset.cmsProcessed) {
                        parent.dataset.cmsProcessed = 'true';
                        templates.push({
                            element: parent,
                            originalHtml: parent.innerHTML,
                            template: parent.innerHTML
                        });
                    }
                    break; // Found template syntax, no need to check other attributes
                }
            }
        }
        
        // Process each template (always log count so we see if templates were found)
        console.log('[Birkly] processTemplates: found', templates.length, 'templates');
        Birkly.log('Birkly: About to process', templates.length, 'templates');
        for (let template of templates) {
            try {
                Birkly.log('Birkly: Processing template element:', template.element.tagName, template.element.className, 'Template length:', template.template.length, 'Has template tags:', template.template.includes('{'), 'Sample:', template.template.substring(0, 200));
                
                // Check if this template contains entry blocks - if so, preserve them in DOM
                // IMPORTANT: Only preserve entry blocks if they're DIRECT children of this template element
                // Don't preserve them if they're nested deeper (they'll be preserved by their parent template)
                const entryBlocksInDOM = template.element.querySelectorAll('.birkly-entry-block');
                const preservedBlocks = [];
                
                // Filter to only direct children (not nested deeper)
                const directEntryBlocks = Array.from(entryBlocksInDOM).filter(block => {
                    // Check if this block is a direct child of template.element
                    return block.parentElement === template.element;
                });
                
                // Also check if the template HTML string contains entry block placeholders
                const hasEntryBlockInHTML = template.template.includes('birkly-entry-block');
                
                if (directEntryBlocks.length > 0) {
                    Birkly.log('Birkly: Found', directEntryBlocks.length, 'direct entry blocks in DOM, preserving them');
                    directEntryBlocks.forEach((block, index) => {
                        // Clone the element so we can restore it after innerHTML replacement
                        const clonedElement = block.cloneNode(true);
                        // Store the actual element reference and its position
                            preservedBlocks.push({
                                originalElement: block,
                                clonedElement: clonedElement,
                                html: block.outerHTML,
                                parent: block.parentElement,
                                nextSibling: block.nextSibling,
                                collectionSlug: block.dataset.birklyCollection
                            });
                            Birkly.log('Birkly: Preserved entry block', index, 'for collection:', block.dataset.birklyCollection);
                    });
                } else if (hasEntryBlockInHTML && entryBlocksInDOM.length === 0) {
                    // Only extract from HTML if there are NO entry blocks in DOM at all
                    // (meaning they're in the HTML but not yet in DOM, or were removed)
                    // Entry block is in the HTML string but not in DOM - this shouldn't happen
                    // but if it does, we need to extract it from the HTML and create a DOM element
                    Birkly.warn('Birkly: Template HTML contains entry block placeholder, but not found in DOM');
                    Birkly.warn('Birkly: Attempting to extract entry block from HTML string');
                    
                    // Try to extract the entry block HTML from template.template
                    // Try multiple regex patterns to match the entry block
                    Birkly.log('Birkly: Template HTML length:', template.template.length, 'Contains birkly-entry-block:', template.template.includes('birkly-entry-block'));
                    
                    let entryBlockMatch = template.template.match(/<div[^>]*class="birkly-entry-block"[^>]*>.*?<\/div>/s);
                    if (!entryBlockMatch) {
                        // Try matching with class attribute in different position
                        entryBlockMatch = template.template.match(/<div[^>]*class\s*=\s*"birkly-entry-block"[^>]*>.*?<\/div>/s);
                    }
                    if (!entryBlockMatch) {
                        // Try matching by ID pattern
                        entryBlockMatch = template.template.match(/<div[^>]*id="[^"]*birkly-entry-block[^"]*"[^>]*>.*?<\/div>/s);
                    }
                    if (!entryBlockMatch) {
                        // Try matching with more flexible pattern
                        entryBlockMatch = template.template.match(/<div[^>]*\s+class\s*=\s*["']birkly-entry-block["'][^>]*>[\s\S]*?<\/div>/);
                    }
                    
                    if (entryBlockMatch) {
                        const entryBlockHTML = entryBlockMatch[0];
                        Birkly.log('Birkly: Found entry block HTML, length:', entryBlockHTML.length);
                        Birkly.log('Birkly: Entry block HTML (first 500 chars):', entryBlockHTML.substring(0, 500));
                        
                        // Extract collection slug directly from HTML string to avoid parsing issues
                        const collectionSlugMatch = entryBlockHTML.match(/data-birkly-collection\s*=\s*["']([^"']+)["']/);
                        const collectionSlug = collectionSlugMatch ? collectionSlugMatch[1] : null;
                        
                        if (collectionSlug) {
                            Birkly.log('Birkly: Extracted collection slug from HTML:', collectionSlug);
                            // Create a proper entry block element from the HTML
                            const tempDiv = document.createElement('div');
                            // Use the exact HTML we found, but ensure it's properly formatted
                            tempDiv.innerHTML = entryBlockHTML;
                            let extractedBlock = tempDiv.firstElementChild;
                            
                            // If parsing failed, create a new element with the correct attributes
                            if (!extractedBlock || !extractedBlock.classList || !extractedBlock.classList.contains('birkly-entry-block')) {
                                Birkly.log('Birkly: HTML parsing failed, creating element manually');
                                extractedBlock = document.createElement('div');
                                extractedBlock.className = 'birkly-entry-block';
                                extractedBlock.id = entryBlockHTML.match(/id\s*=\s*["']([^"']+)["']/)?.[1] || 'birkly-entry-block-' + Math.random().toString(36).substr(2, 9);
                                extractedBlock.dataset.birklyCollection = collectionSlug;
                                
                                // Extract data-birkly-content from raw HTML string
                                // The content is HTML-encoded in the attribute, we need to extract it from the raw HTML
                                // The browser's parser may truncate attribute values, so extract directly from the string
                                let extractedContent = null;
                                
                                // Find the start of the data-birkly-content attribute
                                const attrStart = entryBlockHTML.indexOf('data-birkly-content="');
                                if (attrStart !== -1) {
                                    let contentStart = attrStart + 19; // Length of 'data-birkly-content="'
                                    let i = contentStart;
                                    
                                    // Find the closing quote by character-by-character parsing
                                    // The content is HTML-encoded, so we need to find the closing quote
                                    // that comes AFTER the content, not within it
                                    // Strategy: Look for the closing quote that's followed by whitespace and then 'class="birkly-entry-block"'
                                    // or the closing quote that's followed by '>'
                                    
                                    // First, find the position of 'class="birkly-entry-block"' to know where the attribute should end
                                    const classAttrPos = entryBlockHTML.indexOf(' class="birkly-entry-block"', attrStart);
                                    const closingTagPos = entryBlockHTML.indexOf('>', attrStart);
                                    
                                    // The closing quote should be before the class attribute or closing tag
                                    const maxPos = (classAttrPos !== -1 && closingTagPos !== -1) 
                                        ? Math.min(classAttrPos, closingTagPos) 
                                        : (classAttrPos !== -1 ? classAttrPos : closingTagPos);
                                    
                                    if (maxPos === -1) {
                                        Birkly.warn('Birkly: Could not find class attribute or closing tag to determine attribute end');
                                        // Fallback: Search backwards from the end
                                        let searchEnd = entryBlockHTML.length - 1;
                                        while (searchEnd > contentStart) {
                                            if (entryBlockHTML[searchEnd] === '"') {
                                                // Check if this quote is followed by whitespace or 'class='
                                                let afterQuote = searchEnd + 1;
                                                while (afterQuote < entryBlockHTML.length && /\s/.test(entryBlockHTML[afterQuote])) {
                                                    afterQuote++;
                                                }
                                                if (entryBlockHTML.substring(afterQuote, afterQuote + 6) === 'class=' || 
                                                    entryBlockHTML.substring(afterQuote, afterQuote + 1) === '>') {
                                                    extractedContent = entryBlockHTML.substring(contentStart, searchEnd);
                                                    break;
                                                }
                                            }
                                            searchEnd--;
                                        }
                                    } else {
                                        // Search backwards from maxPos to find the closing quote
                                        // We need to find the quote that's immediately followed by whitespace and then 'class='
                                        let searchEnd = maxPos - 1;
                                        while (searchEnd > contentStart) {
                                            if (entryBlockHTML[searchEnd] === '"') {
                                                // Check if this is not part of an entity
                                                // Look backwards to see if it's &quot;
                                                if (searchEnd >= 6 && entryBlockHTML.substring(searchEnd - 6, searchEnd) === '&quot;') {
                                                    searchEnd--;
                                                    continue;
                                                }
                                                // Check if it's escaped
                                                if (searchEnd > contentStart && entryBlockHTML[searchEnd - 1] === '\\') {
                                                    searchEnd--;
                                                    continue;
                                                }
                                                // Verify this is the closing quote by checking what comes after it
                                                let afterQuote = searchEnd + 1;
                                                // Skip whitespace after the quote
                                                while (afterQuote < entryBlockHTML.length && /\s/.test(entryBlockHTML[afterQuote])) {
                                                    afterQuote++;
                                                }
                                                // Check if after whitespace we have 'class=' or '>'
                                                // The closing quote should be followed by whitespace and then 'class="birkly-entry-block"'
                                                const afterWhitespace = entryBlockHTML.substring(afterQuote, afterQuote + 25);
                                                if (entryBlockHTML.substring(afterQuote, afterQuote + 6) === 'class=' || 
                                                    entryBlockHTML[afterQuote] === '>') {
                                                    // Verify this is really the closing quote by checking if we're near the class attribute
                                                    // The closing quote should be very close to 'class="birkly-entry-block"'
                                                    const distanceToClass = classAttrPos - searchEnd;
                                                    if (distanceToClass > 0 && distanceToClass < 50) {
                                                        // This is the closing quote!
                                                        extractedContent = entryBlockHTML.substring(contentStart, searchEnd);
                                                        
                                                        // Aggressively remove any trailing malformed patterns
                                                        // The content might end with: button&gt;="" "="" data-birkly-preserved="false
                                                        // We need to remove everything from the first quote that's followed by = or data-haru
                                                        
                                                        // Remove any trailing malformed patterns BEFORE checking for data-birkly-preserved
                                                        // This helps us find the actual end of the content
                                                        let cleanedContent = extractedContent.replace(/\s*=""?\s*$/, '');
                                                        cleanedContent = cleanedContent.replace(/\s*=\s*""?\s*$/, '');
                                                        cleanedContent = cleanedContent.replace(/\s*"\s*$/, '');
                                                        
                                                        // Check if cleaned content still ends with data-birkly-preserved
                                                        const dataHaruIndex = cleanedContent.indexOf('data-birkly-preserved');
                                                        if (dataHaruIndex !== -1) {
                                                            // Find the quote before data-birkly-preserved
                                                            let quoteBeforeDataHaru = dataHaruIndex - 1;
                                                            while (quoteBeforeDataHaru > 0 && cleanedContent[quoteBeforeDataHaru] !== '"' && cleanedContent[quoteBeforeDataHaru] !== '&') {
                                                                quoteBeforeDataHaru--;
                                                            }
                                                            // If we found a quote, check if it's part of &quot; or a real quote
                                                            if (quoteBeforeDataHaru >= 5 && cleanedContent.substring(quoteBeforeDataHaru - 5, quoteBeforeDataHaru + 1) === '&quot;') {
                                                                // It's part of &quot;, skip it
                                                                quoteBeforeDataHaru = quoteBeforeDataHaru - 5;
                                                            }
                                                            if (quoteBeforeDataHaru > 0 && cleanedContent[quoteBeforeDataHaru] === '"') {
                                                                // Check what's before this quote - if it's = or whitespace, trim from there
                                                                if (quoteBeforeDataHaru > 0 && (cleanedContent[quoteBeforeDataHaru - 1] === '=' || /\s/.test(cleanedContent[quoteBeforeDataHaru - 1]))) {
                                                                    // Trim from the quote before data-haru
                                                                    cleanedContent = cleanedContent.substring(0, quoteBeforeDataHaru);
                                                                    // Also trim any trailing = or whitespace
                                                                    cleanedContent = cleanedContent.replace(/\s*=\s*$/, '');
                                                                } else {
                                                                    // Just trim from data-birkly-preserved
                                                                    cleanedContent = cleanedContent.substring(0, dataHaruIndex);
                                                                }
                                                            } else {
                                                                // Just trim from data-birkly-preserved
                                                                cleanedContent = cleanedContent.substring(0, dataHaruIndex);
                                                            }
                                                        }
                                                        
                                                        // Final cleanup
                                                        cleanedContent = cleanedContent.replace(/\s*=""?\s*$/, '');
                                                        cleanedContent = cleanedContent.replace(/\s*=\s*""?\s*$/, '');
                                                        cleanedContent = cleanedContent.replace(/\s*"\s*$/, '');
                                                        
                                                        extractedContent = cleanedContent;
                                                        
                                                        break;
                                                    }
                                                }
                                            }
                                            searchEnd--;
                                        }
                                    }
                                    
                                    if (extractedContent) {
                                        // Check if the extracted content includes extra characters at the end
                                        // The last chars show: button&gt;="" "="" data-birkly-preserved="false
                                        // This means we're including part of the next attribute
                                        // We need to trim it properly
                                        // The content should end before the closing quote, not include it
                                        // But we already extracted it correctly, so the issue must be elsewhere
                                        Birkly.log('Birkly: Extracted content from raw HTML string, length:', extractedContent.length);
                                        Birkly.log('Birkly: First 200 chars:', extractedContent.substring(0, 200));
                                        Birkly.log('Birkly: Last 100 chars:', extractedContent.substring(Math.max(0, extractedContent.length - 100)));
                                        Birkly.log('Birkly: Content ends with:', extractedContent.substring(Math.max(0, extractedContent.length - 20)));
                                    } else {
                                        Birkly.warn('Birkly: Could not find closing quote for data-birkly-content attribute. contentStart:', contentStart, 'HTML length:', entryBlockHTML.length);
                                        Birkly.warn('Birkly: HTML around attribute:', entryBlockHTML.substring(Math.max(0, attrStart - 50), Math.min(entryBlockHTML.length, attrStart + 200)));
                                    }
                                } else {
                                    Birkly.warn('Birkly: Could not find data-birkly-content attribute in HTML string');
                                }
                                
                                if (extractedContent) {
                                    extractedBlock.dataset.birklyContent = extractedContent;
                                    Birkly.log('Birkly: Final extracted content length:', extractedContent.length);
                                } else {
                                    Birkly.warn('Birkly: Could not extract data-birkly-content from entry block HTML');
                                }
                                
                                extractedBlock.textContent = 'Loading form...';
                            }
                            
                            if (extractedBlock && extractedBlock.dataset && extractedBlock.dataset.birklyCollection) {
                                // Store the extracted block
                                preservedBlocks.push({
                                    originalElement: null, // No original element in DOM
                                    clonedElement: extractedBlock.cloneNode(true),
                                    html: entryBlockHTML,
                                    parent: null,
                                    nextSibling: null,
                                    collectionSlug: extractedBlock.dataset.birklyCollection
                                });
                                Birkly.log('Birkly: Extracted entry block from HTML for collection:', extractedBlock.dataset.birklyCollection);
                            } else {
                                Birkly.warn('Birkly: Could not create valid entry block element');
                            }
                        } else {
                            Birkly.warn('Birkly: Could not extract collection slug from entry block HTML');
                        }
                    } else {
                        Birkly.warn('Birkly: Could not match entry block in template HTML');
                    }
                }
                
                // Extract collection names from template
                // Match both quoted {for each entry in 'blog'} and unquoted {for each entry in profile_posts}
                // so profile page (profile_posts from prefetch) uses the collection branch and gets prefetches.
                const collectionMatchesQuoted = template.template.match(/\{for\s+(?:each\s+)?[a-zA-Z0-9_]+\s+in\s+['"]([^'"]+)['"][^}]*\}/g);
                const collectionMatchesUnquoted = template.template.match(/\{for\s+(?:each\s+)?[a-zA-Z0-9_]+\s+in\s+([a-zA-Z0-9_\.]+)(?:\s+with\s+[^{}]+?)?(?:\s+ordered\s+by\s+[^{}]+?)?\s*\}/g);
                const reservedLoopPaths = new Set(['content', 'tags', 'entry', 'query', 'comments']);
                const hasQuotedCollectionLoops = !!(collectionMatchesQuoted && collectionMatchesQuoted.length);
                const hasPrefetchCollectionLoops = !!(collectionMatchesUnquoted && collectionMatchesUnquoted.some((match) => {
                    const m = match.match(/\s+in\s+([a-zA-Z0-9_\.]+)/);
                    return m && !reservedLoopPaths.has(m[1]);
                }));
                
                Birkly.log('Birkly: Checking for collection loops in template. Template length:', template.template.length, 'Has quoted collection loop:', hasQuotedCollectionLoops, 'Has prefetch loop:', hasPrefetchCollectionLoops);
                
                if (hasQuotedCollectionLoops || hasPrefetchCollectionLoops) {
                    const collections = [];
                    (collectionMatchesQuoted || []).forEach(match => {
                        const m = match.match(/['"]([^'"]+)['"]/);
                        if (m && collections.indexOf(m[1]) === -1) collections.push(m[1]);
                    });
                    (collectionMatchesUnquoted || []).forEach(match => {
                        const m = match.match(/\s+in\s+([a-zA-Z0-9_\.]+)/);
                        if (m && !reservedLoopPaths.has(m[1]) && collections.indexOf(m[1]) === -1) collections.push(m[1]);
                    });
                    
                    Birkly.log('Birkly: Found collections:', collections);
                    
                    // Fetch data for all collections
                    // Note: site_title is not provided by CMS - websites should define their own
                    const data = {
                        current_year: new Date().getFullYear(),
                        site_elements: siteElements, // Add site elements for use in templates
                        query: query,
                        current: {
                            language: Birkly.currentLanguage || 'en'
                        }
                    };
                    mergePageBundleIntoData(data);
                    Birkly.log('Birkly: Data object created with site_elements:', Object.keys(data.site_elements || {}));

                    // Load template functions metadata first (required before hydration)
                    await loadTemplateFunctionsMeta();
                    Birkly.log('Birkly: Template functions loaded, zeroArg count:', Birkly.templateFunctions.zeroArg.size, 'names:', Array.from(Birkly.templateFunctions.zeroArg));
                    
                    // Hydrate template functions for each collection template
                    // We'll hydrate per-template in the loop below
                    await performPrefetches(data);
                    
                    // If a "collection" name is already an array in data (e.g. from a plugin prefetch like user_list → users), use it and don't fetch from CMS
                    await Promise.all(collections.map(async (collectionName) => {
                        const prefetched = data[collectionName];
                        if (Array.isArray(prefetched) && prefetched.length > 0) {
                            Birkly.log('Birkly: Using prefetched/virtual list for:', collectionName, 'count:', prefetched.length);
                            return;
                        }
                        if (Array.isArray(prefetched) && prefetched.length === 0 && !Birkly.pageBundle.failed.has(collectionName)) {
                            Birkly.log('Birkly: Using empty bundled list for:', collectionName);
                            return;
                        }
                        Birkly.log('Birkly: Fetching entries for collection:', collectionName);
                        const apiData = await fetchContent('list_entries', { collection: collectionName });
                        if (apiData && apiData.status === 'ok' && apiData.data.entries) {
                            const flatEntries = apiData.data.entries.map(flattenLocalizedEntry);
                            data[collectionName] = flatEntries;
                            Birkly.log('Birkly: Fetched', flatEntries.length, 'entries for', collectionName, 'example title:', flatEntries[0]?.title);
                        } else {
                            if (Birkly.debug) Birkly.warn('Birkly: Failed to fetch entries for', collectionName, apiData);
                            if (!(Array.isArray(data[collectionName]) && data[collectionName].length > 0)) {
                                data[collectionName] = [];
                            }
                        }
                    }));
                    
                    // Hydrate template functions before rendering
                    await hydrateTemplateFunctionsIntoData(data, template.template);
                    Birkly.log('Birkly: Data before rendering collection template:', Object.keys(data));
                    // Render template
                    const pre = await preprocessTemplateFunctionCalls(template.template, data);
                    Birkly.log('Birkly: Preprocessed template length:', pre.length, 'still has template tags:', pre.includes('{for'), 'sample:', pre.substring(0, 200));
                    let rendered = renderTemplate(pre, data);
                    rendered = processNestedLoops(rendered, data);
                    Birkly.log('Birkly: Rendered collection template length:', rendered.length, 'still has template tags:', rendered.includes('{for'), 'sample:', rendered.substring(0, 300));
                    
                    // Safety check: if rendered output still contains template tags, strip them and ALWAYS update DOM (never leave raw tags)
                    const hadUnprocessedTags = rendered.includes('{for') || rendered.includes('{if') || rendered.includes('{entry') || rendered.includes('{endif}') || rendered.includes('{endfor}');
                    if (hadUnprocessedTags) {
                        console.error('[Birkly] Rendered output still has template tags - stripping and updating DOM');
                        Birkly.error('Birkly: Rendered output still contains template tags - stripping and updating DOM. Template sample:', template.template.substring(0, 300), 'Rendered sample:', rendered.substring(0, 300));
                        rendered = stripRemainingTemplateTags(rendered);
                        // Set innerHTML directly so we never leave raw tags (bypass short-content refusal)
                        template.element.innerHTML = rendered;
                        continue;
                    }
                    
                    // Restore entry blocks BEFORE setting innerHTML
                    if (preservedBlocks.length > 0) {
                        Birkly.log('Birkly: Restoring', preservedBlocks.length, 'entry blocks in rendered HTML before setting innerHTML');
                        
                        // Replace all placeholders in the rendered HTML string BEFORE setting innerHTML
                        preservedBlocks.forEach((block, index) => {
                            const collectionSlug = block.collectionSlug || (block.originalElement ? block.originalElement.dataset.birklyCollection : null);
                            if (!collectionSlug) {
                                Birkly.warn('Birkly: Entry block', index, 'has no collection slug');
                                return;
                            }
                            
                            // Clone the element
                            const restoredElement = block.clonedElement.cloneNode(true);
                            restoredElement.setAttribute('data-birkly-preserved', 'true');
                            
                            // Try to find the placeholder in the rendered HTML and replace it
                            const placeholderIdMatch = block.html.match(/id="([^"]*birkly-entry-block[^"]*)"/);
                            if (placeholderIdMatch) {
                                const placeholderId = placeholderIdMatch[1];
                                // Try to find placeholder by ID in rendered HTML
                                const placeholderRegex = new RegExp('<div[^>]*id="' + placeholderId.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '"[^>]*>.*?<\/div>', 's');
                                if (rendered.match(placeholderRegex)) {
                                    // Replace placeholder with actual block
                                    rendered = rendered.replace(placeholderRegex, restoredElement.outerHTML);
                                    Birkly.log('Birkly: Entry block', index, 'replaced in rendered HTML for collection:', collectionSlug);
                                    return;
                                }
                            }
                            
                            // Fallback: Try to find any placeholder and replace it
                            const placeholderFullRegex = /<div[^>]*class="birkly-entry-block"[^>]*>.*?<\/div>/s;
                            if (rendered.match(placeholderFullRegex)) {
                                rendered = rendered.replace(placeholderFullRegex, restoredElement.outerHTML);
                                Birkly.log('Birkly: Entry block', index, 'replaced in rendered HTML (fallback) for collection:', collectionSlug);
                                return;
                            }
                            
                            Birkly.warn('Birkly: Entry block', index, 'placeholder not found in rendered HTML for collection:', collectionSlug);
                        });
                    }
                    
                    // Debug: log rendered output to check for else blocks
                    if (rendered.includes('<!--ELSE-->') || rendered.includes('<!--ENDIF-->')) {
                        Birkly.warn('Birkly: Warning - rendered output still contains else/endif markers:', rendered.substring(0, 300));
                    }
                    
                    // Debug: check if content is duplicated (check for duplicate h2 tags or similar)
                    const h2Count = (rendered.match(/<h2[^>]*>/g) || []).length;
                    if (h2Count > 1 && template.template.includes('{if') && template.template.includes('{else}')) {
                        Birkly.warn('Birkly: Warning - multiple h2 tags found in rendered output, possible duplication:', h2Count, 'Sample:', rendered.substring(0, 500));
                    }
                    
                    // Set innerHTML only once after all placeholders are replaced
                    if (!safeSetInnerHTML(template.element, rendered, template.template, 'collection-loop')) {
                        // Content was not replaced due to safety check - skip this template
                        continue;
                    }
                    
                    // Verify entry blocks exist in DOM after setting innerHTML
                    if (preservedBlocks.length > 0) {
                        preservedBlocks.forEach((block, index) => {
                            const collectionSlug = block.collectionSlug || (block.originalElement ? block.originalElement.dataset.birklyCollection : null);
                            if (!collectionSlug) return;
                            
                            const existingBlock = template.element.querySelector('.birkly-entry-block[data-birkly-collection="' + collectionSlug + '"]');
                            if (existingBlock) {
                                Birkly.log('Birkly: Entry block', index, 'verified in DOM for collection:', collectionSlug);
                            } else {
                                Birkly.warn('Birkly: Entry block', index, 'NOT found in DOM after restoration for collection:', collectionSlug);
                                // Last resort: Insert into DOM
                                const restoredElement = block.clonedElement.cloneNode(true);
                                restoredElement.setAttribute('data-birkly-preserved', 'true');
                                
                                const container = template.element;
                                if (block.nextSibling && block.nextSibling.parentElement === container) {
                                    container.insertBefore(restoredElement, block.nextSibling);
                                } else {
                                    container.appendChild(restoredElement);
                                }
                                Birkly.log('Birkly: Entry block', index, 'inserted into DOM as fallback');
                            }
                        });
                    }
                    Birkly.log('Birkly: Template rendered successfully');
                } else {
                    // No collections, just render variables
                    // Note: site_title is not provided by CMS - websites should define their own
                    const data = {
                        current_year: new Date().getFullYear(),
                        site_elements: siteElements, // Add site elements for use in templates
                        query: query,
                        current: {
                            language: Birkly.currentLanguage || 'en'
                        }
                    };
                    Birkly.log('Birkly: Data object (no collections) with site_elements:', Object.keys(data.site_elements || {}));
                    Birkly.log('Birkly: Rendering template without collections');
                    
                    // Load template functions metadata first
                    await loadTemplateFunctionsMeta();
                    Birkly.log('Birkly: Template functions loaded, zeroArg count:', Birkly.templateFunctions.zeroArg.size, 'names:', Array.from(Birkly.templateFunctions.zeroArg));
                    
                    await hydrateTemplateFunctionsIntoData(data, template.template);
                    await performPrefetches(data);
                    Birkly.log('Birkly: Data after hydration:', Object.keys(data));
                    const pre = await preprocessTemplateFunctionCalls(template.template, data);
                    let rendered = renderTemplate(pre, data);
                    Birkly.log('Birkly: Rendered output length:', rendered.length, 'sample:', rendered.substring(0, 200));
                    
                    // Safety check: if rendered output still contains template tags, strip them and ALWAYS update DOM
                    const hadUnprocessedTagsNoColl = rendered.includes('{for') || rendered.includes('{if') || rendered.includes('{entry') || rendered.includes('{endif}') || rendered.includes('{endfor}');
                    if (hadUnprocessedTagsNoColl) {
                        console.error('[Birkly] No-collections branch: output still has template tags - stripping and updating DOM');
                        Birkly.error('Birkly: Rendered output still contains template tags (no-collections) - stripping and updating DOM.');
                        rendered = stripRemainingTemplateTags(rendered);
                        template.element.innerHTML = rendered;
                        continue; // Process next template (do not exit processTemplates)
                    }
                    
                    // Safety check: if rendered output is suspiciously short, don't replace content
                    if (!safeSetInnerHTML(template.element, rendered, template.template, 'no-collections')) {
                        // Content was not replaced due to safety check - skip this fragment only
                        continue;
                    }
                    
                    // Restore entry blocks BEFORE setting innerHTML
                    if (preservedBlocks.length > 0) {
                        Birkly.log('Birkly: Restoring', preservedBlocks.length, 'entry blocks in rendered HTML before setting innerHTML');
                        
                        // Replace all placeholders in the rendered HTML string BEFORE setting innerHTML
                        preservedBlocks.forEach((block, index) => {
                            const collectionSlug = block.collectionSlug || (block.originalElement ? block.originalElement.dataset.birklyCollection : null);
                            if (!collectionSlug) {
                                Birkly.warn('Birkly: Entry block', index, 'has no collection slug');
                                return;
                            }
                            
                            // Clone the element
                            const restoredElement = block.clonedElement.cloneNode(true);
                            restoredElement.setAttribute('data-birkly-preserved', 'true');
                            
                            // Try to find the placeholder in the rendered HTML and replace it
                            const placeholderIdMatch = block.html.match(/id="([^"]*birkly-entry-block[^"]*)"/);
                            if (placeholderIdMatch) {
                                const placeholderId = placeholderIdMatch[1];
                                // Try to find placeholder by ID in rendered HTML
                                const placeholderRegex = new RegExp('<div[^>]*id="' + placeholderId.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '"[^>]*>.*?<\/div>', 's');
                                if (rendered.match(placeholderRegex)) {
                                    // Replace placeholder with actual block
                                    rendered = rendered.replace(placeholderRegex, restoredElement.outerHTML);
                                    Birkly.log('Birkly: Entry block', index, 'replaced in rendered HTML for collection:', collectionSlug);
                                    return;
                                }
                            }
                            
                            // Fallback: Try to find any placeholder and replace it
                            const placeholderFullRegex = /<div[^>]*class="birkly-entry-block"[^>]*>.*?<\/div>/s;
                            if (rendered.match(placeholderFullRegex)) {
                                rendered = rendered.replace(placeholderFullRegex, restoredElement.outerHTML);
                                Birkly.log('Birkly: Entry block', index, 'replaced in rendered HTML (fallback) for collection:', collectionSlug);
                                return;
                            }
                            
                            Birkly.warn('Birkly: Entry block', index, 'placeholder not found in rendered HTML for collection:', collectionSlug);
                        });
                    }
                    
                    // Debug: log rendered output to check for else blocks
                    if (rendered.includes('<!--ELSE-->') || rendered.includes('<!--ENDIF-->')) {
                        Birkly.warn('Birkly: Warning - rendered output still contains else/endif markers:', rendered.substring(0, 300));
                    }
                    
                    // Debug: check if content is duplicated (check for duplicate h2 tags or similar)
                    const h2Count = (rendered.match(/<h2[^>]*>/g) || []).length;
                    if (h2Count > 1 && template.template.includes('{if') && template.template.includes('{else}')) {
                        Birkly.warn('Birkly: Warning - multiple h2 tags found in rendered output, possible duplication:', h2Count, 'Sample:', rendered.substring(0, 500));
                    }
                    
                    // Set innerHTML only once after all placeholders are replaced
                    if (!safeSetInnerHTML(template.element, rendered, template.template, 'collection-loop')) {
                        // Content was not replaced due to safety check - skip this template
                        continue;
                    }
                    
                    // Verify entry blocks exist in DOM after setting innerHTML
                    if (preservedBlocks.length > 0) {
                        preservedBlocks.forEach((block, index) => {
                            const collectionSlug = block.collectionSlug || (block.originalElement ? block.originalElement.dataset.birklyCollection : null);
                            if (!collectionSlug) return;
                            
                            const existingBlock = template.element.querySelector('.birkly-entry-block[data-birkly-collection="' + collectionSlug + '"]');
                            if (existingBlock) {
                                Birkly.log('Birkly: Entry block', index, 'verified in DOM for collection:', collectionSlug);
                            } else {
                                Birkly.warn('Birkly: Entry block', index, 'NOT found in DOM after restoration for collection:', collectionSlug);
                                // Last resort: Insert into DOM
                                const restoredElement = block.clonedElement.cloneNode(true);
                                restoredElement.setAttribute('data-birkly-preserved', 'true');
                                
                                const container = template.element;
                                if (block.nextSibling && block.nextSibling.parentElement === container) {
                                    container.insertBefore(restoredElement, block.nextSibling);
                                } else {
                                    container.appendChild(restoredElement);
                                }
                                Birkly.log('Birkly: Entry block', index, 'inserted into DOM as fallback');
                            }
                        });
                    }
                    Birkly.log('Birkly: Template rendered successfully');
                }
            } catch (error) {
                console.error('Birkly: Error processing template:', error);
                console.error('Birkly: Error stack:', error.stack);
                // Fallback: strip raw tags from original template and set so user never sees unprocessed tags
                try {
                    const stripped = stripRemainingTemplateTags(template.template);
                    if (stripped.length > 0) template.element.innerHTML = stripped;
                } catch (e) {
                    Birkly.warn('Birkly: Strip fallback failed:', e);
                }
            }
        }
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
                        const renderedContent = renderTemplate(formContent, fieldData);
                        
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
        render: renderTemplate,
        
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
