# Phase 50 — Lead-engineer handoff (content model clarity & admin quality)

**Status:** Planned (2026-07-29) — **execute after human approves plan PR**  
**Human rule:** Execute P50-M1→M6 autonomously. Notify human **once** at P50-M6 closure.

**Master plan:** `docs/reviews/P50-content-model-clarity-plan.md`  
**Gap register:** `docs/reviews/P50-gap-register.md`  
**Matrix:** `docs/reviews/P50-verification-matrix.md`

**Product lock:** No new content primitives. Collections + entries stay the model. Docs SSOT = **Birkly-Docs only** for human product documentation.

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 50 — Content model clarity, docs SSOT, fieldtype QA, collection editor UX.

Human approved P50 (date TBD). Execute P50-M1→M6 autonomously in ONE continuous run (or two PR waves: Docs then CMS).
Notify human ONCE at P50-M6 with verification matrix green + design-reviewer sign-off on collection save UX.

GOAL:
1. One clear content-model story in Birkly-Docs (overview, examples, best practices).
2. CMS does NOT host authoritative human docs — only syncs from Birkly-Docs (live fetch + offline mirror).
3. DynamicItems fieldtype: Add Block works (single-lang + multilang).
4. Collection/sub-collection save: unmissable feedback + sensible post-create navigation.
5. Fieldtype audit matrix for all 26 built-in types; fix blockers.

DO NOT add singleton/document content types.
DO NOT duplicate full user guides into docs/ai/playbooks/ — link via ai_topic.
DO NOT remove docs/ai/playbooks/ or TEMPLATE_LANGUAGE_SPEC from CMS (agent ops docs stay in CMS).

READ FIRST:
1. docs/reviews/P50-content-model-clarity-plan.md
2. docs/reviews/P50-gap-register.md
3. docs/reviews/P50-verification-matrix.md
4. core/help_docs.php + scripts/sync-birkly-docs-manifest.php
5. api/admin_templates.php (birkly_admin_multilang_field_html)
6. fieldtypes/DynamicItems/ (PHP + fieldtype.js)
7. admin/js/collection-editor-core.js + api/admin_collections.php
8. admin/js/ui-feedback.js + docs/admin-design-system.md (P49 publish feedback patterns)
9. Birkly-Docs: 02-content/*.md, help-manifest.json, DOCS_ARCHITECTURE.md

═══ WAVE 1 — DOCS SSOT (M1 + M2) ═══

P50-M1 (docs-writer, repo: Birkly-Docs):
- Create 02-content/content-model-overview.md — decision tree, blog/site-elements/forms examples, best practices
- Create 02-content/dynamic-items-field.md + site-elements pattern section/page
- Expand collections.md, entries.md, sub-collections.md cross-links
- Add help-manifest topics: content-model-overview, collections (dedicated id)
- npm run build; CONTENTS_INDEX + changelog-docs
- Matrix: V-DOC-01–07

P50-M2 (cms-developer, repo: Birkly):
- Extend sync-birkly-docs-manifest.php → sync docs/help-manifest.json + full ai_topic in offline manifest
- Unique snapshot per topic (fix collection-groups → entries.md reuse)
- admin/docs/README.md deprecating *.php legacy
- Update playbook cross-refs (content-model-playbook, web-project-patterns) → Birkly-Docs paths
- Run ./scripts/sync-birkly-docs.sh after Birkly-Docs M1 merged
- Matrix: V-SYNC-01–07

═══ WAVE 2 — BLOCKERS (M3 + M4) ═══

P50-M3 DynamicItems (cms-developer):
- Fix multilang id/onclick mismatch in birkly_admin_multilang_field_html OR DynamicItems render
- Remove duplicate init from DynamicItems.php getJavaScript()
- Fix fieldtypes-shim selector if needed
- PHPUnit: multilang rewrite + render_for_dynamicitems
- Matrix: V-DI-01–07

P50-M4 Collection editor UX (frontend-developer + cms-developer):
- Post-create success: modal/banner with "Add first entry" + "Back to collections" (not toast-only)
- Toast when submit blocked by language input focus
- Fix admin_collections.php: $targetIndex in update_sub_collection response
- Standalone sub-collection: save nested sub_collections from form
- Sub-collection removal on save (replace semantics, not preserve-only merge)
- Sub-collection field reorder container
- Matrix: V-COL-01–10

═══ WAVE 3 — FIELDTYPE AUDIT (M5) ═══

P50-M5 (qa-verifier + cms-developer):
- Execute V-FT-01–26 matrix manually; log in gap register
- Fix blocking entry-editor failures
- Optional: bundle getJavaScript() for types with fieldtype.js but empty PHP JS (G50-20)
- Matrix: all V-FT rows pass or documented deferral

═══ WAVE 4 — CLOSURE (M6) ═══

P50-M6 (lead-engineer):
- ./scripts/ci-check.sh
- design-reviewer on collection save success + DynamicItems entry UI
- Close gap register
- docs/roadmap/ROADMAP.md → P50 complete
- Human notify once

DELEGATION:
- docs-writer: M1 (Birkly-Docs PR first)
- cms-developer: M2, M3, sub-collection PHP fixes, PHPUnit
- frontend-developer: M4 collection editor JS/CSS success states
- qa-verifier: V-DI, V-COL, V-FT manual matrix
- design-reviewer: V-COL-10 at M6

ACCEPTANCE:
- Non-dev reads content-model overview and understands collections vs entries + when to use site-elements
- DynamicItems Add Block works on cms.birkly.cloud-style multilang site-elements collection
- New collection save → user sees clear success and can add first entry in one click
- No authoritative human doc edits required in admin/docs/*.php
- V-CI green
```

---

## PR strategy

| PR | Repo | Branch suggestion | Contents |
|----|------|-------------------|----------|
| 1 | Birkly-Docs | `cursor/p50-content-model-docs-030a` | M1 only |
| 2 | Birkly | `cursor/p50-content-model-cms-030a` | M2–M6 after Docs merged + sync run |

Single CMS PR acceptable if Docs merged first and sync committed in same CMS PR.

---

## Key file map

| Area | Paths |
|------|-------|
| Docs sync | `scripts/sync-birkly-docs.sh`, `scripts/sync-birkly-docs-manifest.php`, `core/help_docs.php`, `docs/help-manifest.json` |
| Help UI | `admin/js/help.js`, `api/help_manifest.php` |
| DynamicItems | `fieldtypes/DynamicItems/*`, `api/admin_fieldtypes.php`, `api/admin_templates.php` |
| Collection save | `admin/js/collection-editor-core.js`, `api/admin_collections.php`, `admin/templates/collection-editor.html` |
| Sub-collections | `core/sub_entries.php`, `admin_collections.php` add/update_sub_collection |
| Fieldtypes registry | `fieldtypes/FieldRegistry.php`, `api/admin_fieldtypes.php` |
| Agent docs (keep) | `docs/ai/playbooks/content-model-playbook.md`, `web-project-patterns-playbook.md` |

---

## Human approval checklist

- [ ] P50 plan PR merged
- [ ] Confirm post-create redirect default: **modal with CTAs** vs auto-redirect to entries list
- [ ] Confirm deprecating `admin/docs/*.php` (no removal in P50 — README + playbook links only)
