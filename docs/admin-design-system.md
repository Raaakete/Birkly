# Birkly admin design system

Developer reference for admin UI tokens, components, and interaction patterns. Canonical source: `admin/css/core/variables.css`. Phase 44 (P44) extends Phase 41 with settings IA, save semantics, hosting wizard, and feedback standards.

**Related:** Birkly-Docs `guides/admin-ux.md` (user-facing) · `docs/reviews/P44-verification-matrix.md` (V-UX checklist)

---

## CSS tokens

All tokens are defined in `admin/css/core/variables.css` and consumed via `var(--token-name)`.

### Primary (mint)

| Token | Light default | Use |
|-------|---------------|-----|
| `--color-primary` | `#7CE3BF` | Primary buttons, brand accent |
| `--color-primary-hover` | `#8CECCA` | Hover / dark-theme primary |
| `--color-primary-active` | `#79E1BD` | Pressed state |
| `--primary-50` … `--primary-900` | Mint scale | Backgrounds, borders, focus rings |
| `--primary-light` | `var(--primary-100)` | Subtle highlight fills |

Prefer `--primary-500` / `--primary-600` for focus outlines and active states. Do **not** use Tailwind blue (`#3b82f6`, `#2563eb`) in new admin CSS.

### Neutrals, semantic, surfaces

- **Neutrals:** `--color-neutral-100` … `--color-neutral-900`, `--gray-*` (legacy)
- **Semantic:** `--success-*`, `--error-*`, `--warning-*`
- **Surfaces:** `--bg-primary`, `--bg-secondary`, `--bg-elevated`, `--background-global`
- **Text:** `--text-primary`, `--text-secondary`, `--text-tertiary`
- **Borders:** `--border-light`, `--border-medium`, `--border-strong`

Dark mode remaps these via `[data-theme="dark"]` and `@media (prefers-color-scheme: dark)` for `[data-theme="system"]`.

### Spacing

Rem-based scale (preferred):

| Token | Value |
|-------|-------|
| `--space-1` | 0.25rem (4px) |
| `--space-2` | 0.5rem (8px) |
| `--space-3` | 0.75rem (12px) |
| `--space-4` | 1rem (16px) |
| `--space-6` | 1.5rem (24px) |
| `--space-8` | 2rem (32px) |
| `--space-10` | 2.5rem (40px) |
| `--space-12` | 3rem (48px) |

Legacy px tokens (`--spacing-xs`, `--spacing-m`, etc.) remain for older rules.

### Typography

| Token | Value |
|-------|-------|
| `--font-sans` / `--font-primary` | System UI stack (see below) |
| `--font-mono` | SF Mono, Cascadia Code, Consolas, … |
| `--font-size-default` | 16px |
| `--font-lineheight-default` | 150% |

Body and headings: `admin/css/core/typography.css`. Links use `--primary-700` on light backgrounds for WCAG AA contrast.

### Radius, shadow, breakpoints

- **Radius:** `--radius-sm` (6px) through `--radius-2xl` (24px)
- **Shadow:** `--shadow-sm` … `--shadow-xl`
- **Breakpoints (document only — use literal values in `@media`):** `--bp-sm` 640px, `--bp-md` 768px, `--bp-lg` 1024px, `--bp-xl` 1280px

---

## Font decision: system UI stack

**P44-D8 / P44-M2:** General Sans was removed. Admin uses the native system UI stack only — no CDN webfont, no `@font-face` for brand text.

```css
--font-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
--font-primary: var(--font-sans);
```

`--font-secondary` (Mergian) and `--font-article` (Newsreader) are reserved for marketing/content contexts, not loaded in admin shell.

---

## Buttons

Unified in `admin/css/components/buttons.css`. Base class: `.btn`.

| Variant | Class | Notes |
|---------|-------|-------|
| Primary | `.btn--primary` or `.btn-primary` | Mint fill, dark text |
| Secondary | `.btn--secondary` / `.btn-secondary` | Neutral gray |
| Danger | `.btn--danger` / `.btn-danger` | Destructive actions |
| Success / Warning | `.btn--success`, `.btn--warning` | Semantic actions |
| Sizes | `.btn--sm`, `.btn--lg` | Compact / large |
| Icon | `.btn--icon`, `.btn-icon` | Icon-only controls |

Focus: `outline: 2px solid var(--primary-500)` with `outline-offset: 2px` on `:focus-visible`.

---

## Form validation (`.was-validated` pattern)

`admin/css/components/forms.css` — invalid styling applies **only after** the user submits or the form opts in:

1. Add `was-validated` (or `submitted`) to the `<form>` on submit attempt.
2. Invalid fields show `--error-700` border when `:invalid`, not `:placeholder-shown`, and not `:focus`.

```html
<form id="general-form" class="was-validated" novalidate>
  <div class="form-group">
    <label for="site-url">Site URL</label>
    <input type="url" id="site-url" class="input" required>
  </div>
</form>
```

Explicit states (no automatic `:valid` green):

- `.form-group--error` / `.form-group.error` — server-side or JS errors
- `.form-group--success` — explicit success only

Entry/collection editors use `.field-block--error` with the same color tokens.

**Do not** style `:valid` globally — empty optional fields would show permanent success chrome.

---

## Save bar patterns

Component: `admin/templates/components/sticky-save-bar.php` + `admin/css/components/sticky-save-bar.css`.

### Per-area save (Settings)

Logic: `admin/js/settings-page.js` (P44-M1, M3, M4).

| Tab | Save model |
|-----|------------|
| **General, Security, Storage, Monitoring** | Sticky bar visible; button label per tab (“Save general settings”, etc.) |
| **Hosting, Project, Domains, Webhooks, Redirects, AI, Users, Activity, API** | Sticky bar **hidden** (`main-actions-bar--sticky.is-hidden`); each tab has its own save or inline API actions |

`GLOBAL_SAVE_TABS` and `INDEPENDENT_SAVE_TABS` in `settings-page.js` are the source of truth.

### Dirty state

- Forms in `GLOBAL_SAVE_TABS` are snapshotted on load; `input`/`change` events compare serialized values.
- `#settings-dirty-badge` (“Unsaved changes”) appears when dirty and the global save bar is visible.
- `beforeunload` warns if the user navigates away with unsaved general/security/storage/monitoring edits.

### When global save is hidden

Hosting uses the wizard’s own **Save hosting** button. Project uses per-file save and upload. Domains/webhooks/redirects persist via immediate API calls. Hiding the misleading global **Save settings** on those tabs prevents users from thinking all tabs were saved.

Other pages (Account, Entry editor) include the sticky bar with page-specific labels via the same PHP partial.

---

## Feedback: `BirklyFeedback` toast + confirm

**Target API (P44-M7):** replace `alert()` / `confirm()` with a shared module.

```javascript
// Toast — non-blocking, auto-dismiss
BirklyFeedback.toast('Hosting settings saved', 'success'); // success | error | warning | info

// Confirm — returns Promise<boolean>
const ok = await BirklyFeedback.confirm('Skip setup? You can finish later from the dashboard.');
```

### Requirements

| Concern | Rule |
|---------|------|
| Toast container | Fixed position; `role="status"` and `aria-live="polite"` |
| Confirm | Modal from `admin/css/components/modals.css`; focus trap; Escape closes |
| Errors | Toast or inline message — never blocking `alert()` for routine failures |
| Success | Toast preferred; Settings may use `#settings-message` inline toast |

### Styles

- **Messages:** `admin/css/components/messages.css` (`.message.success`, `.message.error`, …)
- **Modals:** `admin/css/components/modals.css`
- **Reference implementation:** MCP tab local toast (`.mcp-ui-toast` in `settings.css`) — migrate to `BirklyFeedback` in M7

### Transitional APIs

Until M7 lands everywhere:

- `Birkly.Utils.showNotification(message, type)` — basic DOM notification (`admin/js/utils.js`)
- Hosting wizard checks `Birkly.Utils.showToast` when present; prefer `BirklyFeedback.toast` in new code

---

## Settings information architecture (4 areas)

Level 1 sub-nav in `admin/templates/settings.html`:

| Area | `data-area` | Tabs |
|------|-------------|------|
| **Site** | `site` | General, Hosting, Domains, Project, Redirects |
| **Team & security** | `team` | Users & Roles, Security, Activity & audit |
| **Integrations** | `integrations` | API, Webhooks, AI |
| **Infrastructure** | `infrastructure` | Storage, Monitoring |

URL scheme: `?page=settings&area=site&tab=hosting`. Legacy `?tab=hosting` (no `area`) is rewritten client-side to include `area`.

Horizontal tabs inside each area use **tabs-v2** (`admin/js/tabs-v2.js`, `admin/css/components/tabs-v2.css`) — max ~5 tabs per group instead of 13 in one row.

---

## Hosting wizard component

Shared between **Settings → Hosting** and **onboarding** (P44-M5).

| File | Role |
|------|------|
| `admin/js/hosting-wizard.js` | `window.BirklyHostingWizard.create(container, options)` |
| `admin/js/hosting-settings.js` | Mounts wizard on Settings tab |
| `admin/css/components/hosting-wizard.css` | Step UI, Dokploy checklist, preview |
| `api/hosting.php` | Load/save (P43 backend — no routing changes in P44) |

### Steps

1. **CMS URL** — “Where do you log in?” + “Use current host” + self-hosted toggle
2. **Public site** — None / Elsewhere / In Birkly (`project/`)
3. **Preview** — Summary copy + `birkly-client.js` snippet

When **Self-hosted / Docker (Dokploy)** is checked, the Dokploy checklist is shown (not inside a collapsed `<details>`).

```javascript
BirklyHostingWizard.create(document.getElementById('hosting-wizard-mount'), {
  id: 'settings-hosting-wizard',
  mode: 'settings',
  onSave: function (payload) { /* optional hook */ }
});
```

---

## Hex audit / grep guidance

**Rule:** No Tailwind blue in `admin/css/`.

```bash
# Must return zero matches (P44 V-UX-16)
rg '#3b82f6' admin/css/

# Also avoid blue fallbacks in var() — prefer mint scale
rg '2563eb|59, 130, 246' admin/css/
```

`#3b82f6` in `admin/css/` is **zero** as of P44-M11. Remaining cleanup (M2/M9): `rgba(59, 130, 246, …)` focus shadows in some files, `#2563eb` fallbacks in `var(--primary-600, #2563eb)`, and ContentEditor `fieldtypes/ContentEditor/editor.css` (outside `admin/css/` but same token rules apply).

When adding focus rings:

```css
outline: 2px solid var(--primary-500);
box-shadow: 0 0 0 3px rgba(124, 227, 191, 0.25); /* mint, not blue */
```

---

## Quick file map

| Concern | Path |
|---------|------|
| Tokens | `admin/css/core/variables.css` |
| Typography | `admin/css/core/typography.css` |
| Buttons | `admin/css/components/buttons.css` |
| Forms | `admin/css/components/forms.css` |
| Save bar | `admin/templates/components/sticky-save-bar.php` |
| Settings logic | `admin/js/settings-page.js` |
| Hosting wizard | `admin/js/hosting-wizard.js` |
| Accessibility | `admin/js/accessibility.js` |
| Empty states | `admin/css/components/empty-states.css` |
