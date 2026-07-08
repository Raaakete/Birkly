# P24 — Studio visual excellence (render pipeline + pro editor)

**Status:** Proposed (2026-07-08) — awaiting human approval  
**Baseline:** P23 complete — Studio IDE shell, filesystem, tabs, canvas v2; **149 test suites**  
**Known production defect:** `studio_view.php` / SSR shows **raw template tags** (see P24 render gap register)  
**Human rule:** Execute P24-M0→M20 autonomously after approval. Notify human **once** at **P24-M20** only.

**Handoff:** `docs/reviews/P24-lead-engineer-handoff.md`  
**Matrix:** `docs/reviews/P24-verification-matrix.md`

---

## Vision

Turn Studio into a **full advanced visual editor** with professional UI/UX, proper **layout and CSS tooling**, and a **render pipeline that actually works** on Open project, SSR, and in-editor preview.

**Scope size:** ~4 phases in one wave — render engine, UI/UX overhaul, layout studio, CSS studio, bindings, navigation, QA.

**100% focus:** `plugins/studio/` (+ thin tests in `tests/studio_*`, optional `core/` only if shared template shelf required).

---

## Production defect (from kekaba screenshot)

`birkly.kekaba.com/api/studio_view.php?slug=test-project` shows literal:

```
{for each entry in 'about'}
{title}
{end}{from 'site_elements' get entry 'hero'}
```

**Root causes (confirmed in code):**

| Issue | Location | Fix |
|-------|----------|-----|
| Compiler emits `{end}` | `StudioCompiler::renderBinding()` | Emit canonical `{endfor}` / `{endget}` |
| `{from … get entry …}` closed with `{end}` | same | Use `{endget}` block syntax |
| PHP `TemplateParser` ≠ client syntax | `admin/template_parser.php` | **StudioTemplateEngine** in plugin with client parity |
| Loop fields `{title}` not `{entry.title}` | compiler item templates | Field picker emits canonical field refs |
| `studio_view` SSR silently fails → raw HTML | `StudioRenderer::renderHtml()` catch | Fail loud in preview; parity tests |
| Minimal CSS on published pages | kits + generated.css not applied / weak layout | CSS studio + layout compile |

---

## What P24 delivers

| Track | Deliverable |
|-------|-------------|
| **A — Render** | Canonical compile output; `StudioTemplateEngine`; SSR + Open project show real content |
| **B — UI/UX** | Full admin redesign — panels, typography, icons, density, empty states, toasts |
| **C — Layout** | Visual grid/flex: drag resize, gap, align, distribute, box-model overlay |
| **D — CSS** | Rules editor, class manager, token UI, per-breakpoint styles with live preview |
| **E — Canvas** | Selection chrome, inline text, image handles, binding chips with live labels |
| **F — Data** | Binding wizard: pick collection → fields → sort/filter → preview data |
| **G — Site** | Nav/menu builder; page metadata; publish bar with live URL status |
| **H — QA** | Render integration tests, smoke on real collections, V-STU-03 video |

---

## Locked decisions (proposed)

| ID | Decision |
|----|----------|
| **P24-D1** | **Studio-only phase** — no platform milestones outside `plugins/studio/` except tests/docs |
| **P24-D2** | Compiler emits **canonical** syntax per `reference-projects/` + `TEMPLATE_LANGUAGE_SPEC.md` |
| **P24-D3** | **`StudioTemplateEngine.php`** in plugin — SSR path; do not fork `admin/template_parser.php` |
| **P24-D4** | **Open project ↗** must never show raw `{for each…}` tags — acceptance blocker |
| **P24-D5** | In-editor **preview pane** uses `birkly-client.js` (P21 bundle) for WYSIWYG; SSR for public |
| **P24-D6** | **UI redesign** required — design-reviewer sign-off before M8 |
| **P24-D7** | **CSS control** = visual rules panel + code edit of `styles/custom.css` (sync both ways) |
| **P24-D8** | **Layout tools** reuse ContentEditor grid patterns; extend with drag handles in Studio |
| **P24-D9** | Binding blocks use **field picker** (collection schema aware) — no raw `{title}` default |
| **P24-D10** | Published page includes `birkly-client.js` fallback when SSR parity gap detected (dev banner only) |
| **P24-D11** | No npm at runtime |
| **P24-D12** | Single notification at **P24-M20** |

---

## Canonical binding output (target)

```html
{from 'site_elements' get entry 'hero'}
<h2>{'content'.0.'value'}</h2>
{endget}

{for each entry in 'about' with status = published ordered by newest limit 6}
<article>
  <h3>{ title }</h3>
  <p>{ excerpt }</p>
</article>
{endfor}
```

---

## Milestones (one wave — 20 milestones)

| ID | Focus |
|----|--------|
| **P24-M0** | Render gap register + UI/UX spec + acceptance repro (test-project fixture) |
| **P24-M1** | **Fix StudioCompiler** — canonical `{endfor}`, `{endget}`, field syntax |
| **P24-M2** | **`StudioTemplateEngine`** — PHP renderer aligned with client (loops, get, if, fields) |
| **P24-M3** | **SSR integration tests** — real collections; `studio_view.php` smoke; no raw tags |
| **P24-M4** | **UI shell redesign** — layout, color, spacing, iconography, empty states |
| **P24-M5** | **Toolbar v2** — view switcher, breakpoint bar, zoom, publish status chip |
| **P24-M6** | **Canvas chrome** — selection box, hover outline, drag ghosts, drop indicators |
| **P24-M7** | **Layout tools** — column resize drag, grid tracks, flex direction, gap slider |
| **P24-M8** | **Box model overlay** — margin/padding drag handles on selected element |
| **P24-M9** | **Align & distribute** — flex/grid alignment toolbar |
| **P24-M10** | **CSS rules panel** — add rule, selector, properties; writes `custom.css` |
| **P24-M11** | **Class manager** — attach/detach classes; kit class autocomplete |
| **P24-M12** | **Stylesheet tabs** — edit kit overrides, custom.css, generated.css with preview |
| **P24-M13** | **Binding wizard v2** — collection picker, fields, filter/sort/limit UI |
| **P24-M14** | **Live binding preview** — canvas shows sample data labels; client preview pane |
| **P24-M15** | **Navigation builder** — site menu in `project.json` → compile to header component |
| **P24-M16** | **Page settings** — SEO title, meta description, slug, home page |
| **P24-M17** | **Open project + publish hardening** — rendered output, trailing slash, draft/live badge |
| **P24-M18** | **Plugin blocks polish** — commerce/events cards match reference-project markup |
| **P24-M19** | **E2E smoke + visual checklist** — kekaba-class deploy scenario |
| **P24-M20** | Docs, V-STU-03, matrix green, closure |

**Branches:** `cursor/p24-m[N]-<slug>-030a`

---

## Milestone detail (selected)

### P24-M0 — Planning gate

- [ ] **P24-M0-T1** `docs/reviews/P24-studio-render-gap-register.md` — document screenshot defects
- [ ] **P24-M0-T2** `docs/reviews/P24-studio-ux-spec.md` — wireframes for IDE v2
- [ ] **P24-M0-T3** Fixture project `content/studio-projects/_fixture_render/` for CI
- [ ] **P24-M0-T4** Human sign-off P24-D1–D12

### P24-M1 — Compiler syntax fix

- [ ] **P24-M1-T1** Replace `{end}` → `{endfor}` / `{endget}` in `StudioCompiler`
- [ ] **P24-M1-T2** Default item templates use `{ title }`, `{ slug }`, `{ excerpt }` (canonical spacing)
- [ ] **P24-M1-T3** `{for each … with … ordered by … limit N}` when binding options set
- [ ] **P24-M1-T4** `tests/studio_compiler_canonical_test.php`

### P24-M2 — StudioTemplateEngine

- [ ] **P24-M2-T1** `plugins/studio/includes/StudioTemplateEngine.php`
- [ ] **P24-M2-T2** Support: `{for each entry in 'col'}`, `{endfor}`, `{from 'c' get entry 's'}`, `{endget}`, `{if entry has 'x'}`, `{endif}`, `{ field }`, dot paths
- [ ] **P24-M2-T3** Wire `StudioRenderer::renderHtml()` to engine (not admin TemplateParser)
- [ ] **P24-M2-T4** Map `page_bundle` `into` keys to engine variables (`$blog`, `$about`, entry context)
- [ ] **P24-M2-T5** Parity tests against `P20-js-php-parity-matrix.md` Studio rows

### P24-M3 — SSR integration tests

- [ ] **P24-M3-T1** Seed fixture collections; compile page with bindings
- [ ] **P24-M3-T2** `curl studio_view.php?slug=_fixture_render` — body must contain entry title text, **must not** contain `{for each`
- [ ] **P24-M3-T3** Published `/site/{slug}/` same assertion
- [ ] **P24-M3-T4** Regression test for `{end}` never in compiler output

### P24-M4 — UI redesign

- [ ] **P24-M4-T1** New `studio-ide.css` — dark-friendly panels, consistent with admin tokens
- [ ] **P24-M4-T2** Collapsible sidebar regions: Files | Elements | Layers | Nav
- [ ] **P24-M4-T3** design-reviewer pass

### P24-M5 — Toolbar v2

- [ ] **P24-M5-T1** Visual | Code | Split prominent toggle
- [ ] **P24-M5-T2** Breakpoint + zoom controls
- [ ] **P24-M5-T3** Publish chip: Draft / Live + last published time

### P24-M6 — Canvas chrome

- [ ] **P24-M6-T1** Blue selection ring, resize handles on sections/columns
- [ ] **P24-M6-T2** Drop zone highlights between elements
- [ ] **P24-M6-T3** Layers panel sync bidirectional

### P24-M7 — Layout tools

- [ ] **P24-M7-T1** Drag column borders to resize %
- [ ] **P24-M7-T2** Grid column count stepper + gap slider
- [ ] **P24-M7-T3** Flex direction + wrap toggles

### P24-M8 — Box model overlay

- [ ] **P24-M8-T1** Margin/padding drag on canvas (like browser devtools)
- [ ] **P24-M8-T2** Updates element `styles` + optional utility classes

### P24-M9 — Align & distribute

- [ ] **P24-M9-T1** Toolbar: align left/center/right, justify, align-items
- [ ] **P24-M9-T2** Compile to flex/grid CSS

### P24-M10 — CSS rules panel

- [ ] **P24-M10-T1** Rule list from parsed `custom.css`
- [ ] **P24-M10-T2** Add rule: selector + property grid (margin, padding, color, font, border)
- [ ] **P24-M10-T3** Bi-directional sync with code view of `custom.css`

### P24-M11 — Class manager

- [ ] **P24-M11-T1** Autocomplete from bootstrap/tailwind-lite kits
- [ ] **P24-M11-T2** Multi-class chip UI on inspector

### P24-M12 — Stylesheet tabs

- [ ] **P24-M12-T1** Tabs: Custom | Generated | Tokens | Kit (read-only)
- [ ] **P24-M12-T2** Live style injection in canvas iframe

### P24-M13 — Binding wizard v2

- [ ] **P24-M13-T1** Step UI: collection → template fields → filters → insert
- [ ] **P24-M13-T2** Fetch collection schema from API for field list
- [ ] **P24-M13-T3** Preview snippet before insert

### P24-M14 — Live binding preview

- [ ] **P24-M14-T1** Preview iframe loads compiled HTML + `birkly-client.js`
- [ ] **P24-M14-T2** Split bottom pane: Visual canvas | Live preview toggle
- [ ] **P24-M14-T3** Refresh on save/compile

### P24-M15 — Navigation builder

- [ ] **P24-M15-T1** `project.json` `nav[]` — label, page slug, external URL
- [ ] **P24-M15-T2** Compile into header component or page partial
- [ ] **P24-M15-T3** Visual nav editor sidebar

### P24-M16 — Page settings

- [ ] **P24-M16-T1** Per-page meta: title, description, og:image
- [ ] **P24-M16-T2** Slug rename with link update in nav

### P24-M17 — Open project hardening

- [ ] **P24-M17-T1** Open project ↗ uses rendered URL; verify no raw tags (automated)
- [ ] **P24-M17-T2** LiteSpeed/Apache diagnostics retained; fallback chain documented
- [ ] **P24-M17-T3** Optional `?birkly_client=1` on export for static hosts

### P24-M18 — Plugin blocks polish

- [ ] **P24-M18-T1** Commerce product card matches `reference-projects/coffee/`
- [ ] **P24-M18-T2** Events list matches `reference-projects/yoga/`

### P24-M19 — E2E smoke

- [ ] **P24-M19-T1** Script: create project → bind blog → publish → curl assert rendered
- [ ] **P24-M19-T2** `scripts/studio-smoke.sh` in CI optional gate

### P24-M20 — Closure

- [ ] **P24-M20-T1** Matrix STU-RENDER + STU-UX rows green
- [ ] **P24-M20-T2** Birkly-Docs `04-studio/visual-editor.md`
- [ ] **P24-M20-T3** V-STU-03 video — fix screenshot scenario end-to-end
- [ ] **P24-M20-T4** Single human notification

---

## Acceptance criteria (phase)

1. **Screenshot scenario fixed:** `studio_view.php?slug=test-project` shows real entry titles — **zero** visible `{for each` / `{end}` / `{title}` template literals.
2. **Open project ↗** on kekaba production shows styled, rendered site.
3. **Visual editor:** drag column resize, box model, CSS rules panel, class manager — usable without code.
4. **Code view** still works for all files; split sync preserved.
5. **Binding wizard** inserts canonical tags only.
6. P21 `performance_budget_test.php` green; P23 IDE stories still pass.

---

## Dependencies

```
P23 complete
  → M0 → M1 → M2 → M3 (RENDER — ship first, blocks release)
  → M4 → M5 → M6 (UI shell — parallel after M3)
  → M7 → M8 → M9 (layout)
  → M10 → M11 → M12 (CSS)
  → M13 → M14 (bindings)
  → M15 → M16 → M17 (site)
  → M18 → M19 → M20
```

**Critical path:** M1→M2→M3 before calling phase done — render fix is P0.

---

## Out of scope (P25+)

- Animation timeline / interactions
- Popup builder
- Multi-user realtime
- Full Tailwind JIT build
- Theme marketplace

---

## Values check

| Value | Alignment |
|-------|-----------|
| Self-contained | Engine lives in `plugins/studio/includes/` |
| UI decoupled | Editor chrome vs compiled HTML |
| Platform-first | Reuses `page_bundle`; client preview uses `birkly-client.js` |
| Inspectability | Canonical tags in exported HTML |
