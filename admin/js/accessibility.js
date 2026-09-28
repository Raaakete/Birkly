/**
 * Accessibility Enhancements
 * Adds ARIA labels, keyboard navigation, and screen reader support
 */
(function() {
    'use strict';

    var keyboardNavBound = false;
    var bodyObserver = null;
    var reinitTimer = null;
    
    function init() {
        enhanceButtons();
        enhanceForms();
        enhanceNavigation();
        addSkipLinks();
        enhanceModals();
        enhanceTables();
        setupKeyboardNavigation();
    }
    
    /**
     * Add ARIA labels to buttons without text
     */
    function enhanceButtons() {
        document.querySelectorAll('button:not([data-a11y-enhanced]), .btn:not([data-a11y-enhanced])').forEach(button => {
            button.setAttribute('data-a11y-enhanced', 'button');
            const hasText = button.textContent.trim().length > 0;
            const hasAriaLabel = button.hasAttribute('aria-label');
            const title = button.getAttribute('title');
            
            if (!hasText && !hasAriaLabel) {
                // Try to infer from icon or title
                if (title) {
                    button.setAttribute('aria-label', title);
                } else if (button.querySelector('svg')) {
                    // Try to get meaningful label from context
                    const icon = button.querySelector('svg');
                    const iconTitle = icon.getAttribute('title') || icon.getAttribute('aria-label');
                    if (iconTitle) {
                        button.setAttribute('aria-label', iconTitle);
                    }
                }
            }
            
            // Ensure buttons have type attribute
            if (button.tagName === 'BUTTON' && !button.hasAttribute('type')) {
                button.setAttribute('type', 'button');
            }
        });
    }
    
    /**
     * Enhance form accessibility
     */
    function enhanceForms() {
        document.querySelectorAll('.form-group:not([data-a11y-enhanced]), .field-block:not([data-a11y-enhanced])').forEach(group => {
            group.setAttribute('data-a11y-enhanced', 'form-group');
            const input = group.querySelector('input, textarea, select');
            const help = group.querySelector('.help-text, .field-help, .form-text');
            
            if (input && help && !input.hasAttribute('aria-describedby')) {
                // Keep an existing id: page scripts look help text up by it.
                if (!help.id) {
                    help.id = 'help-' + (input.id || Math.random().toString(36).substr(2, 9));
                }
                input.setAttribute('aria-describedby', help.id);
            }
            
            // Mark required fields
            if (input && input.hasAttribute('required')) {
                input.setAttribute('aria-required', 'true');
                
                // Ensure label indicates required
                const label = group.querySelector('label, .field-title');
                if (label && !label.textContent.includes('*') && !label.textContent.includes('Required')) {
                    label.innerHTML += ' <span aria-label="required">*</span>';
                }
            }
        });
        
        // Field-block inline validation feedback
        document.querySelectorAll('.field-block--error .field-feedback.error, .field-block.error .field-feedback.error').forEach(error => {
            const input = error.closest('.field-block')?.querySelector('input, textarea, select');
            if (input) {
                if (!error.id) {
                    error.id = 'error-' + Math.random().toString(36).substr(2, 9);
                }
                input.setAttribute('aria-invalid', 'true');
                input.setAttribute('aria-describedby', error.id);
            }
        });
        
        // Add error announcements
        document.querySelectorAll('.error, .field-error').forEach(error => {
            // Empty feedback slots are placeholders for later validation, not errors.
            if (!error.textContent.trim() || error.hidden) {
                return;
            }
            const input = error.closest('.form-group, .field-block')?.querySelector('input, textarea, select');
            if (input && !input.hasAttribute('aria-invalid')) {
                input.setAttribute('aria-invalid', 'true');
                input.setAttribute('aria-describedby', error.id || (error.id = 'error-' + Math.random().toString(36).substr(2, 9)));
            }
        });
    }
    
    /**
     * Enhance navigation accessibility
     */
    function enhanceNavigation() {
        // Add ARIA labels to navigation
        const nav = document.querySelector('.sidebar, nav, .nav-links');
        if (nav && !nav.hasAttribute('aria-label')) {
            nav.setAttribute('aria-label', 'Main navigation');
        }
        
        // Mark current page in navigation — aria-current only on active links
        document.querySelectorAll('.nav-link').forEach(link => {
            if (link.classList.contains('active')) {
                link.setAttribute('aria-current', 'page');
            } else {
                link.removeAttribute('aria-current');
            }
        });
        
        // Add keyboard navigation to nav items
        document.querySelectorAll('.nav-link:not([data-a11y-nav-enhanced]), .nav-item a:not([data-a11y-nav-enhanced])').forEach((link) => {
            link.setAttribute('data-a11y-nav-enhanced', 'true');
            link.setAttribute('tabindex', '0');
            
            link.addEventListener('keydown', function(e) {
                const links = Array.from(link.parentElement.parentElement.querySelectorAll('a'));
                const currentIndex = links.indexOf(link);
                
                if (e.key === 'ArrowDown' || e.key === 'ArrowRight') {
                    e.preventDefault();
                    const next = links[currentIndex + 1] || links[0];
                    if (next) next.focus();
                } else if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') {
                    e.preventDefault();
                    const prev = links[currentIndex - 1] || links[links.length - 1];
                    if (prev) prev.focus();
                } else if (e.key === 'Home') {
                    e.preventDefault();
                    links[0].focus();
                } else if (e.key === 'End') {
                    e.preventDefault();
                    links[links.length - 1].focus();
                }
            });
        });
    }
    
    /**
     * Add skip links for keyboard users
     */
    function addSkipLinks() {
        if (document.getElementById('skip-links')) return;
        
        const skipLinks = document.createElement('nav');
        skipLinks.id = 'skip-links';
        skipLinks.className = 'skip-links';
        skipLinks.setAttribute('aria-label', 'Skip links');
        skipLinks.innerHTML = `
            <a href="#main-content" class="skip-link">Skip to main content</a>
            <a href="#admin-sidebar" class="skip-link">Skip to navigation</a>
        `;
        
        // Add main content landmark
        const main = document.querySelector('main, .main-content, .editor-main, .dashboard-page, .entries-page') || document.body;
        if (!main.id) {
            main.id = 'main-content';
        }
        
        // Add navigation landmark
        const nav = document.querySelector('.sidebar, nav');
        if (nav && !nav.id) {
            nav.id = 'navigation';
        }
        
        document.body.insertBefore(skipLinks, document.body.firstChild);
        
        // Add styles for skip links
        const style = document.createElement('style');
        style.textContent = `
            .skip-links {
                position: absolute;
                top: -100px;
                left: 0;
                z-index: 10000;
            }
            .skip-link {
                position: absolute;
                top: 0;
                left: 0;
                padding: 8px 16px;
                background: #000;
                color: #fff;
                text-decoration: none;
                z-index: 10001;
                transform: translateY(0);
                transition: transform 0.2s;
            }
            .skip-link:focus {
                transform: translateY(100px);
            }
        `;
        document.head.appendChild(style);
    }
    
    /**
     * Enhance modal accessibility
     */
    function enhanceModals() {
        document.querySelectorAll('.modal:not([data-a11y-modal-enhanced]), .modal-backdrop:not([data-a11y-modal-enhanced])').forEach(modal => {
            modal.setAttribute('data-a11y-modal-enhanced', 'true');
            // Set ARIA attributes
            if (!modal.hasAttribute('role')) {
                modal.setAttribute('role', 'dialog');
            }
            if (!modal.hasAttribute('aria-modal')) {
                modal.setAttribute('aria-modal', 'true');
            }
            
            const modalTitle = modal.querySelector('h2, h3, .modal-title');
            if (modalTitle && !modal.hasAttribute('aria-labelledby')) {
                modal.setAttribute('aria-labelledby', modalTitle.id || (modalTitle.id = 'modal-title-' + Math.random().toString(36).substr(2, 9)));
            }
            
            // Trap focus in modal
            const focusableElements = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            const firstFocusable = focusableElements[0];
            const lastFocusable = focusableElements[focusableElements.length - 1];
            
            modal.addEventListener('keydown', function(e) {
                if (e.key === 'Tab') {
                    if (e.shiftKey) {
                        if (document.activeElement === firstFocusable) {
                            e.preventDefault();
                            lastFocusable.focus();
                        }
                    } else {
                        if (document.activeElement === lastFocusable) {
                            e.preventDefault();
                            firstFocusable.focus();
                        }
                    }
                } else if (e.key === 'Escape') {
                    const closeBtn = modal.querySelector('.modal-close, .modal-close-btn, [aria-label*="close" i]');
                    if (closeBtn) {
                        closeBtn.click();
                    }
                }
            });
            
            // Remember previous focus
            let previousFocus = null;
            const showHandler = () => {
                previousFocus = document.activeElement;
                setTimeout(() => {
                    if (modal.contains(document.activeElement)) {
                        return;
                    }
                    const target = modal.querySelector('[autofocus]') || firstFocusable;
                    if (target) {
                        target.focus();
                    }
                }, 100);
            };
            const hideHandler = () => {
                if (previousFocus) {
                    previousFocus.focus();
                }
            };
            
            // Observe modal visibility
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.attributeName === 'style' || mutation.attributeName === 'class') {
                        const isVisible = modal.style.display !== 'none' && !modal.classList.contains('hidden');
                        if (isVisible) {
                            showHandler();
                        } else {
                            hideHandler();
                        }
                    }
                });
            });
            observer.observe(modal, { attributes: true });
        });
    }
    
    /**
     * Enhance table accessibility
     */
    function enhanceTables() {
        document.querySelectorAll('table:not([data-a11y-table-enhanced])').forEach(table => {
            table.setAttribute('data-a11y-table-enhanced', 'true');
            // Add caption if missing
            if (!table.querySelector('caption')) {
                const caption = document.createElement('caption');
                caption.className = 'sr-only';
                caption.textContent = table.getAttribute('aria-label') || 'Data table';
                table.insertBefore(caption, table.firstChild);
            }
            
            // Ensure proper headers
            const headers = table.querySelectorAll('th');
            headers.forEach(header => {
                const scope = header.closest('thead') ? 'col' : 'row';
                if (!header.hasAttribute('scope')) {
                    header.setAttribute('scope', scope);
                }
            });
            
            // Add keyboard navigation
            table.setAttribute('tabindex', '0');
            table.setAttribute('role', 'table');
        });
    }
    
    /**
     * Setup global keyboard navigation
     */
    function setupKeyboardNavigation() {
        if (keyboardNavBound) return;
        keyboardNavBound = true;

        document.addEventListener('keydown', function(e) {
            // Alt+/ to toggle keyboard shortcuts
            if (e.altKey && e.key === '/') {
                e.preventDefault();
                toggleShortcutsHelp();
            }
            
            // Escape to close modals/dropdowns
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal[style*="block"], .modal:not([style*="none"])');
                if (openModal) {
                    const closeBtn = openModal.querySelector('.modal-close, .modal-close-btn');
                    if (closeBtn) closeBtn.click();
                }
                
                // Close dropdowns
                document.querySelectorAll('.dropdown.open, .dropdown-menu.show').forEach(dropdown => {
                    dropdown.classList.remove('open', 'show');
                });
            }
        });
    }
    
    function toggleShortcutsHelp() {
        let help = document.getElementById('keyboard-shortcuts-help');
        if (!help) {
            help = document.createElement('div');
            help.id = 'keyboard-shortcuts-help';
            help.className = 'keyboard-shortcuts';
            help.setAttribute('role', 'dialog');
            help.setAttribute('aria-modal', 'true');
            help.setAttribute('aria-labelledby', 'shortcuts-title');
            help.innerHTML = `
                <div class="shortcuts-content">
                    <h2 id="shortcuts-title">Keyboard Shortcuts</h2>
                    <ul>
                        <li><kbd>Alt</kbd> + <kbd>/</kbd> - Show this help</li>
                        <li><kbd>Esc</kbd> - Close modals/dropdowns</li>
                        <li><kbd>Tab</kbd> - Navigate forward</li>
                        <li><kbd>Shift</kbd> + <kbd>Tab</kbd> - Navigate backward</li>
                        <li><kbd>Enter</kbd> - Activate button/link</li>
                        <li><kbd>Arrow Keys</kbd> - Navigate lists/menus</li>
                        <li><kbd>Home</kbd> - Go to first item</li>
                        <li><kbd>End</kbd> - Go to last item</li>
                    </ul>
                    <button type="button" class="btn btn-primary" data-modal-dismiss="self">Close</button>
                </div>
            `;
            document.body.appendChild(help);
            
            // Style
            const style = document.createElement('style');
            style.textContent = `
                .keyboard-shortcuts {
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    background: white;
                    padding: 2rem;
                    border-radius: 8px;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    z-index: 10000;
                    max-width: 500px;
                }
                .keyboard-shortcuts h2 {
                    margin-top: 0;
                }
                .keyboard-shortcuts ul {
                    list-style: none;
                    padding: 0;
                }
                .keyboard-shortcuts li {
                    padding: 0.5rem 0;
                    border-bottom: 1px solid #eee;
                }
                .keyboard-shortcuts kbd {
                    background: #f4f4f4;
                    border: 1px solid #ccc;
                    border-radius: 3px;
                    padding: 2px 6px;
                    font-family: monospace;
                    font-size: 0.9em;
                }
            `;
            document.head.appendChild(style);
        }
        
        help.style.display = help.style.display === 'none' ? 'block' : 'none';
    }
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    function scheduleReinit() {
        clearTimeout(reinitTimer);
        reinitTimer = setTimeout(init, 150);
    }

    if (!bodyObserver) {
        bodyObserver = new MutationObserver(function(mutations) {
            for (var i = 0; i < mutations.length; i++) {
                if (mutations[i].addedNodes.length > 0) {
                    scheduleReinit();
                    return;
                }
            }
        });
        bodyObserver.observe(document.body, { childList: true, subtree: true });
    }
})();

