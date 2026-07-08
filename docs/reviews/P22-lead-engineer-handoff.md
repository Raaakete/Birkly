# Phase 22 — Lead-engineer handoff (Studio plugin)

**Status:** Proposed (2026-07-08) — awaiting human approval  
**Baseline:** P20 + P21 complete — `reference-projects/`, bundle v2, performance budgets, 141+ test suites  
**Human rule:** Execute **P22-M0→M12** autonomously after approval. Notify human **once** at **P22-M12** only.

**Master plan:** `docs/reviews/P22-studio-plugin-plan.md`  
**Matrix:** `docs/reviews/P22-verification-matrix.md`

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 22 — Studio plugin (visual site builder).

Human must approve P22 before execution. After approval, run P22-M0→M12 autonomously.
Notify human ONCE at P22-M12 closure only.

Read first:
- docs/reviews/P22-lead-engineer-handoff.md (this file)
- docs/reviews/P22-studio-plugin-plan.md
- docs/PROJECT_VALUES.md
- docs/reviews/P20-js-php-parity-matrix.md
- docs/reviews/P21-performance-excellence-plan.md
- fieldtypes/ContentEditor/layout/ (reuse patterns — do NOT move Studio into fieldtype)
- reference-projects/README.md (connection model)
- plugins/commerce/ (plugin admin shell reference)

MISSION:
Ship plugins/studio — a deactivatable visual site builder. Projects live INSIDE Birkly
at content/studio-projects/{id}/. Primary users: marketers (visual) AND developers (code tab).
Export is supported; in-CMS copy is source of truth. Public sites default to SSR for fast
first paint; admin preview uses live birkly-client.js (P21 bundle path).

CRITICAL (P22-D1–D12):
- Plugin-only (plugins/studio/) — deactivatable
- Storage: content/studio-projects/ — not outside CMS
- Dual editor: visual canvas + code tab (HTML + Birkly template tags)
- Canvas JSON = editor source; compiled HTML/CSS = publish artifact
- Style kits v1: minimal, bootstrap (vendored), tailwind-lite (pre-built utilities)
- NO npm/webpack at runtime — vendored CSS/JS only
- Thin core shelf: register_public_route() for SSR (P22-M1)
- SSR uses page_bundle + PHP render (parity matrix scope)
- Reuse ContentEditor layout patterns; do not fork CMS into Elementor clone
- Single notification at M12

MILESTONES:
- P22-M0: Gap register + human sign-off on locked decisions
- P22-M1: Core public route shelf + plugin scaffold
- P22-M2: Projects + pages CRUD
- P22-M3: Style kits
- P22-M4: Visual layout editor (sections, grid, flex, elements)
- P22-M5: Style & rules editor
- P22-M6: Birkly bindings + StudioCompiler
- P22-M7: Visual ↔ code dual editor
- P22-M8: Live preview iframe (P21 client)
- P22-M9: SSR public route + StudioRenderer
- P22-M10: Export ZIP
- P22-M11: Docs + video V-STU-01
- P22-M12: Matrix green, tests green, single notification

Branches: cursor/p22-m[N]-<slug>-030a

Delegate:
- cms-developer: plugin PHP, SSR, storage, compiler, core route shelf
- frontend-developer: canvas UI, style inspector, preview iframe
- design-reviewer: Studio admin UX after M4/M5
- docs-writer: Birkly-Docs after M9
- qa-verifier: matrix + tests before M12

Before P22-M12:
- studio_* tests in tests/run.php — green
- Create project → layout → blog binding → SSR → export — manual smoke pass
- Deactivate plugin → SSR 503 — verified
- P21 performance_budget_test.php still green (no regression)
- Video V-STU-01 recorded or evidence doc with reproducible steps
```

---

## Execution order

1. **P22-M0** — Gap register, architecture sign-off
2. **P22-M1** — Core route shelf + `plugins/studio/` shell
3. **P22-M2** — Projects + pages storage
4. **P22-M3** — Style kits (parallel-ready after M2)
5. **P22-M4** ∥ **P22-M5** — Layout editor + style editor (after M3)
6. **P22-M6** — Bindings + compiler (needs M4 element model)
7. **P22-M7** — Dual editor (after M6)
8. **P22-M8** — Live preview (after M6 compile works)
9. **P22-M9** ∥ **P22-M10** — SSR + export (after M6)
10. **P22-M11** — Docs + video
11. **P22-M12** — Closure

---

## Specialist routing

| Milestone | Primary owner |
|-----------|---------------|
| M0, M1, M2, M6, M9, M10 | cms-developer |
| M3, M4, M5, M7, M8 | frontend-developer |
| M4, M5 | design-reviewer (after first UI pass) |
| M11 | docs-writer |
| M12 | qa-verifier + lead-engineer |

---

## Escalation

| Situation | Action |
|-----------|--------|
| SSR parity gap blocks publish | Log in gap register; allow publish with `birkly-client.js` fallback + fix in M9 |
| Tailwind-lite insufficient | Document limitation; do not add npm to core without human approval |
| Core route shelf rejected | Fallback: serve only via `content/studio-projects/*/website/` static files + client |
| Scope creep toward Elementor | Enforce v1 element list in plan; defer to P23 |

---

## Human approval checklist

- [ ] P22-D1–D12 accepted
- [ ] P22-D8 core `register_public_route()` accepted
- [ ] `tailwind-lite` approach accepted (not full Tailwind JIT in v1)
- [ ] Public URL pattern `/site/{slug}/` accepted
- [ ] Execute autonomously P22-M0→M12 after approval
