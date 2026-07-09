# Phase 25 — Lead-engineer handoff (Studio production builder)

**Status:** Proposed (2026-07-09) — awaiting human approval  
**Baseline:** P24 complete — SSR engine, binding wizard, nav, 152 suites  
**Human rule:** Execute **P25-M0→M18** autonomously after approval. Notify human **once** at **P25-M18** only.

**Master plan:** `docs/reviews/P25-studio-production-builder-plan.md`  
**Matrix:** `docs/reviews/P25-verification-matrix.md`

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 25 — Studio production builder.

Human approved P25. Execute P25-M0→M18 autonomously in one wave.
Notify human ONCE at P25-M18 closure only.

READ FIRST:
1. docs/reviews/P25-lead-engineer-handoff.md
2. docs/reviews/P25-studio-production-builder-plan.md
3. plugins/studio/ — full codebase (studio-ide.js, StudioTemplateEngine, StudioCompiler)
4. reference-projects/blog/, coffee/, yoga/ — target markup + syntax
5. docs/reviews/P20-js-php-parity-matrix.md
6. birkly-client.js · api/page_bundle.php

MISSION:
P24 fixed raw template tags. P25 makes Studio feel like a REAL visual builder:
- Design canvas with project CSS (not dashed preview boxes only)
- Live preview pane with birkly-client.js inside IDE
- StudioTemplateEngine v2: conditionals, TF, sub-entries
- Box model overlay + column resize + align toolbar
- CSS rules panel on custom.css + class manager + stylesheet tabs
- Rich text toolbar, section presets (styled hero/blog/CTA)
- Page SEO settings, content panel, plugin blocks v2
- studio-smoke.sh CI

CARRY FORWARD: filesystem, tabs, visual/code/split, Open ↗ (P23/P24)

MILESTONES M0–M18 — see master plan
Branches: cursor/p25-m[N]-<slug>-030a

DELEGATION:
cms-developer:     M3–M5, M13–M15, M17 smoke, engine v2
frontend-developer:  M1–M2, M6–M12, M14, M16
design-reviewer:     M1 canvas fidelity, M12 presets (mandatory)
docs-writer:         M17
qa-verifier:         M18

CLOSURE:
[ ] Canvas shows styled layout (kit CSS applied)
[ ] Live preview pane renders collection data
[ ] Engine v2 parity tests pass
[ ] Box model + CSS rules panel usable
[ ] Section preset → publish looks professional
[ ] studio-smoke.sh green
[ ] Single digest notification
```

---

## Human approval checklist

- [ ] P25-D1–D14 accepted
- [ ] M0–M18 single-wave execution approved
