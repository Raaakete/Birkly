# Web components — templating patterns

Patterns for Birkly template tags inside `<birkly-region>`, `[data-birkly-region]`, and enhance-only custom elements.

## Summary

**In short:** Put template syntax in the **Light DOM**. Use `<birkly-region collection="…" entry="…">` for shared header/footer, or `list` + `ordered="newest"` / `ordered="order"` for lists. After render, **enhance** (classes, listeners) — never wipe `innerHTML`. Closed Shadow DOM is fetch + `Birkly.render` only.

![`<birkly-region>` header and list patterns](../media/03-templating/web-components/screenshots/birkly-region-header.svg)

## Beginner

### Shared header / footer

```html
<birkly-region collection="site_elements" entry="header"></birkly-region>

<main>…</main>

<birkly-region collection="site_elements" entry="footer"></birkly-region>

<script src="/birkly-client.js"></script>
```

Or with custom markup:

```html
<birkly-region collection="site_elements" entry="header">
  <header class="site-header">{'content'}</header>
</birkly-region>
```

### List region

```html
<birkly-region collection="blog" list ordered="newest">
  <article>
    <h2><a href="post.html?collection=blog&slug={slug}">{title}</a></h2>
    <p>{'excerpt'}</p>
  </article>
</birkly-region>
```

**Ordering words** (same as loops): `newest`, `oldest`, `last-updated`, `order`.  
**Do not** write `ordered="date:desc"`.

### Explicit root

```html
<main data-birkly-region>
  {from 'blog' get entry 'welcome'}
    <h1>{title}</h1>
    <div>{'content'}</div>
  {endget}
</main>
```

### Enhance only (good)

```html
<script src="/birkly-client.js"></script>
<script src="/birkly-components.js"></script>
<script>
  document.addEventListener('birkly:templates-processed', function () {
    BirklyComponents.setActiveNav(document);
  });
</script>
```

### Wipe after render (bad)

```js
class MyHeader extends HTMLElement {
  connectedCallback() {
    // BAD — deletes CMS-rendered content
    this.innerHTML = '<header></header>';
  }
}
```

## Advanced Users

### For developers & AI

**Discovery**

1. Markup containing `{for` / `{from` / `{if` / placeholders  
2. `data-birkly-region`  
3. `<birkly-region>`

Outermost wins, unless an **explicit** child demotes the ancestor.

**Defaults (`site_elements`)**

| Entry | Default wrapper |
|-------|-----------------|
| `header` | `<header class="birkly-site-header">{'content'}</header>` |
| `footer` | `<footer class="birkly-site-footer">…` |
| `navigation` | `<nav class="birkly-site-nav">…` |

**API**

```js
await Birkly.processTemplates();
await Birkly.processTemplates({ root: el, silent: true, force: false });
```

Events: `birkly:region-processed` (bubbles), `birkly:templates-processed` (full-page only).

**Anti-patterns**

1. `innerHTML = …` on a host that holds templates  
2. Template tags in React/Vue JSX — use [SPA guide](../04-integration/spa-react-vue.md)  
3. Templates inside closed Shadow DOM  
4. Phantom `{entry 'x'.'y'}…{endentry}`  
5. `ordered="date:desc"`

Integration attributes: [Web components (integration)](../04-integration/web-components.md).

## Related

- [Canonical syntax](canonical-syntax.md)
- [Client setup](client-setup.md)
- [Loops and lists](loops-and-lists.md)
- [Site elements pattern](../02-content/site-elements-pattern.md)
- [Client library](../04-integration/client-library.md)
