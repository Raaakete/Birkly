# P26 — Studio creative suite & design bridge

**Status:** Proposed (2026-07-09) — awaiting human approval  
**Baseline:** P25 complete (required) — design canvas, live preview, engine v2, CSS studio, section presets  
**Prerequisite:** Finish **P25-M0→M18** on `main` before starting P26 execution  
**Human rule:** Execute P26-M0→M16 autonomously after approval. Notify human **once** at **P26-M16** only.

**Handoff:** `docs/reviews/P26-lead-engineer-handoff.md`  
**Matrix:** `docs/reviews/P26-verification-matrix.md`

---

## Vision

After P25 makes Studio a **production-grade visual builder**, P26 adds **motion, conversion, and design import** — the features deferred from P23/P25:

- **Animation timeline** — keyframes, transitions, scroll/viewport triggers
- **Popup & modal builder** — overlays with triggers (click, delay, exit intent)
- **Figma import** — frames → sections, styles → kit tokens
- **Light funnel flows** — multi-step page sequences for campaigns

Still **dependency-free at runtime**: animations and popups compile to CSS + small vanilla JS inside `plugins/studio/`. No npm, no GSAP, no React.

**Explicitly deferred to P27+:** multi-user realtime, marketplace block store UI, full Tailwind JIT build pipeline.

**Scope:** ~2–3 prior “out of scope” waves in one autonomous execution · **100% `plugins/studio/`** (+ tests, Birkly-Docs).

---

## P25 → P26 gap

| P25 delivers | P26 adds |
|--------------|----------|
| Styled design canvas + live preview | **Motion** on canvas elements |
| Section presets (hero, CTA, blog) | **Popups/modals** as first-class blocks |
| `custom.css` rules panel | **Compiled animation CSS** + trigger JS |
| Binding wizard + engine v2 | Funnel pages linking studio routes |
| Plugin blocks v2 (commerce/events) | **Figma → section** import pipeline |
| `studio-smoke.sh` | `studio-creative-smoke.sh` (motion + popup assertions) |

---

## Locked decisions (proposed)

| ID | Decision |
|----|----------|
| **P26-D1** | Studio-only phase — extend `plugins/studio/` |
| **P26-D2** | **Animation model** stored in `canvas.json` per element (`animations[]`) |
| **P26-D3** | Compiler emits `@keyframes` + `animation-*` / `transition-*` into `generated.css` |
| **P26-D4** | **Triggers:** load, scroll-into-view, click, hover — vanilla `IntersectionObserver` + event listeners |
| **P26-D5** | **Popup element type** — overlay panel in canvas; compile to hidden DOM + show/hide script |
| **P26-D6** | Popup triggers: button click, time delay, exit intent (mouse leave viewport top) |
| **P26-D7** | **Figma import** — upload `.fig` export JSON or paste Figma REST node JSON (admin token optional) |
| **P26-D8** | Figma mapping: Frame → section, Auto-layout → grid/flex, fills → CSS vars/tokens |
| **P26-D9** | **Funnel** = `project.json` `funnels[]` linking page slugs; nav stepper block preset |
| **P26-D10** | SSR (`StudioTemplateEngine`) + client (`birkly-client.js`) both render popup shell HTML |
| **P26-D11** | Sanitize imported styles — no `expression()`, `javascript:`, external font URLs without allowlist |
| **P26-D12** | No npm at runtime |
| **P26-D13** | Single notification at **P26-M16** |
| **P26-D14** | **Out of P26:** realtime collab (P27), marketplace block store (P27), Tailwind JIT (P28) |

---

## Milestones (16 — one wave)

| ID | Focus |
|----|--------|
| **P26-M0** | Gap register + animation schema + Figma layer mapping spec |
| **P26-M1** | Animation data model in canvas + `StudioCompiler` CSS emission |
| **P26-M2** | **Timeline panel** — duration, delay, easing, keyframe list UI |
| **P26-M3** | Canvas preview playback (design mode) + live preview sync |
| **P26-M4** | Scroll / viewport triggers (`IntersectionObserver`) |
| **P26-M5** | Hover + click animation triggers |
| **P26-M6** | **Popup block type** — overlay, backdrop, close button, size/position |
| **P26-M7** | Popup trigger editor (click target, delay, exit intent) |
| **P26-M8** | Popup compile + minimal `studio-popups.js` (plugin-scoped, CSP-safe) |
| **P26-M9** | **Figma import wizard** — file upload + JSON paste |
| **P26-M10** | Frame → section mapper + color/type token extraction |
| **P26-M11** | Import review screen — accept/reject sections before merge |
| **P26-M12** | **Funnel config** — multi-page steps in `project.json` |
| **P26-M13** | Funnel stepper section preset + compiler nav links |
| **P26-M14** | SSR + client parity tests for animations/popups |
| **P26-M15** | `studio-creative-smoke.sh` + Birkly-Docs `04-studio/creative-suite.md` + V-STU-05 |
| **P26-M16** | Matrix green, closure checklist, single notification |

**Branches:** `cursor/p26-m[N]-<slug>-030a`

---

## Milestone detail (selected)

### P26-M0 — Planning

- [ ] `docs/reviews/P26-studio-gap-register.md`
- [ ] `docs/reviews/P26-animation-schema.md`
- [ ] `docs/reviews/P26-figma-mapping-spec.md`
- [ ] Human sign-off P26-D1–D14

### P26-M1–M5 — Animation

- [ ] Element `animations: [{ id, type, duration, delay, easing, keyframes, trigger }]`
- [ ] Types: `fade`, `slide`, `scale`, `custom-keyframes`
- [ ] Timeline UI in inspector sidebar (not After Effects — simple list + scrubber)
- [ ] `generated.css` receives `@keyframes studio-anim-{id}`
- [ ] Design canvas plays animation on “Preview motion” toggle

### P26-M6–M8 — Popups

- [ ] New palette item: Popup / Modal
- [ ] Children editable like nested section
- [ ] Triggers stored in `popup.triggers[]`
- [ ] Published page: popup hidden by default; script attaches listeners
- [ ] Accessible: focus trap, Esc close, `aria-modal`

### P26-M9–M11 — Figma import

- [ ] Parse Figma node tree (JSON)
- [ ] Map: FRAME → section, TEXT → text element, RECTANGLE → div + styles
- [ ] Extract fills → `--studio-color-*`; text styles → kit typography tokens
- [ ] Preview diff before writing to `canvas.json`
- [ ] Optional: admin setting `figma_personal_access_token` for URL import (server-side fetch)

### P26-M12–M13 — Funnels

- [ ] `funnels: [{ id, name, steps: [{ pageSlug, label }] }]`
- [ ] Stepper block binds to active funnel step
- [ ] Compile next/prev links between published studio pages

### P26-M14 — Parity

- [ ] `StudioTemplateEngine` passes through popup markup
- [ ] Animation classes present in SSR HTML
- [ ] Client hydration does not strip `data-studio-popup` attributes

### P26-M15 — QA & docs

- [ ] `scripts/studio-creative-smoke.sh` — fixture project with fade + popup; curl/DOM assertions
- [ ] V-STU-05 video: timeline + popup publish + Figma import one section

---

## Acceptance criteria

1. Select element → add fade-in on scroll → published page animates without raw tags.
2. Create popup with CTA → trigger on button click → works on `/site/{slug}/` and live preview pane.
3. Import Figma JSON → at least one frame becomes a styled section (not empty boxes).
4. Define 3-step funnel → stepper shows progress; Next links work across pages.
5. `studio-creative-smoke.sh` green; P25 `studio-smoke.sh` still green; 152+ suites PASS.

---

## Out of scope (P27+)

| Item | Target phase | Rationale |
|------|--------------|-----------|
| Multi-user realtime editing | **P27** | Needs presence/OT layer; not Studio-only |
| Marketplace block store UI in Studio | **P27** | Cross-repo: Birkly + Marketplace registry |
| Full Tailwind JIT / npm build | **P28** | Conflicts with dependency-free core unless opt-in dev-only |
| Git per project | P27+ | Ops feature |
| Remote FTP deploy | P27+ | Ops feature |

---

## Dependencies

```
P25 complete (M18 closure on main)
  → M0
  → M1 → M2 → M3
  → M4 ∥ M5 (after M1)
  → M6 → M7 → M8
  → M9 → M10 → M11 (can start after M0)
  → M12 → M13
  → M14 (after M8, M5)
  → M15 → M16
```

---

## Values check

| Value | Alignment |
|-------|-----------|
| Self-contained | Animations/popups/Figma in `plugins/studio/` |
| Dependency-free | CSS + vanilla JS only at runtime |
| UI decoupled | Timeline/popup UI vs compiled output |
| Platform-first | Funnel links use studio public routes |
