# Hub, themes, and site packs

Birkly bootstraps public sites three ways: **Ops starters** during onboarding, **Hub site packs** (`offer_type: template_pack`) from the catalog or **Upload pack**, and separate **theme** offers. Site packs and starters install collections plus files under `project/` — never under `themes/`.

## Summary

In the CMS admin, the catalog tab is labeled **Hub** (APIs and routes may still say `marketplace`). **Site packs** are marketplace offers with `offer_type: template_pack`. They use one shared install engine for Hub download and manual ZIP upload: validate pack layout, optionally confirm missing required plugins, then write `content/collections/` and `project/`. **Demo entries** are opt-in. **Pack updates** skip user-edited `project/` files and entry content. **Themes** remain a separate offer type and install under `themes/`.

## Beginner

**Hub (admin label)**

1. Log in to **admin** (`/admin`).
2. Open **Plugins → Hub**.
3. Browse plugins, **site packs**, and themes (filter by type).
4. Install free offers in one click, or install paid offers from an entitlement / fallback activation key.

> **Note:** The public storefront and registry may still be called the Birkly Marketplace. In the CMS UI, use **Hub**. Internal API actions stay under `/api/marketplace.php` for this phase.

**Ops starters (onboarding)**

When you first set up Birkly, the onboarding wizard offers starter templates:

| Starter | What you get |
|---------|--------------|
| **Marsk** | Flagship slow-living journal — blog, gallery, about pages, and editorial demo site |
| **Start blank** | Empty content and project folder |

Legacy starters (blog, shop, business, docs) remain available via Ops for staff but are not shown in the default onboarding picker.

**Marsk example**

1. Complete CMS deploy and open the admin onboarding wizard.
2. Choose **Marsk** — the wizard downloads the pack from Ops (`GET /api/ops/v1/starters/marsk/download`).
3. Collections installed: `blog`, `about`, `gallery`, `site_elements`.
4. Public site files extract to `project/` — `index.html`, `blog.html`, `post.html`, `about.html`, `gallery.html`, plus `css/marsk.css` and `js/marsk.js`.
5. Browse `/project/index.html` to preview; edit posts in **Content → Blog**.

```bash
# Verify Marsk is published on Ops (stable channel)
curl -s 'https://ops.birkly.cloud/api/ops/v1/starters?channel=stable' | jq '.starters[] | select(.id=="marsk")'
```

**Site packs from Hub**

Providers (and Birkly) publish **site pack** offers. Free and freemium packs install with hidden entitlements and no visible key; paid packs use Stripe checkout, then install from **Plugins → Hub** like any other entitlement-backed offer. Filter type **Site pack** (registry value `template_pack`).

A site pack typically includes:

- Pre-defined **collections** (schemas under `content/collections/`)
- HTML, CSS, and JS for the public site in `project/`
- Optional sample (demo) entries — **off by default**; check **Include demo / sample entries** to add them
- Optional **required** and **recommended** plugins in the pack manifest

**Upload pack (manual ZIP)**

On **Plugins → Installed**, use **Upload pack** to install a site pack ZIP without going through the catalog. The ZIP must use the Ops starter / site pack layout (`manifest.json` + `collections/` + `project/`). Invalid ZIPs and path traversal are rejected. Upload uses the **same install engine** as Hub `template_pack` installs.

> **Note:** Fuller Hub information architecture (Discover · Installed · Updates · Licenses · Upload pack as first-class Hub sections) may still be rolling out. **Upload pack** is available from the Installed plugins area today.

**Install options**

| Option | Behavior |
|--------|----------|
| **Merge** (default) | Overlay pack files onto the existing site; backup first when content already exists |
| **Replace** | Remove pack-targeted collections and `project/` overlay, then install; backup first |
| **Fresh-only** | Refuse install if the site already has collections or project files |
| **Include demo / sample entries** | Opt-in; schemas and `project/` files always install |

**Required plugins**

If the pack declares `required_plugins` that are not installed, install **blocks** until you confirm. Confirming installs the missing required plugins (with entitlement/activation and consent when needed), then continues the pack. Cancel writes nothing. **Recommended** plugins warn only and never block.

**Themes vs site packs**

| Type | `offer_type` | Installs to | What changes |
|------|--------------|-------------|--------------|
| **Theme** | `theme` | `themes/{id}/` | Visual styling package |
| **Site pack** | `template_pack` | `content/collections/` + `project/` | Content structure plus public site files |
| **Plugin** | `plugin` | `plugins/{folder}/` | Admin features, APIs, field types |

A site pack may ship CSS inside `project/`; that is not the same as a theme offer. Theme activation / apply-to-site is a separate track and not required for pack installs.

**After install**

- Edit content in **Content** like any other collection.
- Customize public site files in `project/` (or via your deployment workflow).
- Remove demo entries you do not need; sample content is a starting point, not permanent.

> **Not the same as Git sync:** Settings → Project → Git can push/pull the `project/` **files** to a git remote, but it never touches collections, entries, or media. If you need to move your **content model and starter content** (not just code) to another install, use a site pack, not Git sync. See [Project Git sync](../01-admin/project-git-sync.md).

**Pack updates**

When a newer pack version is available, Hub **Update** (or an equivalent pack update action) re-downloads and applies changes with **safe update** behavior:

- User-edited `project/` files and entry content are **skipped** (tracked via install file hashes).
- Unchanged vendor files from the pack may update.
- Schema / collection config may update even when entries are protected.
- Per-file **reset to pack version** and rare **replace all** (backup + confirm) are available for recovery when you intentionally want pack files back.

## Advanced Users

**Project folder (`project/`)**

Public site files live under `project/` (P36). Legacy `demo-website/` paths migrate on demand. Site packs and starters extract into this folder plus `content/collections/`. They must **never** extract into `themes/`.

**Canonical pack ZIP layout**

```text
{id}-pack-{version}.zip
├── manifest.json         # id, version, collections[], required_plugins[]
├── collections/…         # → content/collections/
└── project/…             # → project/
```

Optional media may be included. Manifest must pass starter/site-pack validation (`manifest.id`, `manifest.version`, non-empty `collections`).

**Shared install engine**

Hub catalog install (`template_pack`) and **Upload pack** (`install_template_pack_zip` / preflight) call the same engine (`birkly_marketplace_install_template_pack_from_zip`). Registry metadata is stored in `installed_template_packs.json` (slug, version, collections, file hashes, source).

| Source | Typical `source` value |
|--------|------------------------|
| Hub offer | `marketplace` |
| Manual ZIP | `upload` |
| Onboarding starter | Ops path (separate download; same extract layout) |

**Overwrite modes (detail)**

| Mode | When site is dirty |
|------|--------------------|
| `merge` | Backup if needed, then overlay pack files |
| `replace` | Backup, remove pack-targeted collections + project overlay, then extract |
| `fresh-only` | Error `site_not_fresh` — no write |

**Demo entries (detail)**

- Default for Hub/ZIP: `include_demo_entries: false`
- Schemas + `project/` HTML/CSS/JS always install
- Entry JSON under `collections/*/entries/` only when opt-in
- Onboarding starters may still seed demo content by design

**Safe update protection**

Protected paths (skip when user-touched unless force/replace-all):

- Everything under `project/`
- Entry files matching `collections/*/entries/`

Collection schema files are not treated as protected user content the same way.

**Ops starter API**

| Endpoint | Purpose |
|----------|---------|
| `GET /api/ops/v1/starters?channel={channel}` | Catalog with `onboarding_enabled` flags |
| `GET /api/ops/v1/starters/{id}/download` | Signed zip download |

CMS resolves catalog URL from `starters_catalog_url` in platform config, `BIRKLY_OPS_STARTERS_URL`, or derived from the Ops feedback relay URL. Default: `https://ops.birkly.cloud/api/ops/v1/starters`.

**Hub / registry install flow (`template_pack`)**

1. Resolve offer + release (`offer_type: template_pack`).
2. Entitlement activation (if paid, with activation key fallback).
3. Permission consent when required plugins need install.
4. Preflight pack layout + missing `required_plugins`.
5. User confirms required plugins if any are missing.
6. Extract to `content/collections/` + `project/` (never `themes/`).
7. Apply demo-entry preference and overwrite mode.
8. Record install + file hashes for safe updates.

See [API reference](api-reference.md) for registry endpoints used during install.

**Provider publish**

Providers can publish site pack offers (`offer_type: template_pack`) with a pack-layout ZIP. Marketplace QC is offer-type-aware: pack artifacts need pack/starter manifest layout, not `plugin.json`. See [For providers](for-providers.md) and [Marketplace — provider publishing guide](../06-extensibility/marketplace-provider-guide.md).

**Local fallback (onboarding)**

If Ops is unreachable, onboarding falls back to bundled catalog metadata and local `demo-content/` when present. Production sites should ensure Ops connectivity or choose **Start blank**.

**Custom starters (platform)**

Birkly Ops staff manage starter pack versions, featured flags, and onboarding visibility. Starters are platform-curated; provider-published site packs are separate Hub offers that use the same zip shape.

## Related

- [Project Git sync](../01-admin/project-git-sync.md) — code-only sync for `project/`, not a substitute for site packs
- [Plugin economy overview](overview.md)
- [For site owners](for-site-owners.md)
- [For providers](for-providers.md)
- [Marketplace — buyer entitlement and install](../06-extensibility/marketplace-buyer-guide.md)
- [Getting started](../00-overview/getting-started.md)
- [Templating overview](../03-templating/templating-overview.md)
- [Reference projects](../03-templating/reference-projects.md)
