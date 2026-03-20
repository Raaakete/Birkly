/**
 * Birkly Client - Utilities Module
 * Core utility functions used across the templating engine
 */

(function(Birkly) {
    'use strict';
    
    if (!Birkly) Birkly = {};
    
    /**
     * Utilities namespace
     */
    Birkly.utils = {
        /**
         * Get nested value from object using dot notation path
         * @param {Object} obj - The object to traverse
         * @param {string} path - Dot notation path (e.g., "user.profile.name")
         * @returns {*} The value at the path, or null if not found
         */
        getNestedValue: function(obj, path) {
            const parts = path.split('.');
            let value = obj;
            for (let part of parts) {
                if (value && typeof value === 'object') {
                    // Handle array indices (e.g., "0", "1")
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
        },
        
        /**
         * Escape HTML special characters
         * @param {string} text - Text to escape
         * @returns {string} Escaped HTML
         */
        escapeHtml: function(text) {
            if (text == null) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        /**
         * Simple hash function for string hashing
         * @param {string} str - String to hash
         * @returns {string} Hexadecimal hash
         */
        simpleHash: function(str) {
            let h = 0;
            for (let i = 0; i < str.length; i++) {
                h = (h * 31 + str.charCodeAt(i)) >>> 0;
            }
            return h.toString(16);
        },
        
        /**
         * Parse quoted arguments from a string
         * Handles both single and double quotes
         * @param {string} raw - Raw string to parse
         * @returns {Array<string>} Array of parsed arguments
         */
        parseQuotedArgs: function(raw) {
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
        },
        
        /**
         * Generate cache key for template function calls
         * @param {string} name - Function name
         * @param {Array} args - Function arguments
         * @returns {string} Cache key
         */
        tfCacheKey: function(name, args) {
            return name + '\0' + JSON.stringify(args || []);
        }
    };
    
})(window.Birkly || (window.Birkly = {}));
