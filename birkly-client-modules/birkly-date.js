/**
 * Birkly Client - Date Formatting Module
 * Date and time formatting utilities
 */

(function(Birkly) {
    'use strict';
    
    if (!Birkly) Birkly = {};
    if (!Birkly.utils) {
        console.error('Birkly: utils module must be loaded before date module');
        return;
    }
    
    /**
     * Map Birkly language codes to BCP 47 locales.
     */
    function localeToBcp47(code) {
        var normalized = String(code || 'en').toLowerCase().replace(/_/g, '-');
        var map = {
            'en': 'en-US',
            'de': 'de-DE',
            'fr': 'fr-FR',
            'es': 'es-ES',
            'it': 'it-IT',
            'pt': 'pt-BR',
            'nl': 'nl-NL',
            'ja': 'ja-JP',
            'zh': 'zh-CN',
            'zh-tw': 'zh-TW',
            'ko': 'ko-KR',
            'ar': 'ar-SA',
            'ru': 'ru-RU',
            'pl': 'pl-PL',
            'sv': 'sv-SE',
            'da': 'da-DK',
            'fi': 'fi-FI',
            'no': 'nb-NO'
        };
        return map[normalized] || normalized.replace(/-/g, '_');
    }

    /**
     * Default display format for a language when template omits explicit format.
     */
    function defaultFormatForLanguage(langCode, includeTime) {
        var lang = String(langCode || 'en').toLowerCase().replace(/_/g, '-');
        if (includeTime) {
            if (lang === 'en') return 'm/d/Y g:i A';
            if (lang === 'de') return 'd.m.Y H:i';
            if (lang === 'ja' || lang === 'zh' || lang === 'zh-tw') return 'Y/m/d H:i';
            if (lang === 'ko') return 'Y.m.d H:i';
            return 'Y-m-d H:i';
        }
        if (lang === 'en') return 'm/d/Y';
        if (lang === 'de') return 'd.m.Y';
        if (lang === 'ja' || lang === 'zh' || lang === 'zh-tw') return 'Y/m/d';
        if (lang === 'ko') return 'Y.m.d';
        return 'Y-m-d';
    }

    /**
     * Date formatting namespace
     */
    Birkly.dateFormatter = {
        localeToBcp47: localeToBcp47,
        defaultFormatForLanguage: defaultFormatForLanguage,

        /**
         * Format date string using PHP-style format codes
         * @param {string} dateString - Date string to format
         * @param {string} format - Format string (e.g., "Y-m-d H:i")
         * @param {string} [localeCode] - Birkly language code for month names
         * @returns {string} Formatted date string
         */
        formatDate: function(dateString, format, localeCode) {
            if (!dateString) return '';
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString;

            var bcp47 = localeToBcp47(localeCode || (Birkly.currentLanguage || 'en'));
            
            // Date and time formatting (PHP-style format codes)
            const map = {
                // Date
                'Y': date.getFullYear(),
                'y': String(date.getFullYear()).slice(-2),
                'm': String(date.getMonth() + 1).padStart(2, '0'),
                'n': date.getMonth() + 1,
                'd': String(date.getDate()).padStart(2, '0'),
                'j': date.getDate(),
                'F': date.toLocaleString(bcp47, { month: 'long' }),
                'M': date.toLocaleString(bcp47, { month: 'short' }),
                // Time
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
        },
        
        /**
         * Check if a field name represents a date field
         * @param {string} fieldName - Field name to check
         * @returns {boolean} True if it's a date field
         */
        isDateField: function(fieldName) {
            return fieldName === 'created_at' || 
                   fieldName === 'updated_at' || 
                   fieldName === 'published_at' || 
                   fieldName === 'date' ||
                   fieldName.endsWith('.created_at') || 
                   fieldName.endsWith('.updated_at') || 
                   fieldName.endsWith('.published_at') ||
                   fieldName.endsWith('.date');
        },
        
        /**
         * Format a value for output, applying date formatting if it's a date field
         * Defaults to locale-aware format for date fields without explicit format
         * @param {*} value - Value to format
         * @param {string} fieldName - Field name (for date detection)
         * @returns {string} Formatted and escaped value
         */
        formatValueForOutput: function(value, fieldName) {
            if (value === null || value === undefined || value === '') {
                return '';
            }
            
            // Check if this is a date field
            const isDateField = this.isDateField(fieldName);
            
            if (isDateField) {
                const valueStr = String(value);
                const lang = Birkly.currentLanguage || 'en';
                const defaultFormat = defaultFormatForLanguage(lang, valueStr.indexOf('T') !== -1);
                // If it's already in YYYY-MM-DD format, apply locale display
                if (valueStr.match(/^\d{4}-\d{2}-\d{2}$/)) {
                    return Birkly.utils.escapeHtml(this.formatDate(valueStr, defaultFormat, lang));
                }
                // If it's an ISO datetime string, format with locale
                if (valueStr.match(/^\d{4}-\d{2}-\d{2}T/)) {
                    return Birkly.utils.escapeHtml(this.formatDate(valueStr, defaultFormat, lang));
                }
                // Try to parse and format as date
                const date = new Date(valueStr);
                if (!isNaN(date.getTime())) {
                    return Birkly.utils.escapeHtml(this.formatDate(valueStr, defaultFormat, lang));
                }
            }
            
            // For non-date fields, just escape HTML
            return Birkly.utils.escapeHtml(String(value));
        }
    };
    
})(window.Birkly || (window.Birkly = {}));
