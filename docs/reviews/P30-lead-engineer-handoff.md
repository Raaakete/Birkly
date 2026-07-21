# Phase 30 — Lead-engineer handoff (Admin form system & navigation UX)

**Status:** Approved (2026-07-21, Keven) — execute now  
**Human rule:** Execute **P30-M0→M18** autonomously. Notify human **once** at **P30-M18**.

**Master plan:** `docs/reviews/P30-admin-form-navigation-plan.md`  
**Gap register:** `docs/reviews/P30-gap-register.md`  
**Matrix:** `docs/reviews/P30-verification-matrix.md`

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 30 — Admin form system & navigation UX.

Human approved P30 (2026-07-21). Execute P30-M0→M18 autonomously in one wave.
Notify human ONCE at P30-M18.

PAUSED: P28 (beta journeys), P26 (Studio creative). Do NOT start those.

READ FIRST:
1. docs/reviews/P30-admin-form-navigation-plan.md
2. docs/reviews/P30-gap-register.md
3. docs/reviews/P30-verification-matrix.md
4. admin/css/layout/header.css  ← breadcrumb overflow issue
5. admin/partials/layout_footer.php  ← moveBreadcrumbsToTopbar()
6. admin/css/pages/content.css  ← .field-block horizontal layout
7. admin/css/components/forms.css  ← .form-group patterns to reuse
8. admin/templates/entry-editor.html
9. admin/templates/collection-editor.html

═══ PILLAR A — SMART BREADCRUMBS (M1–M5) ═══

Problem: On laptop screens, breadcrumb "Content / Collection / Long Entry Title"
overflows topbar. Current CSS ellipsizes from the RIGHT (loses entry name).

Required behavior (P30-D2–D5):
- Collapse FROM THE LEFT when space is tight
- Always show MINIMUM last 2 segments: … / CollectionName / EntryName
- "…" is not a link; full path in aria-label on <nav> + title tooltip
- Re-run on ResizeObserver + sidebar collapse (theme.js localStorage toggle)

Implement: admin/js/breadcrumb-truncate.js
Call after moveBreadcrumbsToTopbar() in layout_footer.php

═══ PILLAR B — STACKED FORM LAYOUT (M6–M11) ═══

Problem: .field-block uses 250px label column LEFT of control — squeezes
ContentEditor and wide fieldtypes on laptop (sidebar + editor sidebar + nav).

Required (P30-D6–D9):
- Label ABOVE control at ALL breakpoints (column layout)
- .field-block--error / --success aligned with .form-group in forms.css
- Fieldtypes full-width in .field-control
- entry-editor.js: map API field errors to inline .field-block--error messages

Files: content.css, forms.css, entry-editor.html, collection-editor.html,
entry-editor.js, accessibility.js

═══ PILLAR C — LAYOUT POLISH (M12–M14) ═══

- editor-layout grid min-widths at ~1280px
- Remove dead page-header spacing after breadcrumb hoist
- Optional: smart-table long title truncation (same left-collapse idea)

═══ MILESTONES ═══

M0:  Gap + matrix verify
M1:  breadcrumb-truncate.js
M2:  Breadcrumb CSS (topbar font scale, segment wrappers)
M3:  ResizeObserver + sidebar hook
M4:  a11y tooltip / aria-label
M5:  breadcrumb tests
M6:  field-block column layout
M7:  field-block error/success tokens
M8:  entry + collection editor templates
M9:  fieldtype width audit
M10: API error → field inline messages
M11: settings + plugin form audit
M12: editor-layout laptop tuning
M13: page-header spacing cleanup
M14: table title truncation (if quick)
M15: admin-content-smoke.sh extended
M16: Birkly-Docs
M17: ux-flow-matrix
M18: Closure + single notification

DELEGATION:
cms-developer:      M1–M14
design-reviewer:    M1–M11 (admin UI)
docs-writer:        M16
qa-verifier:        M5, M15, M18 matrix

DO NOT:
- Start P28 or P26
- Change marketplace/Studio product scope

CLOSURE:
[ ] Breadcrumb shows … / collection / entry on narrow topbar
[ ] All field-blocks label-above-control
[ ] Field errors inline where possible
[ ] admin-content-smoke + tests green
[ ] Single digest at M18
```

---

## Human approval checklist

- [x] P30 breadcrumb left-truncation approved (2026-07-21)
- [x] P30 stacked form labels system-wide approved (2026-07-21)
- [x] Pause P28 until P30-M18 (2026-07-21)
- [x] M0–M18 single-wave execution approved (2026-07-21)
