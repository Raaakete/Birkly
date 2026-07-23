# P35 — Human review pack (Marketplace & Ops excellence)

**Date:** 2026-07-23  
**Status:** Ready for Keven review (M33)  
**Plan:** `docs/reviews/P35-marketplace-ops-excellence-plan.md`  
**Matrix:** `docs/reviews/P35-verification-matrix.md`

---

## Executive summary

Phase 35 hardens the Birkly marketplace ecosystem for production: admin UI parity, SQLite data layer, ops admin users, API-first catalog, rich offer pages, permission consent, official plugin cutover, and paid checkout verification.

This pack covers **closure items M28–M33** (ecosystem cutover + paid commerce + sign-off). Earlier waves (M0–M27) may still be in progress on other branches — see matrix for per-check status.

| Deliverable (M28–M33) | Status |
|-----------------------|--------|
| Official plugins on marketplace only | **Done** — commerce, events, automation, user_management, studio under Birkly provider |
| CMS dev install from registry | **Done** — `install-dev-plugins.sh` pulls from `marketplace.birkly.cloud` |
| P10 coupling register updated | **Done** — ecosystem cutover marked complete |
| Stripe test-mode E2E in CI | **Done** — `stripe_checkout_e2e_test.php` |
| Live paid smoke runbook | **Done** — `Birkly-Marketplace/docs/DOKPLOY-DEPLOY.md` § 4b |
| Human review pack | **This document** |

---

## Automated verification (green)

### Marketplace tests

```bash
cd Birkly-Marketplace && php tests/run.php
```

New suites for M28–M31:

- `official_plugins_cutover_test` — all 5 official plugin offers + artifacts after seed
- `stripe_checkout_e2e_test` — paid checkout, 10% fee, license activate, API paths, refund revoke

### Birkly tests

```bash
cd Birkly && ./scripts/ci-check.sh
```

Key checks:

- `cms_release_manifest_test` — studio in official_plugin_ids
- `install-dev-plugins.sh` — registry or local seed installs all official plugins

### Production smoke (optional)

```bash
cd Birkly-Marketplace && ./scripts/production-smoke.sh
```

Expected: `RESULT: PASS` for ops, providers, marketplace surfaces.

---

## What changed (M28–M33)

### Official plugin cutover (M28–M30)

- [x] Marketplace seed: `catalog/offers/birkly-{commerce,events,automation,user-management,studio}.json`
- [x] Artifact zips in `catalog/packages/` (sync via `scripts/sync-birkly-official-seeds.php`)
- [x] `plugins/README.md` — marketplace-only install messaging
- [x] `scripts/install-dev-plugins.sh` — registry API first, local seed fallback
- [x] `cms-release-manifest.json` — studio added to official plugins
- [x] `P10-plugin-coupling-register.md` — ecosystem complete (P35-M30)

### Paid commerce (M31–M32)

- [x] CI: Stripe mock checkout E2E (checkout → license → activate → refund)
- [x] Dokploy runbook: live paid smoke steps with human Stripe keys

---

## Manual checks (recommended)

### Official plugin install (CMS)

1. On a test CMS site, remove `plugins/commerce` (if present)
2. Run `./scripts/install-dev-plugins.sh` **or** install from Admin → Plugins → Marketplace
3. Confirm all five official plugins install: commerce, events, automation, user_management, studio
4. Verify Commerce admin page loads after install

### Marketplace catalog

1. `https://marketplace.birkly.cloud/api/v1/registry` — lists `birkly-commerce`, `birkly-studio`, etc.
2. Offer detail pages open for each official plugin
3. Free offers show **Install** in CMS without license key

### Paid checkout (requires Stripe keys — M32)

Follow `Birkly-Marketplace/docs/DOKPLOY-DEPLOY.md` § **4b. Live paid checkout smoke**:

1. Complete checkout for `birkly-seo-basics` ($14.99) or a $1 test offer
2. Activate license in CMS and install
3. (Optional) Verify 10% custom provider fee on a test order
4. (Optional) Subscription + refund paths

---

## Pending human decisions

| Item | When | Action |
|------|------|--------|
| **Stripe live keys** | Before production paid checkout | Dokploy env + webhook — see DOKPLOY-DEPLOY.md |
| **P35 UI/DB waves** | Parallel tracks | M1–M27 may land on separate branches — review matrix |
| **P28 resume** | After M33 approval | Beta journeys unblocked (marketplace install path solid) |
| **P34 formal closure** | Anytime | Sign P34 review pack if not done |

---

## Known limitations

- Official plugin zips may trigger legacy security scan warnings (`php://input` in API shims) — P35-M15 scan v2 addresses context-aware rules.
- Live paid checkout not verified in CI (requires human Stripe keys).
- Plugin source remains in Birkly `plugins/` for builds — intentionally not deleted (P35-D9).

---

## Sign-off

| Check | Reviewer | Date | Notes |
|-------|----------|------|-------|
| Official plugins on marketplace | Keven | | |
| CMS registry install works | Keven | | |
| Paid checkout smoke (if keys set) | Keven | | |
| Approve P35-M28–M33 closure | Keven | | |

**Approve** → agents resume P28 per roadmap.  
**Changes requested** → note in GitHub issue or reply to agent thread.
