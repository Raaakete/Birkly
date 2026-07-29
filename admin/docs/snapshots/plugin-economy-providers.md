# For providers — publish on the marketplace

Developers and agencies publish plugins, themes, and template packs on the Birkly Marketplace. Providers manage offers through **Provider Studio**, receive payouts via Stripe Connect, and pass automated quality checks before listings go live.

## Summary

**Providers** sign up at [providers.birkly.cloud](https://providers.birkly.cloud), accept the Provider Agreement, complete Stripe Connect onboarding, then publish versioned offers with zip artifacts. Each offer has pricing (free, one-time, or subscription), license seat tiers, and declared plugin capabilities. Automated preflight validates structure and security; verified-tier offers also receive manual review. Payouts flow to your Connect account after buyer checkout.

## Beginner

**Who is a provider**

A **provider** (also called publisher or seller) is anyone who lists extensions on the marketplace — independent developers, agencies, or Birkly itself for official plugins. Providers are **not** the same as CMS admin users on customer sites.

**Getting started**

1. Create a **provider account** at [providers.birkly.cloud](https://providers.birkly.cloud) (email and password — separate from any CMS login).
2. Accept the **Provider Agreement**.
3. Complete **Stripe Connect** onboarding (identity and bank details for payouts).
4. Click **New offer** and fill in title, slug, offer type, category, pricing, and compatibility.
5. Upload a **release zip** (semver version plus changelog).
6. **Publish** — preflight runs automatically; verified offers enter a review queue.
7. Your offer appears in the [public catalog](https://marketplace.birkly.cloud/catalog) and the registry API for CMS installs.

**What you can sell**

| Offer type | Examples |
|------------|----------|
| **Plugin** | SEO tools, integrations, custom field types |
| **Theme** | Admin or storefront styling packages |
| **Template pack** | Pre-built collections plus public site files |

**Pricing options**

| Model | Buyer experience |
|-------|------------------|
| **Free** | Email capture optional; CMS one-click or license key |
| **One-time** | Single payment; perpetual license per seat tier |
| **Subscription** | Monthly or yearly billing; license active while subscribed |

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

The CMS marketplace client consumes these endpoints. Do not break slug or checksum contracts without a major version bump.

**Revenue split**

Buyer pays gross price → Stripe fee → Birkly application fee → remainder to your Stripe Connect account. Refunds are primarily the provider's responsibility per the Provider Agreement.

**QC pipeline**

Zip must not contain path traversal. PHP files are scanned for dangerous patterns. Declared capabilities must match `plugin.json`. See [Security and review](security-and-review.md) for scan tiers and supply-chain rules.

For step-by-step publishing instructions, manifest examples, GitHub App automation, and provider webhooks, see the full [Marketplace — provider publishing guide](../06-extensibility/marketplace-provider-guide.md).

## Related

- [Plugin economy overview](overview.md)
- [Plugin development](plugin-development.md)
- [Security and review](security-and-review.md)
- [Themes and template packs](theme-and-template-packs.md)
- [Marketplace — provider publishing guide](../06-extensibility/marketplace-provider-guide.md)
- [Provider Studio](https://providers.birkly.cloud)
