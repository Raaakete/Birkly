/**
 * Custom Select Dropdown
 * Converts native select elements to custom dropdowns using the unified dropdown system
 */
(function() {
    'use strict';
    
    function convertSelectToDropdown(select) {
        // Skip if already converted
        if (select.dataset.customSelect === 'true') return;
        select.dataset.customSelect = 'true';
        
        // Create wrapper
        const wrapper = document.createElement('div');
        wrapper.className = 'dropdown custom-select';
        
        // Create trigger button
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'dropdown-trigger custom-select-trigger';
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-haspopup', 'listbox');
        
        // Get selected option text
        function updateTriggerText() {
            const selectedOption = select.options[select.selectedIndex];
            trigger.textContent = selectedOption ? selectedOption.textContent : '';
            trigger.setAttribute('aria-label', select.getAttribute('aria-label') || select.getAttribute('name') || 'Select option');
        }
        updateTriggerText();
        
        // Create dropdown menu
        const menu = document.createElement('div');
        menu.className = 'dropdown-menu custom-select-menu';
        menu.setAttribute('role', 'listbox');
        
        // Create menu items from options
        Array.from(select.options).forEach((option, index) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'dropdown-item custom-select-item';
            item.textContent = option.textContent;
            item.setAttribute('role', 'option');
            item.setAttribute('data-value', option.value);
            if (option.selected) {
                item.classList.add('selected');
            }
            
            item.addEventListener('click', function() {
                // Update select value
                select.value = option.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                
                // Update trigger text
                updateTriggerText();
                
                // Update selected state
                menu.querySelectorAll('.custom-select-item').forEach(i => i.classList.remove('selected'));
                item.classList.add('selected');
                
                // Close dropdown
                menu.classList.remove('show', 'active');
                trigger.setAttribute('aria-expanded', 'false');
            });
            
            menu.appendChild(item);
        });
        
        // Insert wrapper before select
        select.parentNode.insertBefore(wrapper, select);
        
        // Hide original select but keep it for form submission
        select.style.position = 'absolute';
        select.style.opacity = '0';
        select.style.pointerEvents = 'none';
        select.style.width = '1px';
        select.style.height = '1px';
        
        // Move select into wrapper
        wrapper.appendChild(select);
        wrapper.appendChild(trigger);
        wrapper.appendChild(menu);
        
        // Handle select change events (if changed programmatically)
        select.addEventListener('change', function() {
            updateTriggerText();
            menu.querySelectorAll('.custom-select-item').forEach((item, index) => {
                if (index === select.selectedIndex) {
                    item.classList.add('selected');
                } else {
                    item.classList.remove('selected');
                }
            });
        });
    }
    
    function initCustomSelects() {
        // Convert all selects with .collection-filter-select class
        document.querySelectorAll('select.collection-filter-select').forEach(convertSelectToDropdown);
    }
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCustomSelects);
    } else {
        initCustomSelects();
    }
    
    // Watch for dynamically added selects
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === 1) {
                    if (node.tagName === 'SELECT' && node.classList.contains('collection-filter-select')) {
                        convertSelectToDropdown(node);
                    } else if (node.querySelectorAll) {
                        node.querySelectorAll('select.collection-filter-select').forEach(convertSelectToDropdown);
                    }
                }
            });
        });
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
})();

