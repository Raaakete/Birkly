/**
 * Birkly Admin Theme
 * Theme management for the admin interface (light / dark / system)
 */

window.Birkly = window.Birkly || {};

Birkly.Theme = {
    THEMES: ['light', 'dark', 'system'],
    STORAGE_KEY: 'birkly_theme',
    currentTheme: 'light',
    _mediaQuery: null,

    init() {
        const saved = localStorage.getItem(this.STORAGE_KEY);
        if (saved && this.THEMES.includes(saved)) {
            this.currentTheme = saved;
        }
        this.applyTheme();
        this._bindSystemPreferenceListener();
        this.initSwitcher();
        this.initMobileSidebar();
        this.initDesktopSidebarCollapse();
    },

    applyTheme() {
        document.documentElement.setAttribute('data-theme', this.currentTheme);
        document.body.setAttribute('data-theme', this.currentTheme);
        this._updateSwitcherUI();
    },

    setTheme(theme) {
        if (!this.THEMES.includes(theme)) {
            return;
        }
        this.currentTheme = theme;
        localStorage.setItem(this.STORAGE_KEY, theme);
        this.applyTheme();
    },

    toggleTheme() {
        const next = this.currentTheme === 'light' ? 'dark' : 'light';
        this.setTheme(next);
    },

    getCurrentTheme() {
        return this.currentTheme;
    },

    getResolvedTheme() {
        if (this.currentTheme === 'system') {
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        return this.currentTheme;
    },

    initSwitcher() {
        document.querySelectorAll('.theme-option').forEach((btn) => {
            btn.addEventListener('click', () => {
                const theme = btn.getAttribute('data-theme-option');
                if (!theme) {
                    return;
                }
                this.setTheme(theme);
                this._closeThemeDropdown(btn.closest('.theme-switcher-dropdown'));
            });
        });

        this._updateSwitcherUI();
    },

    _closeThemeDropdown(dropdown) {
        if (!dropdown) {
            return;
        }
        const menu = dropdown.querySelector('.theme-switcher-menu');
        const trigger = dropdown.querySelector('.dropdown-trigger');
        if (menu) {
            menu.classList.remove('show', 'active');
            menu.style.display = 'none';
        }
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        }
    },

    _updateSwitcherUI() {
        const labels = { light: 'Light', dark: 'Dark', system: 'Auto' };

        document.querySelectorAll('.theme-switcher-trigger-icon').forEach((icon) => {
            const theme = icon.getAttribute('data-theme-icon');
            icon.classList.toggle('is-active', theme === this.currentTheme);
        });

        const trigger = document.querySelector('.theme-switcher-trigger');
        if (trigger) {
            const label = labels[this.currentTheme] || this.currentTheme;
            trigger.setAttribute('aria-label', `Color theme: ${label}`);
            trigger.setAttribute('title', `Color theme: ${label}`);
        }

        document.querySelectorAll('.theme-option').forEach((btn) => {
            const theme = btn.getAttribute('data-theme-option');
            const active = theme === this.currentTheme;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-checked', active ? 'true' : 'false');
        });
    },

    _bindSystemPreferenceListener() {
        if (this._mediaQuery) {
            return;
        }
        this._mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        this._mediaQuery.addEventListener('change', () => {
            if (this.currentTheme === 'system') {
                this._updateSwitcherUI();
            }
        });
    },

    initMobileSidebar() {
        const toggle = document.getElementById('sidebar-toggle');
        const sidebar = document.querySelector('.sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        if (!toggle || !sidebar) {
            return;
        }

        const closeSidebar = () => {
            sidebar.classList.remove('open');
            if (backdrop) {
                backdrop.classList.remove('visible');
            }
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Open menu');
            document.body.classList.remove('sidebar-open');
        };

        const openSidebar = () => {
            sidebar.classList.add('open');
            if (backdrop) {
                backdrop.classList.add('visible');
            }
            toggle.setAttribute('aria-expanded', 'true');
            toggle.setAttribute('aria-label', 'Close menu');
            document.body.classList.add('sidebar-open');
        };

        let toggleLock = false;
        const handleToggle = (e) => {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            if (toggleLock) {
                return;
            }
            toggleLock = true;
            window.setTimeout(() => {
                toggleLock = false;
            }, 250);
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        };

        toggle.addEventListener('click', handleToggle);

        if (backdrop) {
            backdrop.addEventListener('click', closeSidebar);
        }

        sidebar.querySelectorAll('.nav-links a').forEach((link) => {
            link.addEventListener('click', () => {
                if (window.matchMedia('(max-width: 1279px)').matches) {
                    closeSidebar();
                }
            });
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        });

        window.addEventListener('resize', () => {
            if (window.matchMedia('(min-width: 1280px)').matches) {
                closeSidebar();
            }
        });
    },

    initDesktopSidebarCollapse() {
        const STORAGE_KEY = 'birkly_sidebar_collapsed';
        const DESKTOP_QUERY = '(min-width: 1280px)';
        const COMPACT_QUERY = '(min-width: 1280px) and (max-width: 1439px)';
        const toggle = document.getElementById('sidebar-collapse-toggle');
        if (!toggle) {
            return;
        }

        const hasSavedPreference = () => localStorage.getItem(STORAGE_KEY) !== null;
        const updateCollapseHint = () => {
            const suggest = !hasSavedPreference() && window.matchMedia(COMPACT_QUERY).matches;
            document.body.classList.toggle('sidebar-collapse-suggested', suggest);
        };

        const applyCollapsed = (collapsed) => {
            document.body.classList.toggle('sidebar-collapsed', collapsed);
            toggle.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
            toggle.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
            toggle.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
            const label = toggle.querySelector('.sidebar-collapse-label');
            if (label) {
                label.textContent = collapsed ? 'Expand' : 'Collapse';
            }
            document.dispatchEvent(new CustomEvent('birkly:layout-change'));
        };

        const saved = localStorage.getItem(STORAGE_KEY) === '1';
        if (saved && window.matchMedia(DESKTOP_QUERY).matches) {
            applyCollapsed(true);
        }
        updateCollapseHint();

        toggle.addEventListener('click', () => {
            if (!window.matchMedia(DESKTOP_QUERY).matches) {
                return;
            }
            const next = !document.body.classList.contains('sidebar-collapsed');
            localStorage.setItem(STORAGE_KEY, next ? '1' : '0');
            applyCollapsed(next);
            updateCollapseHint();
        });

        window.addEventListener('resize', () => {
            if (!window.matchMedia(DESKTOP_QUERY).matches) {
                document.body.classList.remove('sidebar-collapsed');
            } else {
                applyCollapsed(localStorage.getItem(STORAGE_KEY) === '1');
            }
            updateCollapseHint();
        });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    Birkly.Theme.init();
    if (Birkly.Utils && typeof Birkly.Utils.enhanceButtons === 'function') {
        Birkly.Utils.enhanceButtons(document);
    }
});

/* Enhance dynamically inserted buttons on first hover (modals, tables, etc.) */
document.addEventListener('pointerover', (event) => {
    const btn = event.target && event.target.closest ? event.target.closest('.btn') : null;
    if (!btn || !Birkly.Utils || typeof Birkly.Utils._enhanceButton !== 'function') return;
    Birkly.Utils._enhanceButton(btn);
}, true);
