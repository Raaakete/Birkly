# Templating Overview

Birkly fills your HTML with CMS content using a plain-English template language: placeholders like `{title}` and blocks like `{for each entry in 'blog'}…{endfor}`. The browser script `birkly-client.js` (or optional SSR) replaces those tags with live data.

## Summary

**In short:** Write normal HTML, leave slots where content should come from Birkly, and load `birkly-client.js`. Collections are quoted (`'blog'`). System fields are bare (`{title}`, `{slug}`); user-defined fields use quotes (`{'excerpt'}`, `{'price'}`). List pages loop a collection; detail pages read one entry from the URL — they must **not** loop the whole collection.

**Canonical engine reference:** Birkly repo `docs/TEMPLATE_LANGUAGE_SPEC.md` (Template Language Spec v1.0). Human SSOT pointer: [Canonical template syntax](canonical-syntax.md).

![Templating mental model: HTML + client + CMS = live site](../media/03-templating/templating-overview/screenshots/templating-mental-model.svg)

## Beginner

### Mental model

| Piece | Role |
|-------|------|
| **Your HTML** | Layout and template tags in the Light DOM |
| **Collections & entries** | Content you edit in admin |
| **`birkly-client.js`** | Fetches published data and replaces `{…}` tags |
| **Public API / page bundle** | Where the client loads entries |

You do **not** paste every blog post into HTML. You write a loop once; new published entries appear automatically.

### Three page patterns

| Pattern | When | Approach |
|---------|------|----------|
| **List** | Many items of one type | `{for each entry in 'blog' …}{endfor}` |
| **Detail** | One item from a link | URL `?collection=blog&slug={slug}` + `{title}` / `{'content'}` — **no collection loop** |
| **Shared chrome** | Header/footer on every page | `<birkly-region>` or `{from 'site_elements' get entry '…'}` |

See [Detail pages & URL context](detail-pages-and-url-context.md) and [Regions / web components](web-components.md).

### Minimal example (blog list)

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Blog</title>
</head>
<body>
  <h1>Blog</h1>
  {for each entry in 'blog' with status = published ordered by newest}
    <article>
      <h2><a href="post.html?collection=blog&slug={slug}">{title}</a></h2>
      {if entry has 'excerpt'}
        <p>{'excerpt'}</p>
      {endif}
      <time>{created_at format "F j, Y"}</time>
    </article>
  {endfor}
  <script src="/birkly-client.js"></script>
</body>
</html>
```

Every templated page needs the client script. On an **external** site (different domain than the CMS), add `data-cms-url="https://your-cms.example"`. See [Client setup](client-setup.md). When User, Commerce, or Events plugins are active, storefront behavior loads automatically — [Project sites and plugins](project-sites-and-plugins.md).

### Learning path

1. [Client setup](client-setup.md) — script tag, same-host vs external, SSR vs client  
2. [Project sites and plugins](project-sites-and-plugins.md) — plugin storefront auto-load, member gates, `Birkly.fetch`  
3. [Variables and output](variables-and-output.md) — `{title}` vs `{'field'}`  
4. [Loops and lists](loops-and-lists.md) — `with`, `ordered by`, `limit`  
5. [Detail pages & URL context](detail-pages-and-url-context.md) — `.current`, anti-patterns  
6. [Conditionals](conditionals.md) · [Dates](dates-and-formatting.md) · [From…get](from-get-and-existence.md)  
7. [Public forms templating](public-forms-templating.md) · [Worked site walkthrough](worked-site-walkthrough.md)  
8. [Cheat sheet](cheat-sheet.md) — quick reference + phantoms  

### Where does replacement happen?

- **Browser (default):** `birkly-client.js` discovers regions, loads a page bundle, replaces tags. Used by all [reference projects](reference-projects.md).  
- **Server (optional):** PHP / Astro / Next can pre-render the same syntax. See [SSR and build-time](../04-integration/ssr-and-build-time.md) and [Production optimization](production-optimization.md).

## Advanced Users

### For developers & AI

**Architecture**

1. Template HTML contains Birkly syntax in the **Light DOM**.  
2. Client discovers regions (syntax-bearing nodes, `data-birkly-region`, or `<birkly-region>`).  
3. Page bundle / public API loads published entries (and always prefetches `site_elements`).  
4. String engine (`Birkly.render` / `processTemplates`) replaces tags.  
5. Events: `birkly:region-processed`, `birkly:templates-processed`.

**Light DOM vs Shadow DOM**

| Surface | Template language | How to render |
|---------|-------------------|---------------|
| Light DOM | Yes — `{for each}`, `{from}`, regions | Write tags in markup |
| Shadow DOM | **No** auto-scan | Fetch API + `Birkly.render(html, data)` |

**Core rules (must match the engine)**

- Collections always in **single quotes**: `'blog'`.  
- User fields quoted: `{'content'}`. System: `{title}`, `{slug}`, `{created_at}`.  
- Ordering: `ordered by newest` / `oldest` / `last-updated` / `order` — **not** `sort="date:desc"`.  
- Dates: `{created_at format "Y-m-d"}` — **not** `{ date "…" }`.  
- Single entry: `{from 'blog' get entry 'slug'}…{endget}` — **not** `{entry 'blog'.'slug'}…{endentry}` (phantom; forms use `{entry for}` which is different).  
- No Jinja/Liquid: not `{{ }}`, not `{% for %}`.

**Interlinks for agents**

| Topic | Doc |
|-------|-----|
| Field types / schema | [Field types](../02-content/field-types.md) |
| Sub-collections model | [Sub-Collections](../02-content/sub-collections.md) |
| Forms | [Public forms templating](public-forms-templating.md), [Public forms](../04-integration/public-forms.md) |
| WebMCP / AI | [Website tools](../04-integration/webmcp-tools.md), [Agent Guide](../02-content/agent-guide.md) |
| Connect AI apps | **Settings → AI** — [MCP connections](../01-admin/mcp-connections.md) |
| Spec (CMS repo) | `docs/TEMPLATE_LANGUAGE_SPEC.md` |

## Related

- [Canonical syntax](canonical-syntax.md)
- [Client setup](client-setup.md)
- [Project sites and plugins](project-sites-and-plugins.md)
- [Cheat sheet](cheat-sheet.md)
- [Choose your stack](../04-integration/choose-your-stack.md)
- [Website integration](../04-integration/website-integration.md)
- [Client library](../04-integration/client-library.md)
- [Content model overview](../02-content/content-model-overview.md)
