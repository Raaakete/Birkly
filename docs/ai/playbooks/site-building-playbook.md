# Site building playbook (Birkly)

Use when the user asks to **build a website**, **portfolio**, **landing page**, or any **public site** in this Birkly instance.

**Also auto-loaded:** `web-project-patterns`, `content-model`, `templating`, `fieldtypes` (when relevant).

## Two ways to publish

| Approach | When | Where |
|----------|------|--------|
| **Project site** (default for custom design) | Full HTML/CSS control, marketing sites, portfolios | `project/` |
| **CMS pages** | Simple admin-managed pages, no custom project shell | `pages` + templates |

Default to **project site** unless the user wants admin pages only.

## Before writing files

1. **Infer the content model** from the request — what repeats, what's shared, what's one-off (see `web-project-patterns`).
2. **Discover once** (do not loop):
   - `get_project_info` — URL prefix, paths, templating hints, **`asset_url_example` / `page_url_example`** (never hardcode `/project/` on public hosts)
   - `list_project_files` — existing tree
   - `list_collections` — reuse or extend; avoid duplicate slugs
3. **`read_birkly_docs`** — only if playbooks are not already pre-loaded this turn. Use topic **`site-delivery`** for routing/URL issues.

## Build order

1. **Schema** — collections + fields (`fieldtypes` playbook for `type` values; never empty `fields: []`)
2. **Entries** — seed with `status: "published"`
3. **HTML** — list pages (loops), detail pages (URL context, no list loop), shared chrome, CSS
4. **Script** — `birkly-client.js` on every templated page
5. **Forms** — if contact/newsletter: `entry_method: public_api` + `{entry for}` (see `public-forms`)
6. **Verify** — CMS data visible, detail links work, styles under correct prefix

## Worked example: small bakery site

User: *"Build me a bakery website"*

### 1. Content model (infer)

| Collection | Purpose | Key fields |
|------------|---------|------------|
| `site_elements` | Shared chrome (nav labels, footer) | title, content blocks |
| `menu` | Repeatable menu items | title, description, price, image |
| `blog` | News/updates | title, excerpt, content, slug |
| `contact` | Inbound messages (public_api) | name, email, message |

Optional: `comments` sub-collection on `blog` with `public_api` + `auto_publish: true`.

### 2. Tools sequence

```
update_collection / create_collection  → menu, blog, contact (+ fields)
create_entry                         → 2–3 menu items, 1 blog post, site_elements
write_project_file                   → index.html, menu.html, blog.html, post.html, contact.html, css/style.css
save_collection_groups               → optional sidebar organization
```

### 3. Minimal index.html pattern

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Bakery</title>
  <link rel="stylesheet" href="/project/css/style.css">
</head>
<body>
  <header><!-- nav --></header>
  <main>
    <h1>Fresh from the oven</h1>
    {for each item in 'menu' with status = published ordered by newest limit 6}
      <article>
        <h2>{title}</h2>
        <p>{'description'}</p>
        <p>{'price'}</p>
      </article>
    {endfor}
  </main>
  <script src="/birkly-client.js"></script>
</body>
</html>
```

Use `get_project_info` → `site_url_prefix` for hrefs (may be `/project/`).

### 4. Contact page snippet

```html
{entry for 'contact'}
  <input name="{name}" required>
  <input type="email" name="{email}" required>
  <textarea name="{message}" required></textarea>
  <button type="submit">Send</button>
{endentry}
```

Requires `contact` collection with matching field slugs and `entry_method: public_api`.

## Anti-patterns

- Templates without `birkly-client.js`
- Detail page that loops the whole collection (use URL context + `of 'blog'.current` for subs)
- Unquoted user fields / phantom `{ date "…" }` / `ordered="date:desc"` — see `templating`
- Hard-coded menu items when a `menu` collection exists
- Empty schema then templates referencing custom fields
- Replacing `{entry for}` with custom fetch JS — forms scaffold is valid (`public-forms`)
- Re-reading project files every turn after first successful discovery
- Fixed "portfolio" recipe regardless of user domain — adapt the model

## Tools

| Intent | Tools |
|--------|--------|
| Project HTML/CSS | `write_project_file`, `read_project_file`, `list_project_files`, `get_project_info` |
| CMS data | `create_collection`, `update_collection`, `create_entry`, … |
| Admin sidebar | `list_collection_groups`, `save_collection_groups` |
| Admin pages | `create_page`, `list_pages` |
| Depth | Pre-loaded playbooks + `read_birkly_docs` |

## Session memory

Note: collections created, list vs detail filenames, preview URL. Content edits happen in admin — not by editing HTML cards.

## Related

- `web-project-patterns` — principles & wiring
- `templating` — syntax, ordering, dates, phantoms
- `fieldtypes` — schema field types
- `public-forms` — contact/newsletter (`{entry for}`)
- `ai-quality-guide` — checklists & failure modes

## Admin cross-reference

| Topic | Help topic id | AI playbook |
|-------|---------------|-------------|
| Entries & collections | `entries` | `content-model` |
| Templating overview | `templating-overview` | `templating` |
| Detail pages | `detail-pages-and-url-context` | `web-project-patterns` |
| Public forms | `public-forms-templating` | `public-forms` |
| Reference projects | `reference-projects` | `web-project-patterns` |

Playbook file: `docs/ai/playbooks/site-building-playbook.md`
