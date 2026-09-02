# Site URL Map (Discovery SEO)

## Summary

**Discovery → SEO → Site URL Map** is the only place to configure how each URL on your site appears in Google, Bing, and social networks. It combines **site defaults**, **per-page SEO** for static HTML files, and **per-item SEO** for collection detail URLs. The same published map powers both SSR and client-side rendering — no duplicate configuration.

## Beginner

### Three layers

| Layer | What it covers |
|-------|----------------|
| **Site defaults** | Fallback title, description, and OG image for the whole site |
| **Per-page SEO** | One row per static project page (`index.html`, `about.html`, …) |
| **Per-item SEO** | Detail URLs (blog posts, products) from collection + template bindings |

### Workflow

1. Open **Discovery → SEO** and turn the SEO pack **on**.
2. Click **Rescan project** to discover HTML files under `project/`.
3. Configure **detail bindings** — link a collection to a detail template file. Templates with `<meta name="birkly-collection" content="slug">` are auto-detected.
4. Optionally override title/description per URL row; add **redirects** (301/302).
5. Click **Publish SEO** — updates sitemap, injects meta tags, and materializes detail URLs.

### Detail bindings

Each binding links:

- A **collection** (e.g. `blog`)
- A **template file** (e.g. `post.html`)
- An **excerpt field** (auto-guessed: `excerpt`, `summary`, `teaser`, …)

Published entries get a URL map row. SEO defaults from entry **title** + excerpt; per-row overrides are optional.

### Pretty URLs (optional)

Set a **URL pattern** on a binding, e.g. `/blog/{slug}`. Birkly routes that path to the detail template without query strings.

### Entry editor change

The entry **Meta Data** tab is removed for collections with a detail binding. Use **Search listing in Discovery** on the entry sidebar instead. Legacy `meta_title` / `meta_description` on entries are imported into the URL map on first SEO publish after upgrade.

## Advanced Users

**SSR vs client rendering:**

| Mode | How SEO applies |
|------|-----------------|
| **Server (SSR)** | Meta tags injected during `core/project_ssr.php` render |
| **Client** | `birkly-client.js` fetches `/api/public/seo-context.php` and applies head tags on boot |

Same published map powers both paths.

**Detail template contract:** Detail pages must use URL context (`?collection=slug&slug={slug}` or pretty URL) with `<meta name="birkly-collection" content="slug">` — not a collection loop. See [Detail pages and URL context](../03-templating/detail-pages-and-url-context.md).

**Redirects:** 301/302 rows are enforced at the HTTP layer when site delivery routing is active (P66).

**Health board:** Discovery → SEO surfaces missing titles, duplicate descriptions, and redirect loops alongside the URL map.

## Related

- [Discovery overview](discovery.md)
- [Detail pages and URL context](../03-templating/detail-pages-and-url-context.md)
- [Website integration](../04-integration/website-integration.md)
