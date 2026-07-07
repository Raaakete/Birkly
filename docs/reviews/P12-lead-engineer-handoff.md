# Phase 12 — Ecosystem growth, live import & production closure — lead-engineer handoff

**Status:** Approved (2026-07-07, Keven) — **blocked on P11 closure**  
**Baseline:** P11 complete (required)  
**Human rule:** Superseded — use **combined single-wave** handoff. Notify once at P12-M8.

**Master plan:** `docs/reviews/P11-P12-complete-plan.md` § Phase 12  
**Combined handoff:** `docs/reviews/P11-P12-lead-engineer-handoff.md` (canonical for execution)  
**Roadmap:** `docs/roadmap/ROADMAP.md` § Phase 12

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 12 — Ecosystem growth, live import & production closure.

Human approved P12 as part of the P11+P12 plan. P11 MUST be complete before starting P12. Notify human ONCE at P12-M8 closure only.

Read first:
- docs/reviews/P11-P12-complete-plan.md § Phase 12
- docs/reviews/P12-lead-engineer-handoff.md
- docs/reviews/P12-import-threat-model.md (create in P12-M0)
- Birkly-Marketplace/docs/PRODUCTION-RUNBOOK.md
- docs/plugin-system-architecture-plan.md § Supply Chain, denylist
- docs/roadmap/ROADMAP.md § Phase 12

## Context

P11 COMPLETE. Commerce has upgrades, dunning, gift cards, quotes, analytics, migration wizard.

P12 DELIVERS:
1. Third-party GITHUB APP — repo connect → release webhook → QC queue
2. Marketplace PROVIDER GROWTH — webhooks, payout dashboard, per-provider fee tiers
3. Live SHOPIFY + WOOCOMMERCE API import (scheduled sync)
4. Platform HARDENING — plugin denylist, user↔commerce membership wiring
5. PRODUCTION DEPLOY closure — smoke scripts, health checks, kekaba checklist
6. Birkly-Website production surface refresh

## Locked decisions (do not re-ask human)

P12-D1: Third-party GitHub App with QC enqueue (not auto-publish without review)
P12-D2: Shopify + Woo REST API import v1
P12-D3: Scheduled sync + manual sync; no external webhooks v1
P12-D4: Agent delivers checklists/smoke; human runs live DNS/Stripe/legal
P12-D5: Monitoring v1 — health endpoint extensions only
P12-D6: Plugin denylist in settings + enforcement
P12-D7: Membership purchase → user tier via PostPurchaseRouter
P12-D8: CMS cloud SSO DEFERRED to P13
P12-D9: Paid official plugin tiers DEFERRED to P13
P12-D10: Single human notification at P12-M8 with deploy checklist

## Execution order

M0 → M1 → M2 → M3 → M4 → M5 ∥ M6 → M7 → M8

Branches: cursor/p12-m[N]-<slug>-030a

## Delegate

- cms-developer: Marketplace M1–M2, Birkly live import M3, denylist/membership M4
- frontend-developer: Provider GitHub connect UI, import wizard UI
- docs-writer: P12-M7 docs, deploy checklists
- qa-verifier: GitHub webhook test, import mock test, smoke script, membership E2E
- design-reviewer: Import UI, provider dashboard growth

Run php tests/run.php on Birkly AND Birkly-Marketplace before merges.
```

---

## Milestone checklist

| ID | Deliverable | Acceptance |
|----|-------------|------------|
| **M0** | Gap register + import threat model | Docs merged |
| **M1** | GitHub App webhook → QC | Webhook test green |
| **M2** | Provider growth features | Sales/payout UI |
| **M3** | Live Shopify/Woo import | Mock import test green |
| **M4** | Denylist + membership wiring | Security tests green |
| **M5** | Deploy closure | Smoke script + checklists |
| **M6** | Birkly-Website prod | DEV.md + copy refresh |
| **M7** | Birkly-Docs sync | Guides published |
| **M8** | Closure | Matrix green; human notified once |
