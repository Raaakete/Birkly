# For site owners — browse, license, and install

Install plugins, themes, and site packs from the Birkly Hub in your CMS admin (catalog backed by the Birkly Marketplace). Free offers install in one click with no visible key; paid and third-party offers require Stripe checkout and install from an entitlement, with a fallback activation key for manual recovery.

## Summary

Open **Plugins → Hub** in the admin to browse the catalog, install free official extensions, start no-card trials, or activate paid entitlements. Before install, the CMS shows a **permission consent** screen listing what the plugin can access. Updates download from the registry with SHA-256 verification. Marketplace buyer accounts are separate from your CMS login. APIs may still use `marketplace_*` names.

## Beginner

**Install a free official plugin**

1. Log in to **admin** (`/admin`).
2. Go to **Plugins → Hub**.
3. Find an official Birkly offer (Commerce, Events, Automation, User Management).
4. Review the **permissions** summary if prompted.
5. Click **Install** — no checkout or visible license key needed for free official plugins.
6. **Enable** the plugin from the Plugins list if it is not enabled automatically.

**Buy and install a paid or third-party extension**

1. Browse [marketplace.birkly.cloud/catalog](https://marketplace.birkly.cloud/catalog) or the CMS **Hub** tab.
2. Click **Purchase**, **Buy**, **Subscribe**, or **Start trial** on the offer.
3. Complete checkout with your email (Stripe for paid offers).
4. Return to **Plugins → Hub** and install from your entitlement, or use **Activate license** with the fallback key from your email / **My purchases**.
5. Review **permission consent** — read what data and admin areas the plugin requests.
6. Accept and **Install**. The CMS downloads, verifies, and extracts the plugin (or site pack — see [Hub, themes, and site packs](theme-and-template-packs.md)).
7. **Enable** the plugin if needed.

**Trials and freemium**

- No-card trials create a time-limited entitlement. Offline use never extends past the trial end date.
- Freemium plans create a hidden entitlement with plan capabilities. You do not copy a key for the free plan.
- Upgrade from trial/freemium to paid through Stripe checkout.

**Permission consent**

Every Hub install shows what the plugin declared in its manifest:

- **Permissions** — admin capabilities (for example manage collections, read settings).
- **Hooks** — events the plugin listens to (for example entry saved, user login).
- **Data access** — read or write access to entries, media, users, or settings.

You must accept before install proceeds. Consent is recorded per offer version. If a new version requests additional access, you see the consent screen again. See [Security and review](security-and-review.md) for denylist and revoking access.

**Site packs**

Filter Hub by **Site pack** (`template_pack`) to install collections + `project/` site files. Demo entries are opt-in. Missing **required plugins** need your confirm before the pack writes. You can also use **Upload pack** on the Installed tab for a private ZIP. Details: [Hub, themes, and site packs](theme-and-template-packs.md).

**Updates**

When a newer version is available, use **Update** on the Hub tab. The CMS re-downloads from the registry and verifies the checksum. Subscription entitlements must stay active for updates to succeed. Site pack updates skip user-edited `project/` files and entry content.

**Seat limits**

Entitlements bind to your site's **installation ID**. A single-site tier allows **one production** install plus **one staging** install. If activation fails with a seat error, deactivate on an old install to free that environment seat or upgrade your license tier.

## Advanced Users

**Installation flow**

1. `GET /api/v1/offer?slug={slug}` — resolve latest release and `permissions_manifest`.
2. Consent check — `consent_required` when no record exists for `{slug, version}` in `settings/marketplace_consent.json`.
3. Entitlement activation — paid offers use entitlement status first; `license_key + installation_id` remains the fallback activation path.
4. Artifact download — verify SHA-256, preflight zip structure, extract to `plugins/`.

**Configuration**

| Setting | Purpose |
|---------|---------|
| `BIRKLY_MARKETPLACE_URL` | Override marketplace base URL (staging) |
| `settings/marketplace.json` | Persistent `marketplace_url` |
| `installation_id` | Auto-generated local UUID for entitlement seats |

**Subscription entitlements**

Offers with `pricing.model: subscription` require an active Stripe Billing subscription. CMS update checks call marketplace status; lapsed, held, or revoked entitlements block updates until renewal or resolution at marketplace **My subscriptions**.

**Offline grace**

The CMS stores a signed local entitlement token. It is a practical deterrent, not perfect DRM. One-time purchases have one 30-day offline grace window after the last successful refresh, subscriptions have 14 days, and trials do not extend past the trial end date.

**Manual fallback**

Download the release zip from **My purchases**, verify SHA-256 from the offer page, extract to `plugins/`. Paid plugins may still enforce entitlement checks at runtime and may require the fallback activation key.

**Refunds**

Request refunds within the default 14-day policy window from **My purchases**. Third-party providers make the primary refund decision; Ops can escalate. A full refund revokes the entitlement and paid features may stop receiving updates. Chargebacks place access on hold immediately and revoke access if the dispute is lost.

## Related

- [Plugin economy overview](overview.md)
- [Official plugins](official-plugins.md)
- [Security and review](security-and-review.md)
- [Marketplace — buyer entitlement and install](../06-extensibility/marketplace-buyer-guide.md)
- [Updates](../06-extensibility/updates.md)
