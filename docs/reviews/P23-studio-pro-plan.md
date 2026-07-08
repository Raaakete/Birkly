# P23 — Studio Pro (advanced visual builder)

**Status:** Proposed (2026-07-08) — awaiting human approval  
**Baseline:** P22 complete — `plugins/studio` v1, SSR, 145 test suites, style kits, basic canvas  
**Human rule:** Execute P23-M0→M12 autonomously after approval. Notify human **once** at **P23-M12** only.

**Handoff:** `docs/reviews/P23-lead-engineer-handoff.md`  
**Matrix:** `docs/reviews/P23-verification-matrix.md`

---

## Vision

Upgrade Studio from a **functional v1 builder** to a **professional visual design environment** — closer to Elementor/Divi in *capability*, while keeping Birkly’s contract: compiled HTML + template tags, in-CMS storage, SSR default, no proprietary public runtime.

**North star:** A marketer can build a multi-page commerce/events site visually; a developer can still own the code tab and exported files.

---

## P22 → P23 gap (why this phase exists)

| P22 shipped | P22 limitation | P23 target |
|-------------|----------------|------------|
| Click-to-add elements | No drag-drop, no layers tree | Canvas v2 with DnD + navigator |
| Single-column sections | Always `rows[0].columns[0]` | Multi-column grid/flex editor |
| Class string inspector | No visual spacing/color/type | Full style panel + design tokens |
| No breakpoints | One layout for all screens | Mobile / tablet / desktop overrides |
| Per-page only | No shared header/footer | Global components (symbols) |
| Text fields in inspector | No inline WYSIWYG | contentEditable on canvas |
| Basic bindings | No conditionals, TF UI, sub-entries | Advanced Birkly binding blocks |
| Fixed element palette | No plugin widgets | `register_studio_block()` shelf |
| Manual sections | No reuse | Section library + page templates |
| No history | Mistakes are permanent | Undo/redo + revision snapshots |
| tailwind-lite static | Limited utilities | Export-time CSS prune (PHP scanner) |
| Crude code round-trip | Loses structure | Structured HTML ↔ canvas parser |

---

## What Studio Pro is (and is still not)

| **Is** | **Is not** |
|--------|------------|
| Professional canvas UX inside `plugins/studio/` | Full Elementor clone (popups, funnel builder, 200 widgets) |
| Responsive visual design + global components | Separate SaaS page builder |
| Plugin-contributed blocks (commerce, events, users) | npm/webpack in CMS runtime |
| Compiled HTML + Birkly tags on public site | JSON runtime on public pages |
| SSR + optional live client in export | Mandatory bake/hydrate |

---

## Locked decisions (proposed — approve before execution)

| ID | Decision |
|----|----------|
| **P23-D1** | **Extend `plugins/studio/`** — no fork; v1 projects remain compatible |
| **P23-D2** | **Canvas schema v2** — backward-compatible import of v1 `.studio.json` |
| **P23-D3** | **Responsive styles** stored per element: `styles.default`, `styles.tablet`, `styles.mobile` |
| **P23-D4** | **Global components** in `content/studio-projects/{id}/components/{name}.studio.json` — instanced on pages |
| **P23-D5** | **Design tokens** per project — colors, fonts, spacing scale in `project.json` → CSS variables |
| **P23-D6** | **Thin core shelf** — `register_studio_block()` in core; plugins ship block definitions (P23-M8) |
| **P23-D7** | **Media picker** uses existing CMS media API — no new storage system |
| **P23-D8** | **Export CSS optimizer** — PHP scans used classes → minimal `styles/bundle.css` (incl. tailwind-lite purge) |
| **P23-D9** | **SSR parity sprint** — all Studio binding blocks render server-side; gaps = bugs |
| **P23-D10** | **Reuse ContentEditor layout modules** for grid/table in Studio canvas (P22-D10 continues) |
| **P23-D11** | **No npm at runtime** — vendored canvas libs OK in plugin folder |
| **P23-D12** | **Single human notification** at P23-M12 only |

---

## Canvas schema v2 (summary)

```json
{
  "version": 2,
  "sections": [{
    "id": "sec-1",
    "layout": { "type": "grid", "columns": 3, "gap": "1.5rem", "align": "stretch" },
    "rows": [{
      "columns": [{
        "width": "33%",
        "elements": [{
          "id": "el-1",
          "type": "text",
          "content": "Hello",
          "classes": ["text-xl"],
          "styles": {
            "default": { "marginTop": "1rem", "color": "var(--studio-primary)" },
            "mobile": { "fontSize": "1rem" }
          }
        }]
      }]
    }]
  }],
  "components": [{ "ref": "header-main", "overrides": {} }]
}
```

---

## Milestones (one wave)

| ID | Focus | Repo |
|----|--------|------|
| **P23-M0** | Advanced gap register + canvas v2 spec + block shelf API design | Birkly |
| **P23-M1** | Canvas v2 engine — drag-drop, selection, layers navigator, keyboard shortcuts | Birkly (`plugins/studio`) |
| **P23-M2** | Multi-column layout editor — grid/flex, resize columns, gap/align (ContentEditor layout reuse) | Birkly (`plugins/studio`) |
| **P23-M3** | Responsive breakpoints — preview widths + per-breakpoint style overrides | Birkly (`plugins/studio`) |
| **P23-M4** | Visual style panel — spacing, color, typography, border, shadow, display + design tokens | Birkly (`plugins/studio`) |
| **P23-M5** | Global components — create, edit, instance on pages, sync updates | Birkly (`plugins/studio`) |
| **P23-M6** | Media library picker + inline WYSIWYG text editing on canvas | Birkly (`plugins/studio`) |
| **P23-M7** | Advanced Birkly blocks — conditionals, template functions, sub-entries, sort/limit/filter UI | Birkly (`plugins/studio`) |
| **P23-M8** | Core `register_studio_block()` + commerce/events/user_management blocks | Birkly |
| **P23-M9** | Section library + page templates (save section, insert from library, starter pages) | Birkly (`plugins/studio`) |
| **P23-M10** | Undo/redo stack + revision snapshots; structured code ↔ canvas parser | Birkly (`plugins/studio`) |
| **P23-M11** | SSR parity sprint + export CSS optimizer + Birkly-Docs + V-STU-02 video | Birkly + Birkly-Docs |
| **P23-M12** | Matrix green, tests green, closure, single notification | Birkly |

**Branches:** `cursor/p23-m[N]-<slug>-030a`

---

## Milestone detail

### P23-M0 — Planning gate

- [ ] **P23-M0-T1** `docs/reviews/P23-studio-advanced-gap-register.md`
- [ ] **P23-M0-T2** `docs/reviews/P23-canvas-v2-schema.md`
- [ ] **P23-M0-T3** Block shelf API sketch in gap register
- [ ] **P23-M0-T4** Human sign-off P23-D1–D12

### P23-M1 — Canvas v2 engine

- [ ] **P23-M1-T1** Drag-drop reorder (sections, columns, elements) — HTML5 DnD or pointer events
- [ ] **P23-M1-T2** Layers navigator panel — tree view, click to select, visibility toggle
- [ ] **P23-M1-T3** Multi-select (shift-click), duplicate, delete, copy/paste
- [ ] **P23-M1-T4** Keyboard shortcuts — delete, undo hook, arrow nudge
- [ ] **P23-M1-T5** Tests: canvas v2 schema validation

### P23-M2 — Multi-column layout

- [ ] **P23-M2-T1** Add/remove columns per row; set width % / fr
- [ ] **P23-M2-T2** Integrate `fieldtypes/ContentEditor/layout/grid-layout.js` patterns (shared or vendored copy)
- [ ] **P23-M2-T3** Section layout types: grid, flex, stack
- [ ] **P23-M2-T4** Compiler emits semantic grid CSS (classes + inline grid-template where needed)
- [ ] **P23-M2-T5** Tests: 3-column compile output

### P23-M3 — Responsive breakpoints

- [ ] **P23-M3-T1** Breakpoint bar: desktop (default) / tablet (≤992px) / mobile (≤576px)
- [ ] **P23-M3-T2** Style inspector edits active breakpoint layer
- [ ] **P23-M3-T3** Compiler emits `@media` rules in `custom.css` from per-breakpoint styles
- [ ] **P23-M3-T4** Preview iframe width toggles match breakpoints
- [ ] **P23-M3-T5** Tests: responsive compile fixtures

### P23-M4 — Visual style panel + design tokens

- [ ] **P23-M4-T1** Inspector controls: margin/padding (linked/unlinked), color picker, font size/weight, border, radius, shadow
- [ ] **P23-M4-T2** Project design tokens UI — primary/secondary colors, font stacks, spacing scale
- [ ] **P23-M4-T3** Tokens → `:root { --studio-* }` in compiled CSS
- [ ] **P23-M4-T4** Kit class suggestions still available (bootstrap/tailwind-lite)
- [ ] **P23-M4-T5** Tests: token CSS output, style sanitization

### P23-M5 — Global components

- [ ] **P23-M5-T1** Component CRUD — save selection as component, edit component file
- [ ] **P23-M5-T2** Insert component instance on any page; `ref` + optional overrides
- [ ] **P23-M5-T3** Update component → recompile all pages using it
- [ ] **P23-M5-T4** Built-in starters: `header-nav`, `footer-simple`
- [ ] **P23-M5-T5** Tests: component instance compile + sync

### P23-M6 — Media + inline text

- [ ] **P23-M6-T1** Media picker modal — browse CMS media, insert URL + alt
- [ ] **P23-M6-T2** Double-click text element → inline contentEditable on canvas
- [ ] **P23-M6-T3** Image element: object-fit, lazy, optional link wrapper
- [ ] **P23-M6-T4** Tests: media URL validation (no javascript:)

### P23-M7 — Advanced Birkly blocks

- [ ] **P23-M7-T1** Conditional block UI — `{if field}` / `{else}` with field picker
- [ ] **P23-M7-T2** Template function block — name + args from discovered plugin TFs
- [ ] **P23-M7-T3** Sub-entry loop block — `{for each entry in 'images' of 'projects'.current}`
- [ ] **P23-M7-T4** List options — sort field, limit, filter builder
- [ ] **P23-M7-T5** Compiler + SSR support for all new block types
- [ ] **P23-M7-T6** Tests: compile + `StudioRenderer` output for each block type

### P23-M8 — Plugin block shelf

- [ ] **P23-M8-T1** `core/studio_blocks.php` — `register_studio_block($id, $def)`
- [ ] **P23-M8-T2** Block def: label, icon, category, `canvas_defaults`, `compile( $el )`, optional `preview_css`
- [ ] **P23-M8-T3** Commerce blocks: product grid, add-to-cart, price display
- [ ] **P23-M8-T4** Events blocks: upcoming list, event card, registration CTA
- [ ] **P23-M8-T5** User blocks: login form, register form, `{if user_logged_in}` shell
- [ ] **P23-M8-T6** Tests: block registration, compile with plugin active/inactive

### P23-M9 — Section library + page templates

- [ ] **P23-M9-T1** Save section to `library/sections/{id}.json`
- [ ] **P23-M9-T2** Insert from library into canvas
- [ ] **P23-M9-T3** Page templates on create — landing, blog index, shop catalog, about
- [ ] **P23-M9-T4** Import P22 v1 project → v2 canvas upgrade path
- [ ] **P23-M9-T5** Tests: library round-trip

### P23-M10 — History + code parser

- [ ] **P23-M10-T1** Undo/redo stack (canvas mutations, 50 steps)
- [ ] **P23-M10-T2** Revision snapshots on publish — `revisions/{n}.json` + restore UI
- [ ] **P23-M10-T3** Structured HTML parser — preserve sections/columns when possible (not raw-body fallback)
- [ ] **P23-M10-T4** Tests: undo stack, revision restore, parser fixtures

### P23-M11 — SSR, export, docs

- [ ] **P23-M11-T1** SSR parity sprint — fix gaps from P23-M7/M8 blocks
- [ ] **P23-M11-T2** `StudioCssOptimizer` — scan HTML+canvas for classes → prune kit CSS
- [ ] **P23-M11-T3** Export ZIP includes optimized CSS
- [ ] **P23-M11-T4** Birkly-Docs — `04-studio/advanced.md` (responsive, components, blocks)
- [ ] **P23-M11-T5** V-STU-02 video — build responsive commerce page with global header

### P23-M12 — Closure

- [ ] **P23-M12-T1** Matrix STU-PRO-01…20 green
- [ ] **P23-M12-T2** `tests/run.php` green; P21 perf regression green
- [ ] **P23-M12-T3** `P23-phase-closure-checklist.md`
- [ ] **P23-M12-T4** Single human notification

---

## Acceptance criteria (phase)

1. Build a **3-page responsive site** with global header/footer, blog loop, commerce product grid block, and mobile layout overrides — entirely in Studio admin.
2. **Drag-drop** reorder sections and columns; **layers panel** reflects structure.
3. **Publish SSR** — all dynamic blocks render without `birkly-client.js` on first paint.
4. **Export ZIP** — optimized CSS, smaller than full kit; hand-editable HTML.
5. **Deactivate plugin** — SSR 503; project files intact.
6. **Upgrade** — open existing P22 v1 project without data loss.
7. Values check: dependency-free, self-contained plugin, UI decoupled.

---

## Dependencies

```
P22 complete
  → P23-M0
  → M1 → M2 → M3 ∥ M4 (M4 can start after M1)
  → M5, M6 (after M2)
  → M7 → M8 (M8 parallel after M7 block types defined)
  → M9, M10 (after M7)
  → M11 → M12
```

---

## Out of scope (v1 of P23 — defer P24)

- Theme-wide site settings outside project (multi-site manager)
- Popup/modal builder
- Animation timeline / interactions builder
- A/B testing
- Full Tailwind JIT (npm) — export prune only
- Real-time multi-user editing
- Marketplace block store (use plugin shelf first)

---

## Values check (planned)

| Value | Alignment |
|-------|-----------|
| Dependency-free | Vendored DnD/editor libs in plugin; PHP CSS optimizer |
| UI decoupled | Canvas admin vs compiled HTML |
| Self-contained | Extends `plugins/studio/`; block shelf is thin core registry |
| Platform-first | Blocks reuse page_bundle + template tags |

**Trade-off:** P23-D6 adds `register_studio_block()` to core — shared shelf for commerce/events/user blocks (same pattern as P22 `register_public_route`).
