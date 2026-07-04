# Phase 6 — Commerce plugins & Birkly Marketplace

**Status:** Proposed — awaiting human approval  
**Repos:** Birkly (primary) · Birkly-Docs · kekaba / Birkly-Website (reference integrations, post-stable)  
**Depends on:** Phase 5.7 complete on `main`  
**Design principle:** Deactivatable plugins in `plugins/` — same packaging as automation and user_management. Commerce plugins communicate via `core/plugins/services.php` and `core/plugins/events.php`, not forked CMS logic.

---

## Why now

Phase 5 closed the **admin platform** gap: settings work, security enforces, DB storage exists, ContentEditor layout studio ships, onboarding/automation polish landed. The next strategic jump is **ecosystem + monetization**:

| Capability | Enables |
|------------|---------|
| **Payments** | Subscriptions, paid content, event tickets, cart checkout |
| **Shopping Cart** | Product catalog + checkout for kekaba-style sites |
| **Events** | Calendars, RSVP, bookings — standalone or paid via Payments |
| **Marketplace** | Discover, install, and trust third-party plugins |

Existing groundwork:

- Plugin service registry (`core/plugins/services.php`)
- Event broadcasting (`core/plugins/events.php`)
- User plugin tiers (`plugins/user_management/`) — payment hooks documented, not wired
- Automation plugin can react to plugin events (`payment_succeeded`, etc.)
- Architecture docs: `docs/plugin-system-architecture-plan.md` § Payment / E-Commerce use cases

---

## Recommended milestone order

```
P6-M0  Commerce foundation + user/payment contracts
  → P6-M1  Plugin: Payments
  → P6-M2  Plugin: Shopping Cart   (depends M1)
  → P6-M3  Plugin: Events          (RSVP standalone; paid tickets need M1)
  → P6-M4  Birkly Marketplace      (depends stable plugin.json + install path)
  → P6-M5  Docs, reference site demos, closure
```

**Rationale:** Payments is the shared dependency. Cart cannot checkout without it. Events can ship RSVP-first in M3, then paid tickets once M1 is green. Marketplace last — it distributes the plugins built in M1–M3.

---

## Milestones (draft)

### P6-M0 — Commerce foundation

**Goal:** Shared contracts before any payment UI.

| Task | Deliverable |
|------|-------------|
| P6-M0-T1 | `payment_service` interface spec + stub in `core/plugins/` (register/discover pattern) |
| P6-M0-T2 | Standard events: `payment_completed`, `payment_failed`, `subscription_renewed`, `order_created` |
| P6-M0-T3 | User plugin: wire tier upgrade/downgrade hooks to payment events (document + implement) |
| P6-M0-T4 | Order/transaction storage schema (flat-file default; optional DB via P5.7 `StorageFactory`) |
| P6-M0-T5 | Human live deploy closure from P5.7 (kekaba embed commit, settings smoke) |

**Acceptance:** Mock payment service passes integration test; user tier changes on simulated `payment_completed`.

---

### P6-M1 — Plugin: Payments

**Goal:** First real commerce plugin — gateway abstraction, admin config, webhooks.

**Location:** `plugins/payments/`

| Area | Scope |
|------|--------|
| Gateways | Start with **one** provider (recommend Stripe **or** Mollie — human pick at approval); abstraction layer for second gateway |
| Admin | Settings tab or dedicated plugin page: API keys, test/live mode, webhook URL |
| Core | `process_payment`, `refund`, `subscription` stubs where gateway supports |
| Storage | Transactions, customers (reference user plugin IDs), webhook idempotency log |
| Automation | Register triggers/actions: payment succeeded/failed, subscription renewed |
| Security | Capability `process_payments`; webhook signature verification; no card data in flat files |

**Acceptance:** Test-mode charge succeeds; webhook updates transaction; automation fires; user tier upgrades when configured.

---

### P6-M2 — Plugin: Shopping Cart

**Goal:** Product catalog + session cart + checkout through Payments service.

**Location:** `plugins/shopping_cart/`

| Area | Scope |
|------|--------|
| Products | Collection-backed products (price field, SKU, inventory optional v1) |
| Cart | Session/guest cart + logged-in cart merge |
| Checkout | Line items → `payment_service` → order record |
| Frontend | Template tags / minimal JS cart widget (reference kekaba integration) |
| Admin | Orders list, order status, link to payment transaction |

**Depends on:** P6-M1 (`payment_service` active)

**Acceptance:** Add to cart → checkout → test payment → order visible in admin; automation on `order_created`.

---

### P6-M3 — Plugin: Events

**Goal:** Event management, RSVP, optional paid admission.

**Location:** `plugins/events/`

| Area | Scope |
|------|--------|
| Events | Collection-backed events (start/end, venue, capacity, visibility) |
| RSVP | Free registration, attendee list, capacity limits |
| Paid tickets | Optional ticket types → Payments plugin (after M1) |
| Calendar | Admin list + template helpers (`upcoming_events`, etc.) |
| Automation | `event_rsvp`, `event_full`, `event_reminder` (scheduled) triggers |

**Acceptance:** Create event → public RSVP → capacity enforced; paid ticket flow works when Payments active.

---

### P6-M4 — Birkly Marketplace

**Goal:** Discover and install plugins from a curated directory — foundation for third-party ecosystem.

| Area | Scope v1 |
|------|----------|
| Registry | JSON/catalog API (hosted on Birkly-Website or static manifest initially) |
| Admin UI | Browse, search, install, update check |
| Install path | Download zip → validate `plugin.json` → capability preflight → activate |
| Trust | Checksum/signature field in manifest; “verified” badge for curated plugins |
| Policies | Per `plugin-system-architecture-plan.md` § Supply Chain & Marketplace |

**Out of v1:** Public developer submissions, ratings/reviews, paid marketplace fees.

**Acceptance:** Install sample plugin from manifest; update check detects new version; unsafe plugin blocked at preflight.

---

### P6-M5 — Docs, demos & closure

| Task | Deliverable |
|------|-------------|
| P6-M5-T1 | Birkly-Docs: Payments, Cart, Events, Marketplace admin guides |
| P6-M5-T2 | Reference demo on kekaba or Birkly-Website (minimal shop or event page) |
| P6-M5-T3 | Integration test matrix |
| P6-M5-T4 | AI context pack commerce section |
| P6-M5-T5 | Roadmap complete; notify human once |

---

## Human decisions needed (before approval)

| # | Question | Recommendation |
|---|----------|----------------|
| 1 | Payment gateway first | Stripe (global) or Mollie (EU) — pick one for M1 |
| 2 | Events vs Cart priority | Cart after Payments (hard dep); Events RSVP can parallel late M1 |
| 3 | Marketplace hosting | Static manifest on Birkly-Website first; API later |
| 4 | kekaba | Demo integration in M5 only — no prod content migration |
| 5 | Phase approval | Approve P6 block for autonomous M0→M5 |

---

## Out of scope (P6)

- Multi-vendor marketplace payouts
- Full ERP/inventory warehouse
- Native mobile apps
- ContentEditor cell merge (separate small phase or P6.1)
- OAuth for AI providers (still separate)

---

## Success criteria

1. Payments plugin processes test transactions and webhooks reliably.  
2. Shopping Cart checkout completes through Payments service.  
3. Events plugin handles RSVP + optional paid tickets.  
4. Marketplace installs a curated plugin with preflight validation.  
5. User tiers upgrade on payment events.  
6. Automation can trigger on commerce events.  
7. Birkly-Docs and one reference demo exist.

---

## Key files (existing)

| Area | Path |
|------|------|
| Service registry | `core/plugins/services.php` |
| Events | `core/plugins/events.php` |
| Capabilities | `core/plugins/capabilities.php` |
| User tiers | `plugins/user_management/includes/UserTiers.php` |
| Automation blocks | `plugins/automation/blocks/triggers.php` |
| Architecture | `docs/plugin-system-architecture-plan.md` |
