// MultiSelect fieldtype JavaScript
(function() {
    'use strict';
    
    if (!window.HaruFieldtypes) {
        window.HaruFieldtypes = {};
    }
    
    window.HaruFieldtypes['multiselect'] = {
        init: function(fieldName) {
            const field = document.getElementById('multiselect_field_' + fieldName);
            if (!field) return;
            
            const hiddenInput = document.getElementById('field_' + fieldName + '_value');
            const badgesContainer = document.getElementById('multiselect_badges_' + fieldName);
            const optionsContainer = document.getElementById('multiselect_options_' + fieldName);
            
            let selectedValues = [];
            
            // Load initial values
            if (hiddenInput && hiddenInput.value) {
                try {
                    selectedValues = JSON.parse(hiddenInput.value);
                    if (!Array.isArray(selectedValues)) {
                        selectedValues = [];
                    }
                } catch (e) {
                    selectedValues = [];
                }
            }
            
            // Update hidden input and badges
            function updateValue() {
                if (hiddenInput) {
                    hiddenInput.value = JSON.stringify(selectedValues);
                }
                updateBadges();
                updateCheckboxes();
            }
            
            function updateBadges() {
                if (!badgesContainer) return;
                
                badgesContainer.innerHTML = '';
                selectedValues.forEach(val => {
                    // Find option label from checkbox
                    const checkbox = optionsContainer.querySelector('.multiselect-option-checkbox[value="' + val.replace(/"/g, '&quot;') + '"]');
                    const label = checkbox ? checkbox.closest('.multiselect-option-label').querySelector('.multiselect-option-text').textContent : val;
                    
                    const badge = document.createElement('span');
                    badge.className = 'multiselect-badge';
                    badge.setAttribute('data-value', val);
                    badge.innerHTML = label + '<span class="badge-remove" onclick="window.HaruFieldtypes[\'multiselect\'].removeValue(\'' + fieldName + '\', \'' + val.replace(/'/g, "\\'") + '\')">×</span>';
                    badgesContainer.appendChild(badge);
                });
            }
            
            function updateCheckboxes() {
                if (!optionsContainer) return;
                
                const checkboxes = optionsContainer.querySelectorAll('.multiselect-option-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = selectedValues.includes(checkbox.value);
                });
            }
            
            // Handle checkbox changes
            if (optionsContainer) {
                optionsContainer.addEventListener('change', function(e) {
                    if (e.target.classList.contains('multiselect-option-checkbox')) {
                        const value = e.target.value;
                        if (e.target.checked) {
                            if (!selectedValues.includes(value)) {
                                selectedValues.push(value);
                            }
                        } else {
                            selectedValues = selectedValues.filter(v => v !== value);
                        }
                        updateValue();
                    }
                });
            }
            
            // Remove value
            this.removeValue = function(fieldName, value) {
                selectedValues = selectedValues.filter(v => v !== value);
                updateValue();
            };
            
            // Initialize
            updateBadges();
            updateCheckboxes();
        },
        
        setValue: function(fieldName, value) {
            const hiddenInput = document.getElementById('field_' + fieldName + '_value');
            if (hiddenInput) {
                const values = Array.isArray(value) ? value : [value];
                hiddenInput.value = JSON.stringify(values);
                this.init(fieldName);
            }
        },
        
        getValue: function(fieldName) {
            const hiddenInput = document.getElementById('field_' + fieldName + '_value');
            if (hiddenInput && hiddenInput.value) {
                try {
                    return JSON.parse(hiddenInput.value);
                } catch (e) {
                    return [];
                }
            }
            return [];
        },
        
        addValue: function(fieldName, value) {
            // Implementation in init
        },
        
        removeValue: function(fieldName, value) {
            // Implementation in init
        }
    };
    
    // Auto-initialize
    function initAllMultiSelectFields() {
        const fields = document.querySelectorAll('.multiselect-field');
        fields.forEach(field => {
            const fieldName = field.id.replace('multiselect_field_', '');
            if (fieldName) {
                window.HaruFieldtypes['multiselect'].init(fieldName);
            }
        });
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllMultiSelectFields);
    } else {
        initAllMultiSelectFields();
    }
})();

