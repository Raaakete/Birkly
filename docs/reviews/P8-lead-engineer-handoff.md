# Phase 8 — Reference site, commerce production & cell merge — lead-engineer handoff

**Status:** Approved (2026-07-06, Keven) — autonomous execution P8-M0→M6  
**Baseline:** P6.1–P6.7 + P7 complete on `main` (**92 test suites**)  
**Human rule:** Execute **P8-M0 → P8-M6** autonomously. Notify human **once** at **P8-M6 closure**.

**Product spec:** `docs/reviews/P8-reference-commerce-plan.md`  
**Roadmap:** `docs/roadmap/ROADMAP.md` § Phase 8  
**Live site:** `https://birkly.kekaba.com` — human deploys via `git pull`.

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 8 — Reference site, commerce production & ContentEditor cell merge.

Human approved full P8 autonomous execution (M0→M6). Notify human ONCE at P8-M6 closure only.

Read first:
- docs/reviews/P8-lead-engineer-handoff.md (this file)
- docs/reviews/P8-reference-commerce-plan.md
- docs/roadmap/ROADMAP.md § Phase 8
- scripts/sync-client-embed.sh
- plugins/commerce/ (checkout, orders, shelf)
- fieldtypes/ContentEditor/layout/table-layout.js
- docs/reviews/P5.5-lead-engineer-handoff.md (table/grid rules — still apply)

## Context

P7 is COMPLETE on main: plugin admin shell, all plugins register docs, payments/shopping_cart
removed, User Management fixed, Events v2 (recurring + iCal), Automation node editor.

P8 closes three gaps:
1. kekaba embed is STALE — missing commerce + events plugins; needs sync + demo front-end
2. Commerce lacks production ops — tax, order emails, refunds, subscription cancel/status
3. ContentEditor cell merge was deferred since P5.5 — now IN SCOPE (colspan/rowspan)

## Locked decisions (do not re-ask human)

### Reference site (P8-D1, P8-D8)
- kekaba: embed sync via ./scripts/sync-client-embed.sh — demo shop + events pages only
- No full kekaba content migration
- Birkly CMS merges before kekaba template PRs
- Human deploys live; you document P8-deploy-checklist.md

### Commerce ops (P8-D2–D5)
- Tax v1: flat default rate + optional country→rate map at checkout; persist on order
- Refunds v1: full + partial via provider shelf; admin UI on order detail
- Order emails: Automation triggers commerce_order_receipt + commerce_order_admin_notify on order_paid
- Subscriptions v1: cancel + status + past_due from webhooks; NO plan upgrades in P8

### ContentEditor (P8-D6, P8-D7)
- ALL code in fieldtypes/ContentEditor/ only
- Merge/split uses native colspan/rowspan in saved HTML
- Strip data-ce-* and editor chrome on save (existing ContentEditorSync pattern)
- Grid rules unchanged: fluid inline tracks, no @media in entry HTML

### Out of scope P8
- Marketplace (P7.0), live Shopify/Woo API import (P7.1)
- Gift cards, B2B quotes, subscription upgrades, dunning automation
- Nested tables / grid-in-grid

## Deliverables

### P8-M0 — Roadmap baseline (if not already on main)
- Current-state table: P7 complete, 92 tests, plugins list without payments/shopping_cart

### P8-M1 — kekaba embed sync
- Run sync-client-embed.sh against kekaba repo
- Commit kekaba Birkly-0.1/ update (commerce, events, P7 admin assets)
- docs/reviews/P8-deploy-checklist.md

### P8-M2 — Client templates
- kekaba: demo products + events collections; shop list/detail, cart/checkout, calendar/list/event pages
- Birkly-Docs: reference template patterns for commerce + events

### P8-M3 — Commerce ops v1
- Tax rules + checkout line item
- Refund admin (full/partial)
- Automation email triggers on order_paid
- tests/commerce_tax_test.php, tests/commerce_refund_test.php

### P8-M4 — Subscription lifecycle v1
- Subscription records for recurring purchase_options
- Admin list/detail + cancel
- Provider webhook status → past_due/canceled
- tests/commerce_subscription_test.php

### P8-M5 — ContentEditor cell merge / split
- Multi-cell select; merge → colspan/rowspan; split restores grid
- Layout inspector Merge/Split buttons
- fieldtypes/ContentEditor/tests/table_merge_test.php

### P8-M6 — Closure
- docs/reviews/P8-verification-matrix.md
- docs/reviews/P8-phase-closure-checklist.md
- Birkly-Docs + AI context pack updates
- php tests/run.php green
- Roadmap P8 complete; daily report; notify human ONCE

## Execution order

M0 → M1 → M2 (needs M1 embed)
M3 + M4 + M5 can run in parallel after M0 (M5 independent of commerce)
→ M6 closure after all green

Branches: cursor/p8-m[N]-<slug>-24b5 (Birkly and kekaba as needed)

## Delegate

- cms-developer: M3, M4, M5 (commerce + ContentEditor)
- frontend-developer: M2 (kekaba templates)
- design-reviewer: shop/events pages, refund admin UI, table merge inspector
- docs-writer: Birkly-Docs, deploy checklist, verification matrix, AI pack
- qa-verifier: embed smoke, checkout tax/refund flows, table merge regression, full test harness

Run php tests/run.php before each PR merge. Merge PRs yourself without per-PR human review.
```

---

## Milestone checklist

| ID | Deliverable | Acceptance |
|----|-------------|------------|
| **M0** | Roadmap + plan on main | Current state accurate; handoff ready |
| **M1** | kekaba embed sync | `commerce` + `events` in `Birkly-0.1/plugins/` |
| **M2** | Client templates | Shop + events demo pages render; variants work |
| **M3** | Commerce ops v1 | Tax on checkout; refund admin; email triggers |
| **M4** | Subscriptions v1 | Cancel + status in admin |
| **M5** | Cell merge/split | colspan/rowspan round-trip; split works |
| **M6** | Closure | Matrix green; human notified once |

---

## Verification matrix (summary)

| Flow | Target |
|------|--------|
| sync-client-embed.sh → kekaba has commerce + events | pass |
| Demo shop listing + product detail + checkout | pass |
| Events calendar shows recurring occurrence | pass |
| Checkout applies tax rule | pass |
| Admin partial refund updates order | pass |
| order_paid fires automation email triggers | pass |
| Subscription cancel updates status | pass |
| Table merge 2 cells → save → reload → split | pass |
| php tests/run.php green | pass |

---

## Branch naming

- Birkly: `cursor/p8-m[N]-<slug>-24b5`
- kekaba: `cursor/p8-m[N]-<slug>-24b5`
