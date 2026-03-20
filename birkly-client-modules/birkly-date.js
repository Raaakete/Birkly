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
     * Date formatting namespace
     */
    Birkly.dateFormatter = {
        /**
         * Format date string using PHP-style format codes
         * @param {string} dateString - Date string to format
         * @param {string} format - Format string (e.g., "Y-m-d H:i")
         * @returns {string} Formatted date string
         */
        formatDate: function(dateString, format) {
            if (!dateString) return '';
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString;
            
            // Date and time formatting (PHP-style format codes)
            const map = {
                // Date
                'Y': date.getFullYear(),
                'y': String(date.getFullYear()).slice(-2),
                'm': String(date.getMonth() + 1).padStart(2, '0'),
                'n': date.getMonth() + 1,
                'd': String(date.getDate()).padStart(2, '0'),
                'j': date.getDate(),
                'F': date.toLocaleString('en-US', { month: 'long' }),
                'M': date.toLocaleString('en-US', { month: 'short' }),
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
         * Defaults to YYYY-MM-DD for date fields without explicit format
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
                // If it's already in YYYY-MM-DD format, return as-is
                if (valueStr.match(/^\d{4}-\d{2}-\d{2}$/)) {
                    return Birkly.utils.escapeHtml(valueStr);
                }
                // If it's an ISO datetime string, extract date part
                if (valueStr.match(/^\d{4}-\d{2}-\d{2}T/)) {
                    return Birkly.utils.escapeHtml(valueStr.substring(0, 10));
                }
                // Try to parse and format as date
                const date = new Date(valueStr);
                if (!isNaN(date.getTime())) {
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    return Birkly.utils.escapeHtml(`${year}-${month}-${day}`);
                }
            }
            
            // For non-date fields, just escape HTML
            return Birkly.utils.escapeHtml(String(value));
        }
    };
    
})(window.Birkly || (window.Birkly = {}));
