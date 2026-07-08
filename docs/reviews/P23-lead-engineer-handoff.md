# Phase 23 — Lead-engineer handoff (Studio Pro)

**Status:** Proposed (2026-07-08) — awaiting human approval  
**Baseline:** P22 complete — `plugins/studio` v1, SSR, 145 test suites  
**Human rule:** Execute **P23-M0→M12** autonomously after approval. Notify human **once** at **P23-M12** only.

**Master plan:** `docs/reviews/P23-studio-pro-plan.md`  
**Matrix:** `docs/reviews/P23-verification-matrix.md`

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 23 — Studio Pro (advanced visual builder).

Human approved P23 (2026-07-08). Execute P23-M0→M12 autonomously in one wave.
Notify human ONCE at P23-M12 closure only.

READ FIRST:
1. docs/reviews/P23-lead-engineer-handoff.md
2. docs/reviews/P23-studio-pro-plan.md
3. docs/PROJECT_VALUES.md
4. plugins/studio/ (entire v1 codebase — extend, do not rewrite from scratch)
5. docs/reviews/P22-studio-gap-register.md
6. docs/reviews/P20-js-php-parity-matrix.md
7. fieldtypes/ContentEditor/layout/ (grid/flex reuse)
8. plugins/commerce/, plugins/events/, plugins/user_management/ (block sources)

MISSION:
Upgrade Studio from v1 (click-to-add, single column, class-string inspector) to a
PROFESSIONAL visual builder: drag-drop canvas, multi-column layouts, responsive
breakpoints, visual style panel, global components, media picker, advanced Birkly
blocks, plugin-contributed widgets, section library, undo/revisions.

KEEP BIRKLY CONTRACT (P22 carry-forward):
- Projects in content/studio-projects/
- Public output = compiled HTML + CSS + Birkly template tags (NOT JSON runtime)
- SSR default at /site/{slug}/
- Export ZIP supported; in-CMS = source of truth
- No npm/webpack at CMS runtime
- P21 performance budgets must not regress

LOCKED DECISIONS (P23-D1–D12):
P23-D1  Extend plugins/studio/ — v1 projects must upgrade cleanly
P23-D2  Canvas schema v2 — backward-compatible v1 import
P23-D3  Responsive: styles.default | .tablet | .mobile per element
P23-D4  Global components in project components/ folder
P23-D5  Design tokens → CSS variables in project.json
P23-D6  Core register_studio_block() shelf (P23-M8)
P23-D7  Media picker uses existing CMS media API
P23-D8  Export CSS optimizer — PHP class scan, prune kits
P23-D9  SSR parity for all new block types — gaps are bugs
P23-D10 Reuse ContentEditor layout modules for grid/flex
P23-D11 No npm at runtime
P23-D12 Single notification at M12

MILESTONES:
P23-M0  Gap register + canvas v2 schema + block API design
P23-M1  Canvas v2 — drag-drop, layers navigator, multi-select, shortcuts
P23-M2  Multi-column layout editor (grid/flex, column resize)
P23-M3  Responsive breakpoints + @media compile
P23-M4  Visual style panel + design tokens
P23-M5  Global components (header/footer symbols)
P23-M6  Media picker + inline WYSIWYG text
P23-M7  Advanced Birkly blocks (conditionals, TF, sub-entries, sort/limit)
P23-M8  register_studio_block() + commerce/events/user blocks
P23-M9  Section library + page templates + v1→v2 upgrade
P23-M10 Undo/redo + revision snapshots + structured code parser
P23-M11 SSR parity sprint + CSS optimizer + docs + V-STU-02
P23-M12 Matrix green, tests green, closure

Branches: cursor/p23-m[N]-<slug>-030a
CI: ./scripts/ci-check.sh && php tests/run.php

DELEGATION:
cms-developer:     M0, M7, M8, M11 (SSR), M10 (revisions API), core block shelf
frontend-developer: M1–M6, M9, M10 (canvas UI, undo, parser client side)
design-reviewer:   after M1/M4 first pass
docs-writer:       M11 Birkly-Docs
qa-verifier:       M12

CLOSURE (P23-M12):
[ ] STU-PRO-01…20 pass in verification matrix
[ ] Smoke: responsive 3-page site with global header + commerce block + SSR
[ ] P22 v1 project opens and upgrades without data loss
[ ] performance_budget_test.php green
[ ] Export ZIP smaller than full kit CSS (optimizer proof)
[ ] V-STU-02 evidence committed
[ ] Single digest notification

ANTI-PATTERNS:
- Rewriting Studio from scratch — extend v1
- Public pages reading .studio.json — compile only
- Elementor JSON widget runtime on public site
- npm build in plugins/studio runtime
- Skipping SSR for new blocks ("client only") — fix renderer
```

---

## Execution order

1. **P23-M0** — Gap register, canvas v2 schema, block API
2. **P23-M1** — Canvas v2 engine (DnD, layers, shortcuts)
3. **P23-M2** — Multi-column layout
4. **P23-M3** ∥ **P23-M4** — Responsive + visual style panel
5. **P23-M5** ∥ **P23-M6** — Global components + media/WYSIWYG
6. **P23-M7** — Advanced Birkly blocks
7. **P23-M8** — Plugin block shelf + commerce/events/user blocks
8. **P23-M9** — Section library + templates + v1 upgrade
9. **P23-M10** — Undo/revisions + code parser
10. **P23-M11** — SSR sprint + CSS optimizer + docs
11. **P23-M12** — Closure

---

## Human approval checklist

- [ ] P23-D1–D12 accepted
- [ ] P23-D6 `register_studio_block()` core shelf accepted
- [ ] Responsive breakpoint values (576 / 992) accepted
- [ ] Execute autonomously P23-M0→M12 after approval
