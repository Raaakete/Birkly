# Themes and template packs

Birkly offers two ways to bootstrap site content and public pages: **Ops-hosted starter packs** during onboarding and **marketplace template packs** sold as offers. Both populate collections and files under `project/` — your public site folder.

## Summary

**Starter packs** are curated demo content and sample sites served from **Birkly Ops** during the setup wizard — blog, shop, business, and docs templates plus a blank option. **Template packs** on the **marketplace** are provider-published offers that install collections and `project/` site files after purchase. **Themes** change visual styling; **template packs** include structure and content.

## Beginner

**Ops starter packs (onboarding)**

When you first set up Birkly, the onboarding wizard offers starter templates:

| Starter | What you get |
|---------|--------------|
| **Marsk** | Flagship slow-living journal — blog, gallery, about pages, and editorial demo site |
| **Start blank** | Empty content and project folder |

Legacy starters (blog, shop, business, docs) remain available via Ops for staff but are not shown in the default onboarding picker.

**Marsk example**

The **Marsk** pack (`marsk`) is the recommended onboarding demo:

1. Complete CMS deploy and open the admin onboarding wizard.
2. Choose **Marsk** — the wizard downloads the pack from Ops (`GET /api/ops/v1/starters/marsk/download`).
3. Collections installed: `blog`, `about`, `gallery`, `site_elements`.
4. Public site files extract to `project/` — `index.html`, `blog.html`, `post.html`, `about.html`, `gallery.html`, plus `css/marsk.css` and `js/marsk.js`.
5. Browse `/project/index.html` to preview; edit posts in **Content → Blog**.

```bash
# Verify Marsk is published on Ops (stable channel)
curl -s 'https://ops.birkly.cloud/api/ops/v1/starters?channel=stable' | jq '.starters[] | select(.id=="marsk")'
```

**Choosing a starter**

1. Complete CMS deploy and open the admin.
2. Follow the **onboarding wizard** (or revisit from Help).
3. Pick **Marsk** or choose **Start blank**.
4. Wait for download and extraction to finish.
5. Explore collections in **Content** and preview the public site.

Availability depends on Ops catalog settings — some starters may show as "coming soon" until the platform publishes a release.

**Marketplace template packs**

Providers can publish **template pack** offers on the marketplace. These are products you buy or get for free through checkout, then install via **Plugins → Marketplace** like any other offer.

Template packs typically include:

- Pre-defined **collections** and sample entries
- HTML, CSS, and JS for the public site in `project/`
- Optional plugin dependencies declared in the offer manifest

**Themes vs template packs**

| Type | What changes |
|------|--------------|
| **Theme** | Visual styling — colors, typography, layout tokens |
| **Template pack** | Content structure plus site files — collections, pages, demo entries |

A template pack may bundle a theme, but they are separate offer types on the marketplace.

**After install**

- Edit content in **Content** like any other collection.
- Customize public site files in `project/` (or via your deployment workflow).
- Remove demo entries you do not need; starter content is a starting point, not permanent.

## Advanced Users

**Project folder (`project/`)**

Public site files live under `project/` (P36). Legacy `demo-website/` paths migrate on demand. Template packs and starters extract into this folder plus `content/collections/`.

**Ops starter API**

| Endpoint | Purpose |
|----------|---------|
| `GET /api/ops/v1/starters?channel={channel}` | Catalog with `onboarding_enabled` flags |
| `GET /api/ops/v1/starters/{id}/download` | Signed zip download |

CMS resolves catalog URL from `starters_catalog_url` in platform config, `BIRKLY_OPS_STARTERS_URL`, or derived from the Ops feedback relay URL. Default: `https://ops.birkly.cloud/api/ops/v1/starters`.

**Marketplace template pack install**

Offer type `template_pack` in the offer manifest. Install flow:

1. License activation (if paid).
2. Permission consent (if the pack includes a plugin component).
3. Extract collections and `project/` files per pack manifest.
4. Record installed version for updates.

See [API reference](api-reference.md) for registry endpoints used during install.

**Local fallback**

If Ops is unreachable, onboarding falls back to bundled catalog metadata and local `demo-content/` when present. Production sites should ensure Ops connectivity or choose **Start blank**.

**Custom starters (platform)**

Birkly Ops staff manage starter pack versions, featured flags, and onboarding visibility. Starters are not provider-published marketplace offers — they are platform-curated content.

## Related

- [Plugin economy overview](overview.md)
- [For site owners](for-site-owners.md)
- [For providers](for-providers.md)
- [Getting started](../00-overview/getting-started.md)
- [Templating overview](../03-templating/templating-overview.md)
- [Reference projects](../03-templating/reference-projects.md)
