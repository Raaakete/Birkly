# P35 — Human review pack (Marketplace & Ops excellence)

**Date:** 2026-07-23  
**Status:** Ready for Keven sign-off (M33)  
**Plan:** `docs/reviews/P35-marketplace-ops-excellence-plan.md`  
**Matrix:** `docs/reviews/P35-verification-matrix.md`

---

## Executive summary

Phase 35 is **code-complete** across M0–M33. The marketplace ecosystem now has:

- Birkly-style admin shell on Ops + Provider Studio
- SQLite data layer with JSON migration path
- Ops admin user CRUD, CSRF, rate-limited login
- Security scan v2 + permission manifest v2 + CMS install consent
- API-first catalog, rich offer pages, `?embed=1` for CMS modal
- Official plugins (commerce, events, automation, user_management, studio) on marketplace
- Stripe test-mode E2E in CI

**Your action:** Redeploy Platform on Dokploy, walk through the checklist below, sign off. P28 beta journeys can resume after approval.

---

## Deploy first

Redeploy the **Platform** app on Dokploy to pick up all merged PRs:

| Repo | Key PRs |
|------|---------|
| Birkly-Marketplace | #21–#24 (shell, SQLite, scan v2, API/offers) |
| Birkly | #206–#208 (consent, embed modal, cutover) |
| Birkly-Website | #11 (marketplace.html) |
| Birkly-Docs | #27 (packaging guide) |

```bash
cd Birkly-Marketplace && ./scripts/production-smoke.sh
```

---

## Quick verification checklist

### Ops (`ops.birkly.cloud`)

- [ ] Sidebar + topbar UI (not old horizontal nav)
- [ ] `/admin/users` — create a moderator account
- [ ] `/admin/providers` — set custom fee % on a provider
- [ ] `/admin/feedback` — feedback inbox still works

### Provider (`providers.birkly.cloud`)

- [ ] Same shell family as Ops
- [ ] Upload plugin zip — preflight shows scan report
- [ ] Edit offer page at `/provider/offers/{slug}/page`

### Marketplace (`marketplace.birkly.cloud`)

- [ ] `/api/v1/registry` returns official plugins
- [ ] `/offers/birkly-commerce` — rich offer page
- [ ] `/offers/birkly-commerce?embed=1` — chromeless embed

### CMS (your test install)

- [ ] Plugins → Marketplace → click offer → iframe modal opens
- [ ] Install shows permission consent before extracting
- [ ] Install Hello Starter or official plugin from registry

### Website

- [ ] `birkly.com/marketplace.html` loads catalog from API

### Paid checkout (optional — needs Stripe keys)

Follow `Birkly-Marketplace/docs/DOKPLOY-DEPLOY.md` § **4b**

---

## Automated tests (all green)

```bash
cd Birkly-Marketplace && php tests/run.php    # 28 suites
cd Birkly && ./scripts/ci-check.sh            # full CMS CI
```

---

## Sign-off

| Check | Reviewer | Date | Notes |
|-------|----------|------|-------|
| Ops + Provider UI walkthrough | Keven | | |
| Official plugins from marketplace | Keven | | |
| CMS embed + consent | Keven | | |
| Paid checkout (if keys set) | Keven | | |
| **Approve P35 closure** | Keven | | |

**Approve** → P28 resumes per roadmap.  
**Changes requested** → note in GitHub issue.
