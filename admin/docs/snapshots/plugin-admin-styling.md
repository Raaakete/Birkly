# Plugin admin styling

## Summary

A plugin's admin pages can look exactly like core without shipping CSS. Put `data-birch` on your page root, then compose the page from the Birch roles and kit:

- `.frame` for a named group, `.slab` for one thing;
- `.button.is-main` for the one main action;
- `.field-group`, `.signal`, `.table`, `.menu`, `.dialog` and `.empty`.

You then inherit light and dark, the three densities, focus rings, icon gestures and reduced motion for free. When you do need CSS, build it only from tokens and name it for what it is (`.order-row`, `.workflow-node`). Pages written for the older P56 design still render in Birch through an adoption layer and two alias files. Both are removed in a later CMS release, and this page's rename tables are the migration path.

## Beginner

### Three steps to a Birch plugin page

1. **Opt in.** Give your page root `class="plugin-page"` and the `data-birch` attribute.
2. **Ask the four questions** for every block, in order, and stop at the first yes:
   - floats and goes away → a float or a menu;
   - one thing → a slab;
   - a named group next to other groups → a frame;
   - anything else → the canvas.

   Something not there yet is dashed; something that needs the user is a signal.
3. **Use the kit.** Buttons, fields, rows, tables, dialogs and empty states already exist. See [Admin design language](../01-admin/admin-design-language.md) for every one, with markup.

### Why not write your own CSS

- **Dark mode and density work automatically.** Kit classes read tokens that are remapped for dark and resized per density. A hard-coded `#ffffff` or `height: 40px` breaks the moment a user switches either.
- **Accessibility comes with it.** Contrast-checked colours, focus rings, reduced motion and the phone More menu are already handled.
- **Your plugin looks native.** Users don't meet a second visual language inside the same admin.
- **You inherit improvements.** When core adjusts a gap or a colour, your page moves with it.

### The rules you cannot break

- **Never restyle a core class.** A rule such as `.button { … }` or `.slab { … }` in your stylesheet changes every page in the admin, including other plugins' pages.
- **One mint button per view.** Mint is the main action. Everything else is a secondary, quiet or danger button.
- **No colour literals.** Every colour is a token, or dark mode breaks.

### Where to put your CSS

A plugin loads its own stylesheet at the top of its admin template:

```html
<link rel="stylesheet" href="{birkly_url '/plugins/my-plugin/admin/my-plugin.css'}">
```

A plugin that only composes kit classes needs no stylesheet at all.

## Advanced Users

### 1. Opting in

```html
{plugin_page_header}

<div class="webhooks-page plugin-page" data-birch>
  …
</div>
```

- `{plugin_page_header}` emits the breadcrumb (`birkly_render_plugin_page_breadcrumbs()`). The last segment becomes the page title in the top bar, so don't render an `<h1>`.
- `<body>` already carries `data-birch`, so the kit applies everywhere. `data-birch` on your `.plugin-page` root turns on the **adoption layer**: P56 markup inside the root takes Birch roles, as the next table shows. Keep it on even after you move to Birch classes; it also sets the 24 px gap between the root's children.

| P56 markup inside `.plugin-page[data-birch]` | Rendered as |
|---------------------------------------------|-------------|
| A top-level `.card` | Frame: no fill, 1 px `--line`, 20 px padding |
| A `.card` inside a card, in a `u-grid-*` / `.stats-grid`, `.card--sunken`, or inside `.card--flush` | Slab |
| `.card-header` / `.card-title` / `.card-description` | Frame head: `title-l`, `small` `--text-3` |
| `.card-body`, `.card-footer` | No padding, border or fill |
| `table.table` in `.table-container` | Header on the canvas; each row a slab 4 px apart |
| `.stat-label` / `.stat-value` | Metric label and `number` value |
| `.empty-state` | Dashed |
| `.callout` | No border, 6 px radius |
| `.field` / `.form-group` label | `small` 500, sentence case |
| `.input` inside a frame · inside a slab | White · `--surface-2` (the field takes the opposite of what it sits on) |
| `.modal` > `.modal-content` | Float; `.modal.is-sheet` docks it right |
| `.btn-primary` · `.btn-secondary` · `.btn-danger` | Mint · secondary · danger-soft |
| `label.switch > input + .switch__slider` | The Birch two-cell switch |

The adoption layer keeps shipped plugins intact. It is not the target: write new pages with the Birch classes in § 3.

### 2. Class names

- **Plain words**, with no prefix, suffix or BEM: `.order-row`, `.workflow-node`, `.tier-card`, `.bulk-bar`.
- **State** through `is-` classes and ARIA: `.is-main`, `.is-selected` / `aria-selected`, `[aria-current="page"]`, `aria-pressed`, `aria-checked`.
- **Core owns the generic names**: `.row`, `.table`, `.card`, `.field`, `.frame`, `.slab`, `.menu` and the rest of the kit. A plugin component is named for what it is, and never reuses a core name for something else.
- **A component's surface comes from its role.** If your `.order-row` is one thing, style it like a slab (`--surface`, 6 px radius, 16 px padding, no border, no shadow), or better, put `class="slab order-row"` on it and style only its layout.
- **No `u-` prefix** for your own helpers; that prefix belongs to core.

### 3. P56 → Birch rename tables

The tables list every P56 class, token and icon name that core still honours, with its Birch replacement. The alias files `admin/css/core/migration-aliases.css` and `admin/css/core/compat-tokens.css`, the icon aliases, and the P56 component sheets are removed in the CMS release after 1.1. Move your plugin before then.

#### Classes

| P56 | Birch | Notes |
|-----|-------|-------|
| `.btn.btn-primary` | `.button.is-main` | One visible per view |
| `.btn`, `.btn-secondary`, `.btn-outline` | `.button` | Secondary is the default |
| `.btn-ghost`, `.btn-link` | `.button.is-quiet` | Cancel and low-weight actions |
| `.btn-danger`, `.btn-danger-quiet` | `.button.is-danger` | Danger-soft fill, danger text |
| `.btn-sm` · `.btn-lg` | `.button.is-small` · `.button` | Height follows density (`--control`) |
| `.btn-icon`, `.btn-icon-sm`, `.btn-icon-xs`, `.btn-icon--edit`, `.btn-icon--danger` | `.icon-button` + `aria-label` | Destructive row actions go in a menu as `.menu-item.is-danger` |
| `.card` (top level) | `.frame` | A named group of slabs |
| `.card` (nested), `.card--elevated`, `.card--bordered`, stat tile | `.slab` | One thing; no border, no shadow |
| `.card--sunken`, `.card--borderless` | `.well` · canvas | |
| `.card-header`, `.card-title`, `.card-description`, `.card-subtitle` | `.frame-head` > `.frame-head-body` > `.frame-title` + `.frame-text` | Actions sit at the end of `.frame-head` |
| `.card-body`, `.card-footer` | No wrapper | Footer actions go in the page bar or a dialog foot |
| `.card-section`, `.card-section--compact` | One `.slab` per section, or `.setting-row` | Slabs 4 px apart inside the frame |
| `.section-header`, `.section-title`, `section__header` | `.frame-head`, or `.group-title` on the canvas | |
| `.stats-grid` > `.stat` (`.stat-label`, `.stat-value`, `.stat-meta`) | `.metrics` > `.metric` (`.metric-label`, `.metric-value`, `.metric-meta`) | Up to four slots in one slab |
| `.callout`, `.callout-info` / `-success` / `-warning` / `-danger`, `.callout--banner`, `.callout--compact` | `.signal` (warning), `.signal.is-info` / `.is-success` / `.is-danger` with `.signal-body` > `.signal-title` + `.signal-text` | Action as a secondary button on the right |
| `.badge`, `.badge-success` / `-warning` / `-danger` / `-info` / `-neutral` | `.status.is-success` / `.is-warning` / `.is-danger` / `.is-info` / `.status`; `.count` for numbers; `.chip` for tags | `.is-plain` drops the fill |
| `.status-indicator` | `.status` | 6 px square dot |
| `.empty-state` (`-icon`, `-title`, `-text`, `-actions`), `.empty-state--compact` | `.empty` (`.empty-icon`, `.empty-title`, `.empty-text`) | Dashed; 26 px icon in an accent-soft well |
| `.loading-state`, `.spinner`, `.skeleton-row`, `.skeleton-block` | `.skeleton` bars; `.button.is-busy` with the `loader` icon | |
| `.field`, `.form-group` (label + control + help) | `.field-group` | In Birch, `.field` is the entry editor's field **slab** |
| `.field-label`, `.field-help`, `.field-error`, `.input` | Unchanged | |
| `.textarea`, `.input-lg`, `.input-search` | `textarea.input`, `.input`, `.search-field` > `.input` | |
| `select.select`, `.dropdown-chevron` | `.select-box` > `select.input` + the `chev-down` icon | |
| `.input-icon-wrap`, prefix addons | `.input-group` > `.prefix.well` + `.input` | |
| `.checkbox-label` > `input` | `label.choice` > `input.check` (`input.radio`) | |
| `label.switch` > `input` + `.switch__slider`, `.switch--sm`, `.switch--disabled` | `button.switch[role="switch"][aria-checked]` with two `<span>` cells (x, check) | The P56 markup keeps working |
| `.segmented` > `.segmented-item.active` | `.segmented.well` > `button[aria-pressed]` | |
| `.tabs` > `.tab.active`, `.tab-panel` | `.tabs[role="tablist"]` > `button[role="tab"][aria-selected]` | 2 px ink underline |
| `.side-nav`, `.side-nav-item`, `.side-nav-chevron` | Unchanged: `.side-nav-item.active` or `aria-selected` | Ink bar for current; the chevron is the `chev-right` icon |
| `.table-container` > `table.table`, `.cell-muted`, `.cell-numeric` | `.table` > `.table-head` + `.table-row` (or keep `table.table` inside the adoption layer); `.row-meta`, `.u-numeric` | Rows are slabs |
| `.data-list` | `.list-rows` > `.list-row` (`.row-body`, `.row-title`, `.row-meta`, `.row-actions`) | |
| `.dropdown-menu` > `.dropdown-item`, `.dropdown-divider`, `.dropdown-header`, `.dropdown-item-meta`, `.dropdown-empty` | `.menu` > `.menu-item`, `.menu-rule`, `.menu-head`, `.row-meta`, `.menu-note` | Menu shadow; never a form |
| `.modal-header`, `.modal-title`, `.modal-body`, `.modal-footer`, `.modal-footer--split` | `.modal-content.dialog` > `.dialog-head` (`.dialog-title`, `.dialog-text`), `.dialog-body`, `.dialog-foot` | Keep the `.modal` + `.modal-backdrop` shell |
| `.sheet-panel`, `.sheet-backdrop`, `.sheet-title`, `.sheet-body`, `.sheet-footer`, `.sheet--mobile-only`, `.drawer-header` / `-body` / `-footer` | `.modal.is-sheet` > `.modal-content.dialog` | Docked right |
| `.main-actions-bar` > `.main-actions-left` / `.main-actions-right` | `.page-bar` > (actions) + `.page-bar-tools` | The More menu handles phones for both |
| `.divider`, `.divider-labelled` | A gap; `.menu-rule` inside menus | |
| `.page--narrow` | `.u-max-w-page-narrow` | |
| `.grid-cols-1`, `.grid-cols-auto-fill`, `.grid-cols-auto-fit` | `.u-grid-auto-sm` / `.u-grid-auto` / `.u-grid-auto-lg`, `.card-grid` | |
| `.birkly-icon--sm` | `.birkly-icon` (13 px) | Two sizes only: 13 and 26 |
| `.u-bg-*`, `.u-border*`, `.u-elevation-*`, `.u-radius-*` | The role class (`.slab`, `.well`, `.frame` …) | The role decides fill, line, radius and shadow |

#### Tokens

`--accent` changed meaning. In P56 it was mint; in Birch it is blue (on, selected, focus). Plugin CSS that painted a mint fill with `var(--accent)` now paints blue. Use `var(--mint)` only for the one main action. Otherwise decide whether you mean "selected" (`--accent-soft`, `--accent-line`) or "link" (`--accent-text`).

Every P56 name that core still defines is listed below with the Birch token it resolves to today. Replace the left column with the middle one.

#### Surfaces

| P56 name | Birch token | Note |
|----------|-------------|------|
| `--canvas` | `--bg` |  |
| `--surface-sunken` | `--surface-2` |  |
| `--surface-raised` | `--surface` |  |
| `--surface-hover` | `--surface-2` |  |
| `--surface-active` | `--surface-3` |  |
| `--surface-selected` | `--accent-soft` |  |
| `--surface-inverse` | `--ink-float` |  |
| `--overlay` | `--scrim` |  |
| `--bg-primary` | `--surface` |  |
| `--bg-secondary` | `--surface-2` |  |
| `--bg-tertiary` | `--surface-2` |  |
| `--bg-elevated` | `--surface` |  |
| `--bg-hover` | `--surface-2` |  |
| `--bg-overlay` | `--scrim` |  |
| `--surface-1` | `--surface` |  |
| `--surface-muted` | `--surface-2` |  |
| `--color-neutral-200` | `--surface-3` |  |
| `--gray-50` | `--surface-2` |  |
| `--gray-100` | `--surface-2` |  |
| `--surface-secondary` | `--surface-2` |  |
| `--surface-color` | `--surface` |  |
| `--bg-subtle` | `--surface-2` |  |

#### Text

| P56 name | Birch token | Note |
|----------|-------------|------|
| `--text-primary` | `--text` |  |
| `--text-secondary` | `--text-2` |  |
| `--text-tertiary` | `--text-3` |  |
| `--text-inverse` | `--on-ink-float` |  |
| `--text-link` | `--accent-text` |  |
| `--text-link-hover` | `--accent-hover` |  |
| `--color-black` | `--text` |  |
| `--gray-500` | `--text-3` |  |
| `--gray-800` | `--text` |  |
| `--gray-900` | `--text` |  |
| `--color-text` | `--text` |  |
| `--color-text-secondary` | `--text-2` |  |
| `--text-muted` | `--text-2` |  |

#### Lines

| P56 name | Birch token | Note |
|----------|-------------|------|
| `--border-subtle` | `--line` |  |
| `--border-default` | `--line` |  |
| `--border-control` | `--line-strong` |  |
| `--border-control-hover` | `--text-3` | A text colour used as a line; prefer `--line-strong`. |
| `--border-strong` | `--text-3` | A text colour used as a line; prefer `--line-strong`. |
| `--field-border` | `--line` |  |
| `--field-border-hover` | `--line-strong` |  |
| `--field-border-focus` | `--accent` |  |
| `--gray-200` | `--line` |  |
| `--gray-300` | `--line` |  |
| `--gray-400` | `--line-strong` |  |
| `--border-light` | `--line` |  |
| `--border-medium` | `--line` |  |
| `--border-color` | `--line` |  |
| `--color-border` | `--line` |  |

#### Accent and mint

| P56 name | Birch token | Note |
|----------|-------------|------|
| `--accent-active` | `--accent-hover` |  |
| `--accent-muted` | `--accent-line` |  |
| `--accent-focus` | `--accent` | `--accent` is blue in Birch (it was mint in P56). |
| `--accent-text-hover` | `--accent-hover` |  |
| `--accent-soft-hover` | `--accent-line` |  |
| `--accent-border` | `--accent-line` |  |
| `--ink-on-accent` | `--on-accent` |  |
| `--control-track` | `--surface-3` |  |
| `--control-track-hover` | `--line-strong` |  |
| `--control-knob` | `--surface` |  |
| `--primary` | `--mint` | Mint means the one main action. Use it only on `.button.is-main`. |
| `--primary-50` | `--mint-50` | Mint tints are legacy. For a tint use `--accent-soft` (selected) or a status `-soft`. |
| `--primary-100` | `--mint-100` |  |
| `--primary-200` | `--mint-200` |  |
| `--primary-300` | `--mint-300` |  |
| `--primary-400` | `--mint-400` |  |
| `--primary-500` | `--mint-500` |  |
| `--primary-600` | `--mint-600` |  |
| `--primary-700` | `--mint-700` | Never a text colour. |
| `--primary-800` | `--mint-800` |  |
| `--color-primary` | `--mint` | As `--primary`. |
| `--color-primary-strong` | `--accent-text` |  |
| `--color-primary-soft` | `--accent-soft` |  |
| `--color-primary-muted` | `--accent-line` |  |
| `--color-primary-border` | `--accent-line` |  |

#### Status

| P56 name | Birch token | Note |
|----------|-------------|------|
| `--success-surface` | `--success-soft` |  |
| `--success-border` | `--success-soft` | Birch signals have no line; the soft fill is the edge. |
| `--success-fg` | `--success` |  |
| `--success-solid` | `--success` |  |
| `--ink-on-success` | `--on-status` |  |
| `--warning-surface` | `--warning-soft` |  |
| `--warning-border` | `--warning-soft` |  |
| `--warning-fg` | `--warning` |  |
| `--warning-solid` | `--warning` |  |
| `--ink-on-warning` | `--on-status` |  |
| `--danger-surface` | `--danger-soft` |  |
| `--danger-border` | `--danger-soft` |  |
| `--danger-fg` | `--danger` |  |
| `--danger-solid` | `--danger` |  |
| `--ink-on-danger` | `--on-status` |  |
| `--info-surface` | `--info-soft` |  |
| `--info-border` | `--info-soft` |  |
| `--info-fg` | `--info` |  |
| `--info-solid` | `--info` |  |
| `--ink-on-info` | `--on-status` |  |
| `--neutral-surface` | `--surface-2` |  |
| `--neutral-border` | `--line` |  |
| `--neutral-fg` | `--text-2` |  |
| `--status-dot-ok` | `--success` |  |
| `--status-dot-ok-glow` | `--success` | Birch dots do not glow. |
| `--status-dot-bad` | `--danger` |  |
| `--status-dot-bad-glow` | `--danger` | Birch dots do not glow. |
| `--success-50` | `--success-soft` |  |
| `--success-100` | `--success-soft` |  |
| `--success-200` | `--success-soft` |  |
| `--success-500` | `--success` |  |
| `--success-600` | `--success` |  |
| `--success-700` | `--success` |  |
| `--success-800` | `--success` |  |
| `--error-50` | `--danger-soft` |  |
| `--error-100` | `--danger-soft` |  |
| `--error-200` | `--danger-soft` |  |
| `--error-500` | `--danger` |  |
| `--error-600` | `--danger` |  |
| `--error-700` | `--danger` |  |
| `--error-800` | `--danger` |  |
| `--color-error` | `--danger` |  |
| `--danger-100` | `--danger-soft` |  |
| `--danger-700` | `--danger` |  |
| `--warning-50` | `--warning-soft` |  |
| `--warning-100` | `--warning-soft` |  |
| `--warning-200` | `--warning-soft` |  |
| `--warning-500` | `--warning` |  |
| `--warning-600` | `--warning` |  |
| `--warning-700` | `--warning` |  |
| `--warning-800` | `--warning` |  |
| `--warning-900` | `--warning` |  |
| `--info-500` | `--info` |  |
| `--danger-color` | `--danger` |  |

#### Space

| P56 name | Birch token | Note |
|----------|-------------|------|
| `--space-0` | `0` |  |
| `--space-1` | `--s-1` |  |
| `--space-2` | `--s-2` |  |
| `--space-3` | `--s-3` |  |
| `--space-4` | `--s-4` |  |
| `--space-5` | `--s-5` |  |
| `--space-6` | `--s-6` |  |
| `--space-8` | `32px` (literal) | App UI stops at 24 px (`--s-6`); larger gaps are docs only. |
| `--space-10` | `40px` (literal) | App UI stops at 24 px (`--s-6`). |
| `--space-12` | `48px` (literal) | App UI stops at 24 px (`--s-6`). |
| `--space-16` | `64px` (literal) | App UI stops at 24 px (`--s-6`). |

#### Type

| P56 name | Birch token | Note |
|----------|-------------|------|
| `--text-2xs` | `--size-caption` |  |
| `--text-xs` | `--size-small` | Careful: `--text-2` is a colour, not a size. |
| `--text-sm` | `--size-small` |  |
| `--text-base` | `--size-body` |  |
| `--text-md` | `--size-slab-title` |  |
| `--text-lg` | `--size-title-l` |  |
| `--text-xl` | `--size-page-title` |  |
| `--text-2xl` | `--size-number` |  |
| `--leading-tight` | `1.25` (literal) | Use the paired `--lh-*` token for the size. |
| `--leading-snug` | `1.4` (literal) |  |
| `--leading-normal` | `1.5` (literal) |  |
| `--leading-relaxed` | `1.6` (literal) |  |
| `--tracking-tight` | `--tracking-title` |  |
| `--tracking-normal` | `0` (literal) |  |
| `--tracking-label` | `0` | Sentence case, no tracking. |
| `--font-family-mono` | `--font-mono` |  |
| `--font-size-base` | `--size-body` |  |
| `--font-medium` | `--weight-medium` |  |

#### Radius

| P56 name | Birch token | Note |
|----------|-------------|------|
| `--radius-none` | `0` |  |
| `--radius-sm` | `--r` |  |
| `--radius-md` | `--r` |  |
| `--radius-lg` | `--r` |  |
| `--radius-pill` | `--r` | No pills in Birch; everything is 6 px. |
| `--radius-xs` | `--r` |  |
| `--radius-full` | `--r` | No pills in Birch; everything is 6 px. |
| `--radius-round` | `--r` |  |

#### Elevation

| P56 name | Birch token | Note |
|----------|-------------|------|
| `--elevation-0` | `none` |  |
| `--elevation-1` | `none` | Slabs are flat. Only floats cast shadows. |
| `--elevation-2` | `--shadow-float-2` |  |
| `--elevation-3` | `--shadow-menu` |  |
| `--elevation-inset` | `none` |  |
| `--elevation-inset-strong` | `none` |  |
| `--shadow-sm` | `none` | Slabs are flat. Only floats cast shadows. |
| `--shadow-md` | `--shadow-float-2` |  |
| `--shadow-lg` | `--shadow-float-2` |  |
| `--shadow-xl` | `--shadow-menu` |  |
| `--shadow-2xl` | `--shadow-menu` |  |

#### Motion

| P56 name | Birch token | Note |
|----------|-------------|------|
| `--motion-fast` | `--dur-1` |  |
| `--motion-base` | `--dur-2` |  |
| `--motion-slow` | `--dur-3` |  |
| `--ease-out` | `--ease` |  |
| `--ease-in-out` | `--ease` |  |
| `--transition-fast` | `var(--dur-1) var(--ease)` | Write `var(--dur-1) var(--ease)`. |
| `--transition-base` | `var(--dur-2) var(--ease)` | Write `var(--dur-2) var(--ease)`. |

#### Sizes, focus and layering

| P56 name | Birch token | Note |
|----------|-------------|------|
| `--control-md` | `--control` |  |
| `--control-lg` | `--chrome-row` | Deprecated; there is one control height per density. |
| `--chrome-stack-gap` | `--s-6` |  |
| `--chrome-content-gap` | `--s-6` |  |
| `--btn-secondary-bg` | `--surface` |  |
| `--btn-secondary-hover` | `--surface-2` |  |
| `--sidebar-width-collapsed` | `--sidebar-rail` |  |
| `--shell-edge-padding` | `--gutter` |  |
| `--focus-color` | `--accent` |  |
| `--focus-ring` | `var(--focus-outline)` | Birch focus is an outline, not a box-shadow ring. |
| `--z-index-modal` | `--z-modal` |  |

### 4. Rules for plugin CSS

1. **Never restyle a core class.** No `.button { … }`, no `.slab { … }`, no `#media-modal .modal-content { … }`. Style your own names. If you must adjust layout inside your page, scope it under your root: `.webhooks-page .table-row { grid-template-columns: … }` is fine, `.table-row { … }` is not.
2. **Tokens, not literals.** No hex, `rgb()`, `hsl()` or `oklch()`. Colour, space, radius, shadow, duration and z-index all come from `var(--…)`.
3. **Take the role's surface.** A slab is `--surface`, 6 px radius, 16 px padding, no border, no shadow. Separate slabs with gaps (4 px stacked, 8 px in a grid), never with borders. Only floats and menus cast shadows (`--shadow-sheet`, `--shadow-menu`, `--shadow-float-2`).
4. **One mint per view.** `--mint` fills exactly one main action. Blue (`--accent`) means on, selected, link or focus. Status colours appear only inside signals and status badges.
5. **Mint and blue are fills.** Text on mint is `--on-mint`; blue text is `--accent-text`.
6. **Sentence case, whole pixels.** No `text-transform: uppercase`, no letter-spaced labels, no half-pixel sizes or offsets.
7. **Follow density.** Use `--control`, `--control-sm`, `--row-list`, `--row-table` and the `--size-*` / `--lh-*` pairs instead of fixed heights and font sizes.
8. **Stepped motion from tokens.** Durations are `--dur-1` … `--dur-4`, `--dur-frame` and `--dur-px`, with `--ease` for anything not stepped. No `transition: all`, no hover scale, no looping decoration, and no reduced-motion block of your own: the global one covers token durations and every animation.
9. **Icons through the registry.** Use `birkly_icon()` / `Birkly.icon()` at 13 or 26 px, sized only with `var(--icon)` / `var(--icon-l)`. No emoji, and no inline SVG where a registry icon exists.
10. **Keep focus visible.** Never remove an outline without an equal replacement (`outline: var(--focus-outline); outline-offset: var(--focus-outline-offset)`).
11. **No `!important` against core.** Losing a specificity fight with a kit rule usually means you are restyling something you shouldn't.
12. **Never reuse the bare `data-theme` attribute.** Dark mode is scoped by `[data-theme="dark"]` on `<html>` / `<body>`, and an attribute selector matches any element. Name yours `data-theme-option` or similar.

A stylesheet that follows these rules:

```css
/* plugins/webhooks/admin/webhooks.css */

.delivery-log {
  display: flex;
  flex-direction: column;
  gap: var(--s-1);
}

.delivery-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto auto;
  align-items: center;
  gap: var(--s-3);
  min-height: var(--row-list);
  padding: var(--s-2) var(--s-4);
  border-radius: var(--r);
  background: var(--surface);
  font-size: var(--size-body);
  line-height: var(--lh-body);
  transition: box-shadow var(--dur-1) var(--ease);
}

.delivery-row.is-selected { box-shadow: 0 0 0 2px var(--accent); }

.delivery-row-time {
  color: var(--text-3);
  font-size: var(--size-caption);
  line-height: var(--lh-caption);
  font-variant-numeric: tabular-nums;
}
```

It uses no literals and no borders, touches no core class, and handles dark mode, density and selection through tokens.

### 5. Worked example: a Birch plugin page

The template below ships one line of CSS, the table's columns. It covers the page bar, a metric bar, a signal, a table, a settings frame, an empty state and a dialog.

```html
<!-- plugins/webhooks/admin/webhooks.html -->

{plugin_page_header}

<div class="webhooks-page plugin-page" data-birch>

  <div class="page-bar">
    <button type="button" class="button is-main" id="webhook-new"><?= birkly_icon('plus') ?>New webhook</button>
    <button type="button" class="button"><?= birkly_icon('download') ?>Export</button>
    <div class="page-bar-tools">
      <div class="search-field"><?= birkly_icon('search') ?><input class="input" type="search" placeholder="Search webhooks" aria-label="Search webhooks"></div>
      <div class="segmented well" role="group" aria-label="Filter">
        <button type="button" aria-pressed="true">All <span class="count">12</span></button>
        <button type="button" aria-pressed="false">Failing <span class="count is-warning">2</span></button>
      </div>
    </div>
  </div>

  <div class="metrics">
    <div class="metric">
      <span class="metric-label"><?= birkly_icon('send') ?>Delivered today</span>
      <span class="metric-value">1,284</span>
      <span class="metric-meta">99.4% success</span>
    </div>
    <div class="metric">
      <span class="metric-label"><?= birkly_icon('alert') ?>Failing endpoints</span>
      <span class="metric-value">2</span>
      <span class="metric-meta">Retrying</span>
    </div>
  </div>

  <div class="signal">
    <?= birkly_icon('alert') ?>
    <div class="signal-body">
      <p class="signal-title">2 endpoints are failing</p>
      <p class="signal-text">Deliveries retry three times with backoff.</p>
    </div>
    <button type="button" class="button is-small">Review</button>
  </div>

  <div class="table" role="table" aria-label="Endpoints">
    <div class="table-head" role="row"><span>Endpoint</span><span>Event</span><span>Status</span><span></span></div>
    <div class="table-row" role="row">
      <span class="row-body"><span class="row-title">https://example.com/hooks/orders</span><span class="row-meta mono">order.created</span></span>
      <span>842 deliveries</span>
      <span><span class="status is-success">Active</span></span>
      <span><button type="button" class="icon-button" aria-label="Endpoint menu"><?= birkly_icon('dots') ?></button></span>
    </div>
  </div>

  <div class="empty" id="webhooks-empty" hidden>
    <span class="empty-icon"><?= birkly_icon('send') ?></span>
    <p class="empty-title">No endpoints yet</p>
    <p class="empty-text">Add an endpoint to start receiving events.</p>
  </div>

  <section class="frame">
    <div class="frame-head">
      <div class="frame-head-body">
        <h2 class="frame-title">Delivery settings</h2>
        <p class="frame-text">Applies to every endpoint.</p>
      </div>
    </div>
    <div class="setting-rows">
      <div class="setting-row">
        <div class="setting-label"><label for="wh-timeout">Timeout</label><small class="field-help">Seconds to wait for a 2xx response.</small></div>
        <div class="setting-value"><input class="input" id="wh-timeout" type="number" value="10"></div>
      </div>
      <div class="setting-row">
        <div class="setting-label"><span class="u-label">Retry failed deliveries</span></div>
        <div class="setting-value">
          <button type="button" class="switch" role="switch" aria-checked="true" aria-label="Retry failed deliveries"><span><?= birkly_icon('x') ?></span><span><?= birkly_icon('check') ?></span></button>
        </div>
      </div>
    </div>
  </section>
</div>

<div class="modal" id="webhook-modal">
  <div class="modal-backdrop" data-modal-dismiss></div>
  <div class="modal-content dialog" role="dialog" aria-modal="true" aria-labelledby="webhook-modal-title">
    <div class="dialog-head">
      <div><h2 class="dialog-title" id="webhook-modal-title">New webhook</h2><p class="dialog-text">We sign every delivery with your secret.</p></div>
      <button type="button" class="icon-button" data-modal-dismiss aria-label="Close"><?= birkly_icon('x') ?></button>
    </div>
    <form class="dialog-body" id="webhook-form">
      <div class="slab">
        <div class="field-group">
          <label class="field-label" for="wh-url">Endpoint URL</label>
          <input class="input" id="wh-url" name="url" type="url" required>
        </div>
      </div>
    </form>
    <div class="dialog-foot">
      <button type="button" class="button is-quiet" data-modal-dismiss>Cancel</button>
      <button type="submit" form="webhook-form" class="button is-main">Save</button>
    </div>
  </div>
</div>
```

Notes:

- **One mint per view.** New webhook is the page's mint action. The dialog is its own view, and Save is the only mint in it.
- **Phones.** Below 768 px the page bar keeps New webhook and moves Export, the search and the filter into the More menu, with no code of your own. The kit's `.metrics` and `.table` also reflow.
- **Opening the dialog.** Add `.active` to the `.modal` from your script (`document.getElementById('webhook-modal').classList.add('active')`). Anything with `data-modal-dismiss` inside it closes it again.
- **Table columns.** A `div.table` lays out its head and rows on `--table-columns`. Set it once in your stylesheet: `.webhooks-page .table { --table-columns: minmax(0, 1fr) 140px 100px 32px; }`.
- **States.** Toggle states with the `hidden` attribute, not `style="display:none"`.
- **Plain HTML templates.** When you can't call PHP, use `<span data-icon="send"></span>` and `Birkly.initIcons(root)`.
- **Feedback.** Toasts and confirmations come from `BirklyFeedback.toast(…)` and `await BirklyFeedback.confirm(…)`, never `alert()` or `confirm()`.
- **Money.** Format it with `Birkly.money(minor, currency)` or `birkly_money()`.

### 6. Fieldtypes

Fieldtypes own their presentation, and core provides the shared vocabulary they build on.

| Method (`fieldtypes/BaseField.php`) | Returns |
|--------------------------------------|---------|
| `getIcon()` | A registry icon name for the collection builder and the Add a field dialog (empty string = the generic fieldtype icon) |
| `getTypeLabel()` | Sentence-case type name ("Short text") |
| `getDescription()` | One sentence-case line for the Add a field dialog |
| `getCSS()` | The editor widget's CSS |
| `getListCSS()` | CSS for `renderListCell()` output on entries lists |

- Build editor widgets from kit classes: `.input`, `.select-box`, `.button`, `label.switch`, `.is-mono`, `.well`, `.dashed` for drop zones.
- The frozen `.fieldtype-*` API in `admin/css/plugins/fieldtype-api.css` covers the toolbar rail, the preview grid and async states: `.fieldtype-wrapper`, `.fieldtype-with-toolbar`, `.fieldtype-toolbar`, `-toolbar-group`, `-toolbar-spacer`, `.fieldtype-content`, `.fieldtype-preview-grid`, `-preview-list`, `-preview-item`, `-preview-item-actions`, `.fieldtype-empty-state` (with `-icon` and `-text`), `.fieldtype-loading`, `.fieldtype-error` and `.fieldtype-help`.
- List cells use `.ft-list-row`, `.ft-list-text`, `.ft-list-muted`, `.ft-list-mono`, `.ft-list-badge`, `.ft-list-empty` and `.ft-list-image-square` (in `components/tables.css`).
- The entry editor places each field in its own slab (`.entry-field`), so a widget sits inside a slab. Its inputs are therefore `--surface-2` at rest; don't wrap the widget in a second box.
- **A fieldtype never adds a mint or red button to the editor.** The page's mint action is Save.

Fieldtype CSS follows the same rules as plugin CSS: tokens only, no `!important`, and no restyling of core classes or of another fieldtype's classes.

### 7. Icons

Use registry names everywhere: in templates (`birkly_icon('send')`), in JS (`Birkly.icon('send')`), in `getIcon()`, and for your sidebar item (`navigation.icon` in `plugin.json` and `sidebar.icon` in `register_plugin_admin_meta()`). A registry name draws a 13 px Birch icon with its hover gesture. A plugin's own inline `<svg>` is still accepted as a fallback, sized to the 13 px box, but it has no gesture. See [Plugin navigation icon](plugin-navigation-icon.md).

The full set, sizes, the grid alphabet and the gesture list are in [Admin design language § Icons](../01-admin/admin-design-language.md#6-icons). To turn a gesture off, for example on a drag handle, pass `['gesture' => false]` or `data-icon-gesture="false"`. To play one from script, for example when your count goes up, call `Birkly.iconPlay(el)`.

#### Icon names

Pre-Birch icon names resolve to Birch icons through aliases in `core/icon_data.php`. The aliases are removed with the token aliases, so switch to the Birch name:

| P56 name | Birch icon | P56 name | Birch icon |
|----------|------------|----------|------------|
| `archive` | `box` | `gauge` | `dashboard` |
| `arrow-counter-clockwise` | `history` | `gear` | `sliders` |
| `arrows-clockwise` | `refresh` | `image` | `media` |
| `arrows-in` | `collapse` | `import` | `download` |
| `arrows-out` | `expand` | `layout` | `template` |
| `article` | `news` | `list-checks` | `list` |
| `calendar-clock` | `calendar` | `magnifying-glass` | `search` |
| `caret-down` | `chev-down` | `music-notes` | `music` |
| `caret-left` | `chev-left` | `paper-plane-tilt` | `send` |
| `caret-right` | `chev-right` | `pencil-simple` | `pencil` |
| `caret-up` | `chev-up` | `question` | `help` |
| `chat-circle` | `chat` | `shopping-bag` | `bag` |
| `check-square` | `check-circle` | `sign-out` | `log-out` |
| `circle-dot` | `select` | `sliders-horizontal` | `sliders` |
| `dots-six-vertical` | `grip` | `speedometer` | `dashboard` |
| `dots-three-vertical` | `dots` | `squares-four` | `grid` |
| `envelope` | `mail` | `stack` | `layers` |
| `export` | `upload` | `text-aa` | `type` |
| `file-text` | `content` | `toggle-left` | `toggle` |
| `folder-root` | `folder` | `warning` | `alert` |

Every other pre-Birch name was already a Birch name: `bell`, `plus`, `trash`, `x`, `check`, `info`, `users`, `user`, `folder`, `copy`, `link`, `sun`, `moon`, `monitor`, `globe`, `shield`, `play`, `stop`, `sparkle`, `hash`, `calendar`, `clock`, `palette`, `tag`, `map-pin`, `phone`, `code`, `list`.

### 8. Review checklist before you publish

- [ ] `data-birch` on the `.plugin-page` root; no page `<h1>`; the breadcrumb comes from `{plugin_page_header}`
- [ ] Every block answers the four questions: frames hold slabs, slabs hold wells, floats hold slabs, and nothing is boxed without a role
- [ ] No border or shadow on a slab; gaps of 4 / 8 / 24 px instead
- [ ] Exactly one visible `.button.is-main` per view (page, dialog, sheet); buttons inside signals are secondary
- [ ] No colour literal in plugin CSS; blue and mint never as text colour; text on mint is `--on-mint`
- [ ] Checked in light and dark, and in Compact, Comfortable and Large
- [ ] Checked at 390, 768, 1024, 1280 and 1440 px; the page bar collapses into More on a phone
- [ ] Empty, loading and error states for every async region
- [ ] Focus visible on every control; icon buttons have `aria-label`; Escape closes dialogs
- [ ] Icons via the registry at 13 / 26 px; no emoji; sidebar icon is a registry name
- [ ] Durations are tokens; no `transition: all`, no scale, no own reduced-motion block
- [ ] Sentence case everywhere
- [ ] No P56 class, token or icon name from the rename tables in new code

## Related

- [Admin design language](../01-admin/admin-design-language.md): roles, tokens, density, components, shell, icons, motion, CI rules
- [Admin design system](../01-admin/design-system.md): short orientation
- [Admin chrome and action zones](../01-admin/admin-chrome-action-zones.md): where each kind of action belongs
- [Plugin development](plugin-development.md): structure, permissions and packaging
- [Plugin navigation icon](plugin-navigation-icon.md)
- [Field types](../02-content/field-types.md)
- [Security and review](security-and-review.md)
