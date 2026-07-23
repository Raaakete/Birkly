# Phase 35 — Lead-engineer handoff (Marketplace & Ops excellence)

**Status:** Approved — execute now (2026-07-23, Keven)  
**Human rule:** Execute **P35-M0→M33** autonomously. Notify human **once** at **P35-M33**.

**Master plan:** `docs/reviews/P35-marketplace-ops-excellence-plan.md`  
**Gap register:** `docs/reviews/P35-gap-register.md`  
**Matrix:** `docs/reviews/P35-verification-matrix.md`  
**P34 baseline:** `docs/reviews/P34-human-review-pack.md`

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 35 — Marketplace & Ops excellence.

Human approved P35 (2026-07-23). Execute P35-M0→M33 autonomously. Notify human ONCE at P35-M33.

BASELINE: P34 deployed — ops/providers/marketplace surfaces live on Dokploy.
P34 closure sign-off may still be open; do not regress surface routing or Ops APIs.

PAUSED until P35-M28: Phase 28 beta journeys (need solid marketplace install path).

LOCKED DECISIONS:
- Ops + Provider UI must match Birkly admin shell (sidebar, cards, tokens)
- SQLite v1 for accounts/orders/licenses (JSON migration script)
- API-first catalog; Birkly-Website renders from /api/v1/*
- Rich offer pages with ?embed=1 for CMS modal iframe
- Permission manifest v2 + CMS install consent
- Security scan v2 (fix commerce base64_decode false positive)
- Ops admin user CRUD at /admin/users
- Fix per-provider fee tier form (CSRF, no nested forms)
- Official plugins published under Birkly provider; dev tree marketplace-only

READ FIRST:
1. docs/reviews/P35-marketplace-ops-excellence-plan.md
2. docs/reviews/P35-gap-register.md
3. Birkly-Marketplace/docs/BIRKLY_OPS_VISION.md
4. Birkly-Marketplace/core/surface.php
5. Birkly-Marketplace/core/artifact.php (security scan)
6. Birkly-Marketplace/views/admin/providers.php (fee form bug)
7. Birkly/admin/css/ (tokens, layout, components — port patterns)
8. Birkly/admin/js/plugins-page.js (marketplace modal)
9. Birkly/scripts/build-plugin-zip.sh
10. Birkly/plugins/commerce/plugin.json (permissions model)

═══ WAVE 1 — ADMIN UI SHELL (M1–M6) ═══

Port Birkly admin design tokens + shell to Marketplace.
Rebuild Ops /admin/* and Provider /provider/* on shared layout.
Match cards, tables, forms, breakpoints.

═══ WAVE 2 — OPS FIXES + ADMIN USERS (M7–M10) ═══

Fix provider fee-tier POST (dedicated route, CSRF token).
Build /admin/users CRUD with roles admin|moderator|support.
Remove production dependence on env default password.

═══ WAVE 3 — DATABASE (M11–M14) ═══

SQLite schema + migrate JSON data.
Repository layer for providers, offers, orders, licenses.
CSRF, rate limits, Argon2id, backup runbook.

═══ WAVE 4 — UPLOAD & PERMISSIONS (M15–M18) ═══

Security scan v2. Permission manifest v2 in plugin.json.
CMS install consent step. Provider packaging guide + Studio preflight UI.

═══ WAVE 5 — API & OFFER PAGES (M19–M27) ═══

Enrich registry/offer APIs. Website marketplace section.
Provider offer page editor. embed=1 for CMS iframe modal.

═══ WAVE 6 — ECOSYSTEM CUTOVER (M28–M30) ═══

Publish official plugins from Birkly provider account.
install-dev-plugins.sh from marketplace. Update P10 coupling register.

═══ WAVE 7 — PAID COMMERCE + CLOSURE (M31–M33) ═══

Stripe test E2E in CI. Live smoke with human keys.
Verification matrix + human review pack.

DELEGATION:
- cms-developer: M17, M27 (CMS consent + modal embed)
- frontend-developer: M1–M6, M22, M24–M26 (UI shell, website, offer pages)
- docs-writer: M18, M30, M33 docs
- qa-verifier: M31–M33 matrix

HUMAN PAIRING:
- M32: Stripe live keys on Dokploy (Keven)
- M33: review pack sign-off

BRANCHES: cursor/p35-m[N]-<slug>-030a
TESTS: Birkly-Marketplace php tests/run.php + new DB/surface/UI tests; Birkly ./scripts/ci-check.sh
```

---

## Specialist task map

| Milestone | Owner | Repo |
|-----------|-------|------|
| M0 | lead-engineer | Birkly |
| M1–M6, M7, M11–M16, M19–M21 | lead-engineer + frontend-developer | Marketplace |
| M8–M10 | lead-engineer | Marketplace |
| M17, M27 | cms-developer | Birkly |
| M18, M30 | docs-writer | Birkly-Docs + Marketplace |
| M22, M24–M26 | frontend-developer | Birkly-Website + Marketplace |
| M28–M29 | lead-engineer | Birkly + Marketplace |
| M31–M33 | qa-verifier + lead-engineer | All |

---

## Human tasks (Keven)

| When | Task |
|------|------|
| Before M1 | Approve P35 plan PR | **done** |
| M32 | Stripe live/test keys on Dokploy if not done in P34 |
| M33 | Human review pack sign-off |
| Optional | Create Birkly official provider account on providers.birkly.cloud for M28 |
