# Birkly admin design system

> **This file is a pointer, not a reference.**
>
> The design language — tokens, the `u-*` helper layer, the component catalog, the page-chrome recipe and the do/don't rules — is documented once, publicly, in Birkly-Docs:
>
> - **[Admin design language](https://docs.birkly.com/01-admin/admin-design-language.html)** — the build kit
> - **[Plugin admin styling](https://docs.birkly.com/06-plugin-economy/plugin-admin-styling.html)** — adoption tiers, the public class contract, the `.fieldtype-*` API
> - **[Admin design system](https://docs.birkly.com/01-admin/design-system.html)** — short overview, icons, theming
>
> Source repo: `Raaakete/Birkly-Docs` → `01-admin/admin-design-language.md`, `06-plugin-economy/plugin-admin-styling.md`.
>
> **Do not re-document tokens, classes or values here.** A second reference drifts, and this one already did. When admin CSS changes, update Birkly-Docs.

Everything below is CMS-contributor-specific: where things live in this repo, and the JS class contracts that a redesign must not break.

---

## Canonical sources in this repo

| Concern | Path |
|---------|------|
| Tokens (only file allowed to contain hex) | `admin/css/core/variables.css` |
| Legacy token aliases (plugin compatibility only) | `admin/css/core/compat-tokens.css` |
| Reset, focus ring, global reduced-motion block | `admin/css/core/reset.css` |
| Element typography, `.prose`, `.rich-text` | `admin/css/core/typography.css` |
| `u-*` helper layer | `admin/css/core/helpers.css` |
| Source-order overrides (`[hidden]`) | `admin/css/core/overrides.css` |
| Components | `admin/css/components/*.css` |
| Sidebar, topbar, page chrome, main content, grid | `admin/css/layout/*.css` |
| Fieldtype CSS API (frozen class names) | `admin/css/plugins/fieldtype-api.css` |
| Plugin page chrome + plugin dialect bridges | `admin/css/plugin-page.css` |
| Import hub and load order | `admin/css/style.css` |
| Residual page stylesheets (not a pattern to copy) | `admin/css/pages/*.css` |

**Load order matters.** `core/helpers.css` and `core/overrides.css` are imported **last**, after every component. A `u-*` helper has to beat a component that sets the same property at equal specificity, and source order is what decides that.

---

## Rules enforced in CI

`internal/cms/scripts/design-language-check.sh` (Birkly-Ops) gates:

1. No raw hex outside `core/variables.css` (plus one documented third-party-brand exception).
2. No legacy token name in admin CSS — those exist only in the compat shim for plugin CSS.
3. Every `var(--x)` resolves to a definition.
4. No raw `z-index` layer number; `--z-*` only (`0` and `1` allowed as intra-stacking-context values).
5. Media queries use only 640 / 768 / 1024 / 1280 / 1440 and their `max-width` complements.
6. `!important` budget — allowlisted to the global reduced-motion block in `core/reset.css`.
7. No `transition: all`.
8. No `[data-theme]` rule outside the token file and the topbar shadow cast in `layout/header.css`.
9. Every public contract class is defined somewhere in admin CSS.
10. `compat-tokens.css` contains only aliases, inside budget.
11. No `color: var(--accent | --primary-500 | --primary-700)` — the accent-as-text ban.
12. Total admin CSS code lines and the largest single page stylesheet stay within budget.
13. Inline `style="` attributes and inline `<style>` blocks in templates stay within budget.

Run it before opening a UI PR.

---

## JS class contracts

CSS deletion without the matching JS edit is the most likely way a redesign silently breaks a page. These scripts build or toggle markup and depend on the class names shown.

| Script | Contract |
|--------|----------|
| `admin/js/layout_footer.php` inline / topbar hoist | `moveBreadcrumbsToTopbar()` moves `.page-header .breadcrumbs` into `#admin-topbar-breadcrumbs` and adds `.page-header--hoisted-empty` to the emptied row |
| `admin/js/breadcrumb-truncate.js` | Adds `.breadcrumb-segment`, `.breadcrumb-segment--current`, `.breadcrumb-ellipsis`, `.breadcrumbs--collapsed`, `.breadcrumbs--segment-ellipsis` |
| `admin/js/sticky-save-bar.js` | Toggles `.is-stuck` / `.is-stuck-enter` on `.main-actions-bar--sticky`; expects `.main-actions-left`, `.main-actions-right`, `.main-actions-tools`, `.main-actions-message` |
| `admin/js/tabs-v2.js` | Owns `.tabs-v2-root`, `.tabs-v2--desktop`, `.tabs-v2--scroll`, `.tabs-v2--picker`, `.tabs-v2-picker-wrap`, `.tabs-v2-more-wrap`, `.tabs-v2-more-menu`, `.tabs-v2-more-item` |
| `admin/js/ui-feedback.js` | `BirklyFeedback.toast/confirm/prompt`. Dialogs are ordinary `.modal-backdrop` + `.modal-content` modals; toasts use `.birkly-feedback-toast*` |
| `admin/js/global-search.js` | `.search-overlay`, `.search-overlay-backdrop`, `.search-overlay-content` — a third overlay root alongside `.modal` |
| `admin/js/entries.js` | `.entries-smart-table`, `.table-viewport`, `.head-actions`, `.cell-actions`, `.is-scrolled-x` |
| `admin/js/theme.js` | Sets `data-theme` on `<html>` and `<body>` (`light` / `dark` / `system`) |
| `admin/js/icon.js` | `Birkly.icon()`, `Birkly.iconImg()`, `Birkly.initIcons()` hydrating `[data-icon]`; registry parity with `core/icons.php` |

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
2. Invalid controls then show a `--danger-fg` border.

Explicit states, independent of `:invalid`: `.is-invalid` on the control, or `.field-error-state` / `.form-group--error` / `.form-group.error` / `.field-block--error` on the wrapper. `.is-valid` / `.field-success-state` for explicit success only.

**Do not** style `:valid` globally — empty optional fields would show permanent success chrome.

---

## Font decision

Admin uses the native system UI stack only — no CDN webfont, no `@font-face` for brand text, per project values.

```css
--font-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
--font-mono: "SF Mono", Monaco, "Cascadia Code", "Roboto Mono", Consolas, "Courier New", monospace;
```

There is no `--font-primary`, `--font-secondary` or `--font-article`.
