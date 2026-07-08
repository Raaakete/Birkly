# Production optimization (advanced)

**Phase:** P20-M11 preview  
**Audience:** Teams shipping high-traffic or SEO-critical client sites

Reference projects in `reference-projects/` intentionally use the **default live path** only. This document describes **optional** advanced optimizations — use when templating alone cannot meet performance or SEO targets.

---

## Default path (recommended)

```html
<script src="/birkly-client.js" data-cms-url="https://cms.example.com"></script>
```

- Content fetched at runtime via `page_bundle` and templating
- Target: ≤5 API round-trips per page (see `docs/benchmarks/kekaba-index-roundtrips.md`)
- No build step; HTML templates are the source of truth
- **All P20 reference projects use this path**

If a user story requires hydrate or snapshots on a reference project, treat it as a **templating or client bug** (P20-D10) — fix the engine first.

---

## Optional: Hydrate (`birkly-hydrate.js`)

Hydrate is a **secondary** delivery mode for sites that need faster first paint while keeping editable templates.

| Aspect | Live client | Hydrate |
|--------|-------------|---------|
| First paint | Waits for API + render | Static HTML shell, then enhancement |
| Build step | None | Pre-render or bake step in deploy pipeline |
| Script | `birkly-client.js` | `birkly-hydrate.js` (+ optional baked meta) |
| Reference projects | **Yes** | **No** |

Typical flow:

1. Deploy pipeline renders templates server-side (PHP `public.php` or CI bake script)
2. Published HTML includes content placeholders or `meta[name=birkly-baked]`
3. `birkly-hydrate.js` attaches interactivity (forms, cart, auth) without re-fetching all content

**When to consider:** Marketing pages with strict LCP budgets, CDN-only static hosting with CMS on a subdomain.

**When to avoid:** Admin previews, rapid iteration sites, or any project that has not exhausted live-client optimizations.

---

## Optional: Snapshots

Snapshots are **pre-generated static HTML** copies of slug pages (e.g. `/publish/post/my-slug.html`).

| Use case | Benefit |
|----------|---------|
| SEO crawlability | Fully static files for search bots |
| Edge CDN | No runtime CMS dependency for reads |
| Archive / export | Point-in-time content freeze |

Trade-offs:

- Regenerate on publish (webhook, cron, or manual bake)
- Slug URL mapping must stay in sync with live routes
- Interactive features still need `birkly-client.js` or hydrate for forms/cart/auth

kekaba uses snapshots for case studies and insights; that is an **advanced** pattern, not the Birkly default.

---

## Optional: Bake (`birkly-baked` meta)

Baking embeds resolved template output into HTML at build time.

```html
<meta name="birkly-baked" content="true">
```

Used with hydrate or static export tooling. **Not** used in reference projects.

---

## Decision guide

```
Need SEO + max performance?
  ├─ Live client meets ≤5 RTTs? → Stay on live client (default)
  ├─ Need faster LCP only? → Consider hydrate + server render
  └─ Need fully static URLs for crawlers? → Snapshots + live client for interactive pages

Need user auth / cart / forms?
  └─ Always ship birkly-client.js (live or hydrate mode)
```

---

## Related docs

- [Reference projects README](../../reference-projects/README.md)
- [User stories catalog](../reference-projects/user-stories.md)
- [P20 templating excellence plan](../reviews/P20-templating-excellence-plan.md)
- Commerce template functions: `plugins/commerce/docs/help-templating.md`
- Events plugin: `plugins/events/docs/ai-context.md`

---

## Summary

| Technique | Reference projects | Production option |
|-----------|-------------------|-------------------|
| Live `birkly-client.js` | **Default** | Always supported |
| `birkly-hydrate.js` | No | Optional advanced |
| `birkly-baked` meta | No | Optional advanced |
| Snapshot static HTML | No | Optional advanced |
| Custom `fetch()` in site JS | No | Discouraged — use `data-birkly-*` |

Start with the reference project that matches your use case, install collections, and ship live client. Add hydrate or snapshots only when measurement shows it is necessary.
