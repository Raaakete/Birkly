# Admin design language

## Summary

The Birkly admin is drawn in **Birch**, one design language shared by core pages and plugin pages. Every surface on a screen plays one of **eight roles**: canvas, frame, slab, well, dashed, signal, float and menu. **Four questions** decide which role a thing gets. The roles, a small set of tokens and a component kit do all the styling, so a page ships no CSS of its own.

Three colours carry meaning:

- Mint marks the one main action per view.
- Blue means on, selected, link or focus.
- Ink (the text colour) means "you are here".

Icons are 13 × 13 pixel art. Each one plays a short, stepped gesture when you hover or focus the control around it. This page is the full reference: roles, tokens, density, components, shell, icons, motion, and the rules CI enforces.

## Beginner

### What the admin looks like

Open any admin screen and you see the same few things:

- **A warm grey page** (the *canvas*). Page titles, tabs, filters and toolbars sit straight on it with no box around them.
- **White blocks** (the *slabs*). Each one is one thing: a row in a list, an entry field, a setting, a person, a card in a grid. Slabs have no outline and no shadow; they are set apart by gaps: 4 px when stacked, 8 px in a grid.
- **Thin outlines around a group** (the *frames*). A frame has a title in its top-left corner and holds slabs. Frames are 24 px apart.
- **Grey insets** (the *wells*) inside a slab: a count, a chip, a thumbnail, the track of a segmented control.
- **Dashed outlines** for what is not there yet: an empty list, a drop zone, "Add another field".
- **Soft coloured blocks** (the *signals*) for something that needs you.
- **Floating panels** (the *floats* and *menus*): dialogs, side sheets, dropdowns and tooltips. These are the only things with a shadow.

### The four questions

Whenever you place something on a screen, ask these in order and stop at the first yes:

1. **Does it float and go away?** Then it is a *float* (dialog, sheet) or a *menu* (dropdown, popover).
2. **Is it one thing?** Then it is a *slab*.
3. **Is it a named group next to other groups?** Then it is a *frame*.
4. **Anything else** sits on the *canvas*.

Two more cover states: something that **is not there yet** is *dashed*, and something that **needs you** is a *signal*.

### Three colours that mean something

| Colour | Means | You see it on |
|--------|-------|---------------|
| **Mint** | The one main action | The first button of the page bar (New entry, Save) and the footer action of a dialog. **One per view.** |
| **Blue** | On, selected, link, focus | Switches that are on, checked boxes, the ring around a selected card, links, the focus ring |
| **Ink** | You are here | The bar beside the current sidebar item, the current tab's underline, the last breadcrumb |

Status colours (green, amber, red, blue) appear only inside signals and small status badges. Nothing else on the page is coloured.

### Where the page title comes from

Admin pages have no `<h1>` of their own. Each page emits a breadcrumb trail, and its last segment *is* the page title. The admin moves the whole trail into the top bar, so "Settings › Roles" appears once, at the top of the window. On a phone the breadcrumb truncates from the left, so the current page always stays readable.

### Light, dark and three densities

The theme switcher (light, dark or auto) is in the top bar, or in the drawer on a phone. Every colour is a token that is remapped for dark, so nothing needs its own dark rule.

**Account → Display** offers three densities:

- **Compact** has 13 px text and tight rows, for long lists.
- **Comfortable** is the default, with 14 px text.
- **Large** has 16 px text and taller controls.

Icons stay 13 px in all three.

### Icons that move

Hover a sidebar item or a button and its icon plays a gesture of a few frames: the bell rings, the trash lid lifts, the refresh arrow turns in quarter steps, the check mark draws itself. Gestures step from frame to frame instead of sliding, so the pixel grid never blurs. They play once per hover, and not at all when your system asks for reduced motion.

## Advanced Users

### 1. Source of truth and load order

| Layer | File |
|-------|------|
| Tokens (the only file with colour literals) | `admin/css/core/variables.css` |
| Density overrides | `admin/css/core/density.css` (`body[data-density="compact" \| "large"]`) |
| Reset, focus, the one reduced-motion block | `admin/css/core/reset.css` |
| Role map → generated role CSS | `admin/css/birch/roles.json` → `birch/roles.generated.css` (`php scripts/build-birch-roles.php`) |
| Component kit | `admin/css/birch/kit.css` |
| Icon gestures and micro-interactions | `admin/css/birch/motion.css` |
| Icon registry (grids, gestures, aliases) | `core/icon_data.php` → `admin/js/icon.js`, `admin/assets/icons/*.svg` (`php scripts/build-icons.php`) |
| P56 token names (temporary) | `admin/css/core/migration-aliases.css`, `admin/css/core/compat-tokens.css` |
| Layout helpers | `admin/css/core/helpers.css` (`u-stack-*`, `u-row`, `u-cluster`, `u-grid-*`, `u-split`, `u-sr-only`) |
| Import hub | `admin/css/style.css` |

`style.css` imports the tokens first and then components, pages and helpers. `birch/kit.css` and `birch/motion.css` come **last**, in that order. Every admin `<body>` carries `data-birch`, and the kit and role rules are scoped to `[data-birch]`.

Build tools regenerate the role CSS and the icon assets. Never edit `roles.generated.css`, `admin/js/icon.js`'s icon tables or `admin/assets/icons/*.svg` by hand; CI runs both builders with `--check`.

### 2. Roles

| Role | Class | Look | Holds |
|------|-------|------|-------|
| Canvas | none (the page) | `--bg` | Page title, tabs, filters, toolbars, column headers, counts, pagination, help |
| Frame | `.frame` | No fill, 1 px `--line`, 6 px radius, 20 px padding; `.frame-head` with `.frame-title` (`title-l`) and optional `.frame-text` (`small`, `--text-3`) | Slabs, 4 px apart |
| Slab | `.slab` | `--surface`, no line, no shadow, 6 px radius, 16 px padding | Wells and hairlines only |
| Well | `.well` | `--surface-2`, no line, 6 px radius (3 px when tiny) | Content only |
| Dashed | `.dashed` | 1 px dashed `--line-strong`, no fill, 6 px radius; centred `--text-3` text | Empty states, drop zones, add-another, advice |
| Signal | `.signal` | A status `-soft` fill, no line, 6 px radius, status icon, action on the right as a secondary button | One message |
| Float | `.float` (`.dialog`, `.sheet`) | `--bg` body, sheet shadow, 6 px radius (a sheet is square on the edge it slides from) | Headings and slabs, never frames |
| Menu | `.menu` (`.popover`) | `--surface`, menu shadow, 6 px radius, 4 px padding | Menu items, never a form |

The eight role classes are generic. `roles.json` also gives named components their role, so `.setting-row`, `.list-row`, `.collection-card`, `.plugin-card`, `.metric`, `.table-row`, `.field-def` and the 32 others are slabs, and `.count`, `.chip`, `.segmented`, `.prefix` and `.thumb` are wells. A named component gets its fill, line, radius and shadow from its role, never from its own rule. CI rejects a `border` on any of the 39 slab selectors.

**A field takes the opposite of what it sits on.** On the canvas or a float, an input is `--surface` (white). Inside a slab it is `--surface-2` at rest, `--surface-3` on hover, and `--surface` with a 1 px accent border on focus.

**Gaps, not borders.** Stacked slabs are 4 px apart, grid cards 8 px, frames and sections 24 px. A hairline only splits one slab into parts, such as label and value.

**Selection is a ring.** A selected slab gets `box-shadow: 0 0 0 2px var(--accent)`; a selected table row also gets the `--accent-soft` fill.

### 3. Tokens

All tokens live in `core/variables.css`. Colours are oklch; the hex in each comment is the value it round-trips to.

#### 3.1 Colour

| Token | Light | Dark | Use |
|-------|-------|------|-----|
| `--bg` / `--bg-side` | `#F5F4F2` | `#0F1112` | Canvas; behind the sidebar panels |
| `--surface` | `#FEFEFD` | `#191A1C` | Slab, menu, field on the canvas |
| `--surface-2` | `#EFEFED` | `#202224` | Well; hover inside a slab; field inside a slab |
| `--surface-3` | `#E7E6E3` | `#282A2D` | Pressed well, switch track |
| `--line` | `#E4E4E1` | `#282A2D` | Hairline: frame, split inside a slab |
| `--line-strong` | `#D1D0CD` | `#3E4044` | Secondary button edge, dashed |
| `--text` | `#161719` | `#F0F0EE` | Primary text, **ink** (17.8:1 · 15.3:1) |
| `--text-2` | `#46484A` | `#B9BBBE` | Secondary text |
| `--text-3` | `#67696C` | `#909296` | Meta, placeholders. **Never on `--surface-3`** (4.4:1 in light) |
| `--accent` / `--accent-hover` | `#3767C4` / `#2855AD` | `#77A4F6` / `#8DB7FF` | Blue: on, selected ring, focus |
| `--accent-soft` / `--accent-line` | `#E2EDFF` / `#AFC8F6` | `#1D2D4C` / `#395487` | Selected row fill; soft selected ring |
| `--accent-text` | `#2855AD` | `#94BBFF` | Links and blue text |
| `--on-accent` | `#FAFCFF` | `#0A111F` | Text on an accent fill |
| `--mint` / `--mint-hover` | `#7DE8D6` / `#65DBC9` | `#6DDFCC` / `#89EDDB` | The one main action |
| `--on-mint` | `#05201C` | `#05201C` | Text and icon on mint |
| `--success` / `--success-soft` | `#1C6C31` / `#DBF7DE` | `#73D885` / `#19351E` | |
| `--warning` / `--warning-soft` | `#7C4B02` / `#FFF1C8` | `#F8C153` / `#463104` | |
| `--danger` / `--danger-soft` | `#B02A2D` / `#FFE9E6` | `#FF8880` / `#53211F` | |
| `--info` / `--info-soft` | `#155AA7` / `#E3F2FF` | `#8ABEFF` / `#183150` | |
| `--on-status` | `#FEFEFD` | `#0F1112` | Text on a solid status fill |
| `--ink-float` / `--on-ink-float` | `#111214` / `#FAFAF8` | `#F1F1EF` / `#111214` | Tooltip and toast (they invert in dark) |
| `--scrim` | `rgb(22 23 25 / .32)` | `rgb(0 0 0 / .56)` | Behind dialogs and sheets |
| `--scroll-shade` | `rgb(32 34 29 / .12)` | `rgb(0 0 0 / .5)` | Edge shade on horizontally scrolled tables |

Rules:

- **Mint and blue are fills, not text.** Text on mint is `--on-mint`; blue text is `--accent-text`. CI rejects `color: var(--accent)` and `color: var(--mint)`.
- **Status hues only inside signals and small badges.**
- **Hairlines split; they never identify a control.** A field is recognisable by its fill and label, and focus is always the 2 px accent ring.
- Every text token clears 4.5:1 against every surface it is allowed on, in both themes. CI computes this matrix, with the one `--text-3` on `--surface-3` exception encoded.

Third-party brand colours (the Google result preview in Discovery) are tokens too: `--serp-title`, `--serp-url`, `--serp-desc`.

#### 3.2 Type

System stacks only: `--font-sans` (`system-ui, -apple-system, "Segoe UI", Roboto, sans-serif`) and `--font-mono` (`ui-monospace, "SF Mono", Menlo, Consolas, monospace`). Each step is a pair of tokens, `--size-*` and `--lh-*`, with the weight from `--weight-regular` (400), `--weight-medium` (500) or `--weight-semibold` (600).

| Step | Comfortable | Weight | Use |
|------|-------------|--------|-----|
| `number` | 28 / 32 | 600, −2.5 % | Metric values; always `tabular-nums` |
| `page-title` | 19 / 26 | 600, −1 % | Breadcrumb current item |
| `title-l` | 17 / 24 | 600 | Dialog, sheet and frame titles |
| `slab-title` | 15 / 22 | 500 | Title of a slab (a person, an order, a plugin card) |
| `title` | 14 / 20 | 600 | Row labels, card titles, tab labels, buttons (500) |
| `body` | 14 / 21 | 400 | Default |
| `small` | 13 / 18 | 400 | Help text, meta, field labels (500) |
| `caption` | 12 / 16 | 400 | Timestamps, table column headers |
| `mono` | 13 / 20 | 400 | IDs, slugs, API names, code |

Sentence case everywhere: no uppercase labels and no letter-spaced overlines in the admin. Numbers in metrics, tables and money use `font-variant-numeric: tabular-nums`. **Whole pixels only**: no half-pixel sizes, line heights, strokes, rings or offsets.

#### 3.3 Space

| Token | px | Use |
|-------|----|-----|
| `--s-1` | 4 | Between stacked slabs; tool gaps |
| `--s-2` | 8 | Between grid cards; toolbar and sidebar-panel gaps |
| `--s-3` | 12 | Icon to label; control padding |
| `--s-4` | 16 | Slab padding; 12-column grid gap; field gap |
| `--s-5` | 20 | Inside a frame; dialog header |
| `--s-6` | 24 | Between frames and sections; `--gutter` (page gutter) |

App UI stops at 24 px.

#### 3.4 Radius

| Token | px | Applies to |
|-------|----|-----------|
| `--r` | 6 | Everything with a corner |
| `--r-inset` | 3 | Only inside a 6 px surface: switch thumb, unread badge, key cap |
| `--r-pixel` | 0 | Status dots, meter segments, the sidebar ink bar and rows |

No pills. Chips and avatars are 6 px rounded squares.

#### 3.5 Elevation

Only floats cast shadows. Slabs, frames, tables and sidebar panels are flat.

| Token | Use |
|-------|-----|
| `--shadow-float-2` | Tooltip, toast |
| `--shadow-menu` | Menu, popover (a 1 px edge ring plus a soft drop) |
| `--shadow-sheet` | Dialog, sheet |

#### 3.6 Motion

| Token | Value | Use |
|-------|-------|-----|
| `--dur-1` | 120 ms | Hover, press, colour |
| `--dur-2` | 200 ms | Reveals, focus rings, tooltips, menus opening |
| `--dur-3` | 320 ms | Sidebar collapse, tabs, launcher mount |
| `--dur-4` | 640 ms | Bars and meters filling, in `steps()` |
| `--dur-px` | 1 s | One icon gesture budget: eight frames |
| `--dur-frame` | `calc(var(--dur-px) / 8)` = 125 ms | One frame of a gesture or stepped animation |
| `--ease` | `cubic-bezier(.2, .7, .2, 1)` | Everything that is not stepped |

Durations are always tokens. CI rejects a literal duration in admin CSS (`0s` and `0ms` are allowed).

#### 3.7 Focus

`outline: var(--focus-outline)` (2 px solid `--accent`) with `outline-offset: var(--focus-outline-offset)` (1 px), on `:focus-visible`. Inputs also switch to `--surface` with a 1 px accent border. Never remove a focus outline without an equal replacement.

#### 3.8 Sizes and density

Density is stored as `ui_preferences.density` (`compact`, `default`, `large`), mirrored to `localStorage.birkly_ui_density`, and set as `data-density` on `<body>`. `default` is labelled **Comfortable**. Write `var(--control)`, `var(--row-list)` and so on, and a component follows the user's density without a density rule of its own.

| Token | Compact | Comfortable | Large |
|-------|---------|-------------|-------|
| `--control` (button, input) | 28 | 34 | 40 |
| `--control-sm` (small button, icon button) | 24 | 30 | 34 |
| `--chrome-row` (top bar = chrome row + 16) | 36 | 44 | 48 |
| `--nav-row` (sidebar row = brand square) | 26 | 30 | 36 |
| `--row-list` | 32 | 44 | 52 |
| `--row-table` | 36 | 52 | 60 |
| `--card-h` (collection card) | 84 | 108 | 128 |
| `--sidebar-width` | 216 | 232 | 256 |
| Body · small · page title · number | 13/20 · 12/16 · 18/24 · 26/28 | 14/21 · 13/18 · 19/26 · 28/32 | 16/24 · 14/20 · 22/30 · 32/36 |
| `--icon` / `--icon-l` | 13 / 26 | 13 / 26 | 13 / 26 |

Also fixed across densities: `--gutter` 24, `--sidebar-rail` 52 (manual collapse), `--sidebar-rail-auto` 64 (automatic at 1024–1279 px), `--page-max` 1440, `--page-max-narrow` 880, and `--measure-sm` / `-md` / `-lg` (42 / 68 / 90 ch).

#### 3.9 Layering and breakpoints

Z-index is always a token: `--z-base` 0 · `--z-raised` 10 · `--z-dragging` 50 · `--z-sticky` 100 · `--z-chrome` 200 · `--z-backdrop` 300 · `--z-drawer` 310 · `--z-modal` 400 · `--z-popover` 500 (above modals, so menus inside dialogs work) · `--z-toast` 600 · `--z-tooltip` 700.

Media queries use 640 / 768 / 1024 / 1280 / 1440, `min-width` preferred, with `max-width` complements (767, 1023 …). A phone is below 768 px. The Dashboard and Discovery grids document a few extra column steps in their own stylesheets.

### 4. Components

The kit page (`admin/?page=birch-kit`, with developer mode on in Settings › System) shows every component live, in the current theme and density. Markup below is copied from it.

#### 4.1 Buttons

```html
<button type="button" class="button is-main"><?= birkly_icon('plus') ?>New entry</button>
<button type="button" class="button">Preview<?= birkly_icon('eye') ?></button>
<button type="button" class="button is-quiet">Customize</button>
<button type="button" class="button is-danger"><?= birkly_icon('trash') ?>Delete</button>
<button type="button" class="button is-small">Fix</button>
<button type="button" class="button is-busy"><?= birkly_icon('loader') ?>Saving…</button>
<button type="button" class="icon-button" aria-label="More"><?= birkly_icon('dots') ?></button>
```

| Class | Look |
|-------|------|
| `.button` | Secondary: `--surface`, 1 px `--line-strong`, `--control` high, 12 px padding, `title` 500, 13 px icon |
| `.is-main` | Mint fill, `--on-mint` text. **One visible per view** |
| `.is-quiet` | No fill, no line, `--text-2`; for Cancel and low-weight actions |
| `.is-danger` | `--danger-soft` fill, `--danger` text |
| `.is-small` | `--control-sm` high |
| `.is-busy` | No pointer; its `loader` icon loops the chase gesture |
| `:disabled`, `[aria-disabled="true"]` | 50 % opacity, no pointer, no gesture |
| `.icon-button` | `--control-sm` square, no fill until hover. Needs `aria-label` (and a tooltip) |

Buttons inside a signal are secondary, never mint. Destructive actions confirm through `BirklyFeedback.confirm()`.

#### 4.2 Fields

```html
<div class="field-group">
  <label class="field-label" for="name">Collection name</label>
  <input class="input" id="name" value="Blog">
  <span class="field-help">Shown in the sidebar and lists.</span>
</div>

<div class="field-group">
  <label class="field-label" for="slug">Address</label>
  <div class="input-group"><span class="prefix well">/blog/</span><input class="input is-mono" id="slug"></div>
</div>

<div class="field-group">
  <label class="field-label" for="method">Entry method</label>
  <span class="select-box"><select id="method" class="input"><option>Admin only</option></select><?= birkly_icon('chev-down') ?></span>
</div>

<div class="field-group">
  <label class="field-label" for="title">Title</label>
  <input class="input is-invalid" id="title" aria-invalid="true" aria-describedby="title-error">
  <span class="field-error" id="title-error">A title is required.</span>
</div>
```

- The label is `small` 500 and the help text `small` `--text-3`; errors are `small` `--danger`.
- `textarea.input` works the same way.
- `.search-field` wraps an input with a leading search icon.
- Invalid styling shows only after submit (`form.was-validated`) or with an explicit `.is-invalid`.
- `.setting-row` is the settings slab: `.setting-label` (label and help) | `.setting-value` (control), split 32 / 68.

#### 4.3 Choices

```html
<label class="choice"><input type="checkbox" class="check" checked>On</label>
<label class="choice"><input type="radio" class="radio" name="r" checked>One</label>
<button type="button" class="switch" role="switch" aria-checked="true" aria-label="Required">
  <span><?= birkly_icon('x') ?></span><span><?= birkly_icon('check') ?></span>
</button>

<div class="segmented well" role="group" aria-label="View">
  <button type="button" aria-pressed="true"><?= birkly_icon('grid') ?>Cards</button>
  <button type="button" aria-pressed="false"><?= birkly_icon('list') ?>List</button>
</div>

<div class="tabs" role="tablist">
  <button type="button" role="tab" aria-selected="true">Needs attention <span class="count is-warning">4</span></button>
  <button type="button" role="tab" aria-selected="false">Activity</button>
</div>
```

- Checkbox and radio are 14 px squares, filled with accent when on. The indeterminate state draws a minus, and a check mark draws in over four frames.
- The switch is a two-cell segmented x / check control with a 3 px thumb, blue when on. The older `label.switch > input + .switch__slider` markup renders as the same Birch switch.
- In a segmented control the active cell is a slab.
- Tabs sit on the canvas and show a 2 px ink underline on the current tab; counts are `.count` wells.

#### 4.4 Counts, chips, status, avatars

```html
<span class="status is-published">Published</span>
<span class="status">Draft</span>
<span class="status is-failed is-plain">Failed</span>
<span class="count">4</span> <span class="count is-warning">4</span> <span class="count is-accent">10</span>
<span class="chip well">+5 sub-entries</span>
<span class="lang well">EN</span> <span class="missing dashed">DE</span>
<span class="kbd">⌘K</span>
<span class="avatar">K</span> <span class="avatar is-accent">KB</span> <span class="avatar is-ink">BC</span>
```

- A status is a 6 px square dot and a label on a soft fill. Variants are `.is-published`, `.is-success`, `.is-scheduled`, `.is-archived`, `.is-warning`, `.is-failed`, `.is-danger` and `.is-info`; `.is-plain` drops the fill.
- A count is a `caption` well with tabular numbers.
- An avatar is a 6 px square with `caption` 600 initials.

#### 4.5 Signals

```html
<div class="signal">
  <?= birkly_icon('lock') ?>
  <div class="signal-body">
    <p class="signal-title">Homepage can't read "Blog"</p>
    <p class="signal-text">Public read is off for this collection.</p>
  </div>
  <button type="button" class="button is-small">Fix</button>
</div>
```

The default signal is warning. The other variants are `.is-info`, `.is-success` and `.is-danger`. A signal holds one message, and its action is a secondary button on the right.

#### 4.6 Metrics, meters, ranked bars

```html
<div class="metrics">
  <div class="metric">
    <span class="metric-label"><?= birkly_icon('check-circle') ?>Published</span>
    <span class="metric-value">341 <small>/ 425</small></span>
    <span class="metric-meta">80% of all entries</span>
    <div class="meter" data-value="80"><span></span></div>
  </div>
  <div class="metric">…</div>
  <button type="button" class="icon-button metrics-customize" aria-label="Customize metrics"><?= birkly_icon('sliders') ?></button>
</div>

<ul class="ranked">
  <li><span class="ranked-label">/club.html</span><span class="ranked-value">165</span><div class="meter" data-value="43"><span></span></div></li>
</ul>
```

- The metric bar is one slab with up to four slots, split by hairlines. Labels are sentence case with an icon, values use the `number` step, and meta is `small`.
- A meter is a row of 0 px segments that fills in `--dur-4` steps.
- Money is always exact: use `birkly_money()` or `Birkly.money()`, never an abbreviated amount.

#### 4.7 Rows, lists, timeline, cards

```html
<div class="row">
  <span class="avatar">C</span>
  <div class="row-body"><span class="row-title">Casen</span><span class="row-meta">Alpha Waitlist · 1d ago</span></div>
  <span class="status is-published">Published</span>
</div>

<ul class="list-rows">
  <li class="list-row"><div class="row-body">…</div><div class="row-actions">…</div></li>
</ul>

<ul class="timeline">
  <li><span class="row-icon is-success"><?= birkly_icon('bag') ?></span><div class="row-body">…</div></li>
</ul>

<div class="card-grid">
  <a class="collection-card" href="…">
    <span class="collection-card-title">Blog</span>
    <span class="collection-card-text">Articles for the marketing site.</span>
    <span class="collection-card-foot"><strong>10</strong> entries</span>
  </a>
</div>
```

- `.row` and `.list-row` are slabs, stacked 4 px apart.
- `.row-icon` is an icon well, with `.is-info`, `.is-success` and `.is-warning` variants.
- `.card-grid` lays out slabs 8 px apart, with a column count that follows density.

#### 4.8 Table and bulk bar

```html
<div class="bulk-bar" role="toolbar" aria-label="Bulk actions">
  <span class="bulk-bar-count">2 selected</span>
  <button type="button" class="button is-small">Publish</button>
  <button type="button" class="button is-danger is-small">Delete</button>
  <button type="button" class="button is-quiet is-small"><?= birkly_icon('x') ?>Clear selection</button>
</div>
<div class="table" role="table">
  <div class="table-head" role="row"><span>Title</span><span>Status</span></div>
  <div class="table-row is-selected" role="row" aria-selected="true">…</div>
  <div class="table-row" role="row">…</div>
</div>
```

- The header sits on the canvas in `small` `--text-3`, and each row is a slab 4 px apart. Columns come from `--table-columns` (a `grid-template-columns` value) on the `.table`.
- A selected row gets the accent ring and `--accent-soft` fill.
- With a selection, the page bar becomes the bulk bar: an `--accent-soft` fill, a 1 px `--accent-line` ring and the count in `--accent-text`.

#### 4.9 Menu

```html
<div class="menu">
  <button type="button" class="menu-item"><?= birkly_icon('user') ?>Profile &amp; security</button>
  <button type="button" class="menu-item"><?= birkly_icon('keyboard') ?>Keyboard shortcuts<span class="kbd">?</span></button>
  <div class="menu-rule" role="separator"></div>
  <button type="button" class="menu-item is-danger"><?= birkly_icon('log-out') ?>Sign out</button>
</div>
```

Items are `--control-sm` high and hover to `--surface-2`. `.menu-head` holds an optional avatar and title block. A menu never holds a form.

#### 4.10 Dialog and sheet

```html
<div class="modal" id="refund-modal">
  <div class="modal-backdrop" data-modal-dismiss></div>
  <div class="modal-content dialog" role="dialog" aria-modal="true" aria-labelledby="refund-title">
    <div class="dialog-head">
      <div>
        <h2 class="dialog-title" id="refund-title">Refund ORD-0001</h2>
        <p class="dialog-text">Paid €15.00 with Stripe</p>
      </div>
      <button type="button" class="icon-button" data-modal-dismiss aria-label="Close"><?= birkly_icon('x') ?></button>
    </div>
    <div class="dialog-body">
      <div class="slab">…fields…</div>
      <div class="summary-note dashed">Refund <strong>€15.00</strong> to the original payment method.</div>
    </div>
    <div class="dialog-foot">
      <button type="button" class="button is-quiet" data-modal-dismiss>Cancel</button>
      <button type="button" class="button is-main">Refund €15.00</button>
    </div>
  </div>
</div>
```

- The float body is `--bg`. It holds headings and slabs 4 px apart, never frames.
- The footer has Cancel (quiet) and the one mint action.
- Add `.is-sheet` to `.modal` to dock the panel to the right edge; it slides in 16 px over `--dur-2`.
- `BirklyFeedback.confirm()` and `BirklyFeedback.prompt()` build this same dialog.
- `data-modal-dismiss` on the backdrop, the close button and Cancel closes the dialog (or `data-modal-dismiss="refund-modal"` from anywhere). `admin/js/accessibility.js` traps focus inside an open modal, restores it on close, and presses the close button on Escape, so keep `aria-label="Close"` on it.

#### 4.11 Empty, loading, tooltip, toast

```html
<div class="empty">
  <span class="empty-icon"><?= birkly_icon('inbox') ?></span>
  <p class="empty-title">No entries yet</p>
  <p class="empty-text">Create the first entry and it will appear here, ready to publish.</p>
  <button type="button" class="button is-main is-small"><?= birkly_icon('plus') ?>New entry</button>
</div>

<span class="skeleton" data-width="60"></span>
<span class="tooltip" role="tooltip">Search everything <span class="kbd">⌘K</span></span>
<span class="toast" role="status"><?= birkly_icon('check-circle') ?>Entry published<button type="button" class="toast-action">Undo</button></span>
```

- An empty state is dashed: a 26 px icon in an accent-soft well, a title, one line of text and an optional mint action.
- Skeletons are `--surface-2` bars with a shimmer stepped across eight frames.
- Tooltips and toasts are ink floats. Toasts appear top centre below the top bar (`BirklyFeedback.toast()`).

### 5. Shell

| Part | Behaviour |
|------|-----------|
| Sidebar | A floating column of three `--surface` panels 8 px apart on `--bg-side`: main (Dashboard, Content, Media, Discovery), plugins (Hub, then plugin items), system (Settings, Help). Width is `--sidebar-width`. |
| Sidebar rows | `--nav-row` high: 13 px icon, 12 px gap, label. The current row has a 2 px ink bar at the panel's left edge. On hover the bar previews in `--line-strong`, the label moves 2 px right and the icon plays its gesture. A plugin with news shows a 6 px blue square at the right. |
| Brand | A `--nav-row` square (6 px radius) with the installation title beside it. The brand area and the top bar share one height (`--chrome-row` + 16 px), so the top bar's rule meets the top edge of the first nav panel. |
| Collapse | Manual: a 52 px rail with labels fading over `--dur-3` and tooltips naming items. Automatic at 1024–1279 px: a 64 px rail. Below 1024 px: a drawer over the scrim. |
| Top bar | Flat on the canvas with a dotted 1 px rule under it. Breadcrumb on the left (parents in `--text-3`, current in `page-title`), then search (⌘K), view live, theme, notifications and account on the right. |
| Page bar | `.page-bar`, `--control` high, 20 px below the top bar. The mint action comes first, then secondary actions, then state badges; search, filters and view switches go in `.page-bar-tools` on the right. There is none on the Dashboard. |
| Phone (< 768 px) | The top bar is one row: menu button, breadcrumb (truncating from the left), notifications. Search, theme and account with log out move into the drawer. The page bar becomes the second row, keeping the mint action and icon buttons. Every other action, filter and control moves into a **More** menu (dots) at the row's end, and icon-only overflow menus merge into it. |

The More menu is automatic: `admin/js/page-actions.js` moves (never clones) the elements of any `.page-bar`, `.main-actions-bar` or `.page-header-actions` below 768 px, and puts them back when the viewport widens. It keeps `.is-main` / `.btn-primary`, icon-only buttons, `[hidden]` elements, live regions and `.badge`. Make sure your primary action carries `.is-main` and your icon buttons carry an `aria-label`.

### 6. Icons

#### 6.1 The set

There are 102 pixel icons on a 13 × 13 grid, plus 10 smooth-stroke wayfinding versions (`chev-*`, `arrow-left`, `arrow-r`, `arrow-ur`, `sort`) drawn on the same box.

- **Sizes:** 13 px (`--icon`) and 26 px (`--icon-l`, two screen pixels per cell) only. Any requested size of 20 or more renders at 26, anything smaller at 13.
- **Pixel form:** `viewBox="0 0 13 13"`, `fill="currentColor"`, `shape-rendering="crispEdges"`, paths made of whole 1 × 1 cells.
- **Stroke form:** `stroke="currentColor"`, `stroke-width="1"`.
- **Colour:** always `currentColor`, so an icon takes the colour of its control.

`alert` `align-left` `arrow-left` `arrow-r` `arrow-ur` `bag` `bell` `bold` `book` `box` `braces` `branch` `calendar` `card` `chat` `check` `check-circle` `chev-down` `chev-left` `chev-left2` `chev-right` `chev-up` `chev-updown` `clock` `code` `collapse` `content` `copy` `dashboard` `dots` `download` `expand` `eye` `flask` `folder` `gift` `globe` `grid` `grip` `hash` `heart` `help` `help-circle` `history` `home` `inbox` `info` `italic` `keyboard` `layers` `link` `list` `loader` `lock` `log-out` `mail` `map-pin` `media` `minus` `monitor` `moon` `multi` `music` `news` `palette` `pencil` `percent` `phone` `play` `plus` `printer` `quote` `receipt` `refresh` `route` `rows3` `rows4` `save` `scale` `search` `select` `send` `shield` `sliders` `sort` `sparkle` `star` `stop` `sun` `tag` `template` `toggle` `trash` `truck` `type` `upload` `user` `users` `wand` `webhook` `x` `zap`

Pre-Birch names such as `gear`, `magnifying-glass` and `pencil-simple` still resolve through aliases. The full table is in [Plugin admin styling § Icon names](../06-plugin-economy/plugin-admin-styling.md#icon-names).

#### 6.2 API

```php
<?= birkly_icon('trash') ?>                                   // 13 px, with its gesture
<?= birkly_icon('inbox', ['size' => 26]) ?>                   // 26 px
<?= birkly_icon('bell', ['title' => 'Notifications']) ?>      // labelled (not aria-hidden)
<?= birkly_icon('grip', ['gesture' => false]) ?>              // no gesture
<?= birkly_icon('save', ['gesture' => 'lift']) ?>             // another gesture
<?= birkly_icon('chev-down', ['pixel' => true]) ?>            // pixel art of a stroke icon
```

```javascript
Birkly.icon('trash');                          // same markup as PHP
Birkly.icon('grip', { gesture: false });
Birkly.initIcons(root);                        // hydrates [data-icon="name"]
Birkly.iconPlay(el);                           // play the gestures in or at el once
```

`[data-icon]` placeholders take `data-icon-size`, `data-icon-class`, `data-icon-title`, `data-icon-aria-hidden` and `data-icon-gesture` (a name, or `false`). Output always carries `.birkly-icon` (plus `.birkly-icon--l` at 26 px). Size an icon only with `var(--icon)` or `var(--icon-l)`: CI rejects any other width or height on `.birkly-icon`.

#### 6.3 The grid alphabet

Each icon in `core/icon_data.php` is 13 strings of 13 characters:

| Character | Meaning |
|-----------|---------|
| `.` | Empty cell |
| `#` | The body: drawn at rest, moves only when the whole icon moves |
| `a`–`z` | Parts: drawn at rest, each its own `<path data-part="a">` that a gesture can move on its own (a lid, a hand, a clapper) |
| `1`–`9` | Ghosts: `<path data-part="1" opacity="0">`, invisible at rest; a gesture shows them (a spark, a filled heart, the second frame of a blink) |

`php scripts/build-icons.php` writes the JS tables and `admin/assets/icons/*.svg` from this file. Every grid must be exactly 13 × 13 and contain at least one `#` or part cell.

### 7. Motion

#### 7.1 Gestures

Every icon except `chev-updown`, `grip` and `sort` names a gesture. The gesture plays **once** when:

- the control around the icon is hovered or keyboard-focused, and the control is not disabled, `aria-disabled` or `.is-busy`. Controls are `a[href]`, `button`, `summary`, `label` and the roles `button`, `menuitem`, `menuitemradio`, `tab`, `option` and `switch`. The icon must be a direct child of the control or one wrapper deep;
- or script calls `Birkly.iconPlay(el)`, which sets `data-play` for one run. The bell uses this when the unread count rises.

A busy control (`.is-busy`) loops its `loader` chase until the busy state ends.

The engine lives in `birch/motion.css`. A gesture sets custom properties on the SVG: `--g-n` (frames), `--g-base` (keyframes for the body), `--g-a` … `--g-j` and `--g-1` … `--g-3` (keyframes per part or ghost), and `--g-X-at` / `--g-X-n` (start frame and length per part). The trigger maps them onto `--run-*`, which the paths read. Every keyframe is one frame (`--dur-frame`) held with `steps(1, end)`. Moves are whole cells and turns are quarter steps, so nothing ever sits between pixels, and nothing scales.

| Gesture | What happens | Icons |
|---------|--------------|-------|
| `nudge-r` / `-l` / `-u` / `-d` / `-ur` | Two taps one cell in the direction of travel | `arrow-r` `card` `chev-right` `italic` `play` · `arrow-left` `chev-left` `chev-left2` · `chev-up` · `chev-down` `receipt` · `arrow-ur` |
| `hop` | The whole icon up one cell for two frames | `bag` `bold` `book` `branch` `calendar` `folder` `hash` `home` `mail` `map-pin` `multi` `music` `news` `percent` `quote` `route` `save` `tag` `template` `type` `users` |
| `lift` / `sink` | Part a moves up / down one cell | `box` `gift` `info` `layers` `lock` `media` `trash` `user` · `printer` `select` |
| `ring` | The body swings, the clapper follows a frame late | `bell` |
| `drop` / `launch` | The arrow travels two cells and comes back | `download` · `upload` |
| `spread` | Two halves open one cell each way | `braces` `code` |
| `grow` / `squeeze` | Four corners push out / pull in | `expand` · `collapse` |
| `bounce` | Three dots rise one after another | `chat` `dots` |
| `wave` | Lines ripple, one per frame | `align-left` `list` `rows3` `rows4` |
| `draw` | Parts appear in letter order | `check` `check-circle` `content` |
| `chase` | One part dims per frame | `dashboard` `grid` `keyboard` `loader` `palette` `webhook` |
| `tick` / `rewind` / `spin` | Quarter turns clockwise / counter-clockwise / whole icon | `clock` · `history` · `refresh` |
| `copy` | The front sheet slides onto the back one | `copy` |
| `blink` | The eye blinks twice | `eye` |
| `seq` | Ghosts 1, 2, 3 show for one frame each | `flask` `inbox` `monitor` `moon` `wand` |
| `turn` | The meridians roll round | `globe` |
| `beat` / `fill` | The heart beats twice / the shape fills | `heart` · `shield` `star` |
| `wobble` | The question mark wobbles | `help` `help-circle` |
| `tug` | One ring unhooks | `link` |
| `exit` | The arrow leaves through the door | `log-out` |
| `pop` / `flash` | Part a blinks / the whole icon blinks | `alert` `minus` `plus` `sun` `x` · `stop` `zap` |
| `write` | The pencil scribbles | `pencil` |
| `shake` | The phone rings | `phone` |
| `scan` | The lens looks around | `search` |
| `tilt` | The pans see-saw | `scale` |
| `fly` | Flies out to the right and back in from the left | `send` |
| `slide` | The knobs slide against each other | `sliders` |
| `twinkle` | Points pull in, then burst with glints | `sparkle` |
| `flip` | The knob crosses and comes back | `toggle` |
| `drive` | The body bounces on its wheels | `truck` |

Turn a gesture off for drag handles, purely decorative icons and icons inside content that already moves (`'gesture' => false` / `data-icon-gesture="false"`).

#### 7.2 Micro-interactions

All of these come from `birch/motion.css` and the kit. Pages add none of their own.

| Interaction | Behaviour |
|-------------|-----------|
| Press | Buttons, icon buttons and segmented cells sink 1 px while held |
| Focus | The focus ring settles from a 4 px offset to 1 px over `--dur-2` |
| Menus and popovers | Rise 4 px into place over `--dur-2` each time they open |
| Messages | A signal that appears inside a dialog or form rises in; so do `<details>` bodies |
| Check marks | Draw in over four frames |
| Tabs | The underline and current colour ease in |
| Counts | A `.count` or `.notification-badge` with `data-bump` jumps 2 px and settles over two frames |
| Notifications | When the unread count rises, the badge bumps and the bell rings |
| Sidebar | The ink bar previews and the label shifts 2 px over `--dur-1` |
| Sheets | Slide in 16 px from their edge |
| Loading | Skeletons shimmer over eight stepped frames; busy buttons loop their loader |

#### 7.3 Reduced motion

`prefers-reduced-motion: reduce` is handled in exactly two places:

- `core/variables.css` sets `--dur-1`, `--dur-3`, `--dur-4` and `--dur-px` to 0. `--dur-2` stays, because focus rings still fade.
- The global block in `core/reset.css` collapses every animation to one 0.01 ms iteration, so gestures, entrances, bumps and shimmer do not play.

Components never write their own reduced-motion block; CI rejects one. Use duration tokens and the global rules cover you.

### 8. Page patterns

| Pattern | Surfaces | Examples |
|---------|----------|----------|
| Single list | Header row on the canvas; rows are slabs 4 px apart; no frame | Entries, Orders, People, Runs |
| Grid | Cards are slabs 8 px apart; thumbnails are wells | Collections, Media, Hub, Templates |
| Overview | A metric slab; a chart is one slab; lists of actions are frames | Dashboard, Commerce overview |
| Editor | Field slabs on the canvas; the rail is frames 24 px apart; the body is one slab with a well toolbar | Entry, Collection, SEO |
| Settings | Frames of `.setting-row` slabs, label and value split | Settings, Account |
| Dialog / sheet | Grey float; headings and slabs; one mint button in the footer | Refund, Add a field, Feedback |

### 9. Dark mode

Dark mode is a token remap, defined under `[data-theme="dark"]` and under `[data-theme="system"]` inside `@media (prefers-color-scheme: dark)`. `admin/js/theme.js` (`Birkly.Theme`) sets `data-theme` on `<html>` and `<body>`. If a component seems to need a dark rule, a colour is hard-coded somewhere: fix that instead. The only dark rule outside the token file is the top bar's shadow cast. Never put a literal `data-theme` attribute on your own elements; use `data-theme-option` or a name of your own.

### 10. Do and don't

| Do | Don't |
|----|-------|
| Ask the four questions and use the role they give | Draw a box because a section "needs structure" |
| Separate slabs with gaps (4 / 8 / 24 px) | Put a border or shadow on a slab |
| One mint button per view | A second mint button, or mint inside a signal |
| Blue for on, selected, link, focus | Blue or mint as body text (`--accent-text` for links) |
| Status colour inside a signal or a status badge | A coloured heading, border or background to mark importance |
| Sentence case | Uppercase labels or letter-spaced overlines |
| `var(--dur-*)` and `var(--ease)` | Literal durations, `transition: all`, a component reduced-motion block |
| Stepped, whole-pixel motion | Scaling on hover, bouncing, looping decoration |
| `birkly_icon()` at 13 or 26 px | Inline SVGs, emoji, other icon sizes |
| Tokens for every colour | Hex, `rgb()` or `oklch()` outside `core/variables.css` |
| `var(--control)`, `var(--row-list)` and friends | Fixed heights that ignore density |
| Breadcrumb as the page title | A page `<h1>` |

### 11. CI rules

`internal/cms/scripts/design-language-check.sh` (Birkly-Ops) gates every admin UI change; `design-smoke.sh` runs it in a smaller session smoke. Gating checks:

1. No colour literal (hex, `rgb()`, `hsl()`, `oklch()`) outside `core/variables.css`.
2. No retired token name in admin CSS; P56 names live only in the two alias files.
3. Every `var(--x)` resolves to a definition, including properties set from JS.
4. No raw `z-index` layer number; `--z-*` only (0 and 1 allowed inside a stacking context).
5. `@media` uses the canonical breakpoints (plus the documented Dashboard and Discovery grid steps).
6. `!important` only in the global reduced-motion block and a few `display: none` hide guards.
7. No `transition: all`.
8. No component-level dark-mode rule (the top bar cast excepted).
9. Every public contract class (P56 and Birch kit) is still defined.
10. The alias files hold only aliases.
11. Accent and mint are never a text colour.
12. Size budgets: total admin CSS code lines, per-page stylesheets.
13. Inline `style=""` and `<style>` budgets in templates (fieldtype CSS injection excepted).
14. `roles.generated.css`, `icon.js` and `assets/icons` are fresh (`build-birch-roles.php --check`, `build-icons.php --check`).
15. Token contrast is at least 4.5:1 in light and dark, the icon grid is 13 × 13 with integer paths, every role-map selector has one role, gestures use whole-cell frames and motion tokens.
16. No border on slab selectors; at most one visible mint button per template region; sentence case; `.birkly-icon` sized only by `--icon` / `--icon-l`.

## Related

- [Plugin admin styling](../06-plugin-economy/plugin-admin-styling.md): Birch for plugin pages, fieldtypes, and the P56-to-Birch rename tables
- [Admin design system](design-system.md): short orientation
- [Admin appearance](admin-appearance.md): theme and responsive behaviour for users
- [Admin chrome and action zones](admin-chrome-action-zones.md)
- [Plugin navigation icon](../06-plugin-economy/plugin-navigation-icon.md)
