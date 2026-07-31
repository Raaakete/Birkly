# For site owners — browse, license, and install

Install plugins, themes, and template packs from the Birkly Marketplace directly in your CMS admin. Free official plugins install in one click; paid and third-party offers require checkout and a license key.

## Summary

Open **Plugins → Marketplace** in the admin to browse the catalog, install free official extensions, or activate a license for paid offers. Before install, the CMS shows a **permission consent** screen listing what the plugin can access. Updates download from the registry with SHA-256 verification. Marketplace buyer accounts are separate from your CMS login.

## Beginner

**Install a free official plugin**

1. Log in to **admin** (`/admin`).
2. Go to **Plugins → Marketplace**.
3. Find an official Birkly offer (Commerce, Events, Automation, User Management).
4. Review the **permissions** summary if prompted.
5. Click **Install** — no checkout or license key needed for free official plugins.
6. **Enable** the plugin from the Plugins list if it is not enabled automatically.

**Buy and install a paid or third-party extension**

1. Browse [marketplace.birkly.cloud/catalog](https://marketplace.birkly.cloud/catalog) or the CMS Marketplace tab.
2. Click **Purchase** or **Buy** on the offer.
3. Complete checkout with your email (Stripe for paid offers).
4. Return to **Plugins → Marketplace** → **Activate license** (or paste the key from your email).
5. Review **permission consent** — read what data and admin areas the plugin requests.
6. Accept and **Install**. The CMS downloads, verifies, and extracts the plugin.
7. **Enable** the plugin if needed.

**Permission consent**

Every marketplace install shows what the plugin declared in its manifest:

- **Permissions** — admin capabilities (for example manage collections, read settings).
- **Hooks** — events the plugin listens to (for example entry saved, user login).
- **Data access** — read or write access to entries, media, users, or settings.

You must accept before install proceeds. Consent is recorded per offer version. If a new version requests additional access, you see the consent screen again. See [Security and review](security-and-review.md) for denylist and revoking access.

**Updates**

When a newer version is available, use **Update** on the Marketplace tab. The CMS re-downloads from the registry and verifies the checksum. Subscription licenses must stay active for updates to succeed.

**Seat limits**

Licenses bind to your site's **installation ID**. A single-site tier allows one CMS install. Multi-site tiers cover staging plus production. If activation fails with a seat error, deactivate on an old install or upgrade your license tier.

## Advanced Users

**Installation flow**

1. `GET /api/v1/offer?slug={slug}` — resolve latest release and `permissions_manifest`.
2. Consent check — `consent_required` when no record exists for `{slug, version}` in `settings/marketplace_consent.json`.
3. License activation — `license_key + installation_id` for paid offers.
4. Artifact download — verify SHA-256, preflight zip structure, extract to `plugins/`.

**Configuration**

| Setting | Purpose |
|---------|---------|
| `BIRKLY_MARKETPLACE_URL` | Override marketplace base URL (staging) |
| `settings/marketplace.json` | Persistent `marketplace_url` |
| `installation_id` | Auto-generated local UUID for license seats |

**Subscription licenses**

Offers with `pricing.model: subscription` require an active Stripe Billing subscription. CMS update checks call license status; lapsed subscriptions block updates until renewal at marketplace **My subscriptions**.

**Manual fallback**

Download the release zip from **My purchases**, verify SHA-256 from the offer page, extract to `plugins/`. Paid plugins may still enforce license checks at runtime.

**Refunds**

Request refunds within the policy window from **My purchases**. On approval, the license is revoked and paid features may stop receiving updates.

## Related

- [Plugin economy overview](overview.md)
- [Official plugins](official-plugins.md)
- [Security and review](security-and-review.md)
- [Marketplace — buyer license and install](../06-extensibility/marketplace-buyer-guide.md)
- [Updates](../06-extensibility/updates.md)
