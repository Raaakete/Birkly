<?php
/**
 * Theme switcher — light / dark / system (wired by admin/js/theme.js).
 */
?>
<div class="theme-switcher-wrap">
    <div class="dropdown theme-switcher-dropdown">
        <button type="button" class="dropdown-trigger topbar-icon-btn theme-switcher-trigger" aria-label="Color theme" aria-expanded="false" aria-haspopup="true">
            <span class="theme-switcher-trigger-icon theme-switcher-trigger-icon--light" data-theme-icon="light" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="4"></circle>
                    <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path>
                </svg>
            </span>
            <span class="theme-switcher-trigger-icon theme-switcher-trigger-icon--dark" data-theme-icon="dark" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                </svg>
            </span>
            <span class="theme-switcher-trigger-icon theme-switcher-trigger-icon--system" data-theme-icon="system" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="3" width="20" height="14" rx="2"></rect>
                    <path d="M8 21h8M12 17v4"></path>
                </svg>
            </span>
        </button>
        <div class="dropdown-menu dropdown-menu-right theme-switcher-menu" role="menu" aria-label="Color theme">
            <button type="button" class="dropdown-item theme-option" data-theme="light" role="menuitemradio">
                <span class="dropdown-item-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="4"></circle>
                        <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path>
                    </svg>
                </span>
                <span class="dropdown-item-label">Light</span>
            </button>
            <button type="button" class="dropdown-item theme-option" data-theme="dark" role="menuitemradio">
                <span class="dropdown-item-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                    </svg>
                </span>
                <span class="dropdown-item-label">Dark</span>
            </button>
            <button type="button" class="dropdown-item theme-option" data-theme="system" role="menuitemradio">
                <span class="dropdown-item-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2"></rect>
                        <path d="M8 21h8M12 17v4"></path>
                    </svg>
                </span>
                <span class="dropdown-item-label">Auto</span>
            </button>
        </div>
    </div>
</div>
