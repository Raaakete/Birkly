// MultiSelect fieldtype JavaScript
(function() {
    'use strict';
    
    if (!window.HaruFieldtypes) {
        window.HaruFieldtypes = {};
    }
    
    // Store state per field
    const fieldStates = {};
    
    window.HaruFieldtypes['multiselect'] = {
        init: function(fieldName) {
            const field = document.getElementById('multiselect_field_' + fieldName);
            if (!field) return;
            
            const hiddenInput = document.getElementById('field_' + fieldName + '_value');
            const optionsContainer = document.getElementById('multiselect_options_' + fieldName);
            
            // Initialize or load state
            if (!fieldStates[fieldName]) {
                fieldStates[fieldName] = {
                    selectedValues: []
                };
            }
            
            const state = fieldStates[fieldName];
            
            // Load initial values from hidden input
            if (hiddenInput && hiddenInput.value) {
                try {
                    const parsed = JSON.parse(hiddenInput.value);
                    if (Array.isArray(parsed)) {
                        state.selectedValues = parsed;
                    }
                } catch (e) {
                    state.selectedValues = [];
                }
            }
            
            function updateValue() {
                if (hiddenInput) {
                    hiddenInput.value = JSON.stringify(state.selectedValues);
                    console.log('[MultiSelect] Updated hidden input for', fieldName, ':', hiddenInput.value);
                }
                updateCheckboxes();
            }
            
            function updateCheckboxes() {
                // Looked up each time: the container is swapped for a clone below.
                const list = document.getElementById('multiselect_options_' + fieldName);
                if (!list) return;

                const checkboxes = list.querySelectorAll('.multiselect-option-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = state.selectedValues.includes(checkbox.value);
                });
            }
            
            // Handle checkbox changes
            if (optionsContainer) {
                // Remove old listeners by cloning
                const newContainer = optionsContainer.cloneNode(true);
                optionsContainer.parentNode.replaceChild(newContainer, optionsContainer);
                
                newContainer.addEventListener('change', function(e) {
                    if (e.target.classList.contains('multiselect-option-checkbox')) {
                        const value = e.target.value;
                        if (e.target.checked) {
                            if (!state.selectedValues.includes(value)) {
                                state.selectedValues.push(value);
                            }
                        } else {
                            state.selectedValues = state.selectedValues.filter(v => v !== value);
                        }
                        updateValue();
                    }
                });
            }
            
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
            if (fieldStates[fieldName]) {
                fieldStates[fieldName].selectedValues = fieldStates[fieldName].selectedValues.filter(v => v !== value);
                this.updateValue(fieldName);
            }
        },
        
        updateValue: function(fieldName) {
            const hiddenInput = document.getElementById('field_' + fieldName + '_value');
            const optionsContainer = document.getElementById('multiselect_options_' + fieldName);
            
            if (!fieldStates[fieldName]) return;
            const state = fieldStates[fieldName];
            
            if (hiddenInput) {
                hiddenInput.value = JSON.stringify(state.selectedValues);
            }
            
            // Update checkboxes
            if (optionsContainer) {
                const checkboxes = optionsContainer.querySelectorAll('.multiselect-option-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = state.selectedValues.includes(checkbox.value);
                });
            }
        }
    };
    
    // Listen for save events to update hidden inputs
    document.addEventListener('beforeSaveEntry', function() {
        console.log('[MultiSelect] beforeSaveEntry event received');
        const multiselectFields = document.querySelectorAll('.multiselect-field');
        multiselectFields.forEach(field => {
            const fieldName = field.id.replace('multiselect_field_', '');
            if (fieldName && fieldStates[fieldName]) {
                const state = fieldStates[fieldName];
                const hiddenInput = document.getElementById('field_' + fieldName + '_value');
                if (hiddenInput) {
                    // Sync from checkboxes to state
                    const optionsContainer = document.getElementById('multiselect_options_' + fieldName);
                    if (optionsContainer) {
                        const checkboxes = optionsContainer.querySelectorAll('.multiselect-option-checkbox:checked');
                        state.selectedValues = Array.from(checkboxes).map(cb => cb.value);
                    }
                    hiddenInput.value = JSON.stringify(state.selectedValues);
                    console.log('[MultiSelect] Updated hidden input for', fieldName, ':', hiddenInput.value);
        }
            }
        });
    });
    
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

