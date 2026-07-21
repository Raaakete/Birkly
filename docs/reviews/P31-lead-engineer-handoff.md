# Phase 31 — Lead-engineer handoff

**Status:** Approved (2026-07-21, Keven)  
**Human rule:** Execute P31-M0→M18 autonomously. Notify once at P31-M18.

**Master plan:** `docs/reviews/P31-admin-responsive-dashboard-plan.md`  
**Gap register:** `docs/reviews/P31-gap-register.md`  
**Matrix:** `docs/reviews/P31-verification-matrix.md`

## M0 boundary

P30 product code is already on `main` in #178–#181. M0 is documentation-only:

- Record the P30 matrix as green.
- Complete the P30 closure checklist.
- Verify the roadmap records P30 as complete and P31 as active.
- Create the P31 gap register, matrix shell, and closure checklist.

Do not reimplement P30, start P28 or P26, change marketplace scope, add `eval()`, or add widget drag-and-drop.

## P31 implementation contract

- Widget API v2 fields: `size`, `priority`, `endpoint`, `mount`, and `refresh`.
- Mount widgets through named handlers; do not execute widget-supplied JavaScript strings.
- Render every dashboard widget as `.card.dash-widget`.
- Build the responsive dashboard 3 → 2 → 1 columns at xl and md.
- Deliver core widgets plus Commerce, Events, Studio, and Automation widgets.
- Preserve a fixed dashboard layout for v1.

## Milestone ownership

| Owner | Scope |
|-------|-------|
| CMS developer | M0–M16 implementation |
| Frontend developer | Dashboard UI and analytics polish |
| Design reviewer | Widget-grid and card-consistency review |
| Docs writer | M17 Birkly-Docs guide and UX-flow matrix |
| QA verifier | M16 regression checks and M18 matrix |
