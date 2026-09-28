# Birkly admin design system

> **This file is a pointer, not a reference.**
>
> The Birch design language — the four questions, the eight roles, tokens, density, the component kit, pixel icons, gestures and motion, and the do/don't rules — is documented once, publicly, in Birkly-Docs:
>
> - **[Admin design language](https://docs.birkly.com/01-admin/admin-design-language.html)** — the build kit
> - **[Plugin admin styling](https://docs.birkly.com/06-plugin-economy/plugin-admin-styling.html)** — adoption layer, class and token reference, P56-to-Birch rename tables, the `.fieldtype-*` API
> - **[Admin design system](https://docs.birkly.com/01-admin/design-system.html)** — short orientation
> - **[Plugin navigation icon](https://docs.birkly.com/06-plugin-economy/plugin-navigation-icon.html)** — registry names through `birkly_nav_icon()`
>
> Source repo: `Raaakete/Birkly-Docs` → `01-admin/admin-design-language.md`, `06-plugin-economy/plugin-admin-styling.md`.
>
> **Do not re-document tokens, classes or values here.** A second reference drifts. When admin CSS changes, update Birkly-Docs.

Everything below is CMS-contributor-specific: where things live in this repo, which files are generated, and the JS class contracts that a redesign must not break.

---

## Canonical sources in this repo

| Concern | Path |
|---------|------|
| Tokens, light and dark (only file allowed to contain colour values) | `admin/css/core/variables.css` |
| Density: Compact / Comfortable / Large sizes | `admin/css/core/density.css` |
| P56 token and class aliases (plugin compatibility only) | `admin/css/core/compat-tokens.css`, `admin/css/core/migration-aliases.css` |
| Reset, focus ring, global reduced-motion block | `admin/css/core/reset.css` |
| Element typography, `.prose`, `.rich-text` | `admin/css/core/typography.css` |
| Role map (selector → role) | `admin/css/birch/roles.json` |
| Role CSS (generated, do not edit) | `admin/css/birch/roles.generated.css` |
| Birch component kit | `admin/css/birch/kit.css` |
| Micro-interactions and icon gestures | `admin/css/birch/motion.css` |
| `u-*` helper layer | `admin/css/core/helpers.css` |
| Source-order overrides (`[hidden]`) | `admin/css/core/overrides.css` |
| P56 components (being folded into the kit) | `admin/css/components/*.css` |
| Sidebar, topbar, page chrome, main content, grid | `admin/css/layout/*.css` |
| Fieldtype CSS API (frozen class names) | `admin/css/plugins/fieldtype-api.css` |
| Plugin page chrome and the adoption layer | `admin/css/plugin-page.css` |
| Import hub and load order | `admin/css/style.css` |
| Residual page stylesheets (not a pattern to copy) | `admin/css/pages/*.css` |
| Icon grids, parts and gestures (single source) | `core/icon_data.php` |
| Icon renderer and helpers | `core/icons.php` (`birkly_icon()`, `birkly_nav_icon()`) |
| Living component page (developer mode) | `admin/?page=birch-kit` |

**Load order matters.** `core/helpers.css`, `core/overrides.css`, `birch/kit.css` and `birch/motion.css` are imported **last**, after every P56 component, so a Birch rule beats a legacy one at equal specificity.

### Generated files

| Edit | Then run | Regenerates |
|------|----------|-------------|
| `admin/css/birch/roles.json` | `php scripts/build-birch-roles.php` | `admin/css/birch/roles.generated.css` |
| `core/icon_data.php` | `php scripts/build-icons.php` | The icon registry in `admin/js/icon.js` and `admin/assets/icons/*.svg` |

Both scripts take `--check`, which CI runs.

---

## Rules enforced in CI

`internal/cms/scripts/design-language-check.sh` (Birkly-Ops) gates:

1. No raw colour (`#hex`, `rgb()`, `hsl()`, `oklch()` …) outside `core/variables.css`.
2. No legacy (P56) token name in admin CSS; those exist only in the alias files.
3. Every `var(--x)` resolves to a definition.
4. No raw `z-index` layer number; `--z-*` only (`0` and `1` allowed inside a stacking context).
5. Media queries use only 640 / 768 / 1024 / 1280 / 1440 and their `max-width` complements (two documented page-grid exceptions).
6. `!important` only in the reduced-motion block in `core/reset.css` and a small budget of `display: none` hide guards.
7. No `transition: all`.
8. No `[data-theme]` rule outside the token file and the topbar shadow cast in `layout/header.css`.
9. Every public contract class (P56 and Birch kit) is defined somewhere in admin CSS.
10. The alias files contain only aliases.
11. Accent and mint are never a text colour; text uses `--accent-text`.
12. Total admin CSS code lines and each page stylesheet stay within their ratchet budgets.
13. Inline `style="` attributes stay within budget; no inline `<style>` blocks except fieldtype CSS injection.
14. `roles.generated.css`, `icon.js` and `assets/icons/` are fresh (the two `--check` runs).
15. Token contrast is at least 4.5:1 in light and dark; icons sit on the 13 × 13 grid with integer paths at 13 and 26 px; every `roles.json` selector has exactly one role; every gesture frame is whole cells and uses the motion tokens.
16. `birch-design-check.php`: no border on a slab selector, at most one mint button per statically detectable template region, sentence-case labels, icon sizes 13 and 26 only.

`internal/cms/scripts/design-smoke.sh` is the quick smoke: icon registry and Ops parity, `data-birch` on the admin body, kit and motion load order, and the gesture count. Run both before opening a UI PR.

---

## JS class contracts

CSS deletion without the matching JS edit is the most likely way a redesign silently breaks a page. These scripts build or toggle markup and depend on the class names shown.

| Script | Contract |
|--------|----------|
| `admin/partials/layout_footer.php` inline | `moveBreadcrumbsToTopbar()` moves `.page-header .breadcrumbs` into `#admin-topbar-breadcrumbs` and adds `.page-header--hoisted-empty` to the emptied row; toggles `data-bump` on `.notification-badge` when the count rises |
| `admin/js/page-actions.js` | Below 768 px, moves every control of `.page-bar`, `.main-actions-bar` and `.page-header-actions` into a More menu, except `.is-main` / `.btn-primary`, icon-only buttons, `[hidden]`, live regions and `.badge`. Adds `.has-page-more`, `.page-more-keep`, `.page-more-main`, `.page-more-icon`, `.page-more-unwrapped`; moves elements back when the viewport widens |
| `admin/js/breadcrumb-truncate.js` | Adds `.breadcrumb-segment`, `.breadcrumb-segment--current`, `.breadcrumb-ellipsis`, `.breadcrumbs--collapsed`, `.breadcrumbs--segment-ellipsis` |
| `admin/js/sticky-save-bar.js` | Toggles `.is-stuck` / `.is-stuck-enter` on `.main-actions-bar--sticky`; expects `.main-actions-left`, `.main-actions-right` |
| `admin/js/tabs-v2.js` | Owns `.tabs-v2-root`, `.tabs-v2--desktop`, `.tabs-v2--scroll`, `.tabs-v2--picker`, `.tabs-v2-picker-wrap`, `.tabs-v2-more-wrap`, `.tabs-v2-more-menu`, `.tabs-v2-more-item` |
| `admin/js/ui-feedback.js` | `BirklyFeedback.toast/confirm/prompt`. Dialogs are `.modal` + `.modal-backdrop` + `.modal-content.dialog`; toasts use `.birkly-feedback-toast*` |
| `admin/js/delegated-actions.js` | `[data-modal-dismiss]` closes the target modal (`self` or an id) by removing `.active`; to open one, add `.active` |
| `admin/js/accessibility.js` | Traps focus in an open `.modal`; Escape clicks `.modal-close`, `.modal-close-btn` or `[aria-label*="close" i]` |
| `admin/js/global-search.js` | `.search-overlay`, `.search-overlay-backdrop`, `.search-overlay-content` — a third overlay root alongside `.modal` |
| `admin/js/entries.js` | `.entries-smart-table`, `.table-viewport`, `.head-actions`, `.cell-actions`, `.is-scrolled-x` |
| `admin/js/theme.js` | Sets `data-theme` on `<html>` and `<body>` (`light` / `dark` / `system`) |
| `admin/js/icon.js` | `Birkly.icon(name, {gesture})`, `Birkly.iconImg()`, `Birkly.initIcons()` hydrating `[data-icon]` (and `data-icon-gesture`), `Birkly.iconPlay(el)` setting `data-play` for one gesture run; registry generated from `core/icon_data.php` |

Icons play their gesture on hover or `:focus-visible` of the control when the `svg.birkly-icon` is a direct child or one wrapper deep; `.is-busy` loops the loader chase. Plugin sidebar items resolve through `birkly_nav_icon()` in both `admin/partials/sidebar.php` and `api/plugins.php` (`get_navigation`).

`.loading` is kept unprefixed in `core/utilities.css` because admin JS toggles it on containers via `classList.add('loading')`. `.sr-only` is aliased to `.u-sr-only` for the same reason.

---

## Shared template partials

| Partial | Purpose |
|---------|---------|
| `admin/templates/components/sticky-save-bar.php` | The sticky action zone |
| `admin/templates/components/empty-state.html` | Empty state with optional action |
| `admin/templates/components/status-badge.html` | Status pill |
| `admin/templates/components/form-field.html` | Field wrapper |
| `admin/templates/components/modal.html` | Modal shell |
| `admin/templates/components/page-header.html`, `breadcrumb-builder.html` | Breadcrumb title source |
| `admin/templates/components/smart-table.php`, `smart-table.html` | Entry-list table |
| `admin/partials/plugin_page_shell.php` | Plugin breadcrumb header behind `{plugin_page_header}` |

---

## Form validation pattern

`admin/css/components/forms.css` — invalid styling applies **only after** the user submits or the form opts in:

1. Add `was-validated` to the `<form>` on submit attempt.
2. Invalid controls then show a `--danger` border.

Explicit states, independent of `:invalid`: `.is-invalid` on the control, or `.field-error-state` / `.form-group--error` / `.form-group.error` / `.field-block--error` on the wrapper. `.is-valid` / `.field-success-state` for explicit success only.

**Do not** style `:valid` globally — empty optional fields would show permanent success chrome.

---

## Font decision

Admin uses the native system UI stack only — no CDN webfont, no `@font-face` for brand text, per project values.

```css
--font-sans: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
--font-mono: ui-monospace, "SF Mono", Menlo, Consolas, monospace;
```

There is no `--font-primary`, `--font-secondary` or `--font-article`.
