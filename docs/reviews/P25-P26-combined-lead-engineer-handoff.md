# Phases 25 + 26 — Combined lead-engineer handoff

**Status:** Approved (2026-07-09, Keven) — execute now  
**Execution model:** **Single autonomous run** — P25-M0→M18 then P26-M0→M16 without pausing for human approval  
**Notification:** Notify human **once** at **P26-M16** closure only (not at P25-M18)

**Plans:** `docs/reviews/P25-studio-production-builder-plan.md` · `docs/reviews/P26-studio-creative-design-plan.md`  
**Matrices:** `docs/reviews/P25-verification-matrix.md` · `docs/reviews/P26-verification-matrix.md`

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phases 25 + 26 — Studio production builder AND creative suite.

Human approved a SINGLE RUN covering P25-M0→M18 and P26-M0→M16.
Notify human ONCE at P26-M16 closure only (do not send a separate P25 notification).

READ FIRST:
1. docs/reviews/P25-P26-combined-lead-engineer-handoff.md (this file)
2. docs/reviews/P25-studio-production-builder-plan.md
3. docs/reviews/P26-studio-creative-design-plan.md
4. plugins/studio/ — studio-ide.js, StudioCompiler, StudioTemplateEngine
5. reference-projects/blog/, coffee/, yoga/
6. docs/reviews/P20-js-php-parity-matrix.md
7. birkly-client.js · api/page_bundle.php
8. docs/PROJECT_VALUES.md

BASELINE CHECK:
If P25 is already on main (closure checklist green, studio-smoke.sh exists), verify
all P25 matrix rows pass and proceed directly to P26-M0. If any P25 item is missing,
complete it before starting P26.

═══ PHASE 25 — Production builder (M0→M18) ═══

MISSION: Make Studio feel like a REAL visual builder:
- Design canvas with project CSS (not dashed preview boxes only)
- Live preview pane with birkly-client.js inside IDE
- StudioTemplateEngine v2: conditionals, TF, sub-entries
- Box model overlay + column resize + align toolbar
- CSS rules panel on custom.css + class manager + stylesheet tabs
- Rich text toolbar, section presets (styled hero/blog/CTA)
- Page SEO settings, content panel, plugin blocks v2
- Redo + revisions, scripts/studio-smoke.sh CI

Branches: cursor/p25-m[N]-<slug>-030a

═══ PHASE 26 — Creative suite (M0→M16) — continue immediately ═══

MISSION: Add deferred creative features (dependency-free runtime):
- Animation timeline → @keyframes in generated.css + scroll/click triggers
- Popup/modal builder (click, delay, exit intent) + a11y focus trap
- Figma import (JSON upload) → frames as sections, styles as tokens
- Light funnel flows (multi-page stepper)
- scripts/studio-creative-smoke.sh CI

NOT IN THIS RUN (P27+): multi-user realtime, marketplace block store UI, Tailwind JIT

Branches: cursor/p26-m[N]-<slug>-030a

DELEGATION:
cms-developer:      P25 M3–M5, M13–M15, M17; P26 M1, M8, M12–M15
frontend-developer: P25 M1–M2, M6–M12, M14, M16; P26 M2–M7, M9–M11, M13
design-reviewer:    P25 M1 canvas + M12 presets; P26 M2 timeline, M6 popup a11y, M11 Figma (mandatory)
docs-writer:        P25 M17 + P26 M15
qa-verifier:        P26 M16 (final closure for both phases)

COMBINED CLOSURE (P26-M16):
[ ] P25 verification matrix green + P25-phase-closure-checklist.md
[ ] Canvas shows styled layout; live preview pane renders collection data
[ ] Engine v2 parity tests pass; studio-smoke.sh green
[ ] Scroll-triggered animation on published page
[ ] Popup opens on click; Esc closes; focus trapped
[ ] Figma import creates styled section
[ ] Funnel stepper links pages
[ ] studio-creative-smoke.sh green; CI suites PASS
[ ] ROADMAP updated (P25 + P26 complete)
[ ] Single digest notification
```

---

## Human approval checklist

- [x] P25-D1–D14 accepted (2026-07-09)
- [x] P26-D1–D14 accepted (2026-07-09)
- [x] P25-M0→M18 + P26-M0→M16 single-run execution approved (2026-07-09)
- [x] Single notification at P26-M16 only (2026-07-09)
