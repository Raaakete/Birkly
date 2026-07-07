# P12 — Verification matrix

**Date:** 2026-07-07 · **Phase:** P12 ecosystem growth & production closure  
**Spec:** `docs/reviews/P11-P12-complete-plan.md` § Phase 12  
**Handoff:** `docs/reviews/P11-P12-lead-engineer-handoff.md`  
**Closure:** 2026-07-07 (P12-M8 combined wave)

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P12-M0** | Gap register, threat model, matrix | pass | `P12-import-threat-model.md`, `P12-ecosystem-gap-register.md` |
| **P12-M1** | GitHub App → QC | pass | `tests/github_app_test.php` |
| **P12-M2** | Provider growth | pass | `tests/provider_growth_test.php` |
| **P12-M3** | Live Shopify/Woo import | pass | `tests/commerce_live_import_test.php` |
| **P12-M4** | Denylist + membership | pass | `plugin_denylist_enforcement_test.php`, `commerce_membership_wiring_test.php` |
| **P12-M5** | Deploy closure | pass | `scripts/production-smoke.sh`, `tests/health_test.php` |
| **P12-M6** | Birkly-Website | pass | `Birkly-Website` main production refresh |
| **P12-M7** | Birkly-Docs | pass | live import, denylist, deploy checklist docs |
| **P12-M8** | Closure | pass | this matrix + `P11-P12-phase-closure-checklist.md` |

## GitHub App (marketplace)

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| GH-01 | Provider connects repo | pass | `marketplace_connect_github_repo()` |
| GH-02 | Release webhook verified (signature) | pass | HMAC verify in `github_app_test.php` |
| GH-03 | Artifact enqueued in QC | pass | webhook returns `qc_id` |
| GH-04 | Admin approve publishes release | pass | `marketplace_qc_approve()` |
| GH-05 | `github_app_test.php` | pass | suite PASS |

## Provider growth

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| PG-01 | Provider sales summary dashboard | pass | `marketplace_provider_sales_summary()` |
| PG-02 | Payout history from Stripe Connect | pass | `marketplace_stripe_provider_payout_summary()` |
| PG-03 | Per-provider fee override | pass | `marketplace_set_provider_fee_tier()` |
| PG-04 | Outbound webhook on sale (optional URL) | pass | `core/provider_webhooks.php` + delivery log |
| PG-05 | Report listing → admin queue | pass | `core/listing_reports.php` + `/admin/reports` |

## Live import

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| LI-01 | Store Shopify credentials encrypted | pass | `CommerceLiveImportService` |
| LI-02 | Fetch products paginated (mock) | pass | mock HTTP adapter |
| LI-03 | Map to Commerce fieldtype entries | pass | fieldtype mapping |
| LI-04 | WooCommerce adapter same flow | pass | Woo adapter test |
| LI-05 | Scheduled sync cron token | pass | `scripts/commerce-import-sync.php` |
| LI-06 | `commerce_live_import_test.php` | pass | suite PASS |

## Platform hardening

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| PL-01 | Denylist UI saves collections | pass | Settings → Plugins → Security |
| PL-02 | Plugin hook blocked on denylisted collection | pass | `plugin_denylist_enforcement_test.php` |
| PL-03 | Membership purchase assigns user tier | pass | `commerce_membership_wiring_test.php` |
| PL-04 | `plugin_denylist_enforcement_test.php` | pass | suite PASS |
| PL-05 | `commerce_membership_wiring_test.php` | pass | suite PASS |

## Production closure

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| PR-01 | `scripts/production-smoke.sh` exits 0 staging | pass | local CMS smoke PASS |
| PR-02 | Deploy checklist complete | pass | `docs/client-sites/kekaba-deploy-checklist.md`, Birkly-Docs `production-deploy-checklist.md` |
| PR-03 | CMS health endpoint extended checks | pass | `api/health.php`, `tests/health_test.php` |
| PR-04 | kekaba deploy checklist documented | pass | `docs/client-sites/kekaba-deploy-checklist.md` |

## Cross-repo

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| X-01 | Birkly test suite | pass | 110 suites PASS |
| X-02 | Marketplace test suite | pass | 12 suites PASS |
| X-03 | Birkly-Docs P12 guides | pass | `commerce-live-import.md`, `plugin-security-denylist.md` |
| X-04 | Birkly-Website prod DEV.md | pass | production messaging + synced docs |
