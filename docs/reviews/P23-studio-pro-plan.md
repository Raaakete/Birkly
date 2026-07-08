# P23 — Studio IDE (advanced visual builder + project workspace)

**Status:** Approved (2026-07-08, Keven)  
**Baseline:** P22 complete — `plugins/studio` v1, SSR, 145 test suites  
**Human rule:** Execute P23-M0→M16 autonomously. Notify human **once** at **P23-M16** only.

**Handoff:** `docs/reviews/P23-lead-engineer-handoff.md`  
**Matrix:** `docs/reviews/P23-verification-matrix.md`

---

## Vision

Transform Studio from a **single-page form editor** into a **full project IDE** inside Birkly — file tree, tabbed files, **visual view** and **code view** (toggle or split), drag-drop canvas, responsive design, global components, plugin blocks, and SSR publish.

**North star:** Feels like opening a project in a lightweight VS Code + Webflow hybrid — but every file stays in `content/studio-projects/` and compiles to Birkly-native HTML + template tags.

---

## Human requirements (locked for P23)

| Requirement | P23 delivery |
|-------------|--------------|
| **Visual view** | Canvas editor per page — drag-drop, layers, inspector |
| **Code view** | Syntax-highlighted editor for HTML, CSS, JSON, and any project text file |
| **File system** | Sidebar tree of the real project folder — browse, create, rename, delete |
| **File tabs** | Multi-tab bar — several files open; dirty indicator; close/switch |
| **Open project** | Toolbar button opens the project **home page** (`index`) in a **new browser tab** (SSR URL when published, preview URL when draft) |

---

## P22 → P23 gap

| P22 | Limitation | P23 |
|-----|------------|-----|
| Dashboard → single editor | No project workspace | **IDE shell** with tree + tabs |
| One page at a time | No multi-file workflow | **File tabs** across pages, CSS, components |
| Visual OR code tab only | Not true dual view | **Visual + code** toggle **and** side-by-side split |
| No file browser | Opaque storage | **File system tree** mirrors disk |
| Open preview modal only | No quick live site | **Open project** → new tab to `/site/{slug}/` |
| Click-to-add canvas | No pro layout tools | DnD, multi-column, responsive |
| Basic bindings | No pro Birkly blocks | Conditionals, TF, sub-entries, plugin blocks |

---

## Studio IDE layout (target UI)

```
┌──────────────────────────────────────────────────────────────────────────┐
│ Studio · My Coffee Shop    [Open project ↗] [Publish] [Export] [Preview] │
├────────────┬─────────────────────────────────────────────┬───────────────┤
│ FILE TREE  │ [index.studio.json] [custom.css] [about.html]×│  INSPECTOR   │
│ ▼ project  ├─────────────────────────────────────────────┤  (visual)    │
│   project  │  [Visual] [Code] [Split]     Desktop│Tablet│Mobile          │
│   pages/   │ ┌─────────────────────────────────────────┐ │              │
│   styles/  │ │  Visual canvas OR Code editor OR Split  │ │              │
│   components/                                            │ │              │
│   library/ │ └─────────────────────────────────────────┘ │              │
│   website/ │                                             │              │
├────────────┴─────────────────────────────────────────────┴───────────────┤
│ Status · Saved · /site/my-coffee-shop/                                 │
└──────────────────────────────────────────────────────────────────────────┘
```

---

## Locked decisions (approved 2026-07-08, Keven)

| ID | Decision |
|----|----------|
| **P23-D1** | Extend `plugins/studio/` — v1 projects upgrade cleanly |
| **P23-D2** | **IDE-first** — file tree + tabs are primary navigation; page dropdown deprecated |
| **P23-D3** | **Visual view** — canvas for `*.studio.json` page files |
| **P23-D4** | **Code view** — text editor for any project file; vendored highlighter (no npm runtime) |
| **P23-D5** | **Split view** — visual + code side-by-side for same page; sync on explicit save |
| **P23-D6** | **File system** — API lists real paths under `content/studio-projects/{id}/`; CRUD with path sandbox |
| **P23-D7** | **File tabs** — session-persist open tabs in `sessionStorage`; max 20 tabs |
| **P23-D8** | **Open project** — opens `{ssr_url}` or `{preview_url}` for home page in `target=_blank` |
| **P23-D9** | Canvas schema **v2** — backward-compatible v1 import |
| **P23-D10** | Responsive styles: `default` / `tablet` / `mobile` per element |
| **P23-D11** | Global components in `components/` |
| **P23-D12** | Design tokens in `project.json` → CSS variables |
| **P23-D13** | Core `register_studio_block()` shelf |
| **P23-D14** | Export CSS optimizer (PHP class scan) |
| **P23-D15** | SSR parity for all blocks — gaps are bugs |
| **P23-D16** | No npm at runtime |
| **P23-D17** | **Single human notification** at P23-M16 only |

---

## File system (canonical tree)

```
content/studio-projects/{project-id}/
├── project.json
├── styles/
│   ├── kit.css
│   ├── custom.css
│   └── tokens.css          # generated from design tokens
├── pages/
│   ├── index.studio.json   # visual source
│   ├── index.html          # compiled
│   └── about.studio.json
├── components/
│   └── header-main.studio.json
├── library/
│   └── sections/
├── website/                # export mirror
├── revisions/              # publish snapshots
└── collections/            # optional seeds
```

**Editable in code view:** `.html`, `.css`, `.json`, `.studio.json`, `.md` (README)  
**Visual view only:** `pages/*.studio.json`, `components/*.studio.json`

---

## Milestones (one wave — expanded)

| ID | Focus |
|----|--------|
| **P23-M0** | Gap register, IDE UX spec, canvas v2 schema, block API |
| **P23-M1** | **IDE workspace shell** — layout regions, toolbar, **Open project ↗** button |
| **P23-M2** | **File system API + tree** — list, read, write, create, rename, delete (sandboxed) |
| **P23-M3** | **File tabs** — open/close/switch, dirty state, save, keyboard shortcuts |
| **P23-M4** | **Code view** — syntax highlight, line numbers, format-on-save for JSON |
| **P23-M5** | **Visual view + split** — canvas tab, code tab, split pane sync |
| **P23-M6** | Canvas v2 — DnD, layers navigator, multi-select, shortcuts |
| **P23-M7** | Multi-column layout (grid/flex; ContentEditor reuse) |
| **P23-M8** | Responsive breakpoints + `@media` compile |
| **P23-M9** | Visual style panel + design tokens |
| **P23-M10** | Global components |
| **P23-M11** | Media picker + inline WYSIWYG |
| **P23-M12** | Advanced Birkly blocks (conditionals, TF, sub-entries, filters) |
| **P23-M13** | `register_studio_block()` + commerce/events/user blocks |
| **P23-M14** | Section library + page templates + v1→v2 upgrade |
| **P23-M15** | Undo/redo, revisions, SSR sprint, CSS optimizer, docs, V-STU-02 |
| **P23-M16** | Matrix green, tests green, closure, single notification |

**Branches:** `cursor/p23-m[N]-<slug>-030a`

---

## Milestone detail

### P23-M0 — Planning gate

- [ ] **P23-M0-T1** `docs/reviews/P23-studio-advanced-gap-register.md`
- [ ] **P23-M0-T2** `docs/reviews/P23-canvas-v2-schema.md`
- [ ] **P23-M0-T3** `docs/reviews/P23-studio-ide-ux-spec.md` — layout, tabs, tree, open project
- [ ] **P23-M0-T4** Human sign-off P23-D1–D17

### P23-M1 — IDE workspace shell

- [ ] **P23-M1-T1** Replace v1 dashboard/editor with 3-pane IDE layout (tree | editor | inspector)
- [ ] **P23-M1-T2** Project toolbar: name, Publish, Export, Preview, **Open project ↗**
- [ ] **P23-M1-T3** **Open project** — `window.open(ssrOrPreviewUrl, '_blank')` for home page (`index`)
- [ ] **P23-M1-T4** Status bar: save state, public URL, draft/published badge
- [ ] **P23-M1-T5** Design-reviewer pass on shell layout

### P23-M2 — File system

- [ ] **P23-M2-T1** `StudioFilesystem` — sandboxed paths under project root only
- [ ] **P23-M2-T2** API: `list_files`, `read_file`, `write_file`, `create_file`, `rename_file`, `delete_file`
- [ ] **P23-M2-T3** Tree UI — expand/collapse folders, icons by extension, context menu (new, rename, delete)
- [ ] **P23-M2-T4** Block path traversal (`../`, absolute paths)
- [ ] **P23-M2-T5** Tests: filesystem CRUD + security fixtures

### P23-M3 — File tabs

- [ ] **P23-M3-T1** Tab bar — open file from tree → new tab; click to switch; × to close
- [ ] **P23-M3-T2** Dirty dot on unsaved tabs; confirm on close if dirty
- [ ] **P23-M3-T3** `sessionStorage` restore open tabs on reload
- [ ] **P23-M3-T4** Shortcuts: Ctrl+S save, Ctrl+W close tab, Ctrl+Tab next tab
- [ ] **P23-M3-T5** Tests: tab state machine

### P23-M4 — Code view

- [ ] **P23-M4-T1** Vendored code editor (textarea + highlight layer or lightweight editor in plugin)
- [ ] **P23-M4-T2** Modes: HTML, CSS, JSON syntax highlighting
- [ ] **P23-M4-T3** Save writes to disk via filesystem API; triggers recompile when `.studio.json` or `.html` page
- [ ] **P23-M4-T4** Read-only badge for `website/` mirror files (edit source, not mirror)
- [ ] **P23-M4-T5** Tests: write round-trip, JSON validate

### P23-M5 — Visual view + split

- [ ] **P23-M5-T1** View switcher: **Visual** | **Code** | **Split** per `*.studio.json` tab
- [ ] **P23-M5-T2** Visual tab loads canvas v1/v2; Code tab shows compiled HTML or parallel `.html` (configurable)
- [ ] **P23-M5-T3** Split: canvas left, code right (or stacked on narrow admin)
- [ ] **P23-M5-T4** Save sync: visual → compile → refresh code pane; code → parse → refresh canvas (best-effort)
- [ ] **P23-M5-T5** Non-studio files (e.g. `custom.css`) open in code view only

### P23-M6 — Canvas v2 engine

- [ ] **P23-M6-T1** Drag-drop reorder sections, columns, elements
- [ ] **P23-M6-T2** Layers panel synced with canvas selection
- [ ] **P23-M6-T3** Multi-select, duplicate, copy/paste, keyboard delete
- [ ] **P23-M6-T4** Canvas v2 schema + v1 upgrade on open

### P23-M7 — Multi-column layout

- [ ] **P23-M7-T1** Add/remove/resize columns; grid/flex/stack section types
- [ ] **P23-M7-T2** Reuse ContentEditor `grid-layout.js` / `flex` patterns
- [ ] **P23-M7-T3** Compiler emits layout CSS

### P23-M8 — Responsive breakpoints

- [ ] **P23-M8-T1** Breakpoint toolbar: desktop / tablet (≤992px) / mobile (≤576px)
- [ ] **P23-M8-T2** Per-breakpoint styles in inspector
- [ ] **P23-M8-T3** `@media` rules in `custom.css` or `tokens.css`

### P23-M9 — Style panel + design tokens

- [ ] **P23-M9-T1** Visual inspector: spacing, color, typography, border, shadow
- [ ] **P23-M9-T2** Tokens UI in `project.json` or `styles/tokens.css` source
- [ ] **P23-M9-T3** Kit class picker (bootstrap / tailwind-lite)

### P23-M10 — Global components

- [ ] **P23-M10-T1** Component files in `components/` — open in own tab (visual + code)
- [ ] **P23-M10-T2** Insert instance on page; update propagates on recompile
- [ ] **P23-M10-T3** Starters: `header-nav`, `footer-simple`

### P23-M11 — Media + inline text

- [ ] **P23-M11-T1** Media picker from CMS library
- [ ] **P23-M11-T2** Double-click text on canvas → inline edit

### P23-M12 — Advanced Birkly blocks

- [ ] **P23-M12-T1** Conditionals, template functions, sub-entry loops, sort/limit/filter UI
- [ ] **P23-M12-T2** Compiler + SSR for all block types

### P23-M13 — Plugin block shelf

- [ ] **P23-M13-T1** `core/studio_blocks.php` — `register_studio_block()`
- [ ] **P23-M13-T2** Commerce, events, user_management blocks
- [ ] **P23-M13-T3** Blocks appear in visual palette + compile hooks

### P23-M14 — Library + templates

- [ ] **P23-M14-T1** Save section to `library/sections/`
- [ ] **P23-M14-T2** Page templates on new file wizard
- [ ] **P23-M14-T3** New file dialog from tree: Page, Component, CSS, HTML

### P23-M15 — History, SSR, export, docs

- [ ] **P23-M15-T1** Undo/redo (50 steps) + publish revisions in `revisions/`
- [ ] **P23-M15-T2** SSR parity sprint
- [ ] **P23-M15-T3** `StudioCssOptimizer` on export
- [ ] **P23-M15-T4** Birkly-Docs `04-studio/ide.md` + `advanced.md`
- [ ] **P23-M15-T5** V-STU-02 — IDE workflow video evidence

### P23-M16 — Closure

- [ ] **P23-M16-T1** Matrix STU-IDE + STU-PRO rows green
- [ ] **P23-M16-T2** Full smoke: tree → tabs → visual+code split → open project ↗ → publish SSR
- [ ] **P23-M16-T3** `P23-phase-closure-checklist.md`
- [ ] **P23-M16-T4** Single human notification

---

## Acceptance criteria

1. Open project → **file tree** shows real folders; open **3 files in tabs** (page, CSS, component).
2. **Visual** and **code** views work; **split** shows both; save syncs compile output.
3. **Open project ↗** opens home page in new browser tab (SSR when published).
4. Create/rename/delete file from tree; path sandbox blocks traversal.
5. Build responsive 3-page site with global header, commerce block, plugin blocks.
6. Export ZIP with optimized CSS; P22 v1 project upgrades; P21 perf tests green.

---

## Dependencies

```
P22 → M0 → M1 → M2 → M3 → M4 → M5
              → M6 → M7 → M8 → M9 (visual track)
              → M10, M11 (after M7)
              → M12 → M13
              → M14 (parallel after M5)
              → M15 → M16
```

---

## Out of scope (defer P24)

- Git integration / branch per project
- Real-time multi-user
- Popup/funnel builder
- Full Tailwind JIT (npm)
- Marketplace block store UI
- Remote FTP deploy from Studio

---

## Values check

| Value | Alignment |
|-------|-----------|
| Dependency-free | Vendored editor highlighter in plugin |
| UI decoupled | IDE admin vs compiled public HTML |
| Self-contained | `plugins/studio/` + thin core block shelf |
| Inspectability | File tree exposes real files developers can edit |

**Trade-offs:** P23-D13 core block shelf; IDE replaces v1 single-page UI entirely.
