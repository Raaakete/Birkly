# Production optimization

Optional advanced delivery modes for high-traffic or SEO-critical sites. **The default for starter packs and most sites is live `birkly-client.js`.** Add hydrate, snapshots, or bake only when measurement shows the live path cannot meet your targets.

## Summary

Most Birkly sites ship with one script tag: `birkly-client.js` fetches content at runtime and renders your HTML templates. That path needs no build step and is the **recommended default**. **Hydrate** (`birkly-hydrate.js`), **snapshots** (pre-generated static HTML), and **bake** (`meta name="birkly-baked"`) are optional optimizations for stricter performance or SEO — they are **not** required for starter packs or typical sites.

## Beginner

**Start with the live client**

```html
<script src="https://cms.example.com/birkly-client.js"
        data-cms-url="https://cms.example.com"></script>
```

When someone opens your page, the script asks Birkly for content and fills in placeholders like `{ title }` and `{for each entry in 'blog'}`. Your HTML files are the source of truth; you do not need a separate build or deploy bake step.

**When is that enough?**

For blogs, portfolios, small shops, and community sites, the live client is usually sufficient — especially if you follow the [starter pack](reference-projects.md) patterns. Birkly targets a small number of API round-trips per page (often bundled into one request).

**When might you need more?**

Consider advanced options only if you have measured a real problem:

- **Slow first paint** on marketing landing pages with strict performance budgets
- **Search engines** that need fully static HTML files at predictable URLs
- **CDN-only static hosting** with the CMS on a subdomain and no runtime reads on every page view

Even then, try improving templates and the live client first. Starter packs exist to prove that path works without extra tooling.

**What the advanced options do (plain terms)**

| Technique | What it does | Build step? |
|-----------|--------------|-------------|
| **Live client** (default) | Browser fetches content and renders templates | No |
| **Hydrate** | Page loads with content already in HTML; a lighter script adds forms, cart, auth | Yes — pre-render in deploy |
| **Snapshots** | Copies of each page saved as static `.html` files for crawlers or CDN | Yes — regenerate on publish |
| **Bake** | Marks HTML that was fully rendered at build time | Yes — used with hydrate/export |

Interactive features (login, cart, forms) still need `birkly-client.js` or hydrate — even on snapshot sites.

## Advanced Users

**Default path (recommended)**

```html
<script src="/birkly-client.js" data-cms-url="https://cms.example.com"></script>
```

- Content via `page_bundle` and templating; target ≤5 API round-trips per page
- No build step; HTML templates are source of truth
- **Starter packs and most sites use this path**

If a user story requires hydrate or snapshots on a starter site, treat it as a templating or client bug — fix the engine first.

---

### Optional: Hydrate (`birkly-hydrate.js`)

Secondary delivery for faster first paint while keeping editable templates.

| Aspect | Live client | Hydrate |
|--------|-------------|---------|
| First paint | Waits for API + render | Static HTML shell, then enhancement |
| Build step | None | Pre-render or bake in deploy pipeline |
| Script | `birkly-client.js` | `birkly-hydrate.js` (+ optional baked meta) |
| Starter packs (default) | **Yes** | **No** |

Typical flow:

1. Deploy pipeline renders templates server-side (PHP `public.php` or CI bake script)
2. Published HTML includes content placeholders or `meta[name=birkly-baked]`
3. `birkly-hydrate.js` attaches interactivity (forms, cart, auth) without re-fetching all content

**Consider when:** Marketing pages with strict LCP budgets, CDN-only static hosting with CMS on a subdomain.

**Avoid when:** Admin previews, rapid iteration, or before exhausting live-client optimizations.

---

### Optional: Snapshots

Pre-generated static HTML copies of slug pages (e.g. `/publish/post/my-slug.html`).

| Use case | Benefit |
|----------|---------|
| SEO crawlability | Fully static files for search bots |
| Edge CDN | No runtime CMS dependency for reads |
| Archive / export | Point-in-time content freeze |

Trade-offs: regenerate on publish (webhook, cron, or manual bake); slug URL mapping must stay in sync with live routes; interactive features still need `birkly-client.js` or hydrate.

Client sites such as kekaba may use snapshots for selected sections — that is an **advanced** pattern, not the Birkly default.

---

### Optional: Bake (`birkly-baked` meta)

Embeds resolved template output into HTML at build time:

```html
<meta name="birkly-baked" content="true">
```

Used with hydrate or static export tooling. **Not** required for starter packs.

---

### Decision guide

```
Need SEO + max performance?
  ├─ Live client meets ≤5 RTTs? → Stay on live client (default)
  ├─ Need faster LCP only? → Consider hydrate + server render
  └─ Need fully static URLs for crawlers? → Snapshots + live client for interactive pages

Need user auth / cart / forms?
  └─ Always ship birkly-client.js (live or hydrate mode)
```

---

### Summary table

| Technique | Starter packs (default) | Production option |
|-----------|-------------------|-------------------|
| Live `birkly-client.js` | **Default** | Always supported |
| `birkly-hydrate.js` | No | Optional advanced |
| `birkly-baked` meta | No | Optional advanced |
| Snapshot static HTML | No | Optional advanced |
| Custom `fetch()` in site JS | No | Discouraged — use `data-birkly-*` |

Start with the [starter pack](reference-projects.md) that matches your use case, install collections, and ship live client. Add hydrate or snapshots only when measurement shows it is necessary.

## Related

- [Starter packs](reference-projects.md)
- [Client library](../04-integration/client-library.md)
- [Templating overview](templating-overview.md)
- [Website integration](../04-integration/website-integration.md)
- [Production deploy checklist](../07-operations/production-deploy-checklist.md)
