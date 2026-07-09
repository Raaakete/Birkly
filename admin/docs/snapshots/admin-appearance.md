# Admin appearance — theme and responsive layout

Light, dark, and system theme for the Birkly admin, plus responsive layout on key flows (Phase 15).

## Summary

The admin header includes a **theme switcher**: **Light**, **Dark**, or **System** (follow OS preference). Choice persists in browser `localStorage`. Phase 15 audits CSS variables for Commerce, Settings, Marketplace, and ContentEditor panels, and improves mobile layout for dashboard, entry lists, commerce admin, and settings near the **768px** breakpoint.

## Beginner

**Change your theme**

1. Look at the **top bar** of the admin (next to your account menu).
2. Click the **sun** (light), **moon** (dark), or **monitor** (system) icon.
3. The admin recolors immediately; your choice is remembered on this browser.

**System theme**

- **System** uses your computer's light/dark preference.
- If you change OS appearance, the admin updates automatically while System is selected.

**Mobile and tablet**

- On narrow screens, the sidebar collapses behind a **menu** button.
- Dashboard cards, entry tables, and Commerce tabs scroll horizontally or stack vertically so primary actions stay reachable.
- If text is hard to read in dark mode on a specific screen, report via **Feedback** with a screenshot.

**What theme does not change**

- Your public website appearance — only the CMS admin panel.
- Content entry HTML or storefront templates.

## Advanced Users

**Client:** `admin/js/theme.js` — `Birkly.Theme`

- `THEMES: ['light', 'dark', 'system']`
- `STORAGE_KEY: 'birkly_theme'`
- Sets `data-theme` on `document.documentElement` and `body`
- `getResolvedTheme()` resolves `system` via `prefers-color-scheme`
- Switcher markup: `admin/partials/theme-switcher.php`

**CSS:** Design tokens under `[data-theme="dark"]` and shared variables in admin stylesheets. Audited surfaces (P15-M1): Commerce admin tabs, Settings global save bar, Marketplace install panel, ContentEditor side panels, dashboard widgets.

**Responsive:** Breakpoint ~768px — sidebar toggle (`initMobileSidebar`), table overflow, stacked form rows.

**No server setting:** Theme is per-browser client preference; not stored in `settings/`.

## Related

- [Dashboard](dashboard.md)
- [Settings](settings.md)
- [Help and onboarding](help-and-onboarding.md)
