# Phase 34 — Lead-engineer handoff (Birkly Ops & platform production)

**Status:** Approved — execute now (2026-07-22, Keven)  
**Human rule:** Execute **P34-M0→M22** autonomously. Notify human **once** at **P34-M22**.

**Master plan:** `docs/reviews/P34-birkly-ops-platform-plan.md`  
**Matrix:** `docs/reviews/P34-verification-matrix.md`  
**Gap register:** `docs/reviews/P34-gap-register.md`

---

## Execution order (important)

**Develop the Ops platform code first (M1–M3, M15–M17), then VPS/nginx assistance (M4–M6).**

Human has DNS ready (`ops`, `providers`, `marketplace` → `*.birkly.cloud`). Do **not** block on nginx/TLS/Stripe until surface routing and Ops API modules exist and tests pass locally. At **M4–M6**, pair with human to apply nginx vhost template, TLS, and production env on the VPS.

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 34 — Birkly Ops & platform production.

Human approved P34 as NEXT STRATEGIC PHASE (2026-07-22). Execute P34-M0→M22 autonomously.
Notify human ONCE at P34-M22.

BASELINE: P33 complete on Birkly main. P28/P26 paused until P34-M22.

PAUSED until P34-M22: Phase 28 (beta journeys), Phase 26 (Studio creative).

LOCKED DECISIONS:
- THREE SURFACES, ONE REPO (Birkly-Marketplace):
  • ops.birkly.cloud        → staff Ops (/admin, feedback, registry, releases)
  • providers.birkly.cloud  → Provider Studio (/provider/*)
  • marketplace.birkly.cloud → public catalog + /api/v1 registry + webhooks
- CMS marketplace URL = https://marketplace.birkly.cloud (NOT ops)
- Shared data/ on VPS; surface = routing + auth boundary
- Feedback triage moves from CMS owner install to Ops when relay live
- Human DNS for all three subdomains is DONE — VPS/nginx/TLS comes AFTER code (M4–M6)

READ FIRST:
1. docs/reviews/P34-birkly-ops-platform-plan.md
2. Birkly-Marketplace/docs/BIRKLY_OPS_VISION.md
3. Birkly-Marketplace/docs/PRODUCTION-RUNBOOK.md
4. Birkly-Marketplace/core/router.php
5. Birkly-Marketplace/core/admin_routes.php
6. Birkly-Marketplace/core/provider_routes.php
7. Birkly-Marketplace/core/app_config.php
8. Birkly/core/marketplace.php
9. Birkly/api/feedback.php
10. Birkly/core/platform.php

═══ WAVE 1 — CODE FIRST (M0–M3, M15–M17) ═══

P34-M0: Finalize gap register + verification matrix from plan.

P34-M1: marketplace_surface() — resolve from MARKETPLACE_SURFACE env + HTTP_HOST map
  (ops.birkly.cloud → ops, providers.birkly.cloud → provider, marketplace.birkly.cloud → public).

P34-M2: Route guards in router — deny-by-default per surface:
  • ops: /admin/*, /api/ops/v1/*, /health only
  • provider: /provider/*, /health only
  • public: /, /catalog, /offers/*, /buy/*, /legal/*, /api/v1/*, /webhooks/*, /health
  Wrong surface → 404 or redirect to canonical host.

P34-M3: Separate session cookie names per surface (birkly_ops, birkly_provider, birkly_marketplace).
  Add unit tests for surface routing. All tests green before deploy work.

P34-M15: POST /api/ops/v1/feedback — HMAC receiver matching Birkly api/feedback.php relay format.
  Admin UI: /admin/feedback inbox (list, detail, link installation_id).

P34-M16: POST /api/ops/v1/installations/register + GET admin list.
  Fields: installation_id, cms_version, channel, last_seen, program_tier (opt-in).

P34-M17: CMS release channel v1 — upload signed ZIP metadata, channels (alpha/beta/stable),
  GET /api/ops/v1/releases/check?installation_id=&channel=&version=

Commit + push after M3 and after M17. Run php tests/run.php in Marketplace repo.

═══ WAVE 2 — VPS DEPLOY (M4–M6) — pair with human ═══

P34-M3 output: docs/nginx-birkly-cloud.conf.template (3 vhosts → one PHP root).

P34-M4: Harden ops.birkly.cloud — apply template, TLS (certbot), .env secrets, non-default admin password.

P34-M5: providers.birkly.cloud vhost — smoke provider signup/login.

P34-M6: marketplace.birkly.cloud vhost — smoke catalog + /api/v1/registry.

Human assistance needed at M4–M6 for nginx, TLS, and .env on VPS. Provide exact commands and config snippets.

═══ WAVE 3 — MARKETPLACE LIVE (M7–M11) ═══

Seed official catalog on production data/.
Stripe live keys + webhooks on PUBLIC host only.
GitHub App webhook → marketplace.birkly.cloud/webhooks/github.
Update Birkly BIRKLY_MARKETPLACE_DEFAULT_URL to https://marketplace.birkly.cloud.
E2E: CMS admin → browse registry → install official plugin ZIP.

═══ WAVE 4 — PROVIDER STUDIO (M12–M14) ═══

Provider branding on providers host — no admin links, link to public marketplace.
Full flow: provider offer → QC on ops → published on marketplace.

═══ WAVE 5 — CMS WIRE-UP (M18–M19) ═══

Wire CMS feedback relay + updates check to production Ops URLs.
Gate CMS owner feedback triage when relay configured (P34-D8).

═══ WAVE 6 — DOCS & CLOSURE (M20–M22) ═══

Birkly-Docs provider + install guides. Update PRODUCTION-RUNBOOK.
Verification matrix green. Human review pack at M22.

DELEGATION:
- cms-developer: Birkly CMS client URL, feedback/updates wire-up, triage removal (M10–M11, M18–M19)
- frontend-developer: Provider studio branding if needed (M12)
- docs-writer: M20
- qa-verifier: matrix + E2E at M22

BRANCHES: cursor/p34-m[N]-<slug>-030a (Marketplace + Birkly + Docs as needed)
CI: php tests/run.php (Marketplace), ./scripts/ci-check.sh (Birkly) before each PR merge.
```

---

## Specialist task map

| Milestone | Owner | Repo |
|-----------|-------|------|
| M0 | lead-engineer | Birkly |
| M1–M3, M15–M17 | lead-engineer | Marketplace |
| M4–M6 | lead-engineer + human (VPS) | Infra + Marketplace |
| M7–M9, M12–M14 | lead-engineer | Marketplace |
| M10–M11, M18–M19 | cms-developer | Birkly |
| M20 | docs-writer | Birkly-Docs |
| M21–M22 | lead-engineer | All |

---

## Human tasks (Keven)

| When | Task | Status |
|------|------|--------|
| Before M5 | DNS A records: `providers.birkly.cloud`, `marketplace.birkly.cloud` | **done** |
| M4–M6 | Apply nginx vhost template + TLS on VPS (with lead-engineer) | pending |
| Before M8 | Stripe live mode keys + webhook on public URL | pending |
| Before M9 | GitHub App webhook → `marketplace.birkly.cloud/webhooks/github` | pending |
| M22 | Review human pack + approve go-live | pending |

---

## Branch naming

- Marketplace: `cursor/p34-m[N]-<slug>-030a`
- Birkly CMS: `cursor/p34-m[N]-<slug>-030a`
- Docs: `cursor/p34-docs-030a`
