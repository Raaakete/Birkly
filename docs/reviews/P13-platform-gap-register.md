# P13 — Platform gap register

**Date:** 2026-07-07 · **Phase:** P13 platform monetization & production hardening  
**Threat model:** `docs/reviews/P13-sso-threat-model.md`  
**Spec:** `docs/reviews/P13-P15-complete-plan.md` § Phase 13

---

## Gap summary

| Area | P12 baseline | P13 target | Milestone |
|------|--------------|------------|-----------|
| Cloud SSO | Deferred P12-D8 | Optional link; local login primary | M1 |
| Paid official tiers | All official free | `amount_cents` > 0 plumbing | M2 |
| Subscription licenses | Checkout rejects subscription | Stripe Billing + webhook revoke | M3 |
| Health ops | health v1 + smoke v1 | Alert email/webhook on error | M4 |
| Code hygiene | Stray console.log, security TODOs | Critical cleanup only | M5 |
| kekaba staging | Prod checklist only | Staging smoke script | M6 |
| Docs | P11+P12 sync | Cloud account, paid/subscription guides | M7 |

---

## P13-M1 — Cloud SSO link

| Gap | Resolution |
|-----|------------|
| No cloud account link | `core/cloud_account.php` + Settings UI |
| Local login risk | P13-D1: link optional; never required |
| Marketplace owner sync | `installation_owner_email` when linked |

---

## P13-M2 — Paid official tiers

| Gap | Resolution |
|-----|------------|
| All official offers free | Manifest `pricing.amount_cents` placeholders |
| CMS one-click for paid | License key or purchase token gate |
| Marketplace tab UX | Price badge + Purchase CTA |

---

## P13-M3 — Subscription licenses

| Gap | Resolution |
|-----|------------|
| `subscription_not_supported` in checkout | Stripe Subscription checkout path |
| No renewal webhooks | `customer.subscription.*` → license status |
| CMS update on lapse | Block update + admin warning |

---

## P13-M4 — Production ops v2

| Gap | Resolution |
|-----|------------|
| No alert on health error | Settings monitoring email/webhook |
| Smoke minimal | Structured health keys validation |
| Ops digest | Smoke failure section in agent digest |

---

## Deferred to P14+

- Multi-currency, inventory webhooks, warehouse stock
- Admin dark mode full pass
- Live kekaba production deploy (human)
