# Official plugins — Birkly-maintained offers

Birkly publishes and maintains a set of **official plugins** on the marketplace. They are free or paid offers from the Birkly team, kept compatible with each CMS release. Third-party plugins come from independent providers with their own support and trust levels.

## Summary

**Official Birkly plugins** — Commerce, Events, Automation, and User Management — are **free** marketplace offers installed in one click from **Plugins → Marketplace**. Additional official extensions (for example SEO Basics, Analytics Pro) may be **paid** or **subscription** offers. **Third-party** plugins are published by independent providers; review trust level, permissions consent, and reviews before installing. CMS downloads ship without bundled plugins — install what you need from the marketplace.

## Beginner

**Free official plugins**

These four plugins cover common site needs and are maintained by Birkly:

| Plugin | What it adds | Install |
|--------|--------------|---------|
| **Commerce** | Products, cart, checkout, orders, payments | **Plugins → Marketplace → Install** |
| **Events** | Events, RSVP, paid tickets (uses Commerce for sales) | Same |
| **Automation** | Workflows, triggers, conditions, actions | Same |
| **User Management** | Registration, login, tiers, permissions | Same |

No checkout, license key, or marketplace buyer account is required for these four.

**Recommended install order**

1. **Commerce** first if you sell anything.
2. **Events** next if you run ticketed events (install Commerce first for paid tickets).
3. **Automation** when you need workflows.
4. **User Management** when you need public registration or member tiers.

**Paid official plugins**

Some Birkly-published extensions are paid one-time or subscription offers on the marketplace. They show a **price badge** and **Purchase** button in the CMS Marketplace tab. Complete checkout, activate your license, accept permission consent, then install.

**Third-party plugins**

Community and partner plugins are published by **independent providers**, not Birkly. Before installing:

- Check the provider name and trust level on the offer page.
- Read reviews from verified purchases.
- Review the **permission consent** screen carefully.
- Prefer verified-tier offers for production sites.

Third-party support goes to the provider's contact email on the listing. Birkly platform support covers official plugins and marketplace infrastructure.

**Official vs third-party — quick comparison**

| | Official (Birkly) | Third-party |
|--|-------------------|-------------|
| **Publisher** | Birkly team | Independent providers |
| **Core four plugins** | Free, one-click | N/A |
| **Updates** | Tied to Birkly releases | Provider publishes versions |
| **Support** | Birkly platform | Provider |
| **Trust** | Verified by default | Community, trusted, or verified |
| **Pricing** | Free, paid, or subscription | Provider sets pricing |

**Grandfathered installs**

Sites upgraded from older Birkly builds that already had plugins in `plugins/` keep working. Use the Marketplace **Update** action to sync with registry checksums and licenses going forward.

## Advanced Users

**Source and release pipeline**

Official plugin source is built and published to the marketplace registry on tag (`plugin/{name}/v*`). CMS release zips exclude plugin folders — production installs always pull artifacts from the registry API.

**Marketplace slugs (official)**

| Folder | Slug |
|--------|------|
| `commerce` | `birkly-commerce` |
| `events` | `birkly-events` |
| `automation` | `birkly-automation` |
| `user_management` | `birkly-user-management` |

**Dependencies**

Events declares a soft dependency on Commerce for paid ticket flows. User Management integrates with Commerce membership tiers when both are installed. Check `requires_plugins` in each release's `plugin.json`.

**API gating**

Core routes that depend on a plugin return 404 or 503 when the plugin is not installed — for example automation webhooks or commerce APIs — instead of fatal errors.

**Dev installs**

Local development uses `scripts/install-dev-plugins.sh` in the Birkly platform repo to install plugin zips without the marketplace UI. Production and staging should use the CMS marketplace client.

**Compatibility**

Each release declares `requires_birkly` and `requires_php` semver ranges. The CMS preflight blocks install when the site core is too old. Keep Birkly updated before installing new plugin major versions.

## Related

- [Plugin economy overview](overview.md)
- [For site owners](for-site-owners.md)
- [Marketplace go-live — installing plugins](../06-extensibility/marketplace-go-live.md)
- [Commerce plugin](../06-extensibility/plugins/commerce.md)
- [User plugin](../06-extensibility/user-plugin.md)
- [Automation overview](../05-automation/automation-overview.md)
