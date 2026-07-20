# Phase 29 — Lead-engineer handoff (Collections & entries excellence)

**Status:** Approved (2026-07-20, Keven) — execute now  
**Human rule:** Execute **P29-M0→M15** autonomously. Notify human **once** at **P29-M15**.

**Master plan:** `docs/reviews/P29-collections-entries-excellence-plan.md`  
**Gap register:** `docs/reviews/P29-gap-register.md`  
**Matrix:** `docs/reviews/P29-verification-matrix.md`

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 29 — Collections & entries excellence.

Human approved P29 (2026-07-20). Execute P29-M0→M15 autonomously in one wave.
Notify human ONCE at P29-M15.

PAUSED: P28 (beta journeys), P26 (Studio creative). Do NOT start those.

READ FIRST:
1. docs/reviews/P29-collections-entries-excellence-plan.md
2. docs/reviews/P29-gap-register.md
3. docs/reviews/P29-verification-matrix.md
4. admin/css/components/tabs.css  ← ROOT CAUSE of empty sidebar
5. admin/templates/entry-editor.html
6. admin/templates/collection-editor.html
7. core/entry_slug.php
8. admin/js/entry-editor.js

═══ CRITICAL BUG (M1–M2) ═══

Right sidebar tabs show labels but EMPTY panels because:
  tabs.css: .tab-content { display: none }
  Editors use: <div class="tab-content"><div class="tab-pane active">

FIX:
- Scope tabs.css OR add content.css override:
  .sidebar-tabs .tab-content { display: block; }
- Scope tab-switch JS to .sidebar-tabs only (not document-wide .tab-pane)

═══ SLUG RULES (M3–M5) — LOCKED BY HUMAN ═══

Location: SIDEBAR ONLY (General / Settings tab). Never in main field loop.

Auto-generation:
1. Slug from title when title is set (normalized [a-z0-9-])
2. NO title → auto title = entry ID (or collection ID); slug = same ID (normalized)
3. User can type CUSTOM slug any time
4. Checkbox below slug: "Auto-generate slug from title" — CHECKED BY DEFAULT
   - Checked: slug follows title/ID rules live
   - Unchecked: manual slug; no silent overwrite on title save (P2-R11)
   - Re-check: regenerate slug from current title or ID

API:
- Extend birkly_resolve_entry_slug() for ID fallback
- Honor auto_slug / sync flag from POST (name TBD in implementation)
- Collection save: same pattern

Reserved fields (sidebar only, M6):
slug, status, published_at, expires_at, reviewer, meta_title, meta_description

═══ MILESTONES ═══

M0:  Gap register + matrix (verify docs)
M1:  Sidebar CSS fix
M2:  Tab JS scoping
M3:  Entry slug UX + sidebar restore
M4:  Collection slug UX parity
M5:  API slug logic (entry + collection)
M6:  Reserved fields filtered from main loop
M7:  Entries list filter + bulk actions
M8:  Collection editor field polish
M9:  Sub-entry editor parity
M10: Entry detail slug/status display
M11: Tests (sidebar + slug)
M12: scripts/admin-content-smoke.sh + CI
M13: Birkly-Docs update
M14: ux-flow-matrix.md refresh
M15: Closure + single notification

DELEGATION:
cms-developer:      M1–M11, M12
design-reviewer:    M1–M4 (admin UI)
docs-writer:        M13
qa-verifier:        M11–M12, M15 matrix

DO NOT:
- Start P28 journey-runner or P26 Studio
- Change marketplace or Studio plugins

CLOSURE:
[ ] Entry + collection sidebars show all tab content
[ ] Slug rules match P29-D6–D10
[ ] Tests green + admin-content-smoke.sh in CI
[ ] Single digest at M15
```

---

## Human approval checklist

- [x] P29 focus on collections & entries (2026-07-20)
- [x] Pause P28 until P29-M15 (2026-07-20)
- [x] Sidebar bug fix approved
- [x] Slug rules: auto from title; ID fallback; custom override; auto checkbox default on (2026-07-20)
- [x] M0–M15 single-wave execution approved (2026-07-20)
