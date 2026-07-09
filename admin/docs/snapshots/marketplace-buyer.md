# Marketplace — buyer license and install

Purchase plugins, themes, and template packs from the [Birkly Marketplace](https://marketplace.birkly.io) and install them in your self-hosted Birkly CMS. This guide is for **buyers** (site owners and admins). Developers publishing offers should see [Marketplace — provider publishing guide](marketplace-provider-guide.md).

## Summary

**Buyers** browse the marketplace catalog and install extensions in the CMS admin under **Plugins → Marketplace**. **Official Birkly plugins** (commerce, events, automation, user management) are **free**: click **Install** in the CMS — no checkout, email, or manual license key. **Paid official** and **third-party** offers require marketplace checkout (Stripe); **subscription** offers renew monthly/yearly until canceled. A **license key** (emailed or **My purchases** / **My subscriptions**) activates on your site. Licenses bind to an **installation** (`installation_id`); seat tiers limit how many CMS installs may use one key. Marketplace accounts are separate from CMS admin accounts.

## Beginner

**Official free plugins (recommended first)**

1. Log in to your Birkly **admin** (`/admin`).
2. Go to **Plugins → Marketplace**.
3. Find an official Birkly offer (Commerce, Events, Automation, User Management) or open [marketplace.birkly.io/catalog](https://marketplace.birkly.io/catalog).
4. Click **Install** in the CMS. The marketplace auto-issues a free license — you do **not** need to copy a license key.
5. **Enable** the plugin from the Plugins list if needed.

See [Marketplace go-live — installing plugins](marketplace-go-live.md) for the full official-plugins table and fresh-install notes.

**Buying a paid or third-party extension**

1. Visit [marketplace.birkly.io/catalog](https://marketplace.birkly.io/catalog).
2. Open an offer and click **Buy** (or **Get** for free community offers that still use checkout).
3. Enter your **email** at checkout. You may optionally create a marketplace login later to see **My purchases** or **My subscriptions**.
4. Complete payment (Stripe) for paid offers, or confirm for free checkout offers.
5. Check your email for the **license key** and receipt.

**Paid official plugins**

Some official Birkly extensions (for example SEO Basics) are **paid one-time** offers on the marketplace. They show a **price badge** and **Purchase** CTA in **Plugins → Marketplace**.

1. Click **Purchase** — opens marketplace checkout for that offer.
2. Complete Stripe payment with your email.
3. Return to CMS → **Plugins → Marketplace** → **Activate license** (or install with emailed key).
4. Paid official plugins **cannot** install without a valid license — free official plugins still use one-click **Install**.

**Subscription licenses**

Offers with **subscription** pricing (for example Analytics Pro) bill through Stripe Billing on a monthly or yearly interval.

1. Buy from the marketplace checkout (subscription mode).
2. Install in CMS with the issued license key.
3. While the subscription is **active**, updates install normally.
4. If the subscription is **canceled** or lapses, the CMS **blocks plugin updates** and shows an admin warning until you renew.
5. View status at marketplace **My subscriptions** (email magic link or marketplace login).

**Installing in Birkly CMS (paid / license key)**

1. Log in to your Birkly **admin** (`/admin`).
2. Go to **Plugins** (or **Settings → Plugins**).
3. Open the **Marketplace** tab.
4. Browse or search the catalog (read-only view of marketplace listings).
5. Click **Install** on an offer you own, or use **Activate license** and paste your license key.
6. The CMS downloads the artifact, verifies the SHA-256 checksum, runs preflight checks, and extracts the plugin to `plugins/`.
7. **Enable** the plugin from the Plugins list if it is not enabled automatically.

**Free vs paid**

| Offer type | Checkout on marketplace | License key in CMS |
|------------|-------------------------|------------------|
| Official Birkly plugins (commerce, events, automation, user management) | No | No — one-click **Install** |
| Paid official (one-time) | Yes (Stripe) | Yes — required before install |
| Subscription official / third-party | Yes (Stripe Billing) | Yes — active subscription required for updates |
| Free community offers | Often yes (email capture) | Yes — emailed or **My purchases** |
| Paid one-time third-party | Yes (Stripe) | Yes — required before install |

**Seat limits**

| Tier | Typical use |
|------|-------------|
| Single site | One Birkly installation |
| Multi-site (3 / 10) | Agency or staging + production |
| Unlimited | Enterprise tier (as listed on the offer) |

If activation fails with a seat error, deactivate the license on an old installation or purchase a higher tier.

**Refunds**

- Request a refund from your **My purchases** page within the policy window (default **14 days** from purchase).
- **Third-party providers** approve or deny refund requests; Birkly may auto-approve for official free offers.
- On **full refund approval**, payment is reversed via Stripe and the license is **revoked** — the plugin may stop receiving updates and paid features may be disabled.
- For disputes, contact the provider’s support email on the offer page; platform admin can escalate.

Marketplace refund and buyer terms: [marketplace.birkly.io/legal/refund](https://marketplace.birkly.io/legal/refund) · [Terms of service](https://marketplace.birkly.io/legal/terms).

## Advanced Users

**Installation identity**

Each CMS install stores a local `installation_id`. License activation sends `license_key + installation_id` to the marketplace API. The same key on a second install counts toward your seat limit. Official free installs use the same activation API internally; the CMS handles it when you click **Install**.

**Settings**

Default marketplace URL: `https://marketplace.birkly.io`. Override via environment variable `BIRKLY_MARKETPLACE_URL` or `settings/marketplace.json` (`marketplace_url`) for staging or self-hosted marketplace instances.

**Updates**

Installed marketplace plugins store `marketplace_slug` and version metadata. The Marketplace tab checks the registry API for newer semver releases. When an update is available, use **Update** to download, verify checksum, and replace files (backup first — see [Updates](updates.md)). **Subscription** licenses: update is blocked when marketplace reports `license_inactive` or lapsed subscription — renew at **My subscriptions** first.

**Offline / air-gapped installs**

Browse and install require network access to the marketplace registry. Already-installed plugins continue to work offline; update checks fail gracefully until connectivity returns.

**Manual install (fallback)**

If you cannot use the Marketplace tab, download the release zip from your purchase email or **My purchases**, verify the published SHA-256 from the offer page, and extract manually into `plugins/`. You still need a valid license for paid offers if the plugin enforces marketplace licensing.

**Security**

- Install only from the official marketplace or sources you trust.
- Review declared capabilities before enabling a plugin.
- Keep Birkly core and marketplace plugins updated.

## Related

- [Marketplace go-live — installing plugins](marketplace-go-live.md)
- [Marketplace — provider publishing guide](marketplace-provider-guide.md)
- [Plugins](plugins.md)
- [Updates](updates.md)
- [Troubleshooting](../07-operations/troubleshooting.md)
- [Birkly Marketplace](https://marketplace.birkly.io)
