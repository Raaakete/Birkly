# P12 — Ecosystem gap register

**Date:** 2026-07-07 · **Phase:** P12 ecosystem & deploy closure  
**Threat model:** `docs/reviews/P12-import-threat-model.md`  
**Spec:** `docs/reviews/P11-P12-complete-plan.md` § Phase 12

---

## Gap summary

| Area | P10 baseline | P12 target | Milestone |
|------|--------------|------------|-----------|
| Third-party publish | Manual zip upload | GitHub App → build → QC queue | M1 |
| Provider payouts | Stripe Connect onboard only | Dashboard + webhook sync | M2 |
| Fee tiers | Single 15% env var | Per-provider tier config | M2 |
| Live import | CSV presets | Shopify + Woo REST API | M3 |
| Plugin denylist | API exists, no admin UI enforcement gap | Settings UI + enforcement tests | M4 |
| Membership wiring | PostPurchaseRouter exists | Commerce product → user tier wizard | M4 |
| Deploy closure | Runbook only | Smoke scripts, health extensions, kekaba checklist | M5 |
| Website | Beta copy | Production refresh | M6 |
| Docs | P10 marketplace | Full P11+P12 sync | M7 |

---

## P12-M1 — GitHub App → QC

| Gap | Resolution |
|-----|------------|
| No GitHub integration | GitHub App + installation callback |
| No webhook receiver | `POST /webhooks/github` — release/tag events |
| Build pipeline | Clone tag → zip → preflight → store artifact → QC enqueue |
| Auto-publish | **Blocked** — verified offers enter QC (P12-D1) |

---

## P12-M2 — Provider growth

| Gap | Resolution |
|-----|------------|
| No Stripe webhooks | `account.updated`, `payment_intent.succeeded`, `charge.refunded` |
| No payout dashboard | Provider sales summary from orders + Stripe balance API |
| Flat fee only | `fee_tier` on provider JSON; override `application_fee_percent` |

---

## P12-M3 — Live import

| Gap | Resolution |
|-----|------------|
| CSV only | `ShopifyImportAdapter`, `WooImportAdapter` |
| No scheduling | Cron-friendly `commerce_import_run` + manual sync button |
| Webhooks | **Deferred** (P12-D3) |

---

## P12-M4 — Denylist + membership

| Gap | Resolution |
|-----|------------|
| Denylist API without settings UI | Settings → Security → plugin denylist panel |
| Tier mapping manual | Wizard: link commerce SKU/slug → user_management tier |

---

## P12-M5 — Deploy closure

| Gap | Resolution |
|-----|------------|
| No automated smoke | `scripts/production-smoke.sh` per repo |
| Health minimal | Extend `/health` with disk, settings writable, version |
| kekaba | Embed checklist in `docs/client-sites/kekaba-deploy-checklist.md` |

---

## Deferred to P13

- CMS cloud SSO (P12-D8)
- Paid official plugin tiers (P12-D9)
