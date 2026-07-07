# Phase 11 — Commerce production wave — lead-engineer handoff

**Status:** Proposed — awaiting human approval of `P11-P12-complete-plan.md`  
**Baseline:** P10 complete — Birkly **100 test suites** · Marketplace **10 test suites**  
**Human rule:** Execute **P11-M0 → P11-M7** autonomously. Notify human **once** at **P11-M7 closure**.

**Master plan:** `docs/reviews/P11-P12-complete-plan.md` § Phase 11  
**Roadmap:** `docs/roadmap/ROADMAP.md` § Phase 11

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 11 — Commerce production wave.

Human approved P11 as part of the P11+P12 plan. Execute P11-M0→M7 autonomously. Notify human ONCE at P11-M7 closure only. Do NOT start P12 until P11 is closed.

Read first:
- docs/reviews/P11-P12-complete-plan.md § Phase 11
- docs/reviews/P11-lead-engineer-handoff.md
- docs/reviews/P6-commerce-scenarios-and-model.md
- plugins/commerce/ (all work stays in plugin unless denylist touches core in P12)
- docs/roadmap/ROADMAP.md § Phase 11

## Context

P10 COMPLETE. Official plugins distributed via marketplace. Commerce plugin is the merchant surface.

P11 DELIVERS:
1. Subscription UPGRADES (plan change, provider proration)
2. DUNNING (failed payment → automation triggers + admin status)
3. GIFT CARDS / store credit (issue, redeem, partial balance)
4. B2B QUOTES (quote → accept → pay workflow)
5. COMMERCE ANALYTICS (widget, admin reports, CSV export)
6. Shop/Events → Commerce MIGRATION WIZARD
7. Official plugin CI PUBLISH on git tag (Birkly repo GitHub Actions → marketplace registry)

## Locked decisions (do not re-ask human)

P11-D1: Subscription upgrades v1 — same product plan change; simple proration
P11-D2: Dunning via automation triggers + optional built-in email templates
P11-D3: Gift cards as discount/tender type (not separate fieldtype v1)
P11-D4: B2B quotes v1 — quote status workflow, tokenized accept URL
P11-D5: Analytics v1 — dashboard widget + CSV; vanilla JS only
P11-D6: Migration wizard imports legacy Shop/Events data; does not remove legacy plugins
P11-D7: Official CI publish only (no third-party GitHub App — that's P12)
P11-D8: Kekaba deploy deferred to P12-M5
P11-D9: Single human notification at P11-M7

## Execution order

M0 → M1 → M2 → M3 → M4 → M5 → M6 ∥ M6b → M7

Branches: cursor/p11-m[N]-<slug>-030a

## Delegate

- cms-developer: commerce plugin (M1–M6), CI publish (M6b)
- docs-writer: Birkly-Docs commerce guides (M7)
- qa-verifier: subscription upgrade, gift card, quote, migration E2E
- design-reviewer: analytics UI, quote flow, migration wizard

Run php tests/run.php before every Birkly PR merge. ./scripts/ci-check.sh on CMS PRs.
```

---

## Milestone checklist

| ID | Deliverable | Acceptance |
|----|-------------|------------|
| **M0** | Gap register + matrix shell | Docs merged |
| **M1** | Subscription upgrades | Upgrade test green |
| **M2** | Dunning | Automation trigger on failed payment |
| **M3** | Gift cards | Partial redeem test green |
| **M4** | B2B quotes | Quote → pay flow |
| **M5** | Analytics | Widget + CSV export |
| **M6** | Migration wizard | Legacy fixture import |
| **M6b** | CI publish | Tag → registry release |
| **M7** | Closure | Matrix green; human notified once |
