# Admin design language

## Summary

The Birkly admin is built from one internal CSS framework: a set of design tokens, a `u-*` helper layer, and a component catalog. Every admin screen — core pages and plugin pages alike — is composed from those three things and ships **no page-specific CSS**. The visual language is white panels on a warm tinted canvas, hairline borders instead of shadows, one mint accent per screen, 14px base type, and motion you barely notice. This page is the complete build kit: tokens, helpers, components, and the page skeleton. You should be able to build an admin screen that matches core without opening a single existing page stylesheet.

## Beginner

### What the admin looks like, and why

Open any Birkly admin screen and you see the same shapes:

- **A warm off-white background** (the *canvas*) — never pure white. The sidebar and the top bar sit directly on it with no box around them.
- **White panels** floating on that canvas — these are *cards*. Each has a thin grey outline and slightly rounded corners. No drop shadows.
- **One mint-green button per screen** — the main thing you're meant to do. Everything else is grey or plain text.
- **Small, dense text.** Admin work is reading and scanning, so the base text size is 14px rather than the 16px you'd use on a marketing site.

Those four observations *are* the design language. Six rules keep it consistent:

1. **Panels on a canvas.** Content lives in panels. App chrome (sidebar, top bar) sits on the bare canvas. Panels don't nest more than two deep — a panel inside a panel inside a panel means the page needs splitting.
2. **Hairlines, not shadows.** A thin border does the structural work. A shadow means "this floats above the page", so only menus, modals, drawers, toasts and sticky bars get one. A shadow on a normal card is a bug.
3. **One accent per screen.** Mint is a spotlight, not paint. One mint-filled button; everything else neutral. Mint also shows up as small signals — the active sidebar item, focus rings, a selected row.
4. **Quiet density.** Table rows are 40px, not 60px. Labels are grey and light; values are dark and strong. Nothing shouts: no all-caps headings, no bold section chrome, no coloured backgrounds to mark importance.
5. **Flat hierarchy.** Depth comes from text size and spacing, not from boxes and coloured rules. A page has three levels at most: page title → section title → item.
6. **Barely-there motion.** Transitions are 120–240ms and move at most 8px. Nothing scales on hover, nothing bounces, nothing loops except a genuine loading spinner.

### Where the page title comes from

Birkly admin pages **do not have their own `<h1>`**. Instead, each page emits a breadcrumb trail, and the last segment of that trail *is* the page title. The admin then lifts the whole breadcrumb up into the top bar. So "Settings › Roles" appears once, at the top of the window, and the page body starts directly with content.

If you add an `<h1>` to a page, you will see the title twice. That is the single most common mistake when building a new admin screen.

### Dark mode

Users pick light, dark, or "follow my system" from the top bar. You don't write any dark-mode CSS. The framework swaps the *values* behind names like "surface" and "text" — so if you build with those names, your screen just works in both themes. If you find yourself needing a dark-mode rule for a component, that means a colour was hardcoded somewhere, and that's the thing to fix.

### The short version for building something

Use `.card` for panels, `.btn btn-primary` for the one main action, `.table` for data, `.field` + `.input` for forms, `.badge` for status pills, `.empty-state` when there's nothing to show. Use `u-stack-6` on the page body so the gaps between sections come out right. Reach for a `u-*` helper before you write CSS. If you still need CSS, you've probably found a gap in the framework worth reporting.

## Advanced Users

### 1. Source of truth and load order

| Layer | File | Contents |
|-------|------|----------|
| Tokens | `admin/css/core/variables.css` | Every colour, size, radius, shadow, z-index and duration. The only file allowed to contain a hex literal. |
| Legacy aliases | `admin/css/core/compat-tokens.css` | `--old-name: var(--new-name)` only. For plugin CSS. Core admin CSS may not use any name from this file. |
| Base | `admin/css/core/reset.css`, `core/typography.css` | Element defaults, the global focus ring, the single `prefers-reduced-motion` block. |
| Components | `admin/css/components/*.css` | `.card`, `.btn`, `.table`, `.field`, `.badge`, `.callout`, `.modal`, `.tabs`, … |
| Layout | `admin/css/layout/*.css` | Sidebar, topbar and page chrome, main content, grid aliases. |
| Fieldtype API | `admin/css/plugins/fieldtype-api.css` | The frozen `.fieldtype-*` contract. |
| Helpers | `admin/css/core/helpers.css` | The `u-*` composition layer. |
| Overrides | `admin/css/core/overrides.css` | Only rules that must win on source order (`[hidden] { display: none }`). |

`admin/css/style.css` is the import hub and defines the order. **Helpers are imported last, after every component.** A helper has to beat a component that sets the same property at equal specificity, and source order is what decides that — loading helpers earlier silently kills them.

Pages that still carry a stylesheet (`admin/css/pages/*.css`) are page-specific residue, not a pattern to copy. Nothing there is contracted.

---

### 2. Tokens

Every value in admin CSS comes from a token. Raw hex outside `core/variables.css` is a CI failure.

#### 2.1 Primitive ramps

These exist so the semantic tokens below have something to point at. **Components never use them directly.**

| Ramp | Steps |
|------|-------|
| Warm neutral | `--neutral-0` `#FFFFFF` · `--neutral-50` `#F8F9F7` · `--neutral-75` `#F1F3EE` · `--neutral-90` `#EEF0E9` · `--neutral-100` `#E7EAE2` · `--neutral-200` `#DDE1D8` · `--neutral-300` `#CBD0C4` · `--neutral-400` `#B3B8AC` · `--neutral-500` `#9CA096` · `--neutral-600` `#8E9089` · `--neutral-700` `#5E605B` · `--neutral-800` `#3D403A` · `--neutral-900` `#20221D` · `--neutral-950` `#161815` |
| Mint | `--mint-50` `#EDFCF7` · `--mint-100` `#D4F8ED` · `--mint-200` `#AEF0DC` · `--mint-300` `#88E8C9` · `--mint-400` `#7CE3BF` · `--mint-500` `#7CE3BF` · `--mint-600` `#52C49A` · `--mint-700` `#3DA882` · `--mint-800` `#2D8C6B` · `--mint-900` `#1D6B52` · `--mint-950` `#17604A` |

`--neutral-90` sits between `--neutral-75` (the canvas) and `--neutral-100` — see why in Surfaces below.

#### 2.2 Surfaces

| Token | Light | Dark | Role |
|-------|-------|------|------|
| `--canvas` | `#F1F3EE` | `#131511` | App background. Sidebar, topbar and main content sit on it with no fill of their own. |
| `--surface` | `#FFFFFF` | `#252821` | Panels: cards, tables, modals, nav boxes. |
| `--surface-sunken` | `#EEF0E9` (`--neutral-90`) | `#0D0F0B` | Wells: table headers, card footers, code blocks, disabled inputs, empty-state icon tiles. |
| `--surface-raised` | `#FFFFFF` | `#2E322A` | Floating surfaces: dropdowns, popovers, toasts. Always paired with an elevation. |
| `--surface-hover` | `#F8F9F7` | `#2E322A` | Hover on interactive rows and items. |
| `--surface-active` | `#E7EAE2` | `#363A31` | Pressed state, segmented-control track, progress track. |
| `--surface-selected` | `#EDFCF7` | `#1E3A30` | Selected row or item — mint-tinted, very low saturation. |
| `--surface-inverse` | `#20221D` | `#F1F3EE` | Inverse fills: tooltips. |
| `--overlay` | `rgba(32,34,29,0.32)` | `rgba(0,0,0,0.56)` | Modal, drawer and sheet backdrop. |

Note that dark mode makes the canvas **darker** than the surface, preserving the light-mode relationship — panels still read as panels.

**Why `--surface-sunken` uses `--neutral-90`, not `--neutral-100`.** A "recessed" fill (a card footer, a table header band, an options well) sits *inside* a white `.card`, one level further from the page than the canvas itself. If that fill is darker than `--canvas`, it reads as heavier than the entire page background behind it — a well inside a card looks more prominent than the page it's on. `--neutral-90` is a half-step between `--canvas` and the old `--neutral-100`, close enough to the canvas tone that a sunken fill reads as a quiet recess rather than a second, heavier layer. `--surface-active` (hover/press feedback — a different job, where a touch more presence is correct) still uses `--neutral-100`.

#### 2.3 Text

| Token | Light | Dark | Use |
|-------|-------|------|-----|
| `--text-primary` | `#20221D` | `#F4F6F2` | Headings, values, body. 16.1:1 on `--surface`. |
| `--text-secondary` | `#5E605B` | `#B4B7B0` | Labels, descriptions, idle nav. 6.4:1. |
| `--text-tertiary` | `#8E9089` | `#82857E` | Placeholders, disabled, timestamps, separators. ~3.4:1 — **must never carry essential information**. |
| `--text-inverse` | `#F8F9F7` | `#161815` | Text on an inverse or solid dark fill. |
| `--text-link` | `var(--accent-text)` | remapped | Inline links. |
| `--text-link-hover` | `var(--accent-text-hover)` | remapped | Link hover. |

#### 2.4 Borders

All borders are `1px`. 2px+ is reserved for the active-nav rail and focus outlines. The active tab/side-nav item is a filled pill (`--surface-selected`), not an underline or a border — see §4.13.

| Token | Light | Dark | Use |
|-------|-------|------|-----|
| `--border-subtle` | `#DDE1D8` | `#363A31` | **Default.** Panel, table, divider and card hairlines. Decorative. |
| `--border-default` | `#CBD0C4` | `#444840` | Slightly stronger decorative border; hover on a card. |
| `--border-control` | `#8E9089` | `#5F635A` | **Functional** boundaries — inputs, selects, outline buttons. 3.23:1, meets WCAG 1.4.11. |
| `--border-control-hover` | `#5E605B` | `#7A7E74` | Hover on a control boundary. |
| `--border-strong` | `#5E605B` | `#8A8D85` | Emphasis. |

The subtle/default pair are decorative and do not need to meet a contrast minimum; the `control` pair are functional and do. Using `--border-subtle` on an input is an accessibility bug.

**Text fields use a separate family — never `--border-control`.**

| Token | Light | Dark | Use |
|-------|-------|------|-----|
| `--field-border` | `#9CA096` (`--neutral-500`) | `#525649` | **Resting.** Deliberately quieter than `--border-control` — 2.66:1. |
| `--field-border-hover` | `#8E9089` (`--neutral-600`) | `#5F635A` | Hover. 3.23:1. |
| `--field-border-focus` | `#5E605B` (`--neutral-700`) | `#B4B7B0` | **Focus.** A dark neutral, 6.36:1 — never accent. |

`.input`, `.select` and `.textarea` (`components/forms.css`) use `--field-border` / `-hover` / `-focus` for their border, and the focus **outline** is repointed to `--field-border-focus` too — not the global `--focus-color` (mint). `--border-control` stays reserved for buttons, custom-selects and icon buttons, which have no border colour anywhere near it.

The reason for the split: `--accent-focus` (mint) sits in the same visual family as `--success-fg`/`--success-solid` (also green). A focused text field with a mint border or a mint focus ring reads as "this field is valid" the instant you tab into it — indistinguishable from the state a field gets *after* it actually passes validation (`.input.is-valid`). Every other interactive control (buttons, tabs, checkboxes, the sidebar) keeps the brand-coloured focus ring; only text fields sit close enough to the success/danger border vocabulary for the confusion to matter, so only text fields get the neutral, dark `--field-border-focus` instead.

#### 2.5 Accent — read this before using mint

Mint is a **light** colour. The accent is split into a fill ramp and a text ramp, and they are not interchangeable.

| Token | Light value | Contrast | Use |
|-------|-------------|----------|-----|
| `--accent` | `#7CE3BF` | **1.54:1 on white** | **Fills only** — primary button, checked checkbox, active-tab rail, progress bar, avatar. |
| `--accent-hover` | `#8CECCA` | — | Primary button hover. |
| `--accent-active` | `#79E1BD` | — | Primary button pressed. |
| `--accent-muted` | `#52C49A` | 3.7:1 | Decorative borders and indicators. **Never text.** |
| `--accent-focus` | `#2D8C6B` | 4.14:1 | Focus rings and outlines. |
| `--accent-text` | `#1D6B52` | **6.41:1** | Accent text and links on light surfaces. |
| `--accent-text-hover` | `#17604A` | 7.48:1 | Link hover. |
| `--accent-soft` | `#EDFCF7` | — | Tinted backgrounds, selected rows, bulk-action bars. |
| `--accent-soft-hover` | `#D4F8ED` | — | Hover on an accent-soft surface. |
| `--accent-border` | `#AEF0DC` | — | Border on an accent-soft surface. |
| `--ink-on-accent` | `#20221D` | **10.4:1 on mint** | Text and icons **on** a mint fill. |

> **Four hard rules. Breaking any of them is an accessibility defect, and three of them are blocked in CI.**
>
> 1. **Never `color: var(--accent)` on a light surface.** Mint text on white is 1.54:1 — effectively invisible. Use `--accent-text`.
> 2. **Never use `--primary-700` for text.** It resolves to `#3DA882` and measures **2.95:1** — below even the 3:1 non-text threshold, and far below the 4.5:1 needed for 14px body links. Before this framework, every accent link in the admin used it and every one of them failed AA. It survives only as a legacy alias for plugin CSS. Use `--accent-text`.
> 3. **Never white text on a mint fill.** Use `--ink-on-accent`.
> 4. **Don't hardcode either direction for dark mode.** `--accent-text` remaps to `#8CECCA` (11.6:1 on the dark surface) automatically. Its light value would be unreadable on dark, and vice versa. Both directions are tokenised.

In dark mode: `--accent`, `--accent-hover`, `--accent-active` and `--ink-on-accent` keep their light values (mint fills read correctly on dark), while `--accent-text`, `--accent-focus`, `--accent-soft`, `--accent-soft-hover` and `--accent-border` are remapped.

#### 2.6 Semantic colours

Four families — **success, warning, danger, info** — plus a neutral tone for draft/archived/disabled. Each exposes the same roles.

| Role | Meaning |
|------|---------|
| `--{sem}-surface` | Soft tinted background. |
| `--{sem}-border` | Border on that soft surface. |
| `--{sem}-fg` | Text and icon colour on a light or soft surface. AA on `--surface`. |
| `--{sem}-solid` | Saturated fill. **Destructive and confirm buttons only.** |
| `--ink-on-{sem}` | Text colour on the solid fill. |

| Family | surface | border | fg | solid | ink-on |
|--------|---------|--------|-----|-------|--------|
| success | `#E8F5EC` | `#BFE0CC` | `#1B6E3C` (6.3:1) | `#24793F` (5.4:1) | `#FFFFFF` |
| warning | `#FDF3E3` | `#F0DCAE` | `#7A5200` (6.9:1) | `#E0A100` | `#20221D` — warning takes **dark** ink |
| danger | `#FDEDED` | `#F2C6C6` | `#A32020` (7.5:1) | `#D33A3A` (4.7:1) | `#FFFFFF` |
| info | `#E9F1FA` | `#C3D9EF` | `#1F5C99` (6.9:1) | `#2A6FB5` (5.2:1) | `#FFFFFF` |
| neutral | `#E7EAE2` | `#CBD0C4` | `#5E605B` | — | — |

Dark values: `--dk-success-surface` `#16301F` / border `#24492F` / fg `#7BD79B`; warning `#33260C` / `#4D3A12` / `#E8BC5E`; danger `#331A1A` / `#4D2626` / `#F09393`; info `#15263A` / `#22405E` / `#8CBEEA`; neutral `#262823` / `#3A3D36` / `#B4B7B0`. The `-solid` values do not change between themes.

**Design rule.** Status indicators — badges, callouts, inline states, toast bodies — always use `--{sem}-surface` + `--{sem}-fg`, never `--{sem}-solid`. A saturated green pill next to a mint primary button reads muddy; a soft-tinted pill does not. Success is deliberately deeper and less saturated than mint so the two never read as the same colour.

`--info-*` exists so nobody reaches for an arbitrary blue. There is no other blue in the admin.

#### 2.7 Spacing

4px grid, rem-based, one scale.

| Token | Value | Primary use |
|-------|-------|-------------|
| `--space-0` | 0 | Resets |
| `--space-1` | 0.25rem / 4px | Icon-to-label, badge padding, row-action gaps |
| `--space-2` | 0.5rem / 8px | Tight control padding, chip gaps, related controls |
| `--space-3` | 0.75rem / 12px | Control padding, list row padding, unrelated controls in a row |
| `--space-4` | 1rem / 16px | Default gap inside a panel |
| `--space-5` | 1.25rem / 20px | Panel padding (compact), modal body |
| `--space-6` | 1.5rem / 24px | **Panel padding (default), gap between panels** |
| `--space-8` | 2rem / 32px | Gap between page sections |
| `--space-10` | 2.5rem / 40px | Shell edge padding (desktop) |
| `--space-12` | 3rem / 48px | Empty-state / hero interior |
| `--space-16` | 4rem / 64px | Auth and onboarding heroes only |

**Rhythm:** `--space-6` between top-level page sections, never mixed within one page. Panel interior is `--space-6` by default, `--space-5` compact, `--space-4` for dense list panels.

#### 2.8 Type scale

Root stays 16px; **UI base is 14px**.

| Token | Size | Line height | Weight | Use |
|-------|------|-------------|--------|-----|
| `--text-2xs` | 0.6875rem / 11px | snug | 500 | Uppercase micro-labels, table headers, meta |
| `--text-xs` | 0.75rem / 12px | normal | 400 | Badges, help text, timestamps |
| `--text-sm` | 0.8125rem / 13px | normal | 400 | Tables, secondary text, toolbars, field labels |
| `--text-base` | 0.875rem / 14px | normal | 400 | **Default UI text**, inputs, buttons, menu items |
| `--text-md` | 1rem / 16px | relaxed | 400 | Prose and help documentation only |
| `--text-lg` | 1.125rem / 18px | snug | 600 | Card and section titles |
| `--text-xl` | 1.375rem / 22px | tight | 500 | Page title (the breadcrumb `.current` segment) |
| `--text-2xl` | 1.75rem / 28px | tight | 600 | Stat values, auth / onboarding / empty-state heroes |

Supporting tokens:

| Group | Tokens |
|-------|--------|
| Line height | `--leading-tight` 1.25 · `--leading-snug` 1.35 · `--leading-normal` 1.5 · `--leading-relaxed` 1.6 |
| Weight | `--weight-regular` 400 · `--weight-medium` 500 · `--weight-semibold` 600 |
| Tracking | `--tracking-tight` `-0.01em` (at `--text-lg` and above) · `--tracking-normal` 0 · `--tracking-label` `0.04em` (uppercase micro-labels only) |
| Family | `--font-sans` (system UI stack) · `--font-mono` (system mono stack) |

**Three weights only.** 700 does not appear anywhere in page chrome. Uppercase is allowed **only** at `--text-2xs` with `--tracking-label`, via `.u-label` or a component that already does it (table headers, dropdown section labels, stat labels). Uppercase buttons or section titles are wrong.

Headings carry **no margins**. `h1`–`h6` are styled for structure only (`h1` → `--text-xl`/500, `h2` → `--text-lg`/600, `h3`/`h4` → `--text-base`/600, `h5`/`h6` → `--text-sm`). Vertical rhythm belongs to `u-stack-*` on the parent.

Tabular numerals (`.u-numeric`) go on every numeric table column, stat value and count.

#### 2.9 Radius

| Token | Value | Use |
|-------|-------|-----|
| `--radius-none` | 0 | Flush edges inside a group |
| `--radius-sm` | 6px | Controls: buttons, inputs, badges, chips, nav items, icon tiles |
| `--radius-md` | 10px | Panels: cards, tables, sections, wells, dropdown menus |
| `--radius-lg` | 14px | Overlays: modals, drawers, sheets |
| `--radius-pill` | 999px | Pills, avatars, toggle tracks, progress bars |

A child inside a rounded container uses the **next step down**, never the same or larger.

#### 2.10 Elevation

| Token | Value | Allowed on |
|-------|-------|------------|
| `--elevation-0` | `none` | **Default.** Cards, panels, tables, sections, stats. |
| `--elevation-1` | `0 1px 2px rgba(32,34,29,0.06)` | Stuck sticky bars, hover on an interactive card, the switch knob, the active segmented item. |
| `--elevation-2` | `0 4px 12px rgba(32,34,29,0.10)` | Dropdowns, popovers, tooltips, toasts, drag ghosts. |
| `--elevation-3` | `0 16px 40px rgba(32,34,29,0.16)` | Modals, drawers, sheets, the search overlay. |

Dark mode swaps in heavier alphas (`--dk-elevation-1/2/3`). A shadow on a static panel is a review failure.

#### 2.11 Layering

One scale. No raw z-index literal may appear in admin CSS; CI blocks it (`0` and `1` are allowed as intra-stacking-context values, e.g. a scrim behind its own panel).

| Token | Value | Layer |
|-------|-------|-------|
| `--z-base` | 0 | In-flow content |
| `--z-raised` | 10 | Sticky table headers, overlapping cards, pressed button in a group |
| `--z-dragging` | 50 | Drag ghost |
| `--z-sticky` | 100 | Sticky save / action bars |
| `--z-chrome` | 200 | Topbar |
| `--z-backdrop` | 300 | Sidebar scrim |
| `--z-drawer` | 310 | Mobile sidebar, side drawers |
| `--z-modal` | 400 | Modal dialogs, sheets, search overlay |
| `--z-popover` | 500 | Dropdowns, menus, popovers — **above modal**, so menus inside dialogs work |
| `--z-toast` | 600 | Toasts, feedback region |
| `--z-tooltip` | 700 | Tooltips |

#### 2.12 Motion

| Token | Value | Use |
|-------|-------|-----|
| `--motion-fast` | 120ms | Hover, focus, colour changes |
| `--motion-base` | 180ms | Enter/exit, expand/collapse |
| `--motion-slow` | 240ms | Drawers, large panels, sticky-bar transitions |
| `--ease-out` | `cubic-bezier(0.2, 0, 0, 1)` | Entrances, expands |
| `--ease-in-out` | `cubic-bezier(0.4, 0, 0.2, 1)` | Position changes |

Rules: enumerate transition properties (`transition: all` is blocked in CI); maximum travel 8px; looping animation only for indeterminate loading. `prefers-reduced-motion: reduce` is handled in **one** global block in `core/reset.css`; components must not ship their own.

Two scoped exceptions to "no hover translate / no hover scale", both deliberate and both narrow:

- **Buttons get a 1px `translateY` on `:active` only** — never on hover. It's gated on an actual press, not the pointer passing over the element, so it reads as physical feedback rather than motion.
- **Icon-button glyphs** (the `svg`/`.birkly-icon` *inside* a `.btn-icon`, not the button box itself) scale slightly on hover (`1.08`) and active (`0.96`). The hit target never moves or resizes — only the glyph does — so it doesn't read as a card-style hover violation.

**Show/hide panels use a state class, never a raw `display` swap.** Anything that should animate open and closed — drawers, floating windows, FAB menus — stays `display: flex` (or whatever its layout display is) at all times in CSS. A class like `.is-open` toggles `opacity`, `visibility`, `transform` and `pointer-events` instead, so the transition actually runs in both directions. JS calls `classList.add/remove('is-open')` (or reads `classList.contains(...)`) and never reads or writes `element.style.display` for anything that should animate. `.modal`, `.sheet` and `.ai-chat-window` are the reference implementations: always laid out, always transitioning, with `.is-open` (or `.show`/`.active`, depending on the component) as the only thing JS touches. Toggling `style.display` directly makes the open/close instant and jarring — there is nothing left for CSS to transition.

#### 2.13 Focus

| Token | Value |
|-------|-------|
| `--focus-color` | `var(--accent-focus)` |
| `--focus-outline` | `2px solid var(--focus-color)` |
| `--focus-outline-offset` | `2px` |
| `--focus-ring` | `0 0 0 3px color-mix(...)` — a box-shadow ring, available but not currently used by core |

`core/reset.css` applies `:focus-visible { outline: var(--focus-outline); outline-offset: var(--focus-outline-offset) }` globally. Never remove focus without an equivalent replacement. Focus must be visible on `--canvas`, `--surface` **and** `--accent` fills.

#### 2.14 Control sizes

| Token | Height | Use |
|-------|--------|-----|
| `--control-xs` | 1.5rem / 24px | Inline table-cell actions, chip removes, toast close |
| `--control-sm` | 1.75rem / 28px | Dense toolbars, filter chips, `.btn-sm`, modal close |
| `--control-md` | 2.25rem / 36px | **Default for all page controls**: buttons, inputs, selects, icon buttons |
| `--control-lg` | 2.5rem / 40px | **Chrome row only**: topbar tiles, sidebar brand, page-title row, tabs, `.btn-lg`, auth forms |

If the control is in `.admin-topbar` or the page-title row, it's `lg`. Everywhere else, `md`. A 40px button in a page body is wrong.

Related: `--chrome-row` (= `--control-lg`), `--chrome-stack-gap` and `--chrome-content-gap` (both `--space-6`) keep the sidebar brand row, topbar row and page-title row on one rhythm.

#### 2.15 Measure and page width

| Token | Value | Use |
|-------|-------|-----|
| `--measure-sm` | 42ch | Help text, field descriptions, empty-state copy |
| `--measure-md` | 68ch | Prose, help docs, section descriptions |
| `--measure-lg` | 90ch | Settings forms |
| `--page-max` | 1440px | Page body max width |
| `--page-max-narrow` | 880px | Single-column pages: account, auth, onboarding |
| `--sidebar-width` | 280px | Expanded sidebar |
| `--sidebar-width-collapsed` | `var(--chrome-row)` (40px) | Icon rail — matches the logo tile's own width, so the collapsed column is one consistent width top to bottom |
| `--shell-edge-padding` | `--space-10` | Main content padding; steps down at each breakpoint |

#### 2.16 Breakpoints

Five values, mobile-first, `min-width` preferred.

| Token | Value |
|-------|-------|
| `--bp-sm` | 640px |
| `--bp-md` | 768px |
| `--bp-lg` | 1024px |
| `--bp-xl` | 1280px |
| `--bp-2xl` | 1440px |

Custom properties cannot be used inside `@media` conditions, so write the literal and keep it in sync with the token. Only these five values and their `max-width` complements (639/767/1023/1279/1439) are allowed; CI rejects anything else. Prefer `min-width` — the shell and a handful of component collapses use `max-width` complements where a mobile-only rule is genuinely clearer, but new work should not add more.

---

### 3. Helpers (`u-*`)

Helpers are the mechanism that lets a page ship zero CSS.

**Rules:**

- **Prefix `u-`.** Guarantees no collision with plugin-private CSS. There is no unprefixed helper layer.
- **No `!important` in any helper. Ever.** If a helper loses a specificity fight, the component is too specific — fix the component.
- **Helpers load last** (after every component in `style.css`), so at equal specificity they win on source order.
- **Single-purpose and immutable.** A helper never sets component internals.
- **Rule of three.** If the same combination of 3+ helpers recurs on 3+ surfaces, it stops being a composition and becomes a component. Report it rather than pasting it a fourth time.
- **Responsive variants use an explicit suffix** (`u-hide-below-md`), not escaped `@` syntax.

#### 3.1 Layout primitives

The highest-value family — these replace hand-rolled flex and grid.

| Helper | Effect |
|--------|--------|
| `u-stack` | Column flex, `gap: --space-4` |
| `u-stack-0` … `u-stack-10` | Column flex at `--space-{0,1,2,3,4,5,6,8,10}` |
| `u-flow`, `u-flow-2`, `u-flow-3`, `u-flow-6` | Margin-based rhythm (`> * + *`) for prose and rich text where flex breaks the layout |
| `u-row` | Row flex, centre-aligned, `gap: --space-3` |
| `u-row-1`, `-2`, `-3`, `-4`, `-6` | Same at the named space step |
| `u-cluster` | Wrapping row, `gap: --space-2` |
| `u-cluster-3`, `u-cluster-4` | Wrapping row at 12px / 16px |
| `u-split` | Row with `space-between` and `gap: --space-4` |
| `u-split-start` | Top-aligns a `u-split` |
| `u-center` | Flex centre both axes |
| `u-center-col` | Column, centred, `text-align: center` |
| `u-grid` | Grid, `gap: --space-4` |
| `u-grid-2`, `u-grid-3`, `u-grid-4` | Responsive column grids — 1 column below 768px, 2 at 768px, then 3 / 4 at 1024px |
| `u-grid-auto` | `auto-fill, minmax(240px, 1fr)` |
| `u-grid-auto-sm` | `minmax(160px, 1fr)`, `gap: --space-3` |
| `u-grid-auto-lg` | `minmax(320px, 1fr)`, `gap: --space-6` |
| `u-sidebar-layout` | Main + aside grid. Single column below 1024px, aside 260–300px at 1024px, 280–340px at 1440px |
| `u-fill` | `flex: 1 1 auto; min-width: 0` |
| `u-shrink-0`, `u-grow-0` | Flex child sizing |
| `u-wrap`, `u-nowrap-flex`, `u-col` | `flex-wrap` / `flex-direction` overrides |
| `u-push-right`, `u-push-bottom` | `margin-inline-start: auto` / `margin-block-start: auto` |
| `u-align-start`, `-center`, `-end`, `-baseline`, `-stretch` | `align-items` |
| `u-justify-start`, `-center`, `-end`, `-between` | `justify-content` |
| `u-self-start`, `-center`, `-end` | `align-self` |
| `u-block`, `u-inline-block`, `u-inline`, `u-flex`, `u-inline-flex`, `u-contents` | `display` |

#### 3.2 Spacing

Logical properties throughout, so they respect writing direction.

| Family | Members |
|--------|---------|
| Margin (all) | `u-m-0`, `u-m-auto` |
| Margin top | `u-mt-0/1/2/3/4/5/6/8`, `u-mt-auto` |
| Margin bottom | `u-mb-0/1/2/3/4/5/6/8` |
| Margin inline start | `u-ml-0/1/2/3/4`, `u-ml-auto` |
| Margin inline end | `u-mr-0/1/2/3/4` |
| Margin axis | `u-mx-auto`, `u-my-0`, `u-my-4`, `u-my-6` |
| Padding (all) | `u-p-0/1/2/3/4/5/6/8` |
| Padding block | `u-pt-0/2/3/4/6`, `u-pb-0/2/3/4/6`, `u-py-0/2/3/4/6` |
| Padding inline | `u-px-0/2/3/4/6` |
| Gap | `u-gap-0/1/2/3/4/5/6/8`, `u-gap-x-2`, `u-gap-x-4`, `u-gap-y-2`, `u-gap-y-4` |

Prefer a stack/row/cluster gap over per-child margins. Margin helpers exist for the cases where the parent isn't yours to change.

#### 3.3 Typography

| Helper | Effect |
|--------|--------|
| `u-text-2xs` … `u-text-2xl` | Full ladder with matching line height; `lg`/`xl`/`2xl` also apply `--tracking-tight` |
| `u-weight-regular`, `-medium`, `-semibold` | 400 / 500 / 600 |
| `u-leading-tight`, `-normal`, `-relaxed` | Line height only |
| `u-align-text-left`, `-center`, `-right` | `text-align` (note: `u-align-*` without `text` is flex alignment) |
| `u-truncate` | Single-line ellipsis, includes `min-width: 0` |
| `u-clamp-2`, `u-clamp-3` | Multi-line clamp |
| `u-nowrap` | `white-space: nowrap` |
| `u-break-word` | `overflow-wrap: anywhere` |
| `u-numeric` | Tabular figures. Required on numeric columns, stats and counts |
| `u-mono` | `--font-mono` |
| `u-label` | The only sanctioned uppercase treatment: 11px, 500, uppercase, `--tracking-label`, `--text-tertiary` |

#### 3.4 Colour and tone

Semantic only — there are no raw colour helpers.

| Family | Members |
|--------|---------|
| Foreground | `u-fg-primary`, `-secondary`, `-tertiary`, `-inverse`, `-accent`, `-success`, `-warning`, `-danger`, `-info`, `-inherit` |
| Background | `u-bg-canvas`, `-surface`, `-sunken`, `-raised`, `-accent-soft`, `-success-soft`, `-warning-soft`, `-danger-soft`, `-info-soft`, `-neutral-soft`, `-transparent` |

`u-fg-accent` resolves to `--accent-text`, not `--accent`. There is deliberately no helper that puts mint on a light background.

#### 3.5 Surface and shape

| Helper | Effect |
|--------|--------|
| `u-surface` | `--surface` fill + 1px `--border-subtle` + `--radius-md`. A card without the card component. |
| `u-surface-sunken` | `--surface-sunken` fill + `--radius-md` |
| `u-border` | 1px `--border-subtle` all round |
| `u-border-top`, `-bottom`, `-left`, `-right` | Single-side hairline (logical) |
| `u-border-none` | Removes the border |
| `u-border-default`, `u-border-strong` | `border-color` only — combine with `u-border` |
| `u-radius-none`, `-sm`, `-md`, `-lg`, `-pill` | Radius |
| `u-elevation-0`, `-1`, `-2`, `-3` | Shadow |

#### 3.6 Size and measure

| Helper | Effect |
|--------|--------|
| `u-w-full`, `u-w-auto`, `u-w-fit` | Width |
| `u-h-full`, `u-h-auto` | Height |
| `u-min-w-0`, `u-min-h-0` | Flex/grid overflow fixes |
| `u-max-w-measure-sm`, `-measure-md`, `-measure-lg` | Reading measure caps |
| `u-max-w-page`, `u-max-w-page-narrow`, `u-max-w-full` | Page width caps |
| `u-h-control-sm`, `-md`, `-lg` | Match a control row height |
| `u-square-control-sm`, `-md`, `-lg` | Square icon-tile sizing |
| `u-aspect-square`, `u-aspect-video` | Aspect ratio |
| `u-object-cover`, `u-object-contain` | Media fit |

#### 3.7 State and visibility

| Helper | Effect |
|--------|--------|
| `u-hidden` | `display: none` |
| `u-invisible`, `u-visible` | `visibility` |
| `u-sr-only` | Visually hidden, screen-reader accessible. `.sr-only` is the same rule under the pre-framework name and is still emitted by templates and JS. |
| `u-disabled` | 0.55 opacity, no pointer events, `not-allowed` cursor |
| `u-loading` | Hides text and centres a spinner pseudo-element |
| `u-hide-print` | Hidden in print |
| `u-hide-below-sm`, `-md`, `-lg`, `-xl` | Hidden until the named breakpoint |
| `u-hide-above-sm`, `-md`, `-lg`, `-xl` | Hidden from the named breakpoint up |

For "hide this element", prefer the native **`hidden` attribute**. `core/overrides.css` declares `[hidden] { display: none }` after every component, so it beats a component's own `display: flex` without `!important`. That makes `hidden` the correct replacement for `style="display:none"`. JS toggles a class or the attribute — never `element.style.*`, except for genuinely computed values (measured positions, progress widths, drag transforms).

#### 3.8 Interaction

| Helper | Effect |
|--------|--------|
| `u-focus-ring` | `:focus-visible` outline for elements that aren't natively focusable-styled |
| `u-clickable` | `cursor: pointer` |
| `u-no-select` | `user-select: none` |
| `u-scroll-x`, `u-scroll-y` | Scroll with `overscroll-behavior: contain` |
| `u-overflow-hidden`, `u-overflow-visible` | `overflow` |
| `u-sticky-top` | `position: sticky; top: 0; z-index: --z-sticky` |

#### 3.9 Position

| Helper | Effect |
|--------|--------|
| `u-relative`, `u-absolute`, `u-fixed`, `u-sticky`, `u-static` | `position` |
| `u-inset-0` | `inset: 0` |
| `u-z-raised`, `u-z-sticky`, `u-z-popover`, `u-z-modal`, `u-z-toast` | Layer tokens |

---

### 4. Component catalog

Everything below is copy-pasteable. Class names in this section are stable.

#### 4.1 Card

The default panel. Border-only by design.

```html
<section class="card">
  <header class="card-header">
    <div class="card-heading">
      <h2 class="card-title">General</h2>
      <p class="card-description">Site name, language and timezone.</p>
    </div>
    <div class="card-actions">
      <button class="btn btn-sm">Edit</button>
    </div>
  </header>
  <div class="card-body u-stack-4">
    <!-- fields, tables, lists -->
  </div>
  <footer class="card-footer">
    <button class="btn btn-primary">Save</button>
  </footer>
</section>
```

| Part | Notes |
|------|-------|
| `.card` | Column flex, `--surface`, 1px `--border-subtle`, `--radius-md`, `--elevation-0` |
| `.card-header` | Space-between row, `--space-4 --space-6` padding, bottom hairline |
| `.card-title` | `--text-lg`/600. Bare `h2`/`h3` inside `.card-header` get the same treatment |
| `.card-heading` | Column wrapper when the header has a title *and* a description |
| `.card-subtitle`, `.card-description` | `--text-sm`, secondary, capped at `--measure-md` |
| `.card-actions` | Right-hand action group, `gap: --space-2` |
| `.card-body` | `--space-6` padding. Add `u-stack-4` for internal rhythm |
| `.card-footer` | Sunken band with a top hairline |

Modifiers: `.card--compact` (tighter padding on all three regions) · `.card--flush` (zero body padding, for a table or list that supplies its own) · `.card--padded` (the card *is* the body — for JS-composed cards) · `.card--sunken` · `.card--accent` · `.card--danger` · `.card--borderless` · `.card--interactive` (hover border + `--elevation-1`; `a.card` gets it automatically). `.card--bordered` and `.card--elevated` remain as aliases for un-migrated markup — don't reach for them in new work.

##### Card section — stop nesting a card inside a card

Once content already lives inside a `.card`, its children compose with spacing and a divider, not a second box. `.card-section` is a divider-separated sub-block with **no border, background or radius of its own** — it exists specifically so a settings sidebar, a fieldtype's options block, or any other group of fields *inside* a card body doesn't reach for a nested `.card`:

```html
<div class="card-body u-stack-0">
  <div class="card-section">
    <p class="card-section-title">Display</p>
    <!-- fields -->
  </div>
  <div class="card-section">
    <p class="card-section-title">Access</p>
    <!-- fields -->
  </div>
</div>
```

Each `.card-section` after the first grows a top hairline (`.card-section + .card-section`); the first and last sections drop their outer padding so the group still sits flush with the card's own edges. Use `.card-section--compact` for tighter option rows, and `.card-section-title` for a small heading inside one section. See the **box-in-box** rule in §7 (Do and don't) — this is what to reach for instead.

If the nested content is a single explanatory note rather than a group of fields, don't box it at all: plain stacked text (`u-stack-*`) reads better than a bordered aside for one sentence.

#### 4.2 Stat

A labelled metric. Flat, no gradient.

```html
<div class="stat">
  <span class="stat-label">Published entries</span>
  <span class="stat-value">1,284</span>
  <span class="stat-meta">+12 this week</span>
</div>
```

`.stat-value` is `--text-2xl`/600 with tabular figures already applied. Put stats in a `u-grid-auto` or inside a `.card`, not in a bespoke tile.

#### 4.3 Data list

Rows in a panel, for things that aren't tabular enough to be a table.

```html
<div class="card card--flush">
  <div class="data-list">
    <a class="data-list-item" href="#">
      <div class="data-list-main">
        <span class="data-list-title">Weekly digest</span>
        <span class="data-list-meta">Runs Mondays at 09:00</span>
      </div>
      <span class="badge badge-success">Active</span>
      <div class="data-list-actions">
        <button class="btn-icon btn-icon-sm" aria-label="Edit">…</button>
      </div>
    </a>
  </div>
</div>
```

Rows are 44px (`.data-list--compact` → 36px). `a.data-list-item` and `.data-list-item--interactive` get a hover fill. `.data-list--flush` drops the horizontal padding for lists inside an already-padded container. A `.switch` placed directly in a `.data-list-item` is automatically pushed to the trailing edge.

#### 4.4 Key/value

```html
<dl class="kv">
  <dt class="kv-key">Handle</dt>
  <dd class="kv-value">blog</dd>
  <dt class="kv-key">Entries</dt>
  <dd class="kv-value u-numeric">1,284</dd>
</dl>
```

Two-column grid (10rem label column) that collapses to one column below 768px.

#### 4.5 Buttons

One naming convention: single dash.

```html
<button class="btn btn-primary">New entry</button>
<button class="btn btn-secondary">Cancel</button>
<button class="btn">Neutral outline</button>
<button class="btn btn-outline">Explicit outline</button>
<button class="btn btn-ghost">Low priority</button>
<button class="btn btn-danger">Delete</button>
<button class="btn btn-sm">Small</button>
<button class="btn btn-lg">Chrome row</button>
<button class="btn btn-primary btn-loading">Saving</button>
<button class="btn-icon" aria-label="Edit"><!-- 16px icon --></button>
```

| Variant | Appearance |
|---------|------------|
| `.btn` | Neutral outline — `--surface` fill, `--border-control`. Bare `.btn` is a real, complete button; there is no `:not()` chain. |
| `.btn-primary` | The mint fill. **One per screen.** `--ink-on-accent` text. |
| `.btn-secondary` | Filled neutral (`--surface-sunken`) — clearly a button, not a ghost. |
| `.btn-outline` | Explicit alias of the base look. |
| `.btn-ghost` | Borderless, transparent, secondary text. `.btn-muted` is an alias. |
| `.btn-link` | Text link styling — no height, no padding, underlined, `--text-link`. |
| `.btn-danger` | `--danger-solid` fill. Destructive actions. |
| `.btn-danger-quiet` | Transparent with danger text, for menus and table rows. |
| `.btn-success`, `.btn-warning` | Solid semantic fills. Rare — reserve for genuine confirm affordances. |

| Size / modifier | Effect |
|-----------------|--------|
| default | `--control-md` (36px), `--text-base` |
| `.btn-sm` (alias `.btn-small`) | `--control-sm` (28px), `--text-sm`, 14px icons |
| `.btn-lg` (alias `.btn-large`) | `--control-lg` (40px) — chrome row only |
| `.btn-full` | `width: 100%` |
| `.btn-loading` (alias `.btn.loading`) | Hides the label, spins a pseudo-element |
| `.btn-icon` | Square icon-only control at `--control-md`; `.btn-icon-sm` / `.btn-icon-xs` step down. `.btn-icon--danger` and `.btn-icon--edit` are the contracted table-row actions. |
| `.btn-group` | Joined row of buttons; `[aria-pressed="true"]` marks the active one |

Direct `svg` or `.birkly-icon` children are sized automatically (16px, 14px in `.btn-sm`).

**Hover model — each variant darkens toward itself, never toward a shared grey.** Every filled variant's hover and active state is derived from its *own* resting colour with `color-mix()`, not a single grey wash applied to every button. A shared hover treatment across variants is what made primary, secondary and outline feel interchangeable the instant you moved the pointer over them:

- `.btn-primary` hover darkens the mint fill toward black (`color-mix(in srgb, var(--accent) 92%, black 8%)`, ~16% at `:active`) and adds a soft ring in `--accent-soft`. It never *lightens* on hover — brightening an already-light mint fill reads as a flash.
- `.btn-secondary` hover darkens its own fill toward `--text-primary` (`color-mix(in srgb, var(--surface-sunken) 80%, var(--text-primary) 20%)`, deeper again at `:active`), so it stays visually distinct from `.btn-outline`'s plain neutral wash instead of converging on the same hover colour.
- `.btn-danger` / `.btn-success` / `.btn-warning` darken toward their own `-fg` tone with a matching soft-surface ring, on a three-step rest/hover/active ladder.
- `.btn-outline` and `.btn-ghost` have no fill of their own to darken, so they keep the plain `--surface-hover` neutral wash.

A new variant needs its own darken/tint step derived from its own resting colour — do not add a second shared/generic hover rule across variants.

**Press and micro-motion — two narrow, deliberate exceptions to "no hover translate/scale":**

- Every `.btn` gets a **1px `translateY` on `:active` only**, never on hover — a small physical-press cue gated on the actual click.
- `.btn-icon` glyphs (the `svg`/`.birkly-icon` child, not the button box) nudge with `scale(1.08)` on hover and `scale(0.96)` on active. The hit target itself never scales or moves; only the icon glyph does, so it reads as icon feedback rather than a card-style hover violation.

#### 4.6 Forms

```html
<div class="field">
  <label class="field-label" for="site-name">Site name <span class="field-required">*</span></label>
  <input class="input" id="site-name" type="text" required>
  <p class="field-help">Shown in the browser tab and in search results.</p>
  <p class="field-error">Site name is required.</p>
</div>

<div class="field">
  <label class="field-label" for="lang">Language</label>
  <select class="select" id="lang">
    <option>English</option>
  </select>
</div>

<div class="field">
  <label class="field-label" for="bio">Bio</label>
  <textarea class="textarea" id="bio" rows="4"></textarea>
</div>

<label class="checkbox-label">
  <input type="checkbox"> Send me the weekly digest
</label>

<label class="switch">
  <input type="checkbox">
  <span class="switch__slider"></span>
</label>
```

| Class | Notes |
|-------|-------|
| `.field` | Column flex, `gap: --space-2`. `.form-group` and `.field-block` are structural aliases kept because plugin CSS and existing templates use them. |
| `.field-label` (alias `.form-label`) | `--text-sm`/500, `--text-secondary`. A bare `<label>` inside `.field` / `.form-group` gets the same. |
| `.field-required` | The red asterisk |
| `.field-help` (aliases `.form-help`, `.field-hint`, `.form-text`) | `--text-xs`, tertiary, capped at `--measure-sm` |
| `.field-error` (aliases `.form-error`, `.invalid-feedback`) | `--text-xs`, `--danger-fg` |
| `.field-row` | Label-beside-control grid (12rem label column), single column below 768px |
| `.form-grid` | `auto-fit, minmax(220px, 1fr)` |
| `.input`, `.select`, `.textarea` | 36px, `--border-control`, `--radius-sm`. The same rules apply to bare `input`/`select`/`textarea` elements, so unclassed markup still looks right. |
| `.input-sm`, `.input-lg` | 28px / 40px |
| `.input-search` | Adds the leading icon gutter |
| `.input-icon-wrap` | Relative wrapper that absolutely positions a leading icon |
| `.input-group` | Control with an attached button — radii and the 1px overlap are handled |
| `.check`, `.checkbox-row`, `.radio-row` | Top-aligned control + text row |
| `.checkbox-label`, `.radio-label` | Single-line label wrapping its own control |
| `.switch` + `.switch__slider` | The one toggle API. `.switch--sm` / `.switch--lg` resize it; `.switch--disabled` dims it. |

Validation: put `.is-invalid` on the control, or `.field-error-state` / `.form-group--error` / `.field-block--error` on the wrapper. `.was-validated` on the form activates `:invalid` styling after a submit attempt. There is deliberately no global `:valid` green — empty optional fields would show permanent success chrome.

Checkboxes, radios, range, colour and file inputs are all styled from the same tokens; `input[type="file"]` gets a dashed well plus a styled `::file-selector-button`.

#### 4.7 Table

```html
<div class="table-container">
  <table class="table">
    <thead>
      <tr>
        <th>Title</th>
        <th>Status</th>
        <th class="numeric">Views</th>
        <th class="col-shrink"></th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><span class="cell-title">Hello world</span></td>
        <td><span class="badge badge-success">Published</span></td>
        <td class="numeric">1,284</td>
        <td class="cell-actions">
          <button class="btn-icon btn-icon-sm btn-icon--edit" aria-label="Edit">…</button>
          <button class="btn-icon btn-icon-sm btn-icon--danger" aria-label="Delete">…</button>
        </td>
      </tr>
    </tbody>
  </table>
</div>
```

Density: header row 36px on a sunken band with an 11px uppercase label; body row 40px; `.table--compact` → 32px. Inside a `.card` (or `.card--flush`) the container drops its own border, radius and fill so you don't get a double border.

| Class | Effect |
|-------|--------|
| `.table-container` | Horizontal scroll viewport with panel chrome |
| `.cell-title` | Medium-weight primary text |
| `.cell-muted` | Secondary text |
| `.cell-numeric`, `td.numeric`, `th.numeric` | End-aligned, tabular figures |
| `.cell-actions` (alias `.row-actions`) | Right-aligned action group; dimmed to 55% until row hover or focus-within |
| `.col-shrink`, `.col-checkbox`, `.col-drag` | Column width helpers |
| `.table--compact`, `--bordered`, `--striped`, `--hover`, `--sticky` | Modifiers |
| `th[aria-sort]` / `.sortable` | Sortable header affordance |
| `tr.is-selected` / `[aria-selected="true"]` | `--surface-selected` fill |

`.entries-smart-table` (with `.smart-table`, `.table-viewport`, `.head-actions`) is a documented specialisation for the entry list, not a second table system. Its internals are not contracted.

#### 4.8 Badge

One badge system. Tone is always a soft tinted surface plus semantic text — never a saturated fill.

```html
<span class="badge">Draft</span>
<span class="badge badge-success">Published</span>
<span class="badge badge-warning">Scheduled</span>
<span class="badge badge-danger">Failed</span>
<span class="badge badge-info">Beta</span>
<span class="badge badge-accent">New</span>
<span class="badge badge-count u-numeric">12</span>
```

Tones: `badge-neutral` (default), `-success`, `-warning`, `-danger` (alias `-error`), `-info`, `-accent`, `-muted`. Sizes: default 22px, `.badge-sm` 18px, `.badge-lg` 26px. `.badge-count` makes it a numeric pill.

`.status-badge` and `.status-pill` are aliases of `.badge`, and semantic state classes are mapped for you: `.status-badge.published`, `.active`, `.pending`, `.scheduled`, `.draft`, `.unpublished`, `.inactive`, `.error`, `.failed`.

Related small components in the same file:

- `.status-indicator` / `.status-dot` (+ `--success`, `--warning`, `--danger`, `--info`) — an 8px inline state dot.
- `.chip` / `.tag` — a pill-shaped token, 28px, with `.chip--selected`, `.chip--interactive` and `.chip-remove`.
- `.notification-badge` — the count dot on an icon button.

#### 4.9 Callout

The one inline-message system.

```html
<div class="callout callout-warning">
  <svg class="callout-icon">…</svg>
  <div class="callout-body">
    <p class="callout-title">Storage is nearly full</p>
    <p>Delete unused media or upgrade your plan.</p>
  </div>
  <div class="callout-actions"><a class="btn btn-sm" href="#">Manage</a></div>
  <button class="callout-dismiss" aria-label="Dismiss">×</button>
</div>
```

Tones: `callout-info` (also the bare default), `-success`, `-warning`, `-danger` (alias `-error`), `-neutral`, `-accent`. Modifiers: `.callout--compact`, `.callout--block`, `.callout--banner` (full-bleed, for grace-period and alpha notices).

`.message`, `.alert`, `.error-message`, `.success-message`, `.warning-message`, `.info-message` and their `-info`/`-success`/`-warning`/`-error` variants are aliases — they render identically. Use `.callout` in new markup.

#### 4.10 Empty, loading and error states

Every async region needs all three. A region with only a happy path is incomplete.

```html
<div class="empty-state">
  <div class="empty-state-icon">…</div>
  <p class="empty-state-title">No entries yet</p>
  <p class="empty-state-text">Create your first entry to get started.</p>
  <div class="empty-state-actions">
    <button class="btn btn-primary">New entry</button>
  </div>
</div>

<div class="loading-state">Loading entries…</div>

<div class="error-state">
  <div class="empty-state-icon">…</div>
  <p class="empty-state-title">Couldn't load entries</p>
  <p class="empty-state-text">Check your connection and try again.</p>
  <div class="empty-state-actions"><button class="btn">Retry</button></div>
</div>
```

`.loading-state` renders a spinner before its content; `.error-state` (alias `.empty-state--error`) recolours the icon tile and title to the danger tone. Modifiers: `.empty-state--compact` (inside small panels and table bodies), `.empty-state--inline` (single row, for list footers). `.coming-soon` is an alias of the base pattern. The BEM spellings `.empty-state__icon`, `__title`, `__description`, `__action` also work — templates emit both.

#### 4.11 Skeleton, spinner, progress

```html
<div class="skeleton skeleton-title"></div>
<div class="skeleton skeleton-text"></div>
<div class="skeleton skeleton-row"></div>
<div class="skeleton skeleton-block"></div>

<span class="spinner"></span>
<span class="spinner spinner-lg"></span>

<div class="progress"><div class="progress-bar" style="width: 62%"></div></div>
```

`.skeleton` is a sunken block with a sweeping shimmer. `.spinner` borrows the shared `u-spin` keyframe. The progress bar's inline `width` is one of the few legitimate uses of an inline style — it's a computed value.

#### 4.12 Avatar, divider

```html
<span class="avatar">JD</span>
<img class="avatar avatar-sm" src="…" alt="">
<span class="avatar avatar-lg">JD</span>

<hr class="divider">
<div class="divider-labelled">Or</div>
```

`.avatar` is a mint-filled circle with `--ink-on-accent` initials at `--control-md`; `.avatar-sm` is 28px, `.avatar-lg` 48px.

#### 4.13 Secondary navigation — tabs and side nav

There is **one** secondary-navigation concept, in two orientations: a padded, `--radius-sm` item that fills with `--surface-selected` when active — the same active-state fill the main sidebar uses for itself. `.tabs`/`.tab` is the horizontal orientation; `.side-nav`/`.side-nav-item` is the vertical one. They share one selector list for hover, active and focus, so they cannot drift apart the way two independently maintained nav dialects would. This is distinct from the main sidebar (§5, and the note at the end of this section), which is its own locked, unique pattern — don't reach for `.side-nav` there.

**Horizontal — `.tabs` / `.tab`.** The active tab is a **filled pill**, not an underline:

```html
<div class="tabs-root">
  <div class="tabs" role="tablist">
    <button class="tab" role="tab" aria-selected="true">General</button>
    <button class="tab" role="tab" aria-selected="false">
      Security <span class="tab-count">3</span>
    </button>
  </div>
</div>

<div class="tab-panel active" role="tabpanel">
  <div class="u-stack-6"><!-- cards --></div>
</div>
```

Tabs sit `--control-lg` high on a hairline rail (`border-block-end: 1px solid var(--border-subtle)`). The active tab gets `--surface-selected` fill and `--text-primary`; its icon (if any) takes `--accent-text`. `.tab-panel` is canonical; `.tab-content` and `.tab-pane` are kept because templates and JS use both. Show a panel with `.active` or `aria-hidden="false"`.

The responsive overflow behaviour (desktop wrap → tablet scroll + "More" → mobile `<select>` picker) is driven by `admin/js/tabs-v2.js` and its class contract: `.tabs-v2-root`, `.tabs-v2--desktop`, `.tabs-v2--scroll`, `.tabs-v2--picker`, `.tabs-v2-picker-wrap`, `.tabs-v2-more-wrap`, `.tabs-v2-more-menu`, `.tabs-v2-more-item`. You don't write those by hand — add `.tabs-v2-root` and let the script manage the rest.

**Vertical — `.side-nav` / `.side-nav-item`, with optional sub-nav.** For doc- or tree-style vertical navigation (a topic list, a folder tree):

```html
<nav class="side-nav">
  <div class="side-nav-group">
    <p class="side-nav-group-title">Getting started</p>
    <a class="side-nav-item active" href="…">Overview</a>
    <a class="side-nav-item" href="…">Installation</a>
  </div>
  <div class="side-nav-group">
    <a class="side-nav-item" aria-expanded="true">
      Content
      <span class="side-nav-chevron">…</span>
    </a>
    <div class="side-nav-sub">
      <a class="side-nav-sub-item active" href="…">Collections</a>
      <a class="side-nav-sub-item" href="…">Entries</a>
    </div>
  </div>
</nav>
```

| Part | Notes |
|------|-------|
| `.side-nav` | Column of groups, `gap: --space-4` |
| `.side-nav-group` | One labelled (or unlabelled) cluster of items |
| `.side-nav-group-title` | `.u-label`-style uppercase micro-label above a group |
| `.side-nav-item` | Full-width item, `--control-md` high, left-aligned. Same active/hover/focus rule as `.tab` |
| `.side-nav-sub` | Indented nested list under a parent item, with a leading rule |
| `.side-nav-sub-item` | `--control-sm` high, `--text-sm` — a step down from its parent |
| `.side-nav-chevron` | Rotates 90° when the parent carries `aria-expanded="true"` |

Wrap the item's own text in `.side-nav-label` if it needs to truncate (`overflow: hidden; text-overflow: ellipsis`) rather than wrap or overflow the item.

**Not the same thing as the sidebar.** The main app sidebar (`.nav-links` / `.nav-link`, layout/sidebar.css) is a separate, deliberately unique, locked pattern — flush full-width rows inside their own rounded box, with a leading accent rail on the active item. Don't migrate the sidebar to `.side-nav`, and don't invent a third vertical-nav pattern for a new secondary surface — reach for `.side-nav`.

For mode switches (view toggles, assist/source), use the segmented control instead of a third tab dialect:

```html
<div class="segmented" role="group">
  <button class="segmented-item active" aria-pressed="true">Grid</button>
  <button class="segmented-item" aria-pressed="false">List</button>
</div>
```

#### 4.14 Modal

```html
<div class="modal" id="role-modal">
  <div class="modal-content modal--md">
    <header class="modal-header">
      <h2 class="modal-title">Add role</h2>
      <button class="modal-close" aria-label="Close">&times;</button>
    </header>
    <div class="modal-body u-stack-4">
      <!-- fields -->
    </div>
    <footer class="modal-footer">
      <button class="btn btn-secondary">Cancel</button>
      <button class="btn btn-primary">Save</button>
    </footer>
  </div>
</div>
```

The root `.modal` is both positioner and scrim. Open it by adding `.show`, `.active`, `.is-open` or the `open` attribute. `.modal-backdrop` works as a root too (JS-built dialogs use it) and as an optional scrim child inside a `.modal`. `.modal-dialog` is an accepted alias of `.modal-content`.

Sizes on the panel: `.modal--sm` 24rem · `.modal--md` 34rem (default) · `.modal--lg` 48rem · `.modal--xl` 64rem · `.modal--full`. `.modal-footer--split` pushes a destructive action to the left. Entrance is an 8px rise plus fade; below 640px the dialog becomes a bottom sheet automatically.

Keyboard requirements are yours: trap focus inside the dialog, restore it on close, and close on Escape.

#### 4.15 Sheet and drawer

A **sheet** is anchored to the bottom edge — the standard pattern for filters on a list page.

```html
<div class="sheet" id="filters">
  <div class="sheet-backdrop"></div>
  <div class="sheet-panel">
    <header class="sheet-header"><h2 class="sheet-title">Filters</h2></header>
    <div class="sheet-body u-stack-4">…</div>
    <footer class="sheet-footer"><button class="btn btn-primary">Apply</button></footer>
  </div>
</div>
```

Open with `.open`, `.show` or `.active`. Add `.sheet--mobile-only` when the same controls render inline at 640px and up.

A **drawer** slides in from the trailing edge and stays open:

```html
<aside class="drawer open">
  <header class="drawer-header">…</header>
  <div class="drawer-body">…</div>
  <footer class="drawer-footer">…</footer>
</aside>
```

#### 4.16 Dropdown, custom select, popover, tooltip

```html
<div class="dropdown">
  <button class="btn dropdown-trigger" aria-haspopup="true" aria-expanded="false">
    Actions <span class="dropdown-chevron">…</span>
  </button>
  <div class="dropdown-menu">
    <div class="dropdown-header">Manage</div>
    <button class="dropdown-item">
      <span class="dropdown-item-icon">…</span>
      <span class="dropdown-item-label">Duplicate</span>
      <span class="dropdown-item-meta">⌘D</span>
    </button>
    <hr class="dropdown-divider">
    <button class="dropdown-item dropdown-item-danger">
      <span class="dropdown-item-icon">…</span>
      <span class="dropdown-item-label">Delete</span>
    </button>
  </div>
</div>
```

Open the menu with `.show`/`.active`/`.open` on the menu, or `.open`/`.active` on the `.dropdown` wrapper. Menus sit at `--z-popover`, above modals, so a menu inside a dialog works. Alignment: `.dropdown-menu-right` (alias `.dropdown-menu--right`) and `.dropdown-menu--up`. Empty result: `.dropdown-empty`. The chevron rotates automatically when the trigger is `aria-expanded="true"`.

`.custom-select` / `.custom-select-trigger` / `.custom-select-menu` / `.custom-select-item` is the same surface shaped like a form control, for when a native `<select>` can't render the option content.

`.popover` is a generic anchored surface (raised fill, `--elevation-2`, `--z-popover`). `.tooltip` is a small inverse-fill label at `--z-tooltip` with `pointer-events: none`.

#### 4.17 Toasts and confirm dialogs

Don't build these — call the shared module.

```javascript
BirklyFeedback.toast('Settings saved', 'success');   // success | error | warning | info
const ok = await BirklyFeedback.confirm('Delete this role?');
const name = await BirklyFeedback.prompt('New collection name');
```

The toast region is fixed bottom-trailing at `--z-toast`; the confirm and prompt dialogs are ordinary `.modal-backdrop` + `.modal-content` modals, so they pick up the shared scrim, radius, elevation and entrance. Never use `alert()` or `confirm()`.

#### 4.18 Prose and rich text

```html
<article class="prose">
  <h2>Heading</h2>
  <p>Long-form documentation copy.</p>
</article>

<div class="rich-text"><!-- rendered markdown inside a UI-sized container --></div>
```

`.prose` is the **only** place `--text-md` (16px) and heading margins are used; it's capped at `--measure-md` and restores list markers, blockquote rules and table styling. `.rich-text` is the same idea tuned for inline UI contexts (chat bubbles, small rendered-markdown fragments): UI-sized type, no measure cap, no heading margins.

#### 4.19 Icons

Icons come from the registry, not from pasted SVG.

```php
<?= birkly_icon('pencil-simple', ['size' => 16, 'class' => 'nav-icon']) ?>
```

```javascript
Birkly.icon('plus', { size: 16 });
Birkly.iconImg('gear', { size: 16, alt: '' });
Birkly.initIcons(document);          // hydrates [data-icon="name"]
```

Output is inline SVG with class `.birkly-icon`, a 24×24 viewBox and `stroke="currentColor"` — so an icon inherits the colour of whatever it sits in. Size modifiers: `.birkly-icon--sm` 14px · `--md` 16px · `--lg` 20px · `--xl` 24px. Icons inside `.btn`, `.btn-sm`, `.badge`, `.tab`, `.dropdown-item-icon` and the topbar are sized by those components already.

---

### 5. Page chrome recipe

Every admin page has the same shape.

#### 5.1 The breadcrumb-as-title pattern

This is the part people get wrong. A page emits `.page-header > .page-header-left > .breadcrumbs`, and:

- The **last** breadcrumb segment is `<span class="current">` and **is** the page title.
- `moveBreadcrumbsToTopbar()` hoists the whole breadcrumb into `#admin-topbar-breadcrumbs` and collapses the emptied source row with `.page-header--hoisted-empty`.
- The title size is declared once, in the topbar slot: `--text-xl`/500.
- **Pages must not render an `<h1>`.** Section titles are `<h2>` inside `.card-header` or `.section-header`.
- Separators are `<span class="separator">›</span>` — always `›`, never `/`.
- `.page-header-actions` must be present-and-empty or absent. **No CTAs go in it** — the hoist empties and hides that row. Page actions belong in `.main-actions-bar`; global chrome actions belong in `.admin-topbar-actions`.
- `breadcrumb-truncate.js` adds `.breadcrumb-segment` and `.breadcrumb-ellipsis` for narrow screens. Don't emit those yourself.

Exactly one breadcrumb source per page.

#### 5.2 The action bar

```html
<div class="main-actions-bar">
  <div class="main-actions-left">
    <a class="btn btn-primary" href="…">New entry</a>
  </div>
  <div class="main-actions-right">
    <input class="input input-search" type="search" placeholder="Search">
  </div>
</div>
```

The slot rule is fixed and consistent on every page:

| Slot | Contents |
|------|----------|
| `.main-actions-left` | Primary and destructive actions |
| `.main-actions-right` | Search, filters, view switches, sort |

`.main-actions-bar--sticky` is the sticky variant — one per page, at `--z-sticky`, flat while inline and `--elevation-1` once `admin/js/sticky-save-bar.js` adds `.is-stuck`. It also supports a `.main-actions-tools` group and a `.main-actions-message` status region.

#### 5.3 Complete page skeleton

```html
<div class="page" data-page="settings">

  <!-- 1. Title source. Hoisted to the topbar. Never an <h1>. -->
  <div class="page-header">
    <div class="page-header-left">
      <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="?page=content">Content</a>
        <span class="separator">›</span>
        <span class="current">Blog posts</span>
      </nav>
    </div>
    <div class="page-header-actions"></div>
  </div>

  <!-- 2. Optional toolbar. -->
  <div class="main-actions-bar">
    <div class="main-actions-left">
      <button class="btn btn-primary">New entry</button>
    </div>
    <div class="main-actions-right">
      <input class="input input-search" type="search" placeholder="Search">
    </div>
  </div>

  <!-- 3. Body. u-stack-6 owns ALL vertical rhythm between sections. -->
  <div class="page-body u-stack-6">

    <section class="card">
      <header class="card-header">
        <h2 class="card-title">General</h2>
        <div class="card-actions"><button class="btn btn-sm">Edit</button></div>
      </header>
      <div class="card-body u-stack-4">
        <div class="field">
          <label class="field-label" for="site-name">Site name</label>
          <input class="input" id="site-name" type="text">
          <p class="field-help">Shown in the browser tab.</p>
        </div>
      </div>
    </section>

    <section class="card card--flush">
      <header class="card-header"><h2 class="card-title">Recent entries</h2></header>
      <div class="card-body">
        <div class="table-container">
          <table class="table">…</table>
        </div>
      </div>
    </section>

  </div>
</div>
```

Supporting layout classes:

| Class | Use |
|-------|-----|
| `.page` (alias `.page-container`) | Page root, capped at `--page-max` |
| `.page--narrow` | Caps at `--page-max-narrow` (880px) — account, auth, onboarding |
| `.page-body` | Column flex with `--space-6` gap. Equivalent to `u-stack-6`; use either. |
| `.plugin-page` | The plugin page root. Same chrome as a core page. |
| `.section` + `.section__header` + `.section__body` | A titled block that isn't a card |
| `.section-header`, `.section-title`, `.section-description` | Title row and description inside a page or card |
| `.settings-stack` | Vertical stack of panels at `--space-6` |
| `.editor-layout` + `.editor-main` + `.editor-sidebar` | Main-plus-aside editor grid |
| `.content-wrapper` | Width cap at `--page-max` |
| `.back-button` | Back affordance with a built-in arrow |
| `.grid`, `.grid-cols-1/2/3/4`, `.grid-cols-auto-fit`, `.grid-cols-auto-fill` | Thin aliases over the `u-grid*` helpers, kept for existing markup |

#### 5.4 Page variants

| Variant | Shape |
|---------|-------|
| **List page** | Toolbar + one `.card` containing a `.table` or grid. Empty, loading and error states required. |
| **Editor page** | `.editor-layout` (or `u-sidebar-layout`): a stack of `.card`s plus an aside; `.main-actions-bar--sticky` for save. |
| **Tabbed page** | Toolbar + `.tabs` + one `.tab-panel` per tab, each a `u-stack-6` of `.card`s. |
| **Dashboard** | `u-grid-auto` of `.card` widgets; metrics are `.stat`, not bespoke tiles. |
| **Single column** | `.page--narrow` (or `u-max-w-page-narrow`) + `u-stack-6` of `.card`s. |
| **Plugin page** | Identical, with `.plugin-page` on the root and breadcrumbs from `{plugin_page_header}`. |

#### 5.5 Composition rules

1. Vertical rhythm lives on the parent (`u-stack-*` / `.page-body`). Sections and headings never set their own margins.
2. Panels nest at most one level. A settings tab is a `u-stack-6` of sibling `.card`s, not a card containing cards.
3. Prose and help text are capped with `u-max-w-measure-md`; forms with `u-max-w-measure-lg`.
4. Empty, loading and error states are mandatory for every async region.
5. Hover, focus-visible, active, disabled and selected must all be defined for every interactive element.
6. A page ships zero page-specific CSS by default. If you think you need some, you've probably found a framework gap worth filing.

---

### 6. Dark mode

Dark mode is a **token remap and nothing else**.

- `admin/js/theme.js` (`Birkly.Theme`) sets `data-theme` on `<html>` and `<body>`. Values: `light`, `dark`, `system`.
- `core/variables.css` defines the dark values once as `--dk-*` custom properties, then remaps the semantic tokens under `[data-theme="dark"]` and under `[data-theme="system"]` inside `@media (prefers-color-scheme: dark)`.
- `color-scheme: dark` is set so native controls and scrollbars follow.

**If a component needs a `[data-theme="dark"]` rule, that is a token bug, not a dark-mode requirement.** Find the hardcoded value or the token that's pointing at a primitive instead of a semantic role, and fix that instead. CI fails any `data-theme` selector in admin CSS outside the token file and one documented exception: the topbar's radial shadow cast in `layout/header.css`, which is a gradient rather than a colour and genuinely needs a second definition.

> **Never put a literal `data-theme="light"`, `data-theme="dark"` or `data-theme="system"` attribute on any element other than the real theme root (`<html>`/`<body>`).** Dark mode's CSS is scoped entirely by the attribute selector `[data-theme="dark"]` — and a CSS attribute selector matches **any** element carrying that attribute, not just the theme root. A theme-switcher option button, a preview swatch, or any other per-element "which theme is this for" marker that happens to be named `data-theme="dark"` will locally and silently inherit dark-surface tokens, regardless of what theme the page is actually in — a light-grey-on-white-looking control in the middle of a light page, with no CSS rule anywhere that looks wrong. If you need a similar per-element data attribute for your own purposes, name it something else entirely — `data-theme-option`, `data-theme-preview`, anything that isn't the bare `data-theme` the dark-mode system watches. `theme.js`'s two `setAttribute('data-theme', …)` calls on `documentElement`/`body` should be the only places that literal attribute is ever set.

Test every UI change in both themes.

---

### 7. Do and don't

| Don't | Do | Why |
|-------|-----|-----|
| Write a raw hex value in admin CSS | Use a token from `core/variables.css` | Hardcoded colour is broken in dark mode. CI blocks hex outside the token file. |
| Use `--spacing-*`, `--border-radius-*`, `--gray-*`, `--shadow-sm/md/lg/xl`, `--font-size-*`, `--primary-N`, `--bg-*`, `--border-light/medium/color`, `--transition-*` | Use the canonical tokens | These names are retired and CI fails admin CSS that uses one. Most survive as aliases in `compat-tokens.css` for plugin compatibility only — but `--spacing-*` and `--border-radius-*` are **not** aliased and resolve to nothing anywhere. |
| `color: var(--accent)` or `var(--primary-700)` | `color: var(--accent-text)` | 1.54:1 and 2.95:1 respectively. Both fail AA. CI blocks them. |
| White text on a mint fill | `--ink-on-accent` | 10.4:1 instead of ~1.4:1. |
| A saturated `--success-solid` status pill | `--success-surface` + `--success-fg`, i.e. `.badge badge-success` | Saturated green next to the mint primary reads muddy. |
| Invent a blue | `--info-*` | There is exactly one blue family in the admin. |
| `transition: all` | Enumerate the properties you animate | Blocked in CI; `all` animates layout properties by accident. |
| `transform: scale(1.02)` or `translateY(-1px)` on **hover** | Change `background-color` or `border-color` | No hover scale, no hover translate, max 8px travel anywhere. The only exceptions are the button `:active` press and the `.btn-icon` glyph nudge — both scoped, both documented in §4.5, neither is a hover-on-the-box effect. |
| A `box-shadow` on a static card | `--elevation-0` (the default) | Shadow means "floats above the page". Borders do the structural work. |
| A second `.card` nested inside a `.card-body` | `.card-section`, more `u-stack-*` spacing, or a plain `.divider` | Box-in-box. Once content is already inside a panel, its children compose with spacing and a divider — not more boxes. See §4.1. |
| A literal `data-theme="light"` / `"dark"` / `"system"` attribute on anything but the real theme root | A differently-named attribute, e.g. `data-theme-option`, `data-theme-preview` | Dark mode is scoped by the exact selector `[data-theme="dark"]` on `<html>`/`<body>`. Any *other* element carrying that attribute silently inherits dark-surface tokens regardless of the page's actual theme. See §6. |
| A raw `z-index: 9999` | A `--z-*` token | Blocked in CI. The scale already has a slot for what you're doing. |
| A new breakpoint like `900px` | One of the five `--bp-*` values | Blocked in CI. Prefer `min-width`. |
| `style="display: none"` | The `hidden` attribute, or `u-hidden` | `[hidden]` is declared after every component so it actually wins. |
| A page `<h1>` | The breadcrumb `.current` segment | The title is hoisted into the topbar; an `<h1>` duplicates it. |
| A CTA in `.page-header-actions` | `.main-actions-bar > .main-actions-left` | That row is emptied and hidden by the hoist. |
| A 40px button in the page body | `--control-md` (36px), i.e. the default `.btn` | 40px is the chrome row only. |
| `!important` in a helper or component | Correct specificity, or load order | Helpers load last; that's the mechanism. The only allowlisted `!important` is the global reduced-motion block. |
| Margins on headings for spacing | `u-stack-*` on the parent | Heading margins fight every gap-based layout. |
| A new badge / card / tab / modal / empty-state system | The contracted component | One name per concept. |
| 700 weight, or uppercase above 11px | 400 / 500 / 600, and `.u-label` for uppercase | Nothing in the admin shouts. |
| A `[data-theme="dark"]` rule in a component | Fix the token | Dark mode is a remap. CI blocks it. |

## Related

- [Plugin admin styling](../06-plugin-economy/plugin-admin-styling.md) — adoption tiers, the public class contract, and a complete worked plugin page
- [Admin design system](design-system.md) — short overview and the icon/theme entry points
- [Admin chrome and action zones](admin-chrome-action-zones.md) — where each kind of action belongs
- [Admin appearance](admin-appearance.md) — the user-facing theme switcher
- [Plugin navigation icon](../06-plugin-economy/plugin-navigation-icon.md)
- [Plugin development](../06-plugin-economy/plugin-development.md)
