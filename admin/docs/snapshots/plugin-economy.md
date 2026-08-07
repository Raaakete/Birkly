# Plugin economy overview

Birkly separates the **CMS core** (your self-hosted site), the **Marketplace** (where extensions are bought and distributed), and **Ops** (Birkly platform services such as starter packs and release checks). In the CMS admin, buyers install from **Plugins → Hub**. Plugins extend the CMS without changing core code.

## Summary

The **plugin economy** is how Birkly distributes extensions: **official and third-party offers** on the [Birkly Marketplace](https://marketplace.birkly.cloud), **one-click or entitlement-backed installs** in the CMS **Hub**, and **Ops-hosted starter packs** for onboarding. The CMS ships as a thin runtime — no bundled plugins — so you choose only what you need. Marketplace accounts are separate from CMS admin logins. In P55, Hub checkout uses **Stripe Connect only**.

## Beginner

**Three surfaces, one ecosystem**

| Surface | What it is | Who uses it |
|---------|------------|-------------|
| **Birkly CMS** | Your self-hosted site admin and API | Site owners, editors, developers |
| **Marketplace** | Public catalog, checkout, and plugin registry | Buyers and providers |
| **Ops** | Birkly platform services (starters, feedback relay, release checks) | CMS onboarding; platform staff |

**How it fits together**

1. You download and run **Birkly CMS** on your server. The `plugins/` folder starts empty.
2. You browse **Plugins → Hub** in the admin or visit [marketplace.birkly.cloud/catalog](https://marketplace.birkly.cloud/catalog).
3. You **install** free official plugins in one click, or **purchase** paid and third-party offers and install from an entitlement with a fallback activation key.
4. During onboarding, you can install a **starter pack** from Ops — demo collections and a sample public site under `project/` — without using the marketplace. Hub site packs use the same zip shape.

**Why unbundled plugins**

Keeping plugins out of the CMS zip keeps downloads small, lets you skip features you do not need, and ensures updates come from a single registry with checksum verification. Official Birkly plugins (Commerce, Events, Automation, User Management) remain free on the marketplace.

**Where to go next**

| Your role | Start here |
|-----------|------------|
| Site owner installing extensions | [For site owners](for-site-owners.md) |
| Developer publishing an offer | [For providers](for-providers.md) |
| Developer building a plugin | [Plugin development](plugin-development.md) |
| Choosing official vs community plugins | [Official plugins](official-plugins.md) |

## Advanced Users

**Thin client model**

The CMS distribution manifest excludes plugin source. Production installs consume the marketplace **registry API** (`GET /api/v1/registry`, offer detail, release download) or Ops **starter API** (`GET /api/ops/v1/starters`). Override marketplace base URL with `BIRKLY_MARKETPLACE_URL` or `settings/marketplace.json`. Override starters with `BIRKLY_OPS_STARTERS_URL` or `starters_catalog_url` in platform config.

**Identity boundaries**

| Account type | Scope |
|--------------|-------|
| CMS admin user | Local site roles and permissions |
| Marketplace buyer | Entitlements, purchases, subscriptions, fallback activation keys |
| Marketplace provider | Offers, Stripe Connect, payouts |

Entitlement activation binds access to an `installation_id` (local CMS UUID). Paid offers also keep a fallback activation key for recovery/manual activation. Provider and buyer accounts do not grant CMS admin access.

**Commerce and platform roles**

- Birkly is merchant of record only for official Birkly offers.
- Third-party providers are the seller and merchant of record for their offers.
- Birkly collects a Stripe Connect application fee on third-party paid sales (default **15%**, with Ops override).
- Mollie, multi-PSP, and regional PSP support are outside P55 Hub payments scope.

**Entitlement model**

Free and freemium offers create hidden entitlements with no visible key. Paid checkout creates an entitlement, a fallback activation key, and a signed local token for offline checks. Single-site tiers allow one production activation plus one staging activation; buyer self-service deactivation frees a seat. Offline grace is limited: one-time purchases up to 30 days, subscriptions up to 14 days, and trials never extend past trial end.

**Offer types on the marketplace**

| `offer_type` | Installs to | Typical use |
|--------------|-------------|-------------|
| `plugin` | `plugins/{folder}/` | Admin features, APIs, field types |
| `theme` | `themes/{id}/` | Visual styling packages |
| `template_pack` | `content/collections/` + `project/` | Site packs (never `themes/`) |

Admin UI label is **Hub**; registry/API names may still say marketplace. Ops **starter packs** are platform-curated packs served outside the marketplace catalog during onboarding; see [Hub, themes, and site packs](theme-and-template-packs.md).

## Related

- [For site owners](for-site-owners.md)
- [Official plugins](official-plugins.md)
- [Marketplace go-live — installing plugins](../06-extensibility/marketplace-go-live.md)
- [Plugins](../06-extensibility/plugins.md)
- [Birkly Marketplace](https://marketplace.birkly.cloud)
