# P25 — Studio production builder (WYSIWYG canvas + template parity + polish)

**Status:** Proposed (2026-07-09) — awaiting human approval  
**Baseline:** P24 complete — `StudioTemplateEngine`, canonical compiler, IDE redesign, binding wizard, nav builder; **152 test suites**  
**Human rule:** Execute P25-M0→M18 autonomously after approval. Notify human **once** at **P25-M18** only.

**Handoff:** `docs/reviews/P25-lead-engineer-handoff.md`  
**Matrix:** `docs/reviews/P25-verification-matrix.md`

---

## Vision

Close the gap between **“IDE that compiles templates”** and **“visual builder that looks and feels like the live site.”**  
After P24 fixed raw `{for each}` tags, P25 makes Studio **production-grade**: WYSIWYG canvas, live client preview, full template parity, pro layout/CSS tools, and commerce-ready blocks.

**Scope:** ~3–4 phases in one wave · **100% `plugins/studio/`** (+ tests, Birkly-Docs).

---

## P24 → P25 gap (what users still feel)

| P24 delivered | Still missing / thin |
|---------------|----------------------|
| SSR renders loops (`StudioTemplateEngine`) | **No live preview pane** in IDE — must open new tab |
| Canvas shows dashed `studio-col-preview` boxes | **Canvas ≠ real page** — kit CSS not applied in editor |
| CSS inspector (element `styles` JSON) | **No `custom.css` rules editor** (selectors, global rules) |
| Binding wizard (loop / filtered / site_element) | **No conditionals, TF, sub-entry** blocks in wizard + SSR |
| Layout: columns + gap steppers | **No box-model drag**, column border resize, align toolbar |
| Nav in `canvas.nav` | **No page SEO** panel (meta, og, favicon) |
| Plugin block shelf exists | **Blocks not reference-quality** (commerce/events markup) |
| Undo stack (canvas only) | **No redo**, no publish revisions UI |
| Open ↗ works | Published pages can still look **unstyled/basic** without section presets |

---

## Locked decisions (proposed)

| ID | Decision |
|----|----------|
| **P25-D1** | Studio-only phase — extend `plugins/studio/` |
| **P25-D2** | **Live preview pane** uses `birkly-client.js` + P21 bundle (canonical data path) |
| **P25-D3** | **Design canvas** applies project `kit.css` + `custom.css` + `generated.css` inside editor |
| **P25-D4** | `StudioTemplateEngine` v2 — conditionals, `{from … get}`, sub-entry loops, template functions (SSR) |
| **P25-D5** | **CSS studio** — visual rules on `styles/custom.css` + element styles (sync both ways) |
| **P25-D6** | **Box model overlay** on selected element (margin/padding drag) |
| **P25-D7** | **Rich text** for text elements — bold/italic/link/lists (no full ContentEditor fork) |
| **P25-D8** | **Content panel** — read-only browse of collections + “insert field” into bindings |
| **P25-D9** | **Page settings** — title, meta description, slug, home flag in `project.json` / page meta |
| **P25-D10** | **Section presets** — hero, features grid, CTA, blog grid (styled, not empty sections) |
| **P25-D11** | Plugin blocks v2 match `reference-projects/coffee` + `yoga` markup |
| **P25-D12** | `scripts/studio-smoke.sh` in CI — render + no raw tags + basic layout CSS present |
| **P25-D13** | No npm at runtime |
| **P25-D14** | Single notification at **P25-M18** |

---

## Milestones (18 — one wave)

| ID | Focus |
|----|--------|
| **P25-M0** | Gap register + canvas fidelity spec + parity matrix for engine v2 |
| **P25-M1** | **Design canvas** — inject project CSS; canvas iframe or shadow DOM styled like live site |
| **P25-M2** | **Live preview pane** — split bottom/right; `birkly-client.js`; refresh on save |
| **P25-M3** | `StudioTemplateEngine` v2 — `{if}`, `{endif}`, `ordered by`, `limit`, nested fields |
| **P25-M4** | Engine v2 — sub-entry loops + template function calls via `page_bundle` |
| **P25-M5** | Binding wizard steps for conditional, TF, sub-entry blocks |
| **P25-M6** | **Box model overlay** — margin/padding handles on canvas selection |
| **P25-M7** | **Column resize drag** + align/distribute toolbar (flex/grid) |
| **P25-M8** | **CSS rules panel** — parse/edit `custom.css`; add selector + properties |
| **P25-M9** | **Class manager** — kit autocomplete; multi-class chips |
| **P25-M10** | **Stylesheet tabs** — Custom \| Generated \| Tokens \| Kit (read-only) |
| **P25-M11** | **Rich text toolbar** — inline format on text/heading elements |
| **P25-M12** | **Section presets library** — hero, features, blog grid, CTA (styled) |
| **P25-M13** | **Page settings** — SEO meta compile into `<head>` |
| **P25-M14** | **Content panel** — collection browser + field inserter |
| **P25-M15** | **Plugin blocks v2** — commerce product card, events list, user login shell |
| **P25-M16** | Redo + revision history UI (publish snapshots) |
| **P25-M17** | `studio-smoke.sh` + Birkly-Docs `04-studio/production-builder.md` + V-STU-04 |
| **P25-M18** | Matrix green, closure, single notification |

**Branches:** `cursor/p25-m[N]-<slug>-030a`

---

## Milestone detail (selected)

### P25-M0 — Planning

- [ ] `docs/reviews/P25-studio-gap-register.md`
- [ ] `docs/reviews/P25-canvas-fidelity-spec.md`
- [ ] Human sign-off P25-D1–D14

### P25-M1 — Design canvas

- [ ] Canvas surface loads compiled HTML fragment + linked project styles (not preview boxes only)
- [ ] Toggle “chrome” vs “design” mode (outlines optional)
- [ ] Selected element ring matches live site typography

### P25-M2 — Live preview pane

- [ ] Split: Visual \| Code \| **Live** (client) \| Split+Live
- [ ] iframe `srcdoc` or preview URL with `birkly-client.js`
- [ ] Auto-refresh on compile/save; draft uses `birkly_studio_preview=1`

### P25-M3–M4 — Template engine v2

- [ ] Parity rows from `P20-js-php-parity-matrix.md` marked for Studio SSR
- [ ] Tests: conditional, filtered+ordered loop, `{from get}`, sub-entry
- [ ] TF blocks: bundle includes `template_function` requests

### P25-M6–M7 — Layout pro

- [ ] Box model drag → updates `element.styles` + optional utility classes
- [ ] Column width drag on grid; flex align buttons in toolbar

### P25-M8–M10 — CSS studio

- [ ] Rules list UI; bi-directional with code tab on `custom.css`
- [ ] Sanitize CSS (no `expression`, `javascript:`)

### P25-M11 — Rich text

- [ ] Floating toolbar on text selection: B, I, U, link, list
- [ ] Stores HTML in `element.content` (sanitized subset)

### P25-M12 — Section presets

- [ ] `plugins/studio/sections/*.json` — hero, features-3col, blog-grid, cta-banner
- [ ] One-click insert from palette; uses bootstrap/tailwind-lite classes

### P25-M13 — Page settings

- [ ] Sidebar panel: meta title, description, canonical slug
- [ ] Compiler writes `<meta name="description">`, `<title>`

### P25-M14 — Content panel

- [ ] List collections + entry count; expand to see fields
- [ ] “Insert field” → adds `{ field }` to selected text or binding template

### P25-M15 — Plugin blocks v2

- [ ] Commerce: product grid block HTML matches reference-projects/coffee
- [ ] Events: upcoming list matches reference-projects/yoga
- [ ] SSR renders block output (engine v2)

### P25-M17 — QA & docs

- [ ] `scripts/studio-smoke.sh` — create fixture project, publish, curl assertions
- [ ] V-STU-04 video: design canvas + live preview + styled publish

---

## Acceptance criteria

1. Edit in Studio **looks like the live site** (kit CSS on canvas).
2. **Live preview pane** shows real collection data without leaving admin.
3. Binding wizard supports **conditional + TF + sub-entry**; SSR matches client on reference patterns.
4. **Box model + column resize** adjust layout without code.
5. **custom.css rules panel** edits global styles; visible on publish.
6. Insert **hero preset** → published page is styled (not empty boxes).
7. `studio-smoke.sh` green; no `{for each` in output; P21 perf tests green.

---

## Out of scope (P26+)

- Figma import
- Animation timeline
- Popup/funnel builder
- Multi-user realtime
- Full Tailwind JIT / npm build
- Marketplace block store UI

---

## Dependencies

```
P24 complete
  → M0 → M1 ∥ M3 (canvas + engine can parallel after M0)
  → M2 (after M1)
  → M4 → M5
  → M6 → M7
  → M8 → M9 → M10
  → M11 → M12
  → M13 → M14
  → M15 (after M4)
  → M16 → M17 → M18
```

---

## Values check

| Value | Alignment |
|-------|-----------|
| Self-contained | All in `plugins/studio/` |
| Platform-first | Live preview uses `birkly-client.js` + `page_bundle` |
| UI decoupled | Editor chrome vs compiled HTML |
| Dependency-free | No npm runtime |
