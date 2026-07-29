# Web components

Use `<birkly-region>` and `data-birkly-region` so Birkly discovers and fills page regions with one line — without hand-writing `{from}` / `{for}` on every page.

## Summary

**`<birkly-region>`** is a custom element in `birkly-client.js`. Set `collection` + `entry` (one entry) or `collection` + `list` (loop). Optional child HTML is your Light DOM template. **`data-birkly-region`** marks any element as an explicit region root. After render, enhance with `birkly-components.js` — never replace CMS `innerHTML`.

## Beginner

### One-line entry region

```html
<script src="https://your-cms.com/birkly-client.js"
        data-cms-url="https://your-cms.com"></script>

<birkly-region collection="site_elements" entry="header"></birkly-region>
<main>…your page…</main>
<birkly-region collection="site_elements" entry="footer"></birkly-region>
```

Empty entry regions get a small default template (title + content, or site_elements header/footer/nav markup).

### List region

```html
<birkly-region collection="blog" list ordered="date:desc"></birkly-region>
```

Or supply the card markup as children (Light DOM):

```html
<birkly-region collection="blog" list>
  <article>
    <h2><a href="/blog/{slug}">{title}</a></h2>
    <p>{excerpt}</p>
  </article>
</birkly-region>
```

The client wraps children in `{for each entry in 'blog'}…{endfor}` when needed.

### Explicit region root

```html
<section data-birkly-region>
  {from 'blog' get entry 'welcome'}
    <h1>{title}</h1>
    <article>{'content'}</article>
  {endget}
</section>
```

`data-birkly-region` is **supported**: the client treats the attributed element as the region root (and can prepare hosts that also have `collection` / `list`).

### After content appears — enhance, don’t wipe

```html
<script src="https://your-cms.com/birkly-components.js"></script>
<script>
  document.addEventListener('birkly:templates-processed', function () {
    BirklyComponents.setActiveNav(document);
  });
</script>
```

**Never** do this in a custom element’s `connectedCallback`:

```js
// BAD — destroys CMS-rendered (or templated) content
this.innerHTML = '<nav>…</nav>';
```

## Advanced Users

### Attributes

| Attribute | Role |
|-----------|------|
| `collection` | Collection handle (`blog`, `site_elements`, …) |
| `entry` | Entry slug (entry mode) |
| `list` | Presence flag — list/loop mode |
| `ordered` | Optional sort clause for list mode (e.g. `date:desc`) |
| `data-birkly-region` | Explicit region root (also set automatically on `<birkly-region>`) |
| `data-birkly-processed="1"` | Set after a successful render; skip unless `force: true` |

### Lifecycle

1. Page load → `Birkly.processTemplates()` discovers outermost regions (syntax **or** explicit `data-birkly-region` / `<birkly-region>`).
2. Hosts with `collection` / `list` are prepared via `buildRegionTemplate` (defaults or child wrap).
3. Bundle fetch → `Birkly.render.render` into each region’s Light DOM.
4. Each region dispatches **`birkly:region-processed`** (bubbles). Full-document runs also dispatch **`birkly:templates-processed`** on `document`.
5. Late-mounted `[data-birkly-region]` nodes are observed and processed; `<birkly-region>` runs on `connectedCallback`.

Manual re-run:

```js
await Birkly.processTemplates({ root: el, silent: true, force: true });
// or
await Birkly.processRegion(el, { force: true });
```

### Light DOM vs Shadow DOM

| | Light DOM | Shadow DOM |
|--|-----------|------------|
| Templates `{…}` | **Yes** — discovered and processed | **No** — client does not walk closed shadow trees |
| What to do | Put markup/templates as children | Fetch JSON (`Birkly.fetch` / Public API), then `Birkly.render(html, data)` into the shadow root yourself |

### Enhance-only (`birkly-components.js`)

- `BirklyComponents.setActiveNav(root?, currentPage?, options?)` — toggles `.active` / `aria-current` on links.
- `<birkly-enhance-nav>` — same on connect; **never** sets `innerHTML`.

Templating-focused patterns: [Web components (templating)](../03-templating/web-components.md). Stack choice: [Choose your stack](choose-your-stack.md).

## Related

- [Choose your stack](choose-your-stack.md)
- [Client library](client-library.md)
- [Canonical syntax](../03-templating/canonical-syntax.md)
- [Web components (templating)](../03-templating/web-components.md)
- [Website integration](website-integration.md)
