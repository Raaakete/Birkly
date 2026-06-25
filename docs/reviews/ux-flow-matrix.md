# UX Flow Matrix — maintained from P2-M1 baseline

**Last updated:** 2026-06-25 (post Phase 2 verification + template renderer hotfix)  
**Environment:** PHP 8.3.6, `localhost:8080`, demo content from `create-demo-content.php`  
**Automated:** `./scripts/ci-check.sh` PASS · `php tests/run.php` PASS (15 suites)  
**Browser QA:** Onboarding, ContentEditor, groups DnD, client snapshot API — PASS (2026-06-25)

## Matrix

| Flow | Area | Verdict | Phase 2 | Evidence / notes |
|------|------|---------|---------|------------------|
| Admin login | Auth | **pass** | — | Unchanged from P2-M1 baseline. |
| Onboarding wizard (fresh user) | Onboarding | **pass** | P2-M5 | `reset-onboarding.php --keep-entries` → wizard loads; skip/complete sets dashboard checklist (#25). |
| Create collection + fields | Collections | **pass** | P2-M2 | `collection_schema_test.php`; create_collection accepts multiple schema shapes. |
| Create/edit/publish entry | Entries | **pass** | P2-M2/M3 | Select options, slug preservation, legacy `editor`/`media` aliases (#16–#17). |
| ContentEditor visual ↔ HTML save/reload | ContentEditor | **pass** | P2-M3 | Visual/HTML tabs; `html_roundtrip_test.php`; browser QA on blog entry (#19–#24). |
| Media upload + pick in field | Media | **pass** | P2-M3 | `media` alias → `file` fieldtype (#16). |
| Collection groups + drag/drop | Groups/DnD | **pass** | P2-M4 | `collection_groups_test.php`; browser QA reorder groups after refresh (#20). |
| Settings (domains, plugins) | Settings | **pass** | — | Unchanged from P2-M1 baseline. |
| Public API list collections | API | **pass** | P2-M6 | `public_collection_meta_test.php` — contact `id` fallback (#26). |
| Demo website / client render | Client | **pass** | P2-M6/M7 | `page_snapshot_test.php`; browser/curl `page_snapshot.php?page=index` → `status: ok`. |
| page_bundle / page_snapshot | M4 APIs | **pass** | P2-M6 | Snapshots generated via `scripts/generate-page-snapshots.php`; dev smoke documented in P2-M7 notes. |
| CMS update check UI | Ops | **not tested** | P3-M2 | Alpha row — pending P3-M2-T1 |
| Backup create/restore | Ops | **not tested** | P3-M2 | Alpha row — pending P3-M2-T2 |
| Alpha feedback submit → admin | Feedback | **not built** | P3-M3 | Alpha row — pending P3-M3 |
| Help home + doc search | Help | **not built** | P3-M4 | Alpha row — pending P3-M4 |

## Summary counts

| Verdict | P2-M1 | Post P2-M2 | Post Phase 2 verification |
|---------|-------|------------|---------------------------|
| pass | 4 | 9 | **11** |
| partial | 6 | 2 | **0** |
| fail | 1 | 0 | **0** |

## Register closure (P2-M1 → P2-M6)

| ID | Status | PR |
|----|--------|-----|
| P2-R01–R05 | fixed | #16–#24 (ContentEditor) |
| P2-R06–R07 | fixed | #25 (onboarding) |
| P2-R08–R10 | fixed | #20 (groups DnD) |
| P2-R11–R12 | fixed | #16–#17 |
| P2-R13–R15 | fixed | #26 (snapshots, metadata) |

## Gate

**Phase 2 UX gate:** All matrix rows pass. Automated + browser QA complete 2026-06-25.

**Post-merge hotfix:** P2-M6 safe renderer was blocking legacy admin PHP (`require_once`, `include`, `required` keys). Fixed by validating parser output only; trusted template PHP blocks excluded from validation.
