# P12 — Verification matrix

**Date:** 2026-07-07 · **Phase:** P12 ecosystem growth & production closure  
**Spec:** `docs/reviews/P11-P12-complete-plan.md` § Phase 12  
**Handoff:** `docs/reviews/P12-lead-engineer-handoff.md`

Execute and mark at P12-M8 closure.

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P12-M0** | Gap register, threat model, matrix | pass | `P12-import-threat-model.md`, `P12-ecosystem-gap-register.md` |
| **P12-M1** | GitHub App → QC | | |
| **P12-M2** | Provider growth | | |
| **P12-M3** | Live Shopify/Woo import | | |
| **P12-M4** | Denylist + membership | | |
| **P12-M5** | Deploy closure | | |
| **P12-M6** | Birkly-Website | | |
| **P12-M7** | Birkly-Docs | | |
| **P12-M8** | Closure | | |

## GitHub App (marketplace)

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| GH-01 | Provider connects repo | pass | | |
| GH-02 | Release webhook verified (signature) | pass | | |
| GH-03 | Artifact enqueued in QC | pass | | |
| GH-04 | Admin approve publishes release | pass | | |
| GH-05 | `github_release_webhook_test.php` | pass | | |

## Provider growth

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| PG-01 | Provider sales summary dashboard | pass | | |
| PG-02 | Payout history from Stripe Connect | pass | | |
| PG-03 | Per-provider fee override | pass | | |
| PG-04 | Outbound webhook on sale (optional URL) | pass | | |
| PG-05 | Report listing → admin queue | pass | | |

## Live import

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| LI-01 | Store Shopify credentials encrypted | pass | | |
| LI-02 | Fetch products paginated (mock) | pass | | |
| LI-03 | Map to Commerce fieldtype entries | pass | | |
| LI-04 | WooCommerce adapter same flow | pass | | |
| LI-05 | Scheduled sync cron token | pass | | |
| LI-06 | `commerce_live_import_test.php` | pass | | |

## Platform hardening

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| PL-01 | Denylist UI saves collections | pass | | |
| PL-02 | Plugin hook blocked on denylisted collection | pass | | |
| PL-03 | Membership purchase assigns user tier | pass | | |
| PL-04 | `plugin_denylist_test.php` | pass | | |
| PL-05 | `membership_commerce_test.php` | pass | | |

## Production closure

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| PR-01 | `scripts/production-smoke.sh` exits 0 staging | pass | | |
| PR-02 | `P12-production-deploy-checklist.md` complete | pass | | |
| PR-03 | CMS health endpoint extended checks | pass | | |
| PR-04 | kekaba check-production.sh updated | pass | | |

## Cross-repo

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| X-01 | Birkly test suite | pass | | |
| X-02 | Marketplace test suite | pass | | |
| X-03 | Birkly-Docs P12 guides | pass | | |
| X-04 | Birkly-Website prod DEV.md | pass | | |
