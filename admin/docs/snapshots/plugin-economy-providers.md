# For providers — publish on the marketplace

Developers and agencies publish plugins, themes, and site packs on the Birkly Marketplace. Buyers install them from the CMS **Hub**. Third-party providers are the seller and merchant of record for their offers, manage offers through **Provider Studio**, receive payouts via Stripe Connect, and pass automated quality checks before listings go live.

## Summary

**Providers** sign up at [providers.birkly.cloud](https://providers.birkly.cloud), accept the Provider Agreement, complete Stripe Connect onboarding, then publish versioned offers with zip artifacts — including **site packs** (`offer_type: template_pack`). Each offer has pricing (free, freemium, no-card trial, one-time, or subscription), entitlement seat tiers, and declared capabilities. Automated preflight is offer-type-aware (plugin vs pack layout); verified-tier offers also receive manual review. P55 Hub payments use Stripe Connect only.

## Beginner

**Who is a provider**

A **provider** (also called publisher or seller) is anyone who lists extensions on the marketplace — independent developers, agencies, or Birkly itself for official plugins. Providers are **not** the same as CMS admin users on customer sites.

**Getting started**

1. Create a **provider account** at [providers.birkly.cloud](https://providers.birkly.cloud) (email and password — separate from any CMS login).
2. Accept the **Provider Agreement**.
3. Complete **Stripe Connect** onboarding (identity and bank details for payouts). Paid providers must be in a Stripe-supported connected-account country.
4. Click **New offer** and fill in title, slug, offer type, category, pricing, and compatibility.
5. Upload a **release zip** (semver version plus changelog).
6. **Publish** — preflight runs automatically; verified offers enter a review queue.
7. Your offer appears in the [public catalog](https://marketplace.birkly.cloud/catalog) and the registry API for CMS installs.

**What you can sell**

| Offer type | `offer_type` | Examples / install target |
|------------|--------------|---------------------------|
| **Plugin** | `plugin` | SEO tools, integrations, custom field types → `plugins/` |
| **Theme** | `theme` | Styling packages → `themes/` |
| **Site pack** | `template_pack` | Collections + `project/` site files (same zip shape as Ops starters) |

Site pack ZIPs need `manifest.json` + `collections/` + `project/` — not `plugin.json`. Buyers install from **Plugins → Hub** or **Upload pack**. See [Hub, themes, and site packs](theme-and-template-packs.md).

**Pricing options**

| Model | Buyer experience |
|-------|------------------|
| **Free** | Immediate CMS install; hidden entitlement, no visible key |
| **Freemium** | Hidden free entitlement with plan capabilities; paid upgrade through Stripe |
| **Trial** | No-card trial entitlement until trial end |
| **One-time** | Single Stripe payment; entitlement plus fallback activation key per seat tier |
| **Subscription** | Monthly or yearly Stripe Billing; entitlement active while subscribed |

**After publish**

- Upload new versions from the offer editor; each version re-runs preflight.
- View sales and payouts in Provider Studio.
- Reply once to verified-purchase reviews on your listing.
- Optionally connect GitHub for tag-triggered release builds.

**Trust levels**

| Level | Meaning |
|-------|---------|
| Community | Automated preflight only |
| Trusted | Passed automated checks; spot-checks possible |
| Verified | Manual review for first release and major updates |

## Advanced Users

**Publishing workflow**

Offers validate against `offer-manifest.schema.json`. Required fields include `slug`, `title`, `summary`, `offer_type`, `category`, `pricing`, `license`, `compatibility`, `trust_level`, and at least one `releases[]` entry with artifact URL, SHA-256 checksum, and size.

**Registry exposure**

Published offers appear at:

- `GET /api/v1/registry` — catalog index
- `GET /api/v1/offer?slug={slug}` — detail plus releases
- `GET /api/v1/release?slug={slug}&version={version}` — download metadata

The CMS Hub client consumes these endpoints. Do not break slug or checksum contracts without a major version bump.

**Revenue split**

Buyer pays gross price → Stripe fee → Birkly application fee → remainder to your Stripe Connect account. The default Birkly application fee is **15%**; Ops can override it for a provider or agreement. Birkly is merchant of record only for official Birkly offers. Mollie, multi-PSP, and regional PSPs are not available for Hub provider payouts in P55.

**Refunds and chargebacks**

Third-party refunds are provider-primary within the default **14-day** window, with Ops escalation when needed. Full refunds revoke the entitlement and activation slots. Chargebacks put access on hold immediately and revoke access if the dispute is lost.

**QC pipeline**

Zip must not contain path traversal. PHP files are scanned for dangerous patterns. Declared capabilities must match `plugin.json`. See [Security and review](security-and-review.md) for scan tiers and supply-chain rules.

For step-by-step publishing instructions, manifest examples, GitHub App automation, and provider webhooks, see the full [Marketplace — provider publishing guide](../06-extensibility/marketplace-provider-guide.md).

## Related

- [Plugin economy overview](overview.md)
- [Plugin development](plugin-development.md)
- [Security and review](security-and-review.md)
- [Hub, themes, and site packs](theme-and-template-packs.md)
- [Marketplace — provider publishing guide](../06-extensibility/marketplace-provider-guide.md)
- [Provider Studio](https://providers.birkly.cloud)
