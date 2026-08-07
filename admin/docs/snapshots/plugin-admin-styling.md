# Plugin admin styling

## Summary

A Birkly plugin's admin screens can look exactly like core without shipping a single line of CSS. Compose your page from the core classes — `.plugin-page`, `.card`, `.btn`, `.field`, `.table`, `.badge`, `.tabs`, `.modal` — plus the `u-*` helpers, and you inherit the whole design language including dark mode, focus rings and reduced-motion handling for free. If you do need CSS, build it on the design tokens and namespace it as `.{plugin}-*`. Plugins **should** adopt the design language, but are **never required** to: the platform will not force a rewrite of existing plugin CSS, and a compatibility layer keeps old token names working.

## Beginner

### Three ways to style a plugin page

| Tier | What you do | CSS you ship |
|------|-------------|--------------|
| **Tier 0 — Compose** *(recommended)* | Build the page out of core classes and helpers | **None** |
| **Tier 1 — Extend** | Ship a small stylesheet for genuinely plugin-specific UI, built on core tokens | `.{plugin}-*` only |
| **Tier 2 — Independent** | Ship your own look | Fully namespaced, still meets the baseline rules |

Start at Tier 0. Most plugin admin screens are a toolbar, some panels, a table and a form — all of which core already provides. Every line of CSS you don't write is a line that can't drift out of sync when the admin design changes.

### Why Tier 0 is worth it

- **Dark mode works automatically.** Core classes read their colours from tokens that get remapped for dark themes. A plugin that hardcodes `#ffffff` looks broken the moment a user switches theme.
- **Accessibility comes with it.** Focus rings, contrast-checked text colours, `prefers-reduced-motion`, keyboard-visible row actions — all already handled.
- **Your plugin looks native.** Users don't have to learn a second visual language inside the same admin.
- **You inherit improvements.** When core adjusts padding or a border colour, your page moves with it.

### The one rule you cannot break

**Never restyle a core class.** Writing `.btn { … }` or `.card { … }` in your plugin's stylesheet changes buttons and cards for the entire admin, including other plugins' pages. Scope everything to your own namespace.

### Where to put your CSS

Plugin admin templates load their stylesheet themselves, at the top of the template:

```html
<link rel="stylesheet" href="{birkly_url '/admin/css/plugin-page.css'}">
<link rel="stylesheet" href="{birkly_url '/plugins/my-plugin/admin/my-plugin.css'}">
```

A Tier 0 plugin only needs the first line — and often not even that, since `plugin-page.css` is already part of the admin bundle.

## Advanced Users

### 1. Adoption tiers in detail

#### Tier 0 — Compose (recommended)

Use `.plugin-page` as your root, `{plugin_page_header}` for breadcrumbs, and the contracted components below for everything else. Layout comes from `u-stack-*`, `u-row`, `u-cluster`, `u-grid*` and `u-split`. Ship no stylesheet at all.

This is achievable for the large majority of plugin admin screens. If you find a genuine gap — a shape core has no component for — that is worth filing as a core gap rather than absorbing into your plugin.

#### Tier 1 — Extend

Ship a small stylesheet for UI that is genuinely specific to your plugin: a workflow canvas, a chart container, a domain-specific editor. Build it entirely from tokens; use core components for everything around it.

Rules of thumb for Tier 1:

- One stylesheet, named after the plugin, under `admin/` in your plugin folder.
- Every declaration uses `var(--…)` for colour, spacing, radius, shadow, motion and z-index.
- Every selector starts with `.{plugin}-`.
- No selector matches a core class on its own.

#### Tier 2 — Independent

Ship your own look. This is allowed — the platform does not require adoption. You still must not restyle core classes, must not use `!important` against core, must not remove focus outlines, and must handle dark mode yourself (which is more work than adopting the tokens, which is why Tier 2 is rarely the right call).

---

### 2. The public class contract

These class names and their visual meaning are guaranteed across minor versions. Their presence is verified in CI, so a core refactor cannot silently delete one out from under you.

**Page chrome**

`.page` · `.plugin-page` · `.page-header` · `.page-header-left` · `.page-header-actions` · `.breadcrumbs` · `.main-actions-bar` · `.section-header` · `.section-title` · `.section-description`

**Panels**

`.card` · `.card-header` · `.card-title` · `.card-body` · `.card-footer`

Once content is already inside a `.card`, don't nest a second one — use `.card-section` to divide the body into divider-separated sub-blocks instead (a settings group, a fieldtype's options block). See [Admin design language § Card section](../01-admin/admin-design-language.md#card-section--stop-nesting-a-card-inside-a-card) for the markup. `.card-section` is documented and stable, but — unlike the classes listed above — its presence is **not yet** checked by CI (see the note at the end of this section).

**Controls**

`.btn` · `.btn-primary` · `.btn-secondary` · `.btn-outline` · `.btn-danger` · `.btn-sm` · `.btn-lg` · `.btn-icon`

**Forms**

`.field` · `.form-group` · `.input` · `.select` · `.textarea` · `.switch` · `.field-help` · `.field-error`

**Data**

`.table` · `.table-container` · `.data-list` · `.badge`

**Feedback and state**

`.callout` · `.empty-state` · `.skeleton` · `.spinner`

**Navigation and overlays**

`.tabs` · `.tab` · `.tab-panel` · `.modal` · `.modal-header` · `.modal-body` · `.modal-footer` · `.dropdown` · `.dropdown-item`

`.tab`'s active state is a filled pill (`--surface-selected`), not an underline. For vertical secondary navigation, the equivalent component is `.side-nav` / `.side-nav-item` — same active/hover/focus rule as `.tab`, oriented vertically, with an optional `.side-nav-sub` for nested items. Like `.card-section`, `.side-nav` and `.side-nav-item` are documented in [Admin design language](../01-admin/admin-design-language.md) but not yet in the CI presence check below.

**Misc**

`.avatar` · `.divider` · `.birkly-icon`

Also contracted, though not in the CI presence check: every `u-*` helper, every documented token, the `.fieldtype-*` API, the action-zone classes `.main-actions-left` / `.main-actions-right` / `.main-actions-bar--sticky` / `.cell-actions` / `.btn-icon--edit` / `.btn-icon--danger`, and the newer `.card-section` / `.side-nav` / `.side-nav-item` (documented in [Admin design language](../01-admin/admin-design-language.md) but added after the current CI contract list was written, so don't rely on CI to flag a regression in these specifically — treat them as stable by documentation, not yet by automated enforcement).

**Not contracted:** internal element parts not listed above (for example `.card-heading`, `.data-list-main`, `.tabs-v2-*` internals), page-level classes in `admin/css/pages/*.css`, and `.entries-smart-table` internals. Modifier suffixes documented in [Admin design language](../01-admin/admin-design-language.md) — `.card--compact`, `.badge-success`, `.modal--lg`, `.table--compact` and so on — are stable in practice but the list above is what CI guarantees.

---

### 3. Rules for plugin CSS (all tiers)

1. **Never restyle a core class.** No `.btn { … }`, no `.card { … }`, no `#media-modal .modal-content { … }`. Scope to `.{plugin}-*`, or to a root class of your own plus a core class if you genuinely must adjust layout inside your page (`.my-plugin-page .card { margin: 0 }` is tolerable; `.card { margin: 0 }` is not).
2. **Tokens, not hex.** A plugin that hardcodes hex is broken in dark mode — that is the single most common plugin styling defect. Use `var(--surface)`, `var(--text-primary)`, `var(--border-subtle)` and friends.
3. **No `!important` against core.** If you're losing a specificity fight with a core rule, you are almost certainly restyling something you shouldn't be.
4. **No new blues.** Use `--info-surface` / `--info-border` / `--info-fg` / `--info-solid`. Shipping a Tailwind blue as an active-tab colour is the exact failure mode this token family exists to prevent.
5. **Honour focus and reduced motion.** Never remove a focus outline without an equivalent replacement. Don't add unguarded looping animation — the global `prefers-reduced-motion` block covers transitions and animations, but a decorative loop is still a decorative loop.
6. **Namespace everything** as `.{plugin}-*`. Do not use the `u-` prefix for private helpers; that prefix belongs to core.
7. **Don't re-implement a contracted component.** If `.card` doesn't fit your case, that's a core gap — file it rather than shipping a ninth card family.
8. **Respect the accent rules.** `--accent` is a fill and is 1.54:1 on white; `--accent-text` is the text ramp; `--ink-on-accent` is what goes on a mint fill. One mint-filled element per screen.
9. **One page title.** Your page gets its title from the breadcrumb `.current` segment via `{plugin_page_header}`. Don't render an `<h1>`.
10. **No box-in-box.** If a `.card` is already on the page and you're about to wrap a group of fields or options in another bordered, rounded, filled container *inside* that card's body, stop — use `.card-section` (a divider-separated sub-block with no border/background/radius of its own), more spacing (`u-stack-*`), or a plain `.divider` instead. This applies to plugin admin pages exactly as much as core: a fieldtype's options block or a plugin settings group nested inside `.card-body` is the same violation either way.
11. **Text fields use `--field-border`, not `--border-control`.** If you're building your own input-like control in a Tier 1/2 stylesheet, border it with `--field-border` / `--field-border-hover` / `--field-border-focus` (the same family `.input`/`.select`/`.textarea` use), not `--border-control` — and never colour a text field's focus state with `--accent-focus` or any other green/mint token. A green-ish focus border on a field reads as "already validated", which is confusing right next to the platform's own `.input.is-valid` success state. `--border-control` is for buttons, custom-selects and other non-text-field controls.
12. **Never reuse the literal `data-theme` attribute for anything of your own.** Dark mode is scoped by the exact selector `[data-theme="dark"]` on `<html>`/`<body>`; a CSS attribute selector matches *any* element carrying that attribute, not only the real theme root. If your plugin renders anything theme-related — a theme preview swatch, an appearance-option button, a per-item "which mode is this" marker — name that attribute something else (`data-theme-option`, `data-my-plugin-theme`, anything but the bare `data-theme`), or it will silently and locally inherit dark-surface tokens regardless of the page's actual theme.

A Tier 1 stylesheet that follows these rules looks like this:

```css
/* plugins/my-plugin/admin/my-plugin.css */

.my-plugin-canvas {
  position: relative;
  min-height: 24rem;
  padding: var(--space-4);
  border: 1px solid var(--border-subtle);
  border-radius: var(--radius-md);
  background: var(--surface-sunken);
}

.my-plugin-node {
  padding: var(--space-3);
  border: 1px solid var(--border-default);
  border-radius: var(--radius-sm);
  background: var(--surface);
  color: var(--text-primary);
  font-size: var(--text-sm);
  transition: border-color var(--motion-fast) var(--ease-out);
}

.my-plugin-node:hover { border-color: var(--border-control-hover); }

.my-plugin-node.is-selected {
  border-color: var(--accent-focus);
  background: var(--accent-soft);
}

.my-plugin-node:focus-visible {
  outline: var(--focus-outline);
  outline-offset: var(--focus-outline-offset);
}
```

No hex, no `!important`, no core class touched, dark mode handled by the tokens.

---

### 4. Legacy token aliases

Older plugin CSS references token names that no longer exist as primary definitions: `--color-primary`, `--bg-elevated`, `--border-color`, `--gray-200`, `--shadow-md`, `--radius-full`, `--error-600`, `--transition-fast` and similar.

`admin/css/core/compat-tokens.css` keeps every one of those working. It contains **only** aliases of the form `--old-name: var(--new-name)`, no rules and no classes, and it is imported immediately after the canonical token file. That means existing plugin CSS not only keeps working — it also picks up the new design, because each alias points at a semantic token rather than a frozen value.

Two things to know:

1. **Core admin CSS may not use any aliased name.** That is enforced in CI, so the alias list can only shrink.
2. **New plugin CSS should use the canonical names.** The aliases exist for compatibility and will be removed in a future plugin-adoption phase. Writing new code against them means signing up for a migration you could have skipped.

Common migrations:

| Legacy name | Canonical replacement |
|-------------|----------------------|
| `--color-primary`, `--primary-500` | `--accent` (fill) or `--accent-text` (text) |
| `--color-primary-strong`, `--primary-700` | `--accent-text` — **`--primary-700` fails AA as text** |
| `--color-primary-soft`, `--primary-50` | `--accent-soft` |
| `--bg-primary`, `--bg-elevated`, `--surface-1` | `--surface` |
| `--bg-secondary`, `--bg-tertiary`, `--surface-muted` | `--surface-sunken` |
| `--bg-hover` | `--surface-hover` |
| `--bg-overlay` | `--overlay` |
| `--border-light`, `--border-color`, `--gray-200` | `--border-subtle` |
| `--border-medium`, `--gray-300` | `--border-default` |
| `--gray-400` | `--border-control` |
| `--gray-500` | `--text-tertiary` |
| `--gray-800`, `--gray-900`, `--color-black` | `--text-primary` |
| `--error-*`, `--danger-100`, `--danger-700`, `--color-error` | `--danger-surface` / `--danger-border` / `--danger-fg` / `--danger-solid` |
| `--success-50/100/…`, `--warning-50/…` | `--{sem}-surface` / `-border` / `-fg` / `-solid` |
| `--info-500` | `--info-solid` |
| `--shadow-sm/md/lg/xl` | `--elevation-1` / `-2` / `-3` |
| `--radius-xs` | `--radius-sm` |
| `--radius-full`, `--radius-round` | `--radius-pill` |
| `--transition-fast`, `--transition-base` | `--motion-fast` / `--motion-base` plus `--ease-out` |
| `--font-size-base` | `--text-base` |
| `--font-medium` | `--weight-medium` |
| `--font-family-mono` | `--font-mono` |
| `--z-index-modal` | `--z-modal` |

That table is the complete alias set by family — if a legacy name you use is not in `compat-tokens.css`, it is **not** aliased and resolves to nothing. Spacing and radius are the common trap: there is no `--spacing-*` or `--border-radius-*` alias, so CSS using those names has no fallback. Move it to `--space-1` … `--space-16` and `--radius-sm` / `-md` / `-lg` / `-pill`.

Full token reference: [Admin design language](../01-admin/admin-design-language.md).

---

### 5. Worked example — a complete Tier 0 plugin page

This is a full plugin admin template. It ships **no CSS**, uses only contracted classes and `u-*` helpers, and covers page chrome, a stat row, a card, a table with row actions, a form, and a modal.

```html
<!-- plugins/my-plugin/admin/my-plugin.html -->

{plugin_page_header}

<div class="my-plugin-page plugin-page">

  <!-- Toolbar: primary action left, search/filters right -->
  <div class="main-actions-bar">
    <div class="main-actions-left">
      <button type="button" class="btn btn-primary" id="mp-new">New webhook</button>
    </div>
    <div class="main-actions-right">
      <input class="input input-search" type="search" id="mp-search" placeholder="Search webhooks">
      <div class="segmented" role="group" aria-label="View">
        <button type="button" class="segmented-item active" aria-pressed="true">All</button>
        <button type="button" class="segmented-item" aria-pressed="false">Failing</button>
      </div>
    </div>
  </div>

  <div class="page-body u-stack-6">

    <!-- Metrics -->
    <div class="u-grid-auto">
      <div class="stat">
        <span class="stat-label">Delivered today</span>
        <span class="stat-value">1,284</span>
        <span class="stat-meta">99.4% success</span>
      </div>
      <div class="stat">
        <span class="stat-label">Failing endpoints</span>
        <span class="stat-value">2</span>
        <span class="stat-meta">Retrying</span>
      </div>
    </div>

    <!-- A notice -->
    <div class="callout callout-info">
      <div class="callout-body">
        <p class="callout-title">Retries are enabled</p>
        <p>Failed deliveries are retried three times with exponential backoff.</p>
      </div>
    </div>

    <!-- Data panel: flush card wrapping a table -->
    <section class="card card--flush">
      <header class="card-header">
        <div class="card-heading">
          <h2 class="card-title">Endpoints</h2>
          <p class="card-description">Every URL this plugin delivers events to.</p>
        </div>
        <div class="card-actions">
          <button type="button" class="btn btn-sm">Export</button>
        </div>
      </header>

      <div class="card-body">
        <div class="table-container">
          <table class="table">
            <thead>
              <tr>
                <th>Endpoint</th>
                <th>Event</th>
                <th>Status</th>
                <th class="numeric">Deliveries</th>
                <th class="col-shrink"></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><span class="cell-title">https://example.com/hooks/orders</span></td>
                <td><span class="cell-muted">order.created</span></td>
                <td><span class="badge badge-success">Active</span></td>
                <td class="numeric u-numeric">842</td>
                <td class="cell-actions">
                  <button type="button" class="btn-icon btn-icon-sm btn-icon--edit" aria-label="Edit endpoint">
                    <span data-icon="pencil-simple" data-icon-size="14"></span>
                  </button>
                  <button type="button" class="btn-icon btn-icon-sm btn-icon--danger" aria-label="Delete endpoint">
                    <span data-icon="trash" data-icon-size="14"></span>
                  </button>
                </td>
              </tr>
              <tr>
                <td><span class="cell-title">https://example.com/hooks/refunds</span></td>
                <td><span class="cell-muted">order.refunded</span></td>
                <td><span class="badge badge-danger">Failing</span></td>
                <td class="numeric u-numeric">17</td>
                <td class="cell-actions">
                  <button type="button" class="btn-icon btn-icon-sm btn-icon--edit" aria-label="Edit endpoint">
                    <span data-icon="pencil-simple" data-icon-size="14"></span>
                  </button>
                  <button type="button" class="btn-icon btn-icon-sm btn-icon--danger" aria-label="Delete endpoint">
                    <span data-icon="trash" data-icon-size="14"></span>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Required states. Toggle the `hidden` attribute from JS. -->
        <div class="empty-state empty-state--compact" id="mp-empty" hidden>
          <div class="empty-state-icon"><span data-icon="paper-plane-tilt" data-icon-size="20"></span></div>
          <p class="empty-state-title">No endpoints yet</p>
          <p class="empty-state-text">Add an endpoint to start receiving events.</p>
          <div class="empty-state-actions">
            <button type="button" class="btn btn-primary">New webhook</button>
          </div>
        </div>

        <div class="loading-state" id="mp-loading" hidden>Loading endpoints…</div>

        <div class="error-state" id="mp-error" hidden>
          <div class="empty-state-icon"><span data-icon="warning" data-icon-size="20"></span></div>
          <p class="empty-state-title">Couldn't load endpoints</p>
          <p class="empty-state-text">The plugin API did not respond.</p>
          <div class="empty-state-actions">
            <button type="button" class="btn">Retry</button>
          </div>
        </div>
      </div>
    </section>

    <!-- Settings form -->
    <section class="card">
      <header class="card-header">
        <h2 class="card-title">Delivery settings</h2>
      </header>
      <form class="card-body u-stack-4 u-max-w-measure-lg" id="mp-settings">
        <div class="field">
          <label class="field-label" for="mp-timeout">
            Timeout <span class="field-required">*</span>
          </label>
          <input class="input" id="mp-timeout" name="timeout" type="number" value="10" required>
          <p class="field-help">Seconds to wait for a 2xx response before treating the delivery as failed.</p>
        </div>

        <div class="field">
          <label class="field-label" for="mp-signature">Signature algorithm</label>
          <select class="select" id="mp-signature" name="signature">
            <option value="sha256">HMAC SHA-256</option>
            <option value="sha512">HMAC SHA-512</option>
          </select>
        </div>

        <div class="field">
          <label class="field-label" for="mp-notes">Internal notes</label>
          <textarea class="textarea" id="mp-notes" name="notes" rows="3"></textarea>
          <p class="field-error" id="mp-notes-error" hidden>Notes are limited to 500 characters.</p>
        </div>

        <div class="u-row u-justify-between">
          <label class="checkbox-label">
            <input type="checkbox" name="retry" checked>
            Retry failed deliveries
          </label>
          <label class="switch">
            <input type="checkbox" name="enabled" checked>
            <span class="switch__slider"></span>
          </label>
        </div>
      </form>
      <footer class="card-footer">
        <button type="submit" form="mp-settings" class="btn btn-primary">Save settings</button>
        <button type="button" class="btn btn-ghost">Reset</button>
      </footer>
    </section>

  </div>
</div>

<!-- Modal: add / edit endpoint -->
<div class="modal" id="mp-modal">
  <div class="modal-content modal--md">
    <header class="modal-header">
      <h2 class="modal-title" id="mp-modal-title">New webhook</h2>
      <button type="button" class="modal-close" aria-label="Close">&times;</button>
    </header>
    <div class="modal-body">
      <form id="mp-endpoint-form" class="u-stack-4">
        <div class="field">
          <label class="field-label" for="mp-url">Endpoint URL <span class="field-required">*</span></label>
          <input class="input" id="mp-url" name="url" type="url" required>
        </div>
        <div class="field">
          <label class="field-label" for="mp-event">Event</label>
          <select class="select" id="mp-event" name="event">
            <option value="order.created">order.created</option>
            <option value="order.refunded">order.refunded</option>
          </select>
        </div>
      </form>
    </div>
    <footer class="modal-footer">
      <button type="button" class="btn btn-secondary">Cancel</button>
      <button type="submit" form="mp-endpoint-form" class="btn btn-primary">Save</button>
    </footer>
  </div>
</div>
```

Notes on the example:

- `{plugin_page_header}` emits the `.page-header` + `.breadcrumbs` block via `birkly_render_plugin_page_breadcrumbs()`. The last segment becomes the page title in the topbar. There is no `<h1>` anywhere in the template.
- Exactly one `.btn-primary` is visible per view.
- `hidden` is the state switch, not `style="display:none"` — core declares `[hidden] { display: none }` after every component, so it beats a component's own `display: flex`.
- `data-icon` placeholders are hydrated by `Birkly.initIcons(document)`; in PHP use `birkly_icon('trash', ['size' => 14])`.
- Toasts and confirmations come from `BirklyFeedback.toast(...)` and `await BirklyFeedback.confirm(...)`. Don't use `alert()` or `confirm()`.

---

### 6. Fieldtypes

A fieldtype ships outside `admin/`, so it gets its own frozen CSS contract in `admin/css/plugins/fieldtype-api.css`. Every class name there is stable; only the values behind them change when the design language changes, which means an existing fieldtype picks up a redesign without a single markup edit.

**Prefer the shared components first** — `.card`, `.btn`, `.input`, `.field`, `.callout`, `.empty-state`, `.badge` and the `u-*` helpers. Use the `.fieldtype-*` classes for the three things components don't cover: the toolbar rail, the preview grid, and the async states of a field editor.

| Class | Purpose |
|-------|---------|
| `.fieldtype-wrapper` | Padded panel container: `--surface`, hairline border, `--radius-md` |
| `.fieldtype-with-toolbar` | Container for an editor that has a toolbar rail — clips its children so the toolbar sits flush |
| `.fieldtype-toolbar` | Sunken toolbar band with a bottom hairline; stacks vertically below 768px |
| `.fieldtype-toolbar-group` | Group of toolbar controls with a trailing divider |
| `.fieldtype-toolbar-spacer` | Pushes the remaining toolbar controls to the trailing edge |
| `.fieldtype-content` | The editing surface. Shows a focus outline on `:focus-within` |
| `.fieldtype-preview-grid` | `auto-fill, minmax(7.5rem, 1fr)` thumbnail grid |
| `.fieldtype-preview-list` | Vertical preview stack |
| `.fieldtype-preview-item` | One preview tile; border brightens on hover |
| `.fieldtype-preview-item-actions` | Absolutely positioned action cluster, revealed on hover **or** `:focus-within` |
| `.fieldtype-empty-state` | Centred empty block matching the admin's `.empty-state` |
| `.fieldtype-empty-state-icon` | 44px icon tile |
| `.fieldtype-empty-state-text` | Secondary copy, capped at `--measure-sm` |
| `.fieldtype-loading` | Centred spinner block |
| `.fieldtype-error` | Danger-toned inline error panel |
| `.fieldtype-help` | Small tertiary help text below the field |

Example:

```html
<div class="fieldtype-with-toolbar" data-fieldtype="gallery">
  <div class="fieldtype-toolbar">
    <div class="fieldtype-toolbar-group">
      <button type="button" class="btn btn-sm">Add images</button>
      <button type="button" class="btn btn-sm">Reorder</button>
    </div>
    <div class="fieldtype-toolbar-spacer"></div>
    <span class="badge badge-neutral u-numeric">4</span>
  </div>

  <div class="fieldtype-content">
    <div class="fieldtype-preview-grid">
      <figure class="fieldtype-preview-item">
        <img src="…" alt="">
        <div class="fieldtype-preview-item-actions">
          <button type="button" class="btn-icon btn-icon-xs btn-icon--danger" aria-label="Remove">…</button>
        </div>
      </figure>
    </div>

    <div class="fieldtype-empty-state" hidden>
      <div class="fieldtype-empty-state-icon">…</div>
      <p class="fieldtype-empty-state-text">No images selected yet.</p>
    </div>

    <div class="fieldtype-loading" hidden></div>
  </div>
</div>

<p class="fieldtype-help">Drag to reorder. The first image is used as the thumbnail.</p>
<div class="fieldtype-error" hidden>Upload failed — the file exceeds the size limit.</div>
```

Fieldtypes that ship their own CSS follow the same rules as Tier 1 plugin CSS: tokens only, no `!important`, no restyling of core or `.fieldtype-*` classes belonging to someone else.

---

### 7. Review checklist before you publish

- [ ] No hex, `rgb()` or `hsl()` literal in plugin CSS — tokens only
- [ ] No selector that matches a bare core class
- [ ] No `!important`
- [ ] Every selector namespaced `.{plugin}-*` (or scoped under your page root)
- [ ] Screens verified in **both** light and dark themes
- [ ] Exactly one `.btn-primary` visible per view; no page `<h1>`
- [ ] Empty, loading and error states present for every async region
- [ ] Focus visible on every interactive element; Escape closes overlays; focus trapped and restored in modals
- [ ] Text contrast meets AA (4.5:1 body, 3:1 for ≥18px text and UI boundaries)
- [ ] `--accent` and `--primary-700` never used as a text colour; `--ink-on-accent` on every mint fill
- [ ] Checked at 640 / 768 / 1024 / 1280 / 1440+ with no unintended horizontal scroll
- [ ] No `.card` nested inside another `.card`'s body — `.card-section`, spacing or a `.divider` instead
- [ ] Any custom text-like input uses `--field-border` / `-hover` / `-focus`, and its focus state is never accent/green
- [ ] No literal `data-theme="light"` / `"dark"` / `"system"` attribute anywhere in plugin markup or JS other than the real theme root

## Related

- [Admin design language](../01-admin/admin-design-language.md) — full token, helper and component reference
- [Admin design system](../01-admin/design-system.md) — short overview, icons and theming entry points
- [Admin chrome and action zones](../01-admin/admin-chrome-action-zones.md) — where each kind of action belongs
- [Plugin development](plugin-development.md) — structure, permissions and packaging
- [Plugin navigation icon](plugin-navigation-icon.md)
- [Field types](../02-content/field-types.md)
- [Security and review](security-and-review.md)
