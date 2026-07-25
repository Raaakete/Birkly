<?php
/**
 * Theme switcher — light / dark / system (wired by admin/js/theme.js).
 */
if (!function_exists('birkly_icon')) {
    require_once __DIR__ . '/../../core/icons.php';
}
?>
<div class="theme-switcher-wrap">
    <div class="dropdown theme-switcher-dropdown">
        <button type="button" class="dropdown-trigger topbar-icon-btn theme-switcher-trigger" aria-label="Color theme" aria-expanded="false" aria-haspopup="true">
            <span class="theme-switcher-trigger-icon theme-switcher-trigger-icon--light" data-theme-icon="light" aria-hidden="true">
                <?= birkly_icon('sun') ?>
            </span>
            <span class="theme-switcher-trigger-icon theme-switcher-trigger-icon--dark" data-theme-icon="dark" aria-hidden="true">
                <?= birkly_icon('moon') ?>
            </span>
            <span class="theme-switcher-trigger-icon theme-switcher-trigger-icon--system" data-theme-icon="system" aria-hidden="true">
                <?= birkly_icon('monitor') ?>
            </span>
        </button>
        <div class="dropdown-menu dropdown-menu-right theme-switcher-menu" role="menu" aria-label="Color theme">
            <button type="button" class="dropdown-item theme-option" data-theme="light" role="menuitemradio">
                <span class="dropdown-item-icon" aria-hidden="true">
                    <?= birkly_icon('sun') ?>
                </span>
                <span class="dropdown-item-label">Light</span>
            </button>
            <button type="button" class="dropdown-item theme-option" data-theme="dark" role="menuitemradio">
                <span class="dropdown-item-icon" aria-hidden="true">
                    <?= birkly_icon('moon') ?>
                </span>
                <span class="dropdown-item-label">Dark</span>
            </button>
            <button type="button" class="dropdown-item theme-option" data-theme="system" role="menuitemradio">
                <span class="dropdown-item-icon" aria-hidden="true">
                    <?= birkly_icon('monitor') ?>
                </span>
                <span class="dropdown-item-label">Auto</span>
            </button>
        </div>
    </div>
</div>
