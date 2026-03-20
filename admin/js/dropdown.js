/**
 * Unified Dropdown System
 * Simple dropdown handler that works everywhere
 */
(function() {
    'use strict';
    
    // Initialize all dropdowns
    function initDropdowns() {
        // Expose globally for re-initialization
        window.initDropdowns = initDropdowns;
        // Handle all dropdown triggers
        document.querySelectorAll('.dropdown-trigger, [data-dropdown]').forEach(trigger => {
            // Skip if already initialized
            if (trigger.dataset.dropdownInitialized) return;
            trigger.dataset.dropdownInitialized = 'true';
            
            trigger.addEventListener('click', function(e) {
                e.stopPropagation();
                
                const dropdown = this.closest('.dropdown');
                if (!dropdown) return;
                
                const menu = dropdown.querySelector('.dropdown-menu, .collection-actions-menu, .user-menu-dropdown, .notifications-dropdown');
                if (!menu) return;
                
                const isOpen = menu.classList.contains('show') || menu.classList.contains('active') || menu.style.display === 'block' || menu.style.display === 'flex';
                
                // Close all other dropdowns
                document.querySelectorAll('.dropdown-menu, .collection-actions-menu, .user-menu-dropdown, .notifications-dropdown').forEach(m => {
                    if (m !== menu) {
                        m.classList.remove('show', 'active');
                        m.style.display = 'none';
                    }
                });
                document.querySelectorAll('.dropdown-trigger, [data-dropdown]').forEach(t => {
                    if (t !== trigger) {
                        t.setAttribute('aria-expanded', 'false');
                    }
                });
                
                // Toggle current dropdown
                if (isOpen) {
                    menu.classList.remove('show', 'active');
                    // Explicitly set to none to override any inline styles
                    menu.style.display = 'none';
                    trigger.setAttribute('aria-expanded', 'false');
                } else {
                    menu.classList.add('show', 'active');
                    // Use display: block for most, flex for notifications
                    if (menu.classList.contains('notifications-dropdown')) {
                        menu.style.display = 'flex';
                    } else {
                        menu.style.display = 'block';
                    }
                    trigger.setAttribute('aria-expanded', 'true');
                }
            });
        });
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu, .collection-actions-menu, .user-menu-dropdown, .notifications-dropdown').forEach(menu => {
                    menu.classList.remove('show', 'active');
                    menu.style.display = 'none';
                });
                document.querySelectorAll('.dropdown-trigger, [data-dropdown]').forEach(trigger => {
                    trigger.setAttribute('aria-expanded', 'false');
                });
            }
        });
        
        // Close dropdowns on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.dropdown-menu, .collection-actions-menu, .user-menu-dropdown, .notifications-dropdown').forEach(menu => {
                    menu.classList.remove('show', 'active');
                    menu.style.display = 'none';
                });
                document.querySelectorAll('.dropdown-trigger, [data-dropdown]').forEach(trigger => {
                    trigger.setAttribute('aria-expanded', 'false');
                });
            }
        });
    }
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDropdowns);
    } else {
        initDropdowns();
    }
    
    // Re-initialize when new content is added (for dynamic content)
    const observer = new MutationObserver(function(mutations) {
        let shouldReinit = false;
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length > 0) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1 && (node.classList.contains('dropdown') || node.querySelector('.dropdown'))) {
                        shouldReinit = true;
                    }
                });
            }
        });
        if (shouldReinit) {
            initDropdowns();
        }
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
})();

