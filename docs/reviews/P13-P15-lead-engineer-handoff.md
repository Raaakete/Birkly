# Phase 13, 14 & 15 — Combined lead-engineer handoff (single wave)

**Status:** Approved (2026-07-07, Keven) — **single-wave execution** P13-M0→M7, P14-M0→M7, P15-M0→M8 without pause  
**Baseline:** P11+P12 complete — Birkly **110 test suites** · Marketplace **12 test suites**  
**Human rule:** Execute **all P13 + P14 + P15 milestones** autonomously in one wave. Notify human **once** at **P15-M8 closure** only.

**Master plan:** `docs/reviews/P13-P15-complete-plan.md`  
**Roadmap:** `docs/roadmap/ROADMAP.md` § Phase 13 · § Phase 14 · § Phase 15  
**Matrices:** `docs/reviews/P13-verification-matrix.md` · `P14-verification-matrix.md` · `P15-verification-matrix.md`

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 13 + Phase 14 + Phase 15 — single combined wave.

Human approved P13+P14+P15 to be developed IN ONE GO (2026-07-07). Execute P13-M0→M7, then P14-M0→M7, then P15-M0→M8 without waiting for human between phases. Notify human ONCE at P15-M8 closure only.

Read first:
- docs/reviews/P13-P15-lead-engineer-handoff.md (this file)
- docs/reviews/P13-P15-complete-plan.md
- docs/reviews/P12-ecosystem-gap-register.md (deferred items now in P13)
- core/marketplace.php · Birkly-Marketplace/docs/PRODUCTION-RUNBOOK.md
- plugins/commerce/ (P12 live import baseline)
- docs/plugin-system-architecture-plan.md § Supply Chain, denylist, scaffold
- docs/roadmap/ROADMAP.md § Phase 13 · § Phase 14 · § Phase 15
- docs/PROJECT_VALUES.md

## Context

P11+P12 COMPLETE. Commerce production, marketplace GitHub App, live Shopify/Woo import, denylist, deploy smoke done.

ONE WAVE DELIVERS:

### Phase 13 — Platform monetization & production hardening
- P13-M0: Gap register, SSO threat model, verification matrix shell
- P13-M1: CMS cloud SSO optional link (local accounts stay primary — P13-D1)
- P13-M2: Paid official marketplace tiers (P13-D2)
- P13-M3: Subscription marketplace licenses via Stripe Billing (P13-D3)
- P13-M4: Production ops v2 — health alerts, smoke hardening, ops digest
- P13-M5: Critical TODO/debug cleanup only (P13-D4)
- P13-M6: kekaba staging smoke + deploy checklist assist
- P13-M7: Birkly-Docs P13 guides (do NOT notify human — continue to P14)

### Phase 14 — Commerce scale & import depth
- P14-M0: Commerce scale gap register, webhook threat model
- P14-M1: Multi-currency checkout (site default + per purchase option)
- P14-M2: Shopify/Woo inventory webhooks (HMAC verified)
- P14-M3: Warehouse/stock v1 (single warehouse, SKU qty)
- P14-M4: Etsy + Square API live import adapters
- P14-M5: Plugin denylist deny_spaces enforcement (P12 stub → functional)
- P14-M6: Birkly-Docs commerce scale guides
- P14-M7: P14 matrix green (no notification — continue to P15)

### Phase 15 — Alpha closure & UX
- P15-M0: Alpha closure gap register, admin UX audit
- P15-M1: Admin dark mode toggle + responsive pass (top 10 flows)
- P15-M2: kekaba content expansion (new collections/pages, bake static HTML)
- P15-M3: CMS Help → live Birkly-Docs wiring with offline fallback
- P15-M4: Closed-alpha legal pack + feedback widget polish
- P15-M5: Plugin scaffolding CLI (scripts/scaffold-plugin.php)
- P15-M6: Birkly-Website production surface
- P15-M7: Full P13–P15 Birkly-Docs sync
- P15-M8: All matrices green, combined closure checklist, SINGLE human notification

## Locked decisions (do not re-ask human)

P13-D1: Cloud SSO = optional LINK only; local CMS login always works (P9-D1 unchanged)
P13-D2: Paid official tiers — agent ships plumbing; per-plugin prices are placeholders until human sets live amounts
P13-D3: Subscription marketplace licenses — Stripe Billing; CMS blocks updates when subscription lapses
P13-D4: Code hygiene — critical TODO/debug only; no broad refactors
P13-D5: Health alert email/webhook on degraded health; smoke script hardening
P13-D6: kekaba staging smoke + checklist; human runs live prod deploy
P13-D7: NO separate P13 notification

P14-D1: Multi-currency v1 — no FX conversion engine
P14-D2: Inventory webhooks on top of P12 scheduled sync; HMAC required
P14-D3: Warehouse v1 — single warehouse, decrement on pay, restore on refund
P14-D4: Etsy + Square live API adapters (CSV fallback remains)
P14-D5: deny_spaces enforcement (complete P12 stub)
P14-D6: NO separate P14 notification

P15-D1: Dark mode light/dark/system + responsive pass on audited admin flows
P15-D2: kekaba 2–3 new pages/collections + baked HTML
P15-D3: Help manifest fetch from Birkly-Docs with offline stub
P15-D4: Alpha legal templates + feedback polish
P15-D5: scaffold-plugin.php minimal + --privileged checklist
P15-D6: Birkly-Website production deploy notes + messaging
P15-D7: SINGLE human notification at P15-M8 with P13+P14+P15 summary

P13-P15-D-EXEC: Single wave; P13/P14 docs can land with P15-M7 batch; one closure email

## Execution order (strict dependency)

P13-M0
  → P13-M1 → M2 → M3 (Marketplace monetization — can parallel M1 after M0)
  → P13-M4 → M5 ∥ M6
  → P13-M7 (docs partial OK — finalize in P15-M7)
  → P14-M0
  → P14-M1 → M2 (needs P13-M4 health baseline) → M3 → M4 → M5
  → P14-M6 → M7
  → P15-M0
  → P15-M1 ∥ P15-M5 (CLI independent)
  → P15-M2 (after P14-M1 if shop demos use currency)
  → P15-M3 → M4 → M6
  → P15-M7 (full docs)
  → P15-M8 (closure — notify human ONCE)

Prefer completing P13-M2/M3 before P15-M6 (website paid-plugin messaging).
Marketplace P13-M2/M3 can parallel with Birkly P13-M1 after P13-M0.

Branches:
- Birkly: cursor/p13-m[N]-<slug>-030a · cursor/p14-m[N]-<slug>-030a · cursor/p15-m[N]-<slug>-030a
- Birkly-Marketplace: cursor/p13-m[N]-<slug>-030a (paid tiers, subscriptions)
- kekaba: cursor/p13-m6-* or cursor/p15-m2-*
- Birkly-Website: cursor/p15-m6-*
- Cross-repo: same milestone id in branch name when task spans repos

## Delegate

- cms-developer: P13-M1 SSO, P13-M4/M5 ops, P14 commerce (M1–M5), P15-M3 Help, P15-M5 CLI
- cms-developer: Marketplace P13-M2/M3 (paid + subscription licenses)
- frontend-developer: P15-M1 admin UX, P13-M2 CMS marketplace UI, P14 import/webhook admin, P15-M4 feedback
- frontend-developer: kekaba P13-M6/P15-M2 content
- docs-writer: P13-M7 + P14-M6 + P15-M7 + legal copy P15-M4
- qa-verifier: Full P13+P14+P15 verification matrices; staging smoke; E2E
- design-reviewer: P15-M1 dark/responsive; all new admin/marketplace UI

## Verification gates (before P15-M8)

- [ ] docs/reviews/P13-verification-matrix.md — all rows pass
- [ ] docs/reviews/P14-verification-matrix.md — all rows pass
- [ ] docs/reviews/P15-verification-matrix.md — all rows pass
- [ ] php tests/run.php — Birkly green
- [ ] php tests/run.php — Birkly-Marketplace green
- [ ] ./scripts/ci-check.sh — Birkly green
- [ ] ./scripts/production-smoke.sh — exits 0 on staging/local
- [ ] docs/reviews/P13-P15-phase-closure-checklist.md created
- [ ] Roadmap § Phase 13 + § Phase 14 + § Phase 15 marked complete

Run php tests/run.php on Birkly AND Birkly-Marketplace before every merge.
Merge PRs yourself after qa-verifier pass (and design-reviewer when UI changes).

Human still owns (document in closure — do NOT block on these):
- Production DNS/TLS marketplace.birkly.io
- Stripe live keys + Connect + subscription price IDs
- Legal review of alpha terms on live hosts
- kekaba live production deploy (execute checklist)
```

---

## Combined milestone checklist

| Phase | ID | Deliverable | Repo |
|-------|-----|-------------|------|
| P13 | M0 | Gap register + SSO threat model | Birkly + Marketplace |
| P13 | M1 | Cloud SSO optional link | Birkly |
| P13 | M2 | Paid official tiers | Marketplace + Birkly |
| P13 | M3 | Subscription licenses | Marketplace + Birkly |
| P13 | M4 | Health alerts + smoke v2 | Birkly + Marketplace |
| P13 | M5 | Critical code hygiene | Birkly |
| P13 | M6 | kekaba staging assist | kekaba + Birkly |
| P13 | M7 | P13 docs (finalize in P15-M7) | Birkly-Docs |
| P14 | M0 | Webhook threat model | Birkly |
| P14 | M1 | Multi-currency | Birkly |
| P14 | M2 | Inventory webhooks | Birkly |
| P14 | M3 | Warehouse stock | Birkly |
| P14 | M4 | Etsy + Square import | Birkly |
| P14 | M5 | deny_spaces enforcement | Birkly |
| P14 | M6 | Commerce scale docs | Birkly-Docs |
| P14 | M7 | P14 matrix green | Birkly |
| P15 | M0 | UX audit | Birkly |
| P15 | M1 | Dark mode + responsive | Birkly |
| P15 | M2 | kekaba content | kekaba |
| P15 | M3 | Help → Birkly-Docs | Birkly + Birkly-Docs |
| P15 | M4 | Legal + feedback | Birkly + Website |
| P15 | M5 | Plugin scaffold CLI | Birkly |
| P15 | M6 | Website production | Birkly-Website |
| P15 | M7 | Full docs sync | Birkly-Docs |
| P15 | M8 | **Closure + single human notification** | All |

---

## Risk notes (single-wave)

| Risk | Mitigation |
|------|------------|
| P14-M2 before P13-M4 ops | Do not start webhooks until health alerts merged |
| Large cross-repo surface | One milestone per PR; merge frequently |
| Missed mid-phase notifications | Intentionally deferred — one email at P15-M8 |
| Paid plugin price placeholders | Ship with documented env/config; human sets live Stripe prices |
| SSO cloud endpoint not live | Device-flow stub + mock tests; document production URL for human |
