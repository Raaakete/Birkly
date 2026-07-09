# Marketplace go-live — installing plugins

Birkly CMS downloads ship **without bundled plugins**. After a fresh install, the `plugins/` folder is empty and you add extensions from the [Birkly Marketplace](https://marketplace.birkly.io). Official Birkly plugins (commerce, events, automation, user management) are **free** and install in one click from the CMS admin. **Paid official** plugins require marketplace checkout and a license key. **Subscription** official plugins bill monthly/yearly until canceled. **Paid third-party** offers use the same checkout flow.

## Summary

**Fresh installs** contain core CMS only: no commerce, events, automation, or user-management code in the download. Open **Plugins → Marketplace** in the admin to browse the catalog and install extensions. **Free official** plugins install in one click. **Paid official** plugins show a price badge and **Purchase** CTA — complete checkout, then activate the license in CMS. **Existing installs** that already have plugin folders keep working; future updates for those plugins come from the marketplace.

## Beginner

**What changed**

Older Birkly builds included official plugins inside the CMS zip. Current releases ship an empty `plugins/` directory (with a short README). This keeps the core download small and lets you choose only the extensions you need.

**First install workflow**

1. Download and deploy Birkly CMS (see [Getting started](../00-overview/getting-started.md)).
2. Log in to **admin** and complete onboarding if prompted.
3. Open **Plugins → Marketplace**.
4. Browse **Official** or filter the catalog for Birkly-published plugins.
5. Click **Install** on a free official plugin (e.g. Commerce). No marketplace account or license key is needed.
6. When install finishes, **Enable** the plugin if it is not enabled automatically.
7. Repeat for other plugins you need. Install **Commerce** before **Events** if you plan to sell tickets (Events declares a soft dependency on Commerce for paid flows).

**Onboarding hint**

The setup wizard may suggest installing recommended plugins from the marketplace. You can skip this step and install later from **Plugins → Marketplace**.

**Existing sites (grandfathered)**

If you upgraded from an older build that already had plugins in `plugins/`, those copies continue to work. You are not required to reinstall them. When you want updates, use the **Marketplace** tab **Update** action (or reinstall from the marketplace) so checksums and licenses stay in sync.

## Official plugins

These four plugins are maintained by Birkly and listed on the marketplace as **free** offers:

| Plugin folder | Marketplace slug | What it does | Docs |
|---------------|------------------|--------------|------|
| `commerce` | [birkly-commerce](https://marketplace.birkly.io/offers/birkly-commerce) | Payments, catalog, cart, checkout, orders | [Commerce plugin](plugins/commerce.md) |
| `events` | [birkly-events](https://marketplace.birkly.io/offers/birkly-events) | Events, RSVP, paid tickets (uses Commerce) | [Events functions](../03-templating/events-functions.md) |
| `automation` | [birkly-automation](https://marketplace.birkly.io/offers/birkly-automation) | Workflows, triggers, conditions, actions | [Automation overview](../05-automation/automation-overview.md) |
| `user_management` | [birkly-user-management](https://marketplace.birkly.io/offers/birkly-user-management) | Registration, auth, tiers, permissions | [User plugin](user-plugin.md) |

**Paid and subscription official offers** (for example SEO Basics, Analytics Pro) also appear in the catalog with `pricing.model` `one_time` or `subscription`. They require marketplace checkout — see [Marketplace — buyer license and install](marketplace-buyer-guide.md).

Community plugins, themes, and template packs from other providers may be free or paid — see [Marketplace — buyer license and install](marketplace-buyer-guide.md).

## Advanced Users

**Release layout**

The CMS distribution manifest excludes `plugins/*` except placeholder files. Official plugin **source** remains in the Birkly platform repository for development and CI; release zips are built separately and published to the marketplace registry API.

**Dev and CI**

Local development and automated tests use `scripts/install-dev-plugins.sh` in the Birkly repo to install plugin zips without the marketplace UI. Production and staging sites should use the marketplace client.

**Registry and checksums**

Each marketplace release includes a semver version, artifact URL, and SHA-256 checksum. The CMS verifies the checksum before extracting into `plugins/`. Override the marketplace base URL with `BIRKLY_MARKETPLACE_URL` or `settings/marketplace.json` for staging.

**API gating**

Core routes that depend on a plugin (e.g. automation webhooks, commerce APIs) return 404 or 503 when the plugin is not installed, instead of fatal errors.

## Related

- [Marketplace — buyer license and install](marketplace-buyer-guide.md)
- [Marketplace — provider publishing guide](marketplace-provider-guide.md)
- [Plugins](plugins.md)
- [Updates](updates.md)
- [Birkly Marketplace](https://marketplace.birkly.io)
