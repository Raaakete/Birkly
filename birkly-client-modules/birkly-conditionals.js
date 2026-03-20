/**
 * Birkly Client - Conditionals Module
 * Handles {if}, {elseif}, {else}, {endif} conditional rendering
 */

(function(Birkly) {
    'use strict';
    
    if (!Birkly) Birkly = {};
    if (!Birkly.utils) {
        console.error('Birkly: utils module must be loaded before conditionals module');
        return;
    }
    
    const getNestedValue = Birkly.utils.getNestedValue;
    
    /**
     * Conditionals namespace
     */
    Birkly.conditionals = {
        /**
         * Check if a template value is truthy
         * @param {*} value - Value to check
         * @returns {boolean} True if value is truthy
         */
        isTruthyTemplateValue: function(value) {
            if (value === null || value === undefined) return false;
            if (value === false) return false;
            if (value === 0) return false;
            if (typeof value === 'string') {
                const v = value.trim().toLowerCase();
                if (v === '' || v === 'false' || v === '0' || v === 'null' || v === 'undefined') return false;
            }
            return !!value;
        },
        
        /**
         * Resolve a "'collection'.'identifier'" reference into a value in data
         * @param {string} ref - Reference string (e.g., "'site_elements'.'blog_headline'")
         * @param {Object} data - Data object
         * @returns {*} The resolved entry or undefined
         */
        resolveCollectionEntryRef: function(ref, data) {
            const m = String(ref || '').trim().match(/^'([^']+)'\.'([^']+)'$/);
            if (!m) return undefined;
            const collection = m[1];
            const identifier = m[2];

            if (collection === 'site_elements') {
                const se = data.site_elements || {};
                return se[identifier];
            }

            const entries = data[collection];
            if (Array.isArray(entries)) {
                return entries.find(e =>
                    e &&
                    (String(e.slug || '') === identifier || String(e.id || '') === identifier)
                );
            }

            return undefined;
        },
        
        /**
         * Render conditionals in template
         * Supports: {if x}, {if x exist}, {if x has y}, {if x == 'value'}, {else}, {endif}
         * @param {string} template - Template string
         * @param {Object} data - Data object
         * @param {string} itemPrefix - Prefix for item variables (for loops)
         * @returns {string} Rendered template with conditionals processed
         */
        renderConditionals: function(template, data, itemPrefix = '') {
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
                        // Normalize relation-like objects for string comparison (e.g. entry.visibility = 'public')
                        if (leftValue && typeof leftValue === 'object' && !Array.isArray(leftValue)) {
                            if (typeof leftValue.slug === 'string') leftValue = leftValue.slug;
                            else if (typeof leftValue.name === 'string') leftValue = leftValue.name;
                        }
                        if (right && typeof right === 'object' && !Array.isArray(right)) {
                            if (typeof right.slug === 'string') right = right.slug;
                            else if (typeof right.name === 'string') right = right.name;
                        }
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
                    let value = getNestedValue(data, varPath);
                    if (value && typeof value === 'object' && !Array.isArray(value)) {
                        if (typeof value.slug === 'string') value = value.slug;
                        else if (typeof value.name === 'string') value = value.name;
                    }
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
                    let value = this.resolveCollectionEntryRef(varPath, data);
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
                return this.isTruthyTemplateValue(value);
            }.bind(this);

            // Match directives with optional spaces: {if ...}, { endif }, { else }, etc.
            const directiveRe = /\{\s*(if\s+[^}]+|elseif\s+[^}]+|else|endif)\s*\}/g;
            let out = '';
            let lastIdx = 0;

            const stack = []; // {cond: boolean, inElse: boolean, matched: boolean}
            const recomputeInclude = () => {
                if (stack.length === 0) return true;
                for (let i = stack.length - 1; i >= 0; i--) {
                    if (!stack[i].matched) return false;
                }
                return true;
            };

            // Only treat as directive when the current line has only whitespace before the match
            // (allows indented {if} / {else} / {endif}; prevents "Use {if you want}" from breaking the stack)
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

                const token = (m[1] || '').trim();
                if (!isAtLineStart(m.index)) {
                    // Looks like a directive but is mid-line (e.g. in user content) – keep as literal
                    if (include) out += m[0];
                    lastIdx = m.index + m[0].length;
                    continue;
                }

                if (token.startsWith('if ')) {
                    const condStr = token.slice(3).trim();
                    const condVal = evalCondition(condStr);
                    stack.push({ cond: condVal, inElse: false, matched: condVal });
                    include = recomputeInclude();
                } else if (token.startsWith('elseif ')) {
                    if (stack.length) {
                        const condStr = token.slice(7).trim();
                        const condVal = evalCondition(condStr);
                        const last = stack[stack.length - 1];
                        if (!last.matched && condVal) {
                            last.cond = condVal;
                            last.inElse = true;
                            last.matched = true;
                        } else last.inElse = true;
                        include = recomputeInclude();
                    }
                } else if (token === 'else') {
                    if (stack.length) {
                        const last = stack[stack.length - 1];
                        const hadMatched = last.matched;
                        last.inElse = true;
                        if (!last.matched) last.matched = true;
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

            // Remove any remaining conditional markers that weren't processed (safety cleanup)
            out = out.replace(/\{\s*if\s+[^}]+\s*\}/g, '');
            out = out.replace(/\{\s*elseif\s+[^}]+\s*\}/g, '');
            out = out.replace(/\{\s*else\s*\}/g, '');
            out = out.replace(/\{\s*endif\s*\}/g, '');
            
            // Do NOT remove loop markers here.
            // Loops are processed by the loop renderer; stripping them here breaks nested rendering.
            
            return out;
        }
    };
    
})(window.Birkly || (window.Birkly = {}));
