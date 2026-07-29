# Phase 50 — Content model clarity, docs SSOT, fieldtype QA, collection editor UX

**Status:** Planned (2026-07-29) — awaiting human approval, then lead-engineer execution  
**Human decision (locked):** Keep **collections + entries** as the only content primitives. No singleton/document type for now. Improve **understanding** via docs and **quality** via admin UX and fieldtype fixes.

**Artifacts:** `P50-gap-register.md` · `P50-verification-matrix.md` · `P50-lead-engineer-handoff.md`

---

## Problem statement

1. **Concept clarity** — The collection/entry model is sound but not surfaced as one coherent story (mental model, when to use what, site-elements pattern, sub-collections, public forms). Help topics split across entries, admin editors, and legacy PHP copies.
2. **Docs SSOT drift** — Birkly-Docs is declared SSOT, but CMS still carries `docs/help-manifest.json` (17 topics), `admin/docs/*.php` (orphan HTML), partial snapshot reuse (`entries.md` shared by unrelated topics), and sync script gaps (`ai_topic` dropped in offline manifest).
3. **DynamicItems broken in entry editor** — Users cannot add blocks; likely multilang ID/onclick mismatch plus duplicate init in PHP bundle.
4. **Collection save feels silent** — Save succeeds with toast-only feedback, no redirect to entries list; focus in language inputs blocks submit with no message; sub-collection bugs (`$index` undefined, nested subs not saved standalone, removed subs preserved on save).
5. **Fieldtype coverage unknown** — 26 built-in types; several have empty `getJavaScript()` vs `fieldtype.js`; no systematic QA matrix.

---

## Goals

| Goal | Success |
|------|---------|
| G1 — Understandable content model | New user can read one overview + follow examples without guessing “collection vs entry” |
| G2 — Single docs source | Human product docs edited only in Birkly-Docs; CMS = fetch + offline mirror, no authoritative PHP/HTML in `admin/docs/*.php` |
| G3 — DynamicItems works | Add Block modal + nested fields work in single-lang and multilang collections |
| G4 — Collection save trust | Unmissable success state; sensible post-create navigation; sub-collection parity |
| G5 — Fieldtype confidence | Matrix green for all built-in types in collection editor + entry editor smoke |

---

## Architecture — docs (target)

```
Birkly-Docs (SSOT)
  *.md topics + help-manifest.json + npm run build → docs.birkly.com
        │
        ├─ Live: CMS fetches help-manifest.json on help_manifest.php request
        │
        └─ Release: scripts/sync-birkly-docs.sh
              → admin/docs/snapshots/*.md
              → admin/docs/help-offline-manifest.json
              → docs/help-manifest.json (NEW: synced from Birkly-Docs, not hand-edited)

CMS admin Help
  help.js → snapshot markdown + "Read full doc" → docs.birkly.com

CMS AI/MCP (unchanged)
  docs/ai/playbooks/ — operational agent docs, link to Birkly-Docs via ai_topic
```

**Remove / deprecate:** `admin/docs/collections.php`, `fieldtypes.php`, `website-integration.php`, etc. — not wired to Help v2; confuse grep and playbooks.

**Keep in CMS repo:** `docs/TEMPLATE_LANGUAGE_SPEC.md`, `docs/ai/playbooks/` (agent workflows, not duplicate user guides).

---

## Milestones

### P50-M1 — Birkly-Docs: content model hub (docs-writer, Birkly-Docs)

Create and cross-link a **content model overview** that is the canonical “start here” for structure:

| Deliverable | Path (Birkly-Docs) |
|-------------|-------------------|
| Content model overview | `02-content/content-model-overview.md` (+ `.html` via build) |
| Expand collections topic | `02-content/collections.md` — link overview, decision tree |
| Expand entries topic | `02-content/entries.md` — relationship to collections |
| Site elements / chrome pattern | `02-content/site-elements-pattern.md` (or section in overview) |
| Dynamic items field | `02-content/dynamic-items-field.md` |
| Sub-collections primer | tighten `02-content/sub-collections.md` with parent→child diagram |
| Public forms link-back | cross-link `public-forms` integration doc |

**Overview must include:**

- **Two-level model:** collection = type/schema, entry = one item
- **Decision tree:** many of same shape → collection + list; shared chrome → `site-elements` collection + named entries + `<birkly-region>`; belongs to one parent → sub-collection; visitor submits → `public_api` collection
- **Worked examples:** blog (list+detail), bakery menu, site header/footer, contact form submissions
- **Best practices:** define schema before entries; stable handles; `published` for live; don’t mix unrelated types in one collection
- **Explicit note:** we intentionally do **not** have a separate “singleton” type — one-off pages are one entry in a collection (often title + dynamic items)

Update `CONTENTS_INDEX.md`, `meta/changelog-docs.md`, `help-manifest.json` (topic `content-model-overview`, `collections` as dedicated Help id).

### P50-M2 — CMS docs sync hardening (cms-developer, Birkly)

| Task | Detail |
|------|--------|
| Extend `sync-birkly-docs-manifest.php` | Copy full manifest → `docs/help-manifest.json`; preserve `ai_topic`, `section`, `version` in offline manifest |
| Per-topic snapshots | Stop sharing `entries.md` for `collection-groups`; map each topic to its own snapshot file |
| CI note | Document in `scripts/sync-birkly-docs.sh` header: run before CMS release when Birkly-Docs manifest changes |
| Deprecate legacy PHP docs | Add `admin/docs/README.md` — “legacy, do not edit”; strip from playbook cross-refs in `docs/ai/playbooks/` |

### P50-M3 — DynamicItems fix (cms-developer, Birkly)

| Task | Detail |
|------|--------|
| Multilang rewrite | `birkly_admin_multilang_field_html()` — rewrite `_value` ids, onclick `fieldName`, `data-field-config` name to `lang_field` pattern OR exempt DynamicItems from destructive id rewrite |
| Remove duplicate init | Drop appended `initDynamicItemsField` from `DynamicItems.php::getJavaScript()` |
| Shim selector | Align `fieldtypes-shim.js` with `.block-field` |
| Optional | `getConfigUI()` for allowed block types in collection editor |
| Tests | PHPUnit for multilang HTML rewrite; API test for `render_for_dynamicitems` |

### P50-M4 — Collection editor save UX (frontend-developer + cms-developer, Birkly)

| Task | Detail |
|------|--------|
| Post-create navigation | After **create** collection save: success banner/modal with **“Add first entry”** (→ entry editor) and **“Back to collections”** (→ content overview); optional default redirect to entries list |
| Blocked submit feedback | When focus in `#language-input` / `#sub-collection-language-input`: toast “Click outside language field to save” |
| Success beyond toast | Sticky bar state “Saved”; brief highlight on save button area (design-system pattern from P49 publish feedback) |
| Sub-collection create | Same post-save CTA pattern for `add_sub_collection` |
| Bug: `$index` | `admin_collections.php` `update_sub_collection` response — use `$targetIndex` |
| Bug: nested subs standalone | Process `sub_collections[0][sub_collections]` on standalone sub-collection save |
| Bug: removed subs | When user removes sub-collection rows in UI, POST must signal deletion or replace entire `sub_collections` array |
| Sub-collection field reorder | Wire `reorderContainerId` for sub-collection field list |

### P50-M5 — Fieldtype audit matrix (qa-verifier + cms-developer, Birkly)

Execute `P50-verification-matrix.md` V-FT rows manually + automate where cheap:

- Collection editor: add field of each type, save schema, reload
- Entry editor: render, edit, save, reload for each type
- Document gaps in gap register (Checkbox/Select/Radio/Date empty JS bundle, ContentEditor alias, etc.)
- Fix **blocking** issues found; file **non-blocking** improvements as follow-up gaps

### P50-M6 — Closure (lead-engineer)

- `./scripts/ci-check.sh` green
- Run `sync-birkly-docs.sh` with updated Birkly-Docs manifest
- design-reviewer on collection save success state + entry editor DynamicItems
- Update `docs/roadmap/ROADMAP.md` — P50 complete
- Human notify once

---

## Dependencies

```
P50-M1 (Birkly-Docs content) ──┬──► P50-M2 (sync snapshots/manifest)
                               └──► P50-M6 (docs published)

P50-M3 (DynamicItems) ──► P50-M5 (fieldtype matrix includes dynamicitems)

P50-M4 (collection UX) ──► P50-M5 (sub-collection paths in matrix)

P50-M5 ──► P50-M6
```

**Repo order:** Birkly-Docs M1 can start in parallel with Birkly M3/M4. M2 after M1 manifest updated. CMS PR may depend on Docs PR merged first for sync.

---

## Out of scope (P50)

- New content primitives (singleton/document type)
- Vector DB / Agent Guide changes
- Marketplace / MCP transport
- Full Playwright admin suite (smoke + PHPUnit only unless time permits)

---

## Risks

| Risk | Mitigation |
|------|------------|
| Live manifest fetch fails on install | Offline bundle must be complete after sync |
| Multilang fix breaks other fieldtypes | PHPUnit per fieldtype id rewrite |
| Sub-collection deletion semantics | Explicit `sub_collections` replace vs merge — document in API handler |

---

## Task handoff index

See `P50-lead-engineer-handoff.md` for copy-paste lead prompt and delegation waves.
