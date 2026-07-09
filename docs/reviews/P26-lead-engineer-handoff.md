# Phase 26 — Lead-engineer handoff (Studio creative suite & design bridge)

**Status:** Proposed (2026-07-09) — awaiting human approval  
**Prerequisite:** **P25-M18 closure on `main`** — do not start P26 until P25 verification matrix is green  
**Human rule:** Execute **P26-M0→M16** autonomously after approval. Notify human **once** at **P26-M16** only.

**Master plan:** `docs/reviews/P26-studio-creative-design-plan.md`  
**Matrix:** `docs/reviews/P26-verification-matrix.md`

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 26 — Studio creative suite & design bridge.

PREREQUISITE: P25 must be complete on main (P25-M18 closure, studio-smoke.sh green).
Human approved P26. Execute P26-M0→M16 autonomously in one wave.
Notify human ONCE at P26-M16 closure only.

READ FIRST:
1. docs/reviews/P26-lead-engineer-handoff.md
2. docs/reviews/P26-studio-creative-design-plan.md
3. plugins/studio/ — StudioCompiler, studio-ide.js, StudioTemplateEngine (post-P25)
4. docs/reviews/P25-studio-production-builder-plan.md — what P25 delivered
5. docs/PROJECT_VALUES.md — dependency-free runtime

MISSION:
P25 made Studio production-grade. P26 adds deferred creative features:
- Animation timeline (keyframes, easing, scroll/click triggers) → compiled CSS + vanilla JS
- Popup/modal builder with triggers (click, delay, exit intent)
- Figma import (JSON upload) → frames as sections, styles as tokens
- Light funnel flows (multi-page stepper)
- studio-creative-smoke.sh CI

NOT IN P26 (defer P27+):
- Multi-user realtime
- Marketplace block store UI
- Full Tailwind JIT / npm build

MILESTONES M0–M16 — see master plan
Branches: cursor/p26-m[N]-<slug>-030a

DELEGATION:
cms-developer:      M1, M8, M12–M14, M15 smoke, compiler + SSR
frontend-developer: M2–M7, M9–M11, M13, timeline + popup + Figma UI
design-reviewer:    M2 timeline UX, M6 popup a11y, M11 import preview (mandatory)
docs-writer:        M15
qa-verifier:        M16

CLOSURE:
[ ] Scroll-triggered animation on published page
[ ] Popup opens on click; Esc closes; focus trapped
[ ] Figma import creates styled section
[ ] Funnel stepper links pages
[ ] studio-creative-smoke.sh + studio-smoke.sh green
[ ] Single digest notification
```

---

## Human approval checklist

- [ ] P26-D1–D14 accepted
- [ ] P25 complete on `main` confirmed
- [ ] M0–M16 single-wave execution approved
