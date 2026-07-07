# P16 — Stripe live configuration

**Date:** 2026-07-07 · **Milestone:** P16-M2  
**Marketplace runbook:** `Birkly-Marketplace/docs/PRODUCTION-RUNBOOK.md`  
**Env templates:** `Birkly-Marketplace/.env.example`, `Birkly/.env.example`

Human creates live Stripe objects and sets secrets on the production host. Agents document only.

---

## Checklist overview

| Area | Owner | Doc section |
|------|-------|-------------|
| Marketplace Stripe Connect + checkout | Human | § Marketplace env |
| Stripe webhooks (live) | Human | § Webhooks |
| Official paid offer Price IDs | Human | § Catalog → Stripe mapping |
| CMS marketplace client URL | Human | § CMS env |
| kekaba commerce (live) | Human | § Client commerce |

---

## Marketplace environment variables

Copy `Birkly-Marketplace/.env.example` to `.env` on the production host.

| Variable | Required live | Notes |
|----------|---------------|-------|
| `MARKETPLACE_BASE_URL` | Yes | `https://marketplace.birkly.io` |
| `MARKETPLACE_ADMIN_EMAIL` | Yes | Platform ops login |
| `MARKETPLACE_ADMIN_PASSWORD` | Yes | Strong random; not `admin-change-me` |
| `STRIPE_SECRET_KEY` | Yes | `sk_live_...` |
| `STRIPE_PUBLISHABLE_KEY` | Yes | `pk_live_...` (checkout UI if used) |
| `STRIPE_CONNECT_CLIENT_ID` | Yes | Connect Express `ca_...` |
| `STRIPE_APPLICATION_FEE_PERCENT` | Yes | Default `15` |
| `STRIPE_WEBHOOK_SECRET` | Yes | Live endpoint `whsec_...` |
| `GITHUB_APP_ID` | If using GitHub releases | App settings |
| `GITHUB_WEBHOOK_SECRET` | If using GitHub releases | `whsec_...` |
| `GITHUB_APP_PRIVATE_KEY_PATH` | If using GitHub releases | PEM on host, not in git |

---

## CMS environment variables (marketplace client)

Copy `Birkly/.env.example` to `.env` when using env-based config (optional; Settings UI can override).

| Variable | Required live | Notes |
|----------|---------------|-------|
| `BIRKLY_MARKETPLACE_URL` | Recommended | `https://marketplace.birkly.io` |
| `BIRKLY_CLOUD_OAUTH_URL` | If cloud SSO live | `https://cloud.birkly.io` |
| `BIRKLY_CLOUD_ACCOUNT_MOCK` | **Never** in prod | Dev/test only |

---

## Stripe Connect setup (human)

1. Enable **Connect Express** on the Stripe live account.
2. Create Connect application; note **Client ID** (`ca_...`).
3. Configure Connect branding and redirect URLs for provider onboarding.
4. Set `STRIPE_CONNECT_CLIENT_ID` in marketplace `.env`.

---

## Webhooks (live)

Register in Stripe Dashboard → Developers → Webhooks:

| Endpoint | Events |
|----------|--------|
| `https://marketplace.birkly.io/webhooks/stripe` | `account.updated`, `payment_intent.succeeded`, `payment_intent.payment_failed` |

Copy signing secret to `STRIPE_WEBHOOK_SECRET`.

---

## Catalog `amount_cents` → Stripe Price objects

Official catalog amounts (manifest `pricing.amount_cents`) must match live Stripe Prices.

| Offer slug | Model | `amount_cents` | Currency | Stripe action (human) |
|------------|-------|----------------|----------|------------------------|
| `birkly-hello-starter` | free | 0 | USD | No Price object |
| `birkly-minimal-theme` | free | 0 | USD | No Price object |
| `birkly-seo-basics` | one_time | **1499** | USD | Create Product + **one-time** Price `$14.99` |
| `birkly-analytics-pro` | subscription | **999** / month | USD | Create Product + **recurring** Price `$9.99/month` |

### Mapping in checkout (v1)

Marketplace checkout uses `amount_cents` from the offer manifest at purchase time. For live Stripe:

1. Create Stripe Products matching official offer titles.
2. Create Prices with **identical** unit amounts and intervals.
3. Document Price IDs in your ops vault (not committed). Future registry versions may add optional `stripe_price_id` per offer — until then, verify amounts manually before go-live.

### Subscription licenses

Subscription offers (`birkly-analytics-pro`) issue renewable licenses on successful payment. Ensure webhook `payment_intent.succeeded` (and subscription events if added later) are subscribed.

---

## Admin verification (post go-live)

- [ ] Marketplace admin login uses non-default password
- [ ] Test purchase: `birkly-seo-basics` (one-time) with live card in Stripe test mode **disabled**
- [ ] Test subscription: `birkly-analytics-pro` — license activates in CMS Plugins → Marketplace
- [ ] CMS install of purchased plugin succeeds against live registry
- [ ] Application fee appears on Connect payment in Stripe Dashboard

---

## Client site commerce (kekaba / embedded CMS)

Commerce plugin uses **site owner's** Stripe keys in CMS Settings (separate from marketplace). For kekaba production shop:

- [ ] Commerce plugin installed from marketplace (live)
- [ ] Stripe live keys in CMS Settings → Commerce (human)
- [ ] Webhook URL registered for site CMS origin if using Stripe webhooks

See `docs/client-sites/kekaba-deploy-checklist.md` § Live production.

---

## Related

- `docs/reviews/P16-production-deploy-playbook.md`
- `docs/reviews/P16-prod-secrets-threat-model.md`
- `Birkly-Marketplace/docs/PRODUCTION-RUNBOOK.md`
