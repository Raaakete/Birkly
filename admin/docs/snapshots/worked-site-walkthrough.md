# Worked site walkthrough

End-to-end tutorial: content model → list page → detail page → contact form → shared chrome, using only canonical template syntax.

## Summary

**In short:** Infer what repeats (collections), what is shared (site elements), and what visitors submit (public API collection). Define fields, publish sample entries, wire HTML with `birkly-client.js`, then verify list → detail → form. For a ready-made kit, install a [reference project](reference-projects.md) first.

> **Video (follow-along):** coming — multi-step recording (build walkthrough; pairs with reference-projects)

## Beginner

### Scenario

Small bakery site: menu items, a few news posts, contact form, shared header.

### Step 1 — Content model

| Collection | Purpose | Key fields |
|------------|---------|------------|
| `site_elements` | Header / footer chrome | title, content |
| `menu` | Repeatable items | title, description, price, image, order |
| `blog` | News | title, excerpt, content |
| `contact` | Inbound messages | name, email, message — **Public API** |

See [Content model overview](../02-content/content-model-overview.md) and [Field types](../02-content/field-types.md).

### Step 2 — Publish sample entries

Create 2–3 menu items and 1 blog post with **status published**. Add `site_elements` entries `header` and `footer`.

### Step 3 — Shared chrome

```html
<birkly-region collection="site_elements" entry="header"></birkly-region>
```

### Step 4 — Menu list (`menu.html`)

```html
<h1>Menu</h1>
{for each entry in 'menu' with status = published ordered by order}
  <article>
    <h2>{title}</h2>
    {if entry has 'description'}<p>{'description'}</p>{endif}
    {if entry has 'price'}<p>{'price'}</p>{endif}
  </article>
{endfor}
<script src="/birkly-client.js"></script>
```

### Step 5 — Blog list + detail

List (`blog.html`):

```html
{for each entry in 'blog' with status = published ordered by newest}
  <a href="post.html?collection=blog&slug={slug}">{title}</a>
{endfor}
```

Detail (`post.html`) — **no collection loop**:

```html
<meta name="birkly-collection" content="blog">
<h1>{title}</h1>
<div>{'content'}</div>
<script src="/birkly-client.js"></script>
```

### Step 6 — Contact form

```html
{entry for 'contact'}
  <input name="{name}" required>
  <input type="email" name="{email}" required>
  <textarea name="{message}" required></textarea>
  <button type="submit">Send</button>
{endentry}
<script src="/birkly-client.js"></script>
```

Details: [Public forms templating](public-forms-templating.md).

### Step 7 — Verify

1. Menu shows CMS items (not hard-coded cards).  
2. Blog link opens one post (not the full list again).  
3. No raw `{…}` on the page.  
4. Form submit creates an entry in admin.  
5. Styles load under the correct site URL prefix.

## Advanced Users

### For developers & AI

**Tooling order (MCP / Agent Guide workflows)**

1. `get_project_info` → `site_url_prefix`  
2. `list_collections` / `create_collection` with full `fields`  
3. `create_entry` with `status: published`  
4. `write_project_file` for HTML/CSS (paths without `project/` prefix in tools)  
5. Never leave `fields: []` then reference custom fields  

**Anti-patterns checklist** — same as [Cheat sheet](cheat-sheet.md) phantoms + detail-page loop ban.

**Next depth**

- [Sub-collections](sub-collections-in-templates.md) for comments  
- [Multilingual](multilingual-templating.md)  
- [Storefront patterns](storefront-patterns.md) if Commerce/Events  
- Connect AI under **Settings → AI** for agent builds — [MCP connections](../01-admin/mcp-connections.md)

## Related

- [Templating overview](templating-overview.md)
- [Client setup](client-setup.md)
- [Detail pages & URL context](detail-pages-and-url-context.md)
- [Reference projects](reference-projects.md)
- [Website integration](../04-integration/website-integration.md)
