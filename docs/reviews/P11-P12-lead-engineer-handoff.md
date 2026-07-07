# Phase 11 & 12 — Combined lead-engineer handoff (single wave)

**Status:** Approved (2026-07-07, Keven) — **single-wave execution** P11-M0→M7 then P12-M0→M8 without pause  
**Baseline:** P10 complete — Birkly **100 test suites** · Marketplace **10 test suites**  
**Human rule:** Execute **all P11 + P12 milestones** autonomously in one wave. Notify human **once** at **P12-M8 closure** only.

**Master plan:** `docs/reviews/P11-P12-complete-plan.md`  
**Roadmap:** `docs/roadmap/ROADMAP.md` § Phase 11 · § Phase 12  
**Matrices:** `docs/reviews/P11-verification-matrix.md` · `docs/reviews/P12-verification-matrix.md`

**Per-phase handoffs (reference):** `P11-lead-engineer-handoff.md` · `P12-lead-engineer-handoff.md`

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 11 + Phase 12 — single combined wave.

Human approved P11+P12 to be developed IN ONE GO (2026-07-07). Execute P11-M0→M7 then immediately P12-M0→M8 without waiting for human between phases. Notify human ONCE at P12-M8 closure only.

Read first:
- docs/reviews/P11-P12-lead-engineer-handoff.md (this file)
- docs/reviews/P11-P12-complete-plan.md
- docs/reviews/P6-commerce-scenarios-and-model.md
- plugins/commerce/
- Birkly-Marketplace/docs/PRODUCTION-RUNBOOK.md
- docs/plugin-system-architecture-plan.md § Supply Chain, denylist
- docs/roadmap/ROADMAP.md § Phase 11 · § Phase 12

## Context

P10 COMPLETE. Marketplace go-live, plugin unbundling, platform admin done.

ONE WAVE DELIVERS:

### Phase 11 — Commerce production (Birkly primary)
- P11-M0: Gap register, verification matrix shell
- P11-M1: Subscription upgrades + proration
- P11-M2: Dunning (failed payment → automation triggers)
- P11-M3: Gift cards / store credit
- P11-M4: B2B quotes (quote → accept → pay)
- P11-M5: Commerce analytics (widget + CSV)
- P11-M6: Shop/Events → Commerce migration wizard
- P11-M6b: Official plugin CI publish on git tag
- P11-M7: Birkly-Docs commerce guides (do NOT notify human — continue to P12)

### Phase 12 — Ecosystem & deploy closure (cross-repo)
- P12-M0: Import threat model, P12 gap register
- P12-M1: Third-party GitHub App → QC queue (Marketplace)
- P12-M2: Provider growth — webhooks, payouts, fee tiers (Marketplace)
- P12-M3: Live Shopify + WooCommerce API import (Birkly commerce)
- P12-M4: Plugin denylist + user↔commerce membership wiring (Birkly)
- P12-M5: Production deploy closure — smoke scripts, health checks, kekaba checklist
- P12-M6: Birkly-Website production refresh
- P12-M7: Birkly-Docs full P11+P12 sync
- P12-M8: Both matrices green, combined closure checklist, SINGLE human notification

## Locked decisions (do not re-ask human)

P11-D1: Subscription upgrades v1 — same product plan change; simple proration
P11-D2: Dunning via automation triggers + optional built-in email templates
P11-D3: Gift cards as discount/tender type (not separate fieldtype v1)
P11-D4: B2B quotes v1 — quote status workflow, tokenized accept URL
P11-D5: Analytics v1 — dashboard widget + CSV; vanilla JS only
P11-D6: Migration wizard imports legacy Shop/Events; does not remove legacy plugins
P11-D7: Official CI publish on tag (Birkly GitHub Actions only)
P11-D8: Kekaba deploy checklist in P12-M5 (not separate human gate)
P11-D9: NO separate P11 closure notification — rolled into P12-M8

P12-D1: Third-party GitHub App → QC enqueue (not auto-publish without review)
P12-D2: Shopify + Woo REST API import v1
P12-D3: Scheduled sync + manual sync; no Shopify/Woo webhooks v1
P12-D4: Agent delivers checklists/smoke; human runs live DNS/Stripe/legal
P12-D5: Monitoring v1 — health endpoint extensions only
P12-D6: Plugin denylist in settings + enforcement
P12-D7: Membership purchase → user tier via PostPurchaseRouter
P12-D8: CMS cloud SSO DEFERRED to P13
P12-D9: Paid official plugin tiers DEFERRED to P13
P12-D10: SINGLE human notification at P12-M8 with deploy checklist + P11+P12 summary

P11-P12-D-EXEC: Single wave; P11-M7 docs can land with P12-M7 batch; one closure email

## Execution order (strict dependency)

P11-M0
  → P11-M1 → M2 → M3 → M4 → M5 → M6 ∥ M6b
  → P11-M7 (docs partial OK — finalize in P12-M7)
  → P12-M0
  → P12-M1 → M2 (Marketplace — parallel OK after M0)
  → P12-M3 (needs P11 commerce stable — after P11-M3 minimum for import mapping)
  → P12-M4
  → P12-M5 ∥ P12-M6
  → P12-M7 (full docs)
  → P12-M8 (closure — notify human ONCE)

Prefer completing P11-M1–M6 before P12-M3 live import. Marketplace M1–M2 can parallel with P11-M4–M6.

Branches:
- Birkly: cursor/p11-m[N]-<slug>-030a or cursor/p12-m[N]-<slug>-030a (use phase prefix matching milestone)
- Birkly-Marketplace: cursor/p12-m[N]-<slug>-030a
- Cross-repo: same milestone id in branch name when task spans repos

## Delegate

- cms-developer: P11 commerce (M1–M6), P11-M6b CI, P12-M3 import, P12-M4 denylist/membership, P12-M5 health
- cms-developer: Marketplace P12-M1–M2
- frontend-developer: P11 analytics/quotes/wizard UI; P12 GitHub connect, import wizard, provider dashboard
- docs-writer: P11-M7 + P12-M7 + deploy checklists
- qa-verifier: Full P11+P12 verification matrices; E2E smoke
- design-reviewer: All new admin/marketplace UI

## Verification gates (before P12-M8)

- [ ] docs/reviews/P11-verification-matrix.md — all rows pass
- [ ] docs/reviews/P12-verification-matrix.md — all rows pass
- [ ] php tests/run.php — Birkly green
- [ ] php tests/run.php — Birkly-Marketplace green
- [ ] ./scripts/ci-check.sh — Birkly green
- [ ] docs/reviews/P11-P12-phase-closure-checklist.md created
- [ ] Roadmap § Phase 11 + § Phase 12 marked complete

Run php tests/run.php on Birkly AND Birkly-Marketplace before every merge.
Merge PRs yourself after qa-verifier pass (and design-reviewer when UI changes).
```

---

## Combined milestone checklist

| Phase | ID | Deliverable | Repo |
|-------|-----|-------------|------|
| P11 | M0 | Gap register + matrix shell | Birkly |
| P11 | M1 | Subscription upgrades | Birkly |
| P11 | M2 | Dunning | Birkly |
| P11 | M3 | Gift cards | Birkly |
| P11 | M4 | B2B quotes | Birkly |
| P11 | M5 | Analytics | Birkly |
| P11 | M6 | Migration wizard | Birkly |
| P11 | M6b | Official CI publish | Birkly + Marketplace |
| P11 | M7 | Commerce docs (finalize in P12-M7) | Birkly-Docs |
| P12 | M0 | Import threat model | Birkly |
| P12 | M1 | GitHub App → QC | Marketplace |
| P12 | M2 | Provider growth | Marketplace |
| P12 | M3 | Live Shopify/Woo import | Birkly |
| P12 | M4 | Denylist + membership | Birkly |
| P12 | M5 | Deploy closure | All |
| P12 | M6 | Birkly-Website | Birkly-Website |
| P12 | M7 | Full docs sync | Birkly-Docs |
| P12 | M8 | **Closure + single human notification** | All |

---

## Risk notes (single-wave)

| Risk | Mitigation |
|------|------------|
| P12-M3 before P11 commerce stable | Do not start M3 until P11-M1–M3 merged |
| Large PR surface | One milestone per PR; merge frequently |
| Missed P11 notification | Intentionally deferred — one email at P12-M8 |
| Cross-repo drift | Shared milestone id in branch names; lead owns ordering |
