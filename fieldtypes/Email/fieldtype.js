// Email fieldtype JavaScript
(function() {
    'use strict';
    
    // Auto-initialize email fields
    function initEmailFields() {
        const emailFields = document.querySelectorAll('input[type="email"]');
        emailFields.forEach(field => {
            // Add auto-formatting on blur
            field.addEventListener('blur', function() {
                let value = this.value.trim();
                if (value && !value.includes('@')) {
                    // Don't auto-format if user is typing
                    return;
                }
                // Remove spaces
                value = value.replace(/\s+/g, '');
                if (value !== this.value) {
                    this.value = value;
                }
            });
        });
    }
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initEmailFields);
    } else {
        initEmailFields();
    }
})();

