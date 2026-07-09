# Phase 26 — Studio creative suite plan

**Approved:** 2026-07-09 (Keven) · **Baseline:** P25 production builder on `main`

## Goals

Extend Studio from production builder to **creative suite**: motion, modals, design import, and multi-page funnels — still dependency-free, self-contained in `plugins/studio/`.

## Milestones M0–M16

### M0 — Planning
- Gap register, verification matrix, handoff doc

### M1–M4 — Animation pipeline
- Canvas `creative.animations[]` schema
- `StudioCreative::buildAnimationCss()` → `@keyframes`
- Admin timeline panel (name, target element, trigger, keyframes JSON)
- `studio-creative-runtime.js`: scroll (IntersectionObserver), click, load

### M5–M7 — Popup builder
- Canvas `creative.popups[]`
- Triggers: click (element id), delay (ms), exit intent
- Compiled markup: `role="dialog"`, `aria-modal`, close control
- Runtime: focus trap, Esc to close, body scroll lock

### M8–M11 — Figma import
- `StudioFigmaImporter` accepts `frames[]` or `document.children`
- Maps TEXT → elements, FRAME fills → tokens + section background
- API `import_figma` appends sections + merges `tokens.json`
- IDE modal: paste JSON → import

### M10–M12 — Funnel flows
- Project `funnel.json`: `{ enabled, title, steps: [{ slug, label }] }`
- Compiler emits `<nav class="studio-funnel">` stepper with page links
- Sidebar funnel editor + `get_funnel` / `save_funnel`

### M13 — IDE integration
- Creative sidebar (timeline, popups, funnel)
- Inspector **Creative** tab (quick-add animation/popup for selection)
- `data-studio-el` on compiled elements

### M14–M16 — Quality & closure
- `tests/studio_creative_test.php`
- `scripts/studio-creative-smoke.sh` in CI
- Docs: `plugins/studio/docs/creative-suite.md`
- ROADMAP + verification matrix + single human notification

## Canvas creative schema

```json
{
  "creative": {
    "animations": [{
      "id": "anim-1",
      "name": "fadeUp",
      "targetId": "el-hero",
      "trigger": "scroll",
      "scrollOffset": 0.15,
      "duration": 600,
      "easing": "ease-out",
      "keyframes": { "0%": { "opacity": "0" }, "100%": { "opacity": "1" } }
    }],
    "popups": [{
      "id": "popup-1",
      "title": "Welcome",
      "body": "<p>Hello</p>",
      "trigger": "click",
      "clickTargetId": "el-cta",
      "delayMs": 0,
      "ariaLabel": "Welcome dialog"
    }]
  }
}
```

## Values alignment

| Value | How |
|-------|-----|
| Dependency-free | Vanilla JS runtime, no npm |
| Self-contained | All under `plugins/studio/` |
| UI decoupled | Creative data in canvas JSON; runtime separate from admin chrome |
| Platform-first | Published pages load `studio-creative-runtime.js` from plugin assets |

## Acceptance criteria

1. Publish page with scroll animation → element animates when scrolled into view
2. Click-bound popup opens; Esc closes; focus stays in dialog
3. Figma JSON import adds at least one styled section
4. Funnel with 2+ steps renders linked stepper on published pages
5. `studio-creative-smoke.sh` + full CI green; P25 smoke still green
