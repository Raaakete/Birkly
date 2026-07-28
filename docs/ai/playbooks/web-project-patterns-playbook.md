# Web project patterns playbook (Birkly)

Use this **every time** you build or fix a site in `project/`. This is the practical layer on top of `site-building`, `content-model`, and `templating`.

**Goal:** CMS-backed sites that work on first load — not static HTML with collections sitting unused beside it.

---

## Non-negotiable checklist (every templated HTML page)

Before writing any `write_project_file` for HTML:

1. **Script tag** — required or `{…}` shows as raw text in the browser:
   ```html
   <script src="/birkly-client.js"></script>
   ```
   Place before `</body>`. Same-host preview needs no `data-cms-url`; external hosting needs `data-cms-url="https://cms-domain"`.

2. **Collection slugs in single quotes** — `{for each entry in 'blog'}` not `blog` unquoted.

3. **Published entries** — `status: "published"` on every entry that must appear on the public site.

4. **Schema before entries** — define collection `fields` with `update_collection` **before** `create_entry`. Empty-schema collections produce broken templates.

5. **Exact field names** — use names from schema in entries and templates (`{'excerpt'}`, not `summary` unless the field is named `summary`).

---

## Three page types (do not mix them up)

| Page type | Purpose | Template pattern | Example file |
|-----------|---------|------------------|--------------|
| **List** | Show many entries | `{for each entry in 'blog' …}{endfor}` | `blog.html`, homepage teaser section |
| **Detail** | Show **one** entry from URL | Top-level `{ title }`, `{ content }` — **no loop over the collection** | `post.html`, `article.html` |
| **Static** | Fixed copy (no CMS loop) | Plain HTML, or `{from 'site_elements' get entry 'hero'}…{endget}` | `about.html`, legal pages |

### The #1 detail-page bug

**Wrong:** Using `{for each entry in 'blog'}` on `article.html` — every visit shows the full list again.

**Right:** Detail page uses **URL context**. User arrives via:

```html
<a href="article.html?collection=blog&slug={slug}">Read story</a>
```

Detail page head:

```html
<meta name="birkly-page" content="post">
<meta name="birkly-collection" content="blog">
<title>{ title }</title>
```

Detail page body (no collection loop):

```html
<article>
  <h1>{ title }</h1>
  <time>{ created_at }</time>
  <div class="body">{ content }</div>
</article>
<script src="/birkly-client.js"></script>
```

The client reads `?slug=` and `?collection=`, loads that one entry, and exposes `{ title }`, `{ content }`, `{ slug }` at the top level.

Reference templates in this repo: `project/blog.html` (list) + `project/post.html` (detail).

---

## Smart collection design (choose once, wire everywhere)

Design the **content model first** in one plan message, then create collections, then HTML.

### Recipe: Blog / stories / news

**Collection:** `blog` (slug matches template loops)

| Field | Type | Why |
|-------|------|-----|
| `title` | text | Required; system-friendly |
| `content` | editor or textarea | Full article body |
| `excerpt` | textarea | Teaser on list cards — do not truncate in templates |
| `category` | text or select | Optional grouping |
| `read_time` | text | e.g. "5 min read" |
| `hero_image` | image | Optional card/header image |
| `order` | number | Only if manual sort; otherwise use `ordered by newest` |

**Pages:** `index.html` (featured + recent), `blog.html` (archive), `article.html` or `post.html` (detail).

**Do not** create empty `blog` and `site-elements` collections with `"fields": []` — define fields immediately.

### Recipe: Site-wide chrome (header, footer, hero copy)

**Collection:** `site_elements` (slug with underscore is fine)

| Entry slug | Purpose | Field approach |
|------------|---------|----------------|
| `header` | Nav / logo text | `editor` or `textarea` for HTML snippet, or multiple `text` fields |
| `footer` | Copyright, links | same |
| `hero` | Homepage headline block | `editor` with blocks, or `text` + `textarea` |

**One-line include on every page:**

```html
<birkly-region collection="site_elements" entry="header"></birkly-region>
```

Prefer this over copy-pasting `{from 'site_elements' get entry 'header'}…{endget}` on every file.

### Recipe: Portfolio / bakery products / menu items

**Collection:** `projects` or `products` or `menu_items`

| Field | Type | Why |
|-------|------|-----|
| `title` | text | Item name |
| `description` | textarea | Short blurb for cards |
| `content` | editor | Long description (detail page) |
| `image` | image | Card photo |
| `link` | url | External link if needed |
| `order` | number | Manual sort on grid (`ordered by order`) |
| `featured` | checkbox | Homepage filter: `with 'featured' = true` if supported, or dedicated homepage loop |

**Pages:** `index.html` (grid), `item.html` (detail with `?collection=projects&slug={slug}`).

### Recipe: Team, testimonials, FAQ

| Use case | Collection | Key fields |
|----------|------------|------------|
| Team | `team` | name (text), role (text), photo (image), bio (textarea) |
| Testimonials | `testimonials` | quote (textarea), author (text), company (text), order (number) |
| FAQ | `faq` | question (text), answer (textarea or editor), order (number) |

Single-page FAQ: one `{for each entry in 'faq' ordered by order}` on `index.html` — no detail page needed.

### When to use sub-collections

Use **sub-collections** (see `sub-collections` playbook) when rows belong to **one parent entry**:

- Comments on a blog post → `comments` sub-collection of `blog`
- Reviews on a product → `reviews` sub of `products`
- Order line items → sub of `orders`

On a **detail page**, loop sub-entries scoped to current post:

```html
{for each entry in 'comments' of 'blog'.current ordered by newest}
  <p>{'author'}: {'content'}</p>
{endfor}
```

Do **not** create a separate top-level `comments` collection if comments always belong to one post.

---

## Field type picker (avoid weak schemas)

| Need | Use | Avoid |
|------|-----|-------|
| Short label, name, category | `text` | `editor` |
| Paragraph, excerpt, bio | `textarea` | `text` (too small mentally) |
| Article body with formatting | `editor` | plain `textarea` unless user wants markdown only |
| Hero / card photo | `image` | URL in `text` unless external-only |
| Photo gallery | `gallery` | multiple `image` fields |
| Pick one of several | `select` with options | free-text `text` |
| Show/hide on homepage | `checkbox` | string "true" |
| Sortable lists | `number` field + `ordered by order` | random `text` |
| Link out | `url` | unvalidated `text` |

Always include `title` (required). Slug is auto-generated from title unless set explicitly.

---

## List page template (copy-ready)

```html
{for each entry in 'blog' with status = published ordered by newest}
<article class="card">
  <h2><a href="article.html?collection=blog&slug={slug}">{ title }</a></h2>
  {if entry has 'excerpt'}
  <p>{'excerpt'}</p>
  {endif}
  <a href="article.html?collection=blog&slug={slug}">Read story →</a>
</article>
{endfor}
```

Use **`{slug}` inside hrefs** — it resolves to each entry's slug in the loop.

Filters that work: `with status = published`, `ordered by newest`, `ordered by order`, `ordered by oldest`.

---

## Detail page template (copy-ready)

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="birkly-page" content="post">
  <meta name="birkly-collection" content="blog">
  <title>{ title }</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <birkly-region collection="site_elements" entry="header"></birkly-region>
  <main>
    <article>
      <h1>{ title }</h1>
      {if entry has 'category'}<span class="category">{'category'}</span>{endif}
      {if entry has 'read_time'}<span class="read-time">{'read_time'}</span>{endif}
      <div class="content">{ content }</div>
    </article>
    <p><a href="blog.html">← Back to all stories</a></p>
  </main>
  <script src="/birkly-client.js"></script>
</body>
</html>
```

**No `{for each entry in 'blog'}` on this page.**

Alternative for a **fixed** slug (no URL param): `{from 'blog' get entry 'my-slug'}…{endget}` — use only when the page is always one entry.

---

## Build order (follow strictly)

1. `list_collections` — see what exists; reuse or extend, don't duplicate slugs.
2. **Design schema** — write out fields table (this playbook's recipes).
3. `create_collection` or `update_collection` with full `fields` array.
4. `create_entry` × N with `status: "published"` and realistic seed content.
5. **List pages** — loops + correct detail links.
6. **Detail page** — meta tags + current-entry fields, no list loop.
7. `site_elements` entries for shared header/footer if multi-page.
8. Verify: open list → click one item → single article renders.

---

## Common AI mistakes (user-visible bugs)

| Symptom | Cause | Fix |
|---------|-------|-----|
| Raw `{for each entry…}` on page | Missing `/birkly-client.js` | Add script before `</body>` on **every** templated page |
| Collections exist but site is static | Never wired templates | Replace hard-coded cards with loops |
| Click "Read" → list again | Detail page loops entire collection | Use URL detail pattern; `{ title }` not `{for each}` |
| Empty cards / missing fields | Schema has `"fields": []` | `update_collection` with proper fields |
| Wrong field shows empty | Field name mismatch | Match schema exactly: `{'excerpt'}` not `{'summary'}` |
| `{title truncate 80}` etc. | Invalid syntax (Jinja-ism) | Store excerpt in CMS; only `format` filter works on dates |
| `{{title}}` or `{% for %}` | Wrong engine | Single braces Birkly syntax only |
| Duplicate nav on every page | Copy-paste header HTML | `site_elements` + `<birkly-region>` |
| Sub-collection on wrong page | Top-level loop instead of `.current` | `'comments' of 'blog'.current` on detail page only |

---

## File layout convention

```
project/
  index.html          # homepage (can mix static + loops)
  blog.html           # list archive
  article.html        # detail (or post.html)
  about.html          # static or site_elements
  css/style.css
  js/script.js        # optional; dark mode, newsletter UI only — not for CMS rendering
```

Paths in tools: `index.html`, `css/style.css` — never prefix `project/`.

---

## After building — tell the user

- Public preview URL from `get_project_info`
- Which collections power which pages
- That list → detail links use `?collection=…&slug=…`
- That editing content happens in admin → Collections, not by editing HTML cards
