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
        const switcher = document.querySelector('.theme-switcher');
        if (!switcher) {
            return;
        }
        switcher.querySelectorAll('.theme-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                const theme = btn.getAttribute('data-theme');
                if (theme) {
                    this.setTheme(theme);
                }
            });
        });
        this._updateSwitcherUI();
    },

    _updateSwitcherUI() {
        document.querySelectorAll('.theme-switcher .theme-btn').forEach((btn) => {
            const theme = btn.getAttribute('data-theme');
            const active = theme === this.currentTheme;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
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
            document.body.classList.remove('sidebar-open');
        };

        const openSidebar = () => {
            sidebar.classList.add('open');
            if (backdrop) {
                backdrop.classList.add('visible');
            }
            toggle.setAttribute('aria-expanded', 'true');
            document.body.classList.add('sidebar-open');
        };

        toggle.addEventListener('click', () => {
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });

        if (backdrop) {
            backdrop.addEventListener('click', closeSidebar);
        }

        sidebar.querySelectorAll('.nav-links a').forEach((link) => {
            link.addEventListener('click', () => {
                if (window.matchMedia('(max-width: 768px)').matches) {
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
            if (window.matchMedia('(min-width: 769px)').matches) {
                closeSidebar();
            }
        });
    },

    initDesktopSidebarCollapse() {
        const STORAGE_KEY = 'birkly_sidebar_collapsed';
        const toggle = document.getElementById('sidebar-collapse-toggle');
        if (!toggle) {
            return;
        }

        const applyCollapsed = (collapsed) => {
            document.body.classList.toggle('sidebar-collapsed', collapsed);
            toggle.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
            toggle.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
            toggle.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
            const label = toggle.querySelector('.sidebar-collapse-label');
            if (label) {
                label.textContent = collapsed ? 'Expand' : 'Collapse';
            }
        };

        const saved = localStorage.getItem(STORAGE_KEY) === '1';
        if (saved && window.matchMedia('(min-width: 769px)').matches) {
            applyCollapsed(true);
        }

        toggle.addEventListener('click', () => {
            if (!window.matchMedia('(min-width: 769px)').matches) {
                return;
            }
            const next = !document.body.classList.contains('sidebar-collapsed');
            localStorage.setItem(STORAGE_KEY, next ? '1' : '0');
            applyCollapsed(next);
        });

        window.addEventListener('resize', () => {
            if (!window.matchMedia('(min-width: 769px)').matches) {
                document.body.classList.remove('sidebar-collapsed');
            } else {
                applyCollapsed(localStorage.getItem(STORAGE_KEY) === '1');
            }
        });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    Birkly.Theme.init();
});
