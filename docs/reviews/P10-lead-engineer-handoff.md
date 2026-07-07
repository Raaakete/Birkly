# Phase 10 — Marketplace go-live & plugin unbundling — lead-engineer handoff

**Status:** Approved (2026-07-07, Keven) — autonomous execution P10-M0→M7  
**Baseline:** P9 complete on `main` — Birkly **97 test suites** · Marketplace **8 test suites**  
**Human rule:** Execute **P10-M0 → P10-M7** autonomously. Notify human **once** at **P10-M7 closure**.

**Product spec:** `docs/reviews/P10-marketplace-go-live-plan.md`  
**Roadmap:** `docs/roadmap/ROADMAP.md` § Phase 10  
**Repos:** `Raaakete/Birkly-Marketplace` (primary) · `Raaakete/Birkly` · `Raaakete/Birkly-Docs` · `Raaakete/Birkly-Website` · `Raaakete/kekaba`

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 10 — Marketplace go-live, plugin unbundling & platform admin.

Human approved full P10 autonomous execution (M0→M7). Notify human ONCE at P10-M7 closure only.

Read first:
- docs/reviews/P10-lead-engineer-handoff.md
- docs/reviews/P10-marketplace-go-live-plan.md
- docs/roadmap/ROADMAP.md § Phase 10
- docs/reviews/P9-marketplace-plan.md (baseline P9 decisions)
- Birkly-Marketplace/core/admin_routes.php + core/qc.php (admin scaffold — views are stubs)
- Birkly/core/marketplace.php + core/marketplace/install.php (CMS client)
- docs/plugin-system-architecture-plan.md § Supply Chain

## Context

P9 COMPLETE. Marketplace app exists with provider/buyer flows, Stripe Connect, QC backend, reviews backend, registry API, CMS marketplace client.

P10 DELIVERS:
1. UNBUNDLE official plugins from CMS download — commerce, events, automation, user_management install ONLY via marketplace
2. Official plugin zip build pipeline + marketplace listings (free licenses)
3. Marketplace GO-LIVE ops — production config, reviews UI, refunds, provider contact
4. PLATFORM ADMIN UI at /admin/* — QC inbox, offer/provider/review/order moderation, audit log

CMS local accounts UNCHANGED (P9-D1). Marketplace admin is SEPARATE from CMS admin (P10-D12).

## Locked decisions (do not re-ask human)

P10-D1: Plugin source in Birkly/plugins/ for dev; EXCLUDED from CMS release zip
P10-D2: Provider-primary refunds + platform queue; 14-day window
P10-D3: Official plugins FREE for v1 (commerce, events, automation, user_management)
P10-D4: Fresh install = zero plugins; onboarding suggests marketplace
P10-D5: Existing installs grandfathered; updates via marketplace only
P10-D6: kekaba embed excludes plugins/; dev script for commerce/events demos
P10-D7: No GitHub auto-publish in P10 — CI/tag → zip → registry API
P10-D8: Autonomous M0→M7; single human notification at closure
P10-D9: Manual QC for ALL non-official provider offers before publish
P10-D10: Third-party NEW RELEASE versions re-enter QC before registry live
P10-D11: Reviews soft-hide only (no hard delete)
P10-D12: Platform admin at marketplace.birkly.com/admin only
P10-D13: Pause = hide listing, licensed install/update OK; Suspend = block install/update

## Deliverables by milestone

P10-M0: Coupling register, CMS release manifest, verification matrix shell
P10-M1: Exclude plugins from CMS zip; API graceful gating; scripts/install-dev-plugins.sh; CI fixture install
P10-M2: scripts/build-plugin-zip.sh; official marketplace offers; CI publish to registry
P10-M3: CMS marketplace UX — free install, updates, onboarding hint
P10-M4: Production runbook, reviews on catalog, provider contact, refund workflow + license revoke
P10-M4b: Full /admin UI — dashboard, QC, offers, providers, reviews, orders, audit log
P10-M5: Birkly-Docs + marketplace legal pages + Birkly-Website CTA
P10-M6: kekaba embed excludes plugins; demo install path documented
P10-M7: Verification matrix green, closure checklist, human notified once

## Execution order

M0 → M1 (unbundle FIRST — blocks everything) → M2 → M3 ∥ (M4 + M4b) → M5 + M6 → M7

Branches: cursor/p10-m[N]-<slug>-030a

## Critical technical notes

1. M1 BEFORE M2: Tests and CI depend on install-dev-plugins.sh once plugins/ leaves release zip
2. Core coupling: api/index.php, api/public.php, api/automation.php reference plugins/ — gate with file_exists + active check
3. P9 admin views (views/admin/qc.php, reviews.php, login.php) are STUBS — rebuild with shared marketplace.css
4. marketplace_sync_catalog_index() only includes status=published — pause/suspend = status change + sync
5. Extend offer status enum: paused, suspended, delisted, pending_release_review
6. Extend provider status: active, suspended, blocked — gate marketplace_provider_can_sell() and login
7. Official provider id: prov_official_birkly — skip QC on publish (P10-D9 exception)
8. Refund: Stripe refund API + marketplace_revoke_license() on full refund

## Delegate

- cms-developer: Birkly M1–M3, build-plugin-zip, API gating, marketplace client UX
- cms-developer: Marketplace M4, M4b backend + admin routes
- frontend-developer: Marketplace admin UI, reviews on catalog, provider contact
- docs-writer: P10-M5 docs, runbook, verification matrix
- qa-verifier: E2E fresh install → marketplace install → refund → admin QC flow
- design-reviewer: Admin shell, QC queue, catalog review display

Run php tests/run.php on Birkly AND Birkly-Marketplace before each PR merge.
./scripts/ci-check.sh on Birkly PRs.

Merge PRs yourself after qa-verifier pass (and design-reviewer when UI changes).
```

---

## Milestone checklist

| ID | Deliverable | Acceptance |
|----|-------------|------------|
| **M0** | Gap register + matrix shell | Coupling doc complete |
| **M1** | Core unbundling | CMS zip has no plugins/; CI green with dev install script |
| **M2** | Official offers + zip pipeline | 4 official plugins on marketplace; registry releases |
| **M3** | CMS marketplace UX | Fresh install → free plugin install → update check |
| **M4** | Go-live ops | Refunds, reviews UI, provider contact, runbook |
| **M4b** | Platform admin | QC approve/reject UI; pause/block; audit log |
| **M5** | Docs + website | Birkly-Docs + legal + Website CTA |
| **M6** | kekaba embed | plugins/ excluded; demo path works |
| **M7** | Closure | Matrix green; human notified once |

---

## Branch naming

- **Birkly:** `cursor/p10-m[N]-<slug>-030a`
- **Birkly-Marketplace:** `cursor/p10-m[N]-<slug>-030a`
- **Cross-repo:** same milestone id where tasks span repos

---

## Risk register

| Risk | Mitigation |
|------|------------|
| CI breaks when plugins/ removed from tree | M1-T4/T5: install-dev-plugins.sh in CI before tests |
| kekaba shop/events demos break | M6: document marketplace install post-embed |
| Admin stub views confuse QA | M4b rebuilds all admin views |
| Production Stripe not configured | M4 runbook + human checklist; phase can close on staging with human prod steps |

---

## References

- P9 spec: `docs/reviews/P9-marketplace-plan.md`
- P9 matrix: `Birkly-Marketplace/docs/P9-VERIFICATION-MATRIX.md`
- Marketplace policy: `Birkly-Marketplace/docs/MARKETPLACE_POLICY.md`
- Provider agreement: `Birkly-Marketplace/docs/PROVIDER_AGREEMENT.md`
- CMS marketplace client: `Birkly/core/marketplace.php`, `Birkly/api/marketplace.php`
