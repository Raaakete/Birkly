<?php
/**
 * Theme switcher — light / dark / system (wired by admin/js/theme.js).
 */
?>
<div class="theme-switcher" role="group" aria-label="Color theme">
    <button type="button" class="theme-btn" data-theme="light" aria-label="Light theme" title="Light">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <circle cx="12" cy="12" r="4"></circle>
            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path>
        </svg>
        <span class="theme-btn-label">Light</span>
    </button>
    <button type="button" class="theme-btn" data-theme="dark" aria-label="Dark theme" title="Dark">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
        </svg>
        <span class="theme-btn-label">Dark</span>
    </button>
    <button type="button" class="theme-btn" data-theme="system" aria-label="System theme" title="System">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <rect x="2" y="3" width="20" height="14" rx="2"></rect>
            <path d="M8 21h8M12 17v4"></path>
        </svg>
        <span class="theme-btn-label">Auto</span>
    </button>
</div>
