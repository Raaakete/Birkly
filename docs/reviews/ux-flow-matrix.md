# UX Flow Matrix — maintained from P2-M1 baseline

**Last updated:** 2026-06-18 (post P2-M2-T2 fixes, PRs #16–#17)  
**Environment:** PHP 8.3.6, `localhost:8080`, demo content from `create-demo-content.php`  
**Automated:** `./scripts/ci-check.sh` PASS · `php tests/run.php` PASS (5 suites)

## Matrix

| Flow | Area | Verdict | Phase 2 | Evidence / notes |
|------|------|---------|---------|------------------|
| Admin login | Auth | **pass** | — | Unchanged from P2-M1 baseline. |
| Onboarding wizard (fresh user) | Onboarding | **partial** | P2-M5 | P2-R06/R07 — demo content skips forced wizard; completion state unset. |
| Create collection + fields | Collections | **pass** | — | P2-R12 fixed: `create_collection` accepts `fields`, `schema.fields`, or bare field list (`collection_schema_test.php`). |
| Create/edit/publish entry | Entries | **pass** | — | P2-R03 Select flat options render (`select_options_test.php`). P2-R11 slug preserved on title-only update (`entry_slug_test.php`). Legacy `editor`/`media` resolve via aliases (#16). |
| ContentEditor visual ↔ HTML save/reload | ContentEditor | **pass** | — | Visual/HTML tabs in fieldtype; `sync-manager.js` round-trip; `EditorModes` dead paths removed from entry-editor (#19). |
| Media upload + pick in field | Media | **pass** | — | P2-R02: `media` alias → `file` fieldtype; upload API unchanged (P2-M1 pass). |
| Collection groups + drag/drop | Groups/DnD | **partial** | **P2-M4** | P2-R08–R10 unchanged; browser QA deferred to P2-M4. |
| Settings (domains, plugins) | Settings | **pass** | — | Unchanged from P2-M1 baseline. |
| Public API list collections | API | **pass** | — | `list_collections` 200; P2-R15 (`contact` id null) deferred to P2-M6. |
| Demo website / client render | Client | **partial** | P2-M6 | Static demo HTML OK; hydration/browser not re-tested this pass. |
| page_bundle / page_snapshot | M4 APIs | **partial** | P2-M6 | P2-R13/R14 unchanged; bundle works, snapshots 404 in dev. |

## Summary counts

| Verdict | P2-M1 | Post P2-M2-T2 |
|---------|-------|---------------|
| pass | 4 | 8 |
| partial | 6 | 3 |
| fail | 1 | 0 |

## P2-M2 P1 register status

| ID | Status | PR |
|----|--------|-----|
| P2-R01 | fixed | #16 |
| P2-R02 | fixed | #16 |
| P2-R03 | fixed | #16 |
| P2-R11 | fixed | #17 |
| P2-R12 | fixed | #17 |

## Gate

**P2-M2-T3 matrix re-run:** P1 entry/collection rows green. Remaining partial rows tracked in P2-M3–M6. No P0 blockers.
