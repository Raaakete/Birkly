# Phase 8 — closure checklist

**Date:** 2026-07-06  
**Branch (Birkly):** `cursor/p8-m6-closure-24b5` (from `main`)  
**Branch (kekaba):** merged `cursor/p8-m2-client-templates-24b5`  
**Branch (Birkly-Docs):** merged `cursor/p8-m2-client-templates-24b5`

## Milestones

- [x] **P8-M0** Roadmap baseline — P7 complete, 92→96 test suites
- [x] **P8-M1** kekaba embed sync + deploy checklist
- [x] **P8-M2** kekaba shop/events demo templates + Birkly-Docs storefront patterns
- [x] **P8-M3** Commerce ops — tax, refunds, order email automation triggers
- [x] **P8-M4** Subscription lifecycle — cancel, status, webhook past_due
- [x] **P8-M5** ContentEditor table cell merge/split (colspan/rowspan)
- [x] **P8-M6** Verification matrix, AI context pack, roadmap closure, human notification

## M6 deliverables

- [x] **P8-M6-T1** `php tests/run.php` — **PASS** (96 suites)
- [x] **P8-M6-T2** `docs/reviews/P8-verification-matrix.md`
- [x] **P8-M6-T3** `docs/ai/birkly-context-pack.md` — cell merge + commerce ops
- [x] **P8-M6-T4** `plugins/commerce/docs/ai-context.md` — tax, refunds, subscriptions
- [x] **P8-M6-T5** Roadmap Phase 8 marked complete
- [x] **P8-M6-T6** Birkly-Docs changelog entry (P8 storefront + events)
- [x] **P8-M6-T7** This checklist — **single human notification artifact**

## Acceptance

- [x] kekaba `Birkly-0.1/` synced with commerce, events, P7 admin assets
- [x] Demo shop + events front-end on kekaba (no full content migration)
- [x] Tax v1: flat default + optional country→rate map; persisted on order
- [x] Refunds v1: full + partial via provider shelf; admin order detail UI
- [x] Automation triggers `commerce_order_receipt` + `commerce_order_admin_notify` on `order_paid`
- [x] Subscriptions v1: records, admin list/detail, cancel, webhook status — no plan upgrades
- [x] ContentEditor merge/split in `fieldtypes/ContentEditor/` only; native colspan/rowspan in saved HTML
- [ ] **Human:** Live deploy smoke per `docs/reviews/P8-deploy-checklist.md`

## PRs merged

| Repo | PR | Milestone |
|------|-----|-----------|
| Birkly | #107 | M0 |
| Birkly | #108 | M3 |
| Birkly | #109 | M4 |
| Birkly | #110 | M1 deploy checklist |
| Birkly | (M5 merged via #108 rebase chain) | M5 |
| kekaba | #9 | M1 embed |
| kekaba | #10 | M2 templates |
| Birkly-Docs | #14 | M2 docs |

## Sign-off

| Role | Verdict |
|------|---------|
| cms-developer | M3–M5 commerce ops, subscriptions, table merge complete |
| frontend-developer | M2 kekaba shop/events demo pages complete |
| docs-writer | M2 + M6 docs and changelog complete |
| qa-verifier | 96 test suites PASS; milestone tests green |
| lead-engineer | Phase 8 closure ready — human notified once |

## References

- Spec: `docs/reviews/P8-reference-commerce-plan.md`
- Matrix: `docs/reviews/P8-verification-matrix.md`
- Deploy: `docs/reviews/P8-deploy-checklist.md`
- Docs: `Birkly-Docs/03-templating/storefront-patterns.md`, `events-functions.md`
