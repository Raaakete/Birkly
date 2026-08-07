# Choose your stack

Pick how your site talks to Birkly. Four supported paths — start here, then follow one guide.

## Summary

Birkly content can power a site in four ways: **HTML + templates** (default), **web components** (`<birkly-region>`), a **SPA** (React/Vue fetching JSON), or **SSR / build-time** (PHP, Astro, Next). Use this page to choose; each path links to a focused guide.

## Beginner

Ask one question: **who renders the page?**

| If you… | Choose | Guide |
|---------|--------|-------|
| Write normal HTML and want Birkly to fill in titles, lists, and posts | **HTML + templates** | [Website integration](website-integration.md) · [Templating overview](../03-templating/templating-overview.md) |
| Want one-line regions (header, footer, blog list) without repeating `{from}` / `{for}` | **Web components** | [Web components (integration)](web-components.md) |
| Build with React, Vue, or similar and render with your own components | **SPA** | [SPA (React / Vue)](spa-react-vue.md) |
| Need HTML before JavaScript runs (SEO, PHP host, static build) | **SSR / build-time** | [SSR and build-time](ssr-and-build-time.md) |

**Default for most marketing sites and blogs:** HTML + templates with `birkly-client.js`. Add `<birkly-region>` when you want reusable one-line regions.

**Do not mix blindly:** SPA frameworks should **not** put `{for each}` inside JSX. Fetch JSON (Public API or `Birkly.fetch` / `getEntryBySlug`) and render with React/Vue — or call `Birkly.render` into a DOM ref. See the SPA guide.

## Advanced Users

| Path | Runtime | Content load | Template language |
|------|---------|--------------|-------------------|
| HTML + templates | Browser (`birkly-client.js`) | `page_bundle.php` / public API | Light DOM `{…}` tags; auto `processTemplates()` |
| Web components | Browser | Same client | `<birkly-region>` / `data-birkly-region` + optional child templates |
| SPA | Your framework | Public API / `Birkly.fetch` | **No** Birkly tags in JSX; JSON → components (or `Birkly.render` into a ref) |
| SSR / build-time | PHP / Node / CI | Public API or `page_bundle.php` | Your engine, or `Birkly.render` if you run the client string engine in Node |

**Shared rules**

- Single entry: `{from 'collection' get entry 'slug'}…{endget}` (not `{entry}…{endentry}`).
- Light DOM holds templates the client discovers. Closed Shadow DOM is not scanned — fetch + `Birkly.render(html, data)` if you must render inside a shadow root.
- Enhance-only helpers (`birkly-components.js`) must **never** wipe `innerHTML` of CMS-rendered regions.

**Related API surface**

- [Client library](client-library.md) — `processTemplates`, events, `getEntryBySlug`
- [Public API](public-api.md) — JSON for SPA/SSR
- [Canonical syntax](../03-templating/canonical-syntax.md)

## Related

- [Website integration](website-integration.md)
- [Web components](web-components.md)
- [SPA (React / Vue)](spa-react-vue.md)
- [SSR and build-time](ssr-and-build-time.md)
- [Client library](client-library.md)
- [Public API](public-api.md)
