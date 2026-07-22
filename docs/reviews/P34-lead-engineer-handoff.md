# Phase 34 — Lead-engineer handoff (Birkly Ops & platform production)

**Status:** Approved for planning (2026-07-22, Keven)  
**Human rule:** Execute **P34-M0→M22** autonomously. Notify human **once** at **P34-M22**.

**Master plan:** `docs/reviews/P34-birkly-ops-platform-plan.md`  
**Matrix:** `docs/reviews/P34-verification-matrix.md`  
**Gap register:** `docs/reviews/P34-gap-register.md` (create at M0)

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 34 — Birkly Ops & platform production.

Human approved P34 as NEXT STRATEGIC PHASE (2026-07-22).
Execute P34-M0→M22 autonomously. Notify human ONCE at P34-M22.

PAUSED until P34-M22: P33 (CMS responsive), P28 (beta journeys), P26 (Studio).

LOCKED DECISIONS:
- THREE SURFACES, ONE REPO (Birkly-Marketplace):
  • ops.birkly.cloud        → staff Ops (/admin, feedback, registry, releases)
  • providers.birkly.cloud  → provider studio (/provider/*)
  • marketplace.birkly.cloud → public catalog + /api/v1 registry + webhooks
- CMS marketplace URL = marketplace.birkly.cloud (NOT ops)
- Shared data/ on VPS; surface = routing + auth boundary
- Feedback triage moves from CMS owner install to Ops when relay live

READ FIRST:
1. docs/reviews/P34-birkly-ops-platform-plan.md
2. Birkly-Marketplace/docs/BIRKLY_OPS_VISION.md
3. Birkly-Marketplace/docs/PRODUCTION-RUNBOOK.md
4. Birkly-Marketplace/core/router.php
5. Birkly-Marketplace/core/admin_routes.php
6. Birkly-Marketplace/core/provider_routes.php
7. Birkly/core/marketplace.php
8. Birkly/api/feedback.php
9. Birkly/core/platform.php

═══ PILLAR A — SURFACE SPLIT (M1–M6) ═══

Implement marketplace_surface() from MARKETPLACE_SURFACE env + HTTP_HOST map.
Router deny-by-default: ops host must NOT serve /provider or /catalog.
Separate session cookie names per surface.
nginx vhost template for 3 hosts → one PHP root.
Human VPS already has ops.birkly.cloud — add providers + marketplace subdomains.

═══ PILLAR B — PUBLIC MARKETPLACE (M7–M11) ═══

Seed official catalog, Stripe live on public host, GitHub webhooks on public host.
Update Birkly default BIRKLY_MARKETPLACE_URL to https://marketplace.birkly.cloud.
E2E: CMS → install official plugin from production registry.

═══ PILLAR C — PROVIDER STUDIO (M12–M14) ═══

providers.birkly.cloud branding, no admin links.
Full provider → QC → publish flow across surfaces.

═══ PILLAR D — OPS MODULES (M15–M19) ═══

POST /api/ops/v1/feedback (HMAC receiver) + /admin/feedback UI
POST /api/ops/v1/installations/register + admin list
CMS release channel v1 + updates check endpoint
Wire CMS feedback + updates to production Ops URLs
Gate CMS owner feedback triage when relay configured

═══ PILLAR E — DOCS & CLOSURE (M20–M22) ═══

Birkly-Docs provider + install guides, runbook updates, verification matrix green.

DELEGATION:
- cms-developer: Birkly CMS client URL, feedback relay config, triage removal (M10–M11, M18–M19)
- frontend-developer: Provider studio branding if needed (M12)
- docs-writer: M20
- qa-verifier: matrix + E2E at M22
```

---

## Specialist task map

| Milestone | Owner | Repo |
|-----------|-------|------|
| M0 | lead-engineer | Birkly |
| M1–M3, M15–M17 | cms-developer / lead | Marketplace |
| M4–M6 | lead-engineer + human DNS | Infra |
| M7–M9, M12–M14 | lead-engineer | Marketplace |
| M10–M11, M18–M19 | cms-developer | Birkly |
| M20 | docs-writer | Birkly-Docs |
| M21–M22 | lead-engineer | All |

---

## Human tasks (Keven)

| When | Task |
|------|------|
| Before M5 | DNS A records: `providers.birkly.cloud`, `marketplace.birkly.cloud` |
| Before M8 | Stripe live mode keys + webhook endpoint on public URL |
| Before M9 | GitHub App webhook URL → `https://marketplace.birkly.cloud/webhooks/github` |
| M22 | Review human pack + approve go-live |

---

## Branch naming

- Marketplace: `cursor/p34-m[N]-<slug>-030a`
- Birkly CMS: `cursor/p34-m[N]-<slug>-030a`
- Docs: `cursor/p34-docs-030a`
