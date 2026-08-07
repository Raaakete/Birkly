# Marketplace — buyer entitlement and install

Purchase plugins, themes, and site packs from the [Birkly Marketplace](https://marketplace.birkly.io) and install them in your self-hosted Birkly CMS. This guide is for **buyers** (site owners and admins). Developers publishing offers should see [Marketplace — provider publishing guide](marketplace-provider-guide.md).

## Summary

**Buyers** browse the marketplace catalog and install extensions in the CMS admin under **Plugins → Hub** (admin label; APIs may still say marketplace). **Free offers** install immediately and do not show a license key. **Paid official** and **third-party** offers use Stripe checkout; successful checkout creates an **entitlement**, with an **activation key** available as a fallback for manual activation and a signed local token for offline checks. **No-card trials** and **freemium** plans use entitlements too; freemium plan capabilities are hidden from the normal key UI. Marketplace accounts are separate from CMS admin accounts.

## Beginner

**Official free plugins (recommended first)**

1. Log in to your Birkly **admin** (`/admin`).
2. Go to **Plugins → Hub**.
3. Find an official Birkly offer (Commerce, Events, Automation, User Management) or open [marketplace.birkly.io/catalog](https://marketplace.birkly.io/catalog).
4. Click **Install** in the CMS. The marketplace creates a hidden entitlement — you do **not** need to copy or see a license key.
5. **Enable** the plugin from the Plugins list if needed.

See [Marketplace go-live — installing plugins](marketplace-go-live.md) for the full official-plugins table and fresh-install notes.

**Buying a paid or third-party extension**

1. Visit [marketplace.birkly.io/catalog](https://marketplace.birkly.io/catalog).
2. Open an offer and click **Buy**, **Subscribe**, **Start trial**, or **Get** depending on the listing.
3. Enter your **email** at checkout. You may optionally create a marketplace login later to see **My purchases** or **My subscriptions**.
4. Complete payment with **Stripe** for paid offers. Trial and free/freemium offers do not require a card unless the listing says you are upgrading to a paid plan.
5. Return to the CMS Hub. Paid checkout grants an entitlement automatically; keep the emailed activation key or **My purchases** key as a recovery fallback.

**Paid official plugins**

Some official Birkly extensions (for example SEO Basics) are **paid one-time** offers on the marketplace. They show a **price badge** and **Purchase** CTA in **Plugins → Hub**.

1. Click **Purchase** — opens marketplace checkout for that offer.
2. Complete Stripe payment with your email.
3. Return to CMS → **Plugins → Hub** → install from your entitlement, or use **Activate license** with the fallback key if the linked install cannot see the purchase.
4. Paid official plugins **cannot** install without an active entitlement — free official plugins still use one-click **Install**.

**Subscription entitlements**

Offers with **subscription** pricing (for example Analytics Pro) bill through Stripe Billing on a monthly or yearly interval.

1. Buy from the marketplace checkout (subscription mode).
2. Install in CMS from the issued entitlement, or paste the fallback activation key.
3. While the subscription is **active**, updates install normally.
4. If the subscription is **canceled** or lapses, the CMS **blocks plugin updates** and shows an admin warning until you renew.
5. View status at marketplace **My subscriptions** (email magic link or marketplace login).

**Trials and freemium**

- **No-card trials** start without payment details. The entitlement works only until `trial_ends_at`; offline checks never extend a trial past its trial end.
- **Freemium** installs create a hidden entitlement with plan capabilities. The CMS and plugin use those capabilities to decide which features are available, but buyers do not need to copy a key for the free plan.
- Upgrading from trial or freemium to a paid plan goes through Stripe checkout.

**Partner / complimentary keys**

A provider may send you a **partner key** for a paid offer (friends, agencies, press). Activate it in Hub the same way as a purchased fallback key. Seat limits still apply, and the provider can revoke the key later.

**Installing in Birkly CMS (paid / entitlement or activation key)**

1. Log in to your Birkly **admin** (`/admin`).
2. Go to **Plugins** (or **Settings → Plugins**).
3. Open the **Hub** tab.
4. Browse or search the catalog (read-only view of marketplace listings).
5. Click **Install** on an offer you own, or use **Activate license** and paste your fallback activation key.
6. The CMS downloads the artifact, verifies the SHA-256 checksum, runs preflight checks, and extracts by offer type: plugins → `plugins/`, themes → `themes/`, site packs (`template_pack`) → `content/collections/` + `project/`.
7. **Enable** the plugin from the Plugins list if it is not enabled automatically.

For site pack options (demo entries, overwrite mode, required plugins, Upload pack), see [Hub, themes, and site packs](../06-plugin-economy/theme-and-template-packs.md).

**Free vs paid**

| Offer type | Checkout on marketplace | Key visible in CMS |
|------------|-------------------------|--------------------|
| Official Birkly plugins (commerce, events, automation, user management) | No | No — one-click **Install** |
| Free / freemium community offers | No paid checkout | No visible key; hidden entitlement |
| Trial offers | No card for trial start | No visible key unless upgraded |
| Paid official (one-time) | Yes (Stripe) | Fallback key available; entitlement is primary |
| Subscription official / third-party | Yes (Stripe Billing) | Fallback key available; active subscription required |
| Paid one-time third-party | Yes (Stripe) | Fallback key available; entitlement is primary |

**Seat limits**

| Tier | Typical use |
|------|-------------|
| Single site | One production Birkly installation plus one staging installation |
| Multi-site (3 / 10) | Agency or additional production/staging installs |
| Unlimited | Enterprise tier (as listed on the offer) |

If activation fails with a seat error, deactivate the entitlement on an old installation from buyer self-service or the CMS Hub. Deactivation frees that environment seat. Full revocation, such as a full refund, is different from self-service deactivation and removes the right to use the paid offer.

**Offline grace**

Marketplace licensing is a practical deterrent, not perfect DRM. The CMS stores a signed local entitlement token so installed paid features can keep working during short outages:

- One-time purchases: one offline grace window of up to **30 days** from the last successful refresh.
- Subscriptions: up to **14 days** from the last known active status.
- Trials: no extension past the trial end date.

Reconnect the CMS to refresh status before the grace window ends.

**Refunds**

- Request a refund from your **My purchases** page within the policy window (default **14 days** from purchase).
- **Third-party providers** are the seller/merchant of record for their offers and make the primary refund decision. Birkly handles official Birkly offers and marketplace infrastructure.
- Birkly Ops can escalate when a provider is unresponsive or policy requires intervention.
- On **full refund approval**, payment is reversed through Stripe and the entitlement plus activation slots are **revoked** — paid updates and paid features may stop.
- Chargebacks place the entitlement on **hold** immediately; if the dispute is lost, the entitlement is revoked.

Marketplace refund and buyer terms: [marketplace.birkly.io/legal/refund](https://marketplace.birkly.io/legal/refund) · [Terms of service](https://marketplace.birkly.io/legal/terms).

## Advanced Users

**Installation identity**

Each CMS install stores a local `installation_id` and sends it during entitlement activation. A single-site entitlement allows one active production activation plus one active staging activation; staging is detected from local/staging hostnames or an explicit environment value. Paid activation can still use `license_key + installation_id` as a fallback, but the entitlement and signed local token are the main runtime proof.

**Settings**

Default marketplace URL: `https://marketplace.birkly.io`. Override via environment variable `BIRKLY_MARKETPLACE_URL` or `settings/marketplace.json` (`marketplace_url`) for staging or self-hosted marketplace instances.

**Updates**

Installed marketplace plugins store `marketplace_slug` and version metadata. The Hub tab checks the registry API for newer semver releases. When an update is available, use **Update** to download, verify checksum, and replace files (backup first — see [Updates](updates.md)). Site pack updates skip user-edited `project/` files and entry content. **Subscription** entitlements: update is blocked when marketplace reports an inactive, held, revoked, or lapsed subscription — renew at **My subscriptions** first.

**Offline / air-gapped installs**

Browse and install require network access to the marketplace registry. Already-installed paid plugins rely on the signed local token while offline and must reconnect before the grace rules above expire; update checks fail gracefully until connectivity returns.

**Manual install (fallback)**

If you cannot use the Hub tab for a plugin, download the release zip from your purchase email or **My purchases**, verify the published SHA-256 from the offer page, and extract manually into `plugins/`. For site packs, prefer **Upload pack** on the Installed tab (same engine as Hub) rather than hand-extracting into `themes/`. You still need a valid entitlement or fallback activation key for paid offers if the plugin enforces marketplace licensing.

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
