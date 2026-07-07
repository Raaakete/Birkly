# P10 — Verification matrix

**Date:** 2026-07-07 · **Phase:** P10 planning baseline  
**Spec:** `docs/reviews/P10-marketplace-go-live-plan.md`  
**Handoff:** `docs/reviews/P10-lead-engineer-handoff.md`

Execute and mark results at P10-M7 closure.

## Plugin unbundling (Birkly)

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| U-01 | CMS release zip excludes plugins/ | pass | | Release manifest / zip inspect |
| U-02 | Fresh install has no active plugins | pass | | settings/plugins.json |
| U-03 | api/automation.php graceful when plugin missing | pass | | HTTP probe |
| U-04 | api/index.php user routes graceful when plugin missing | pass | | HTTP probe |
| U-05 | install-dev-plugins.sh + CI green | pass | | php tests/run.php |
| U-06 | Existing install grandfather — local plugins still load | pass | | Manual / fixture |

## Official marketplace offers

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| O-01 | build-plugin-zip.sh produces valid zip + sha256 | pass | | Script output |
| O-02 | birkly-commerce listing published | pass | | catalog |
| O-03 | birkly-events listing (depends commerce) | pass | | catalog |
| O-04 | birkly-automation listing | pass | | catalog |
| O-05 | birkly-user-management listing | pass | | catalog |
| O-06 | Free license auto-issue + install from CMS | pass | | marketplace_client_test |

## CMS marketplace client

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| C-01 | Browse official plugins in admin | pass | | |
| C-02 | One-click free install | pass | | |
| C-03 | check_updates detects new release | pass | | |
| C-04 | Upgrade installs new version | pass | | |
| C-05 | Onboarding marketplace hint (optional step) | pass | | |

## Marketplace go-live

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| G-01 | Reviews visible on offer detail | pass | | |
| G-02 | Provider support contact on listing | pass | | |
| G-03 | Buyer refund request | pass | | |
| G-04 | Provider approves refund | pass | | |
| G-05 | Stripe refund + license revoked | pass | | |
| G-06 | Production deploy runbook exists | pass | | docs |

## Platform admin

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| A-01 | Admin login (non-default password warning) | pass | | |
| A-02 | QC queue shows pending third-party offer | pass | | |
| A-03 | QC approve publishes to catalog | pass | | |
| A-04 | QC reject returns offer to draft + notes | pass | | |
| A-05 | Pause offer removes from catalog | pass | | |
| A-06 | Suspend provider blocks login | pass | | |
| A-07 | Hide review updates rating summary | pass | | |
| A-08 | Admin action written to audit log | pass | | |
| A-09 | New release QC before registry live (third-party) | pass | | |

## Cross-repo

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| X-01 | kekaba embed without plugins/ | pass | | sync-client-embed.sh |
| X-02 | kekaba demo shop after marketplace install | pass | | |
| X-03 | Birkly-Docs plugin install guide | pass | | |
| X-04 | Birkly test suite | pass | | php tests/run.php |
| X-05 | Marketplace test suite | pass | | php tests/run.php |
