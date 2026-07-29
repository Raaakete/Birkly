# Phase 50 — Verification matrix

**Plan:** `P50-content-model-clarity-plan.md`

Legend: **pass** | **fail** | **skip** | **manual**

---

## V-DOC — Birkly-Docs content model documentation

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-DOC-01 | `02-content/content-model-overview.md` exists with decision tree + 3+ examples | File review | docs-writer | pending |
| V-DOC-02 | Overview links to collections, entries, sub-collections, site-elements, public forms | Link check | docs-writer | pending |
| V-DOC-03 | `collections.md` references overview; beginner + advanced sections intact | File review | docs-writer | pending |
| V-DOC-04 | `dynamic-items-field.md` explains Add Block + site-elements use case | File review | docs-writer | pending |
| V-DOC-05 | `help-manifest.json` has `content-model-overview` and `collections` topics | JSON review | docs-writer | pending |
| V-DOC-06 | `CONTENTS_INDEX.md` + `changelog-docs.md` updated | File review | docs-writer | pending |
| V-DOC-07 | `npm run build` succeeds; HTML artifacts generated | CI/manual | docs-writer | pending |

---

## V-SYNC — CMS docs mirror (Birkly)

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-SYNC-01 | `sync-birkly-docs.sh` updates `docs/help-manifest.json` from Birkly-Docs | Run script, diff | cms-developer | pending |
| V-SYNC-02 | Each Help topic has unique snapshot file (no shared `entries.md` for groups) | Manifest + files | cms-developer | pending |
| V-SYNC-03 | Offline manifest preserves `ai_topic` | JSON inspect | cms-developer | pending |
| V-SYNC-04 | Admin Help search finds “content model overview” | Manual admin | qa-verifier | pending |
| V-SYNC-05 | “Read full doc” opens correct `docs.birkly.com` URL | Manual admin | qa-verifier | pending |
| V-SYNC-06 | `admin/docs/README.md` marks `*.php` as legacy | File review | cms-developer | pending |
| V-SYNC-07 | Playbooks no longer cite `admin/docs/collections.php` as primary human doc | Grep | cms-developer | pending |

---

## V-DI — DynamicItems fieldtype

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-DI-01 | Single-lang collection: Add Block opens modal | Manual entry editor | qa-verifier | pending |
| V-DI-02 | Add text block → inner field renders and saves | Manual + reload entry | qa-verifier | pending |
| V-DI-03 | Multilang collection: Add Block works on EN tab | Manual | qa-verifier | pending |
| V-DI-04 | Multilang: saved blocks persist per language | Manual | qa-verifier | pending |
| V-DI-05 | `render_for_dynamicitems` API returns ok for text, textarea, number | PHPUnit | cms-developer | pending |
| V-DI-06 | PHPUnit multilang HTML rewrite for DynamicItems render output | PHPUnit | cms-developer | pending |
| V-DI-07 | No duplicate init errors in browser console on entry load | Manual console | qa-verifier | pending |

---

## V-COL — Collection editor save UX

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-COL-01 | Create new collection → save → success state visible (not toast-only) | Manual | qa-verifier | pending |
| V-COL-02 | Post-create CTA: “Add first entry” navigates to entry editor | Manual | qa-verifier | pending |
| V-COL-03 | Post-create CTA: “Back to collections” navigates to content overview | Manual | qa-verifier | pending |
| V-COL-04 | Focus in language input + Save → informative message | Manual | qa-verifier | pending |
| V-COL-05 | Update existing collection → clear saved feedback | Manual | qa-verifier | pending |
| V-COL-06 | Create sub-collection standalone → save → success + URL has `sub_collection` | Manual | qa-verifier | pending |
| V-COL-07 | `update_sub_collection` JSON response includes correct sub payload (no PHP notice) | Network tab / logs | cms-developer | pending |
| V-COL-08 | Remove sub-collection in UI → save → sub removed from `collection.json` | Manual + file inspect | cms-developer | pending |
| V-COL-09 | Nested sub-collection on standalone editor saves correctly | Manual | cms-developer | pending |
| V-COL-10 | design-reviewer: success pattern matches admin design system | Review | design-reviewer | pending |

---

## V-FT — Built-in fieldtypes (26)

Run in **collection editor** (add to schema, save) and **entry editor** (create entry, fill, save, reload).

| ID | Type | Collection schema save | Entry render/edit/save | Notes | Status |
|----|------|------------------------|------------------------|-------|--------|
| V-FT-01 | text | pending | pending | | pending |
| V-FT-02 | textarea | pending | pending | | pending |
| V-FT-03 | number | pending | pending | | pending |
| V-FT-04 | checkbox | pending | pending | JS bundle gap G50-20 | pending |
| V-FT-05 | toggle | pending | pending | | pending |
| V-FT-06 | select | pending | pending | JS bundle gap | pending |
| V-FT-07 | multiselect | pending | pending | | pending |
| V-FT-08 | radio | pending | pending | JS bundle gap | pending |
| V-FT-09 | date | pending | pending | | pending |
| V-FT-10 | datetime | pending | pending | | pending |
| V-FT-11 | time | pending | pending | | pending |
| V-FT-12 | email | pending | pending | | pending |
| V-FT-13 | phone | pending | pending | | pending |
| V-FT-14 | url | pending | pending | | pending |
| V-FT-15 | color | pending | pending | | pending |
| V-FT-16 | range | pending | pending | | pending |
| V-FT-17 | tags | pending | pending | | pending |
| V-FT-18 | file | pending | pending | | pending |
| V-FT-19 | gallery | pending | pending | | pending |
| V-FT-20 | map | pending | pending | | pending |
| V-FT-21 | markdown | pending | pending | | pending |
| V-FT-22 | code | pending | pending | | pending |
| V-FT-23 | oembed | pending | pending | | pending |
| V-FT-24 | relation | pending | pending | | pending |
| V-FT-25 | content-editor / contenteditor | pending | pending | Alias G50-21 | pending |
| V-FT-26 | dynamicitems | pending | pending | Depends V-DI | pending |

---

## V-CI — Closure

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-CI-01 | `./scripts/ci-check.sh` green | Shell | lead-engineer | pending |
| V-CI-02 | New PHPUnit tests pass | CI | cms-developer | pending |
| V-CI-03 | Gap register items closed or explicitly deferred | Review | lead-engineer | pending |
