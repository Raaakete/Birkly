# Web project patterns playbook (Birkly)

Principles for wiring **any** custom site in `project/` to the CMS — blog, portfolio, bakery, agency, landing page, etc.

Layer on top of: `site-building`, `content-model`, `templating`.

**Goal:** Templates and collections work together on first load. The user edits content in admin; HTML provides structure and loops.

---

## How to think about any site request

Before creating collections or files, infer from the user's prompt:

1. **What repeats?** (posts, projects, products, team members, menu items) → one **collection per repeatable type**
2. **What is shared on every page?** (header, footer, hero, nav labels) → **singleton entries** or a small “site chrome” collection + `<birkly-region>`
3. **What is one-off page copy?** (about text, legal) → one entry in a page collection, `{from … get entry …}`, or mostly static HTML
4. **What do visitors submit?** (contact, newsletter) → collection with `entry_method: public_api` + `{entry for}` — see `public-forms`
5. **What belongs to one parent item?** (comments, variants, line items) → **sub-collection**, not a separate top-level collection

Adapt names and fields to the business — do not copy fixed slugs from examples unless they fit.

---

## Non-negotiable technical rules

These apply to **every** templated page, regardless of site type:

| Rule | Why |
|------|-----|
| `<script src="/birkly-client.js"></script>` on each templated HTML page | Without it, `{…}` renders as raw text |
| Use `get_project_info` → `site_url_prefix` for links and assets | e.g. `/project/css/style.css`, not `/css/style.css` |
| Collection slugs in **single quotes** in loops | `{for each entry in 'your-slug'}` |
| Define **full schema** before `create_entry` | Empty `fields: []` breaks templates |
| `status: "published"` for live content | Draft entries are invisible on the public site |
| Field names in templates **match schema exactly** | `{'excerpt'}` only if the field is named `excerpt` |
| `create_entry` auto-generates **slug from title** | Use `{slug}` in list → detail links |

Tool paths for writes: `index.html`, `css/style.css` — never prefix `project/` in `write_project_file`.

---

## Three page patterns (do not mix)

| Pattern | When | Template approach |
|---------|------|-------------------|
| **List** | Many items of the same type | `{for each entry in 'collection-slug' …}{endfor}` on a list or homepage section |
| **Detail** | One item chosen from a link | URL `?collection=<slug>&slug={slug}` + top-level `{ title }`, `{ field }` — **no loop over the whole collection** |
| **Static / singleton** | Fixed page or single CMS block | Plain HTML, `{from 'coll' get entry 'entry-slug'}…{endget}`, or `<birkly-region>` |

### Detail pages — the most common bug

**Wrong:** Detail HTML loops `{for each entry in '…'}` → user always sees a list.

**Right:** List page links to detail with collection + slug; detail page reads **current entry** from the URL:

```html
<!-- On list page, inside a loop -->
<a href="/project/detail.html?collection=YOUR-COLL&slug={slug}">{ title }</a>

<!-- On detail page head -->
<meta name="birkly-collection" content="YOUR-COLL">
<title>{ title }</title>

<!-- On detail page body — no collection loop -->
<h1>{ title }</h1>
<div>{ content }</div>
<script src="/birkly-client.js"></script>
```

Prefer **`?slug={slug}`** in links (slug is auto-set from title). Avoid `?id=` unless you have a specific reason.

Reference implementation in repo: `project/blog.html` + `project/post.html` (example only — names are yours to choose).

---

## Content model design (flexible)

### One collection = one kind of thing

Each collection should represent a **single content type** with fields that belong together. If two sections need different fields, use two collections.

### Field types — match shape of content

| Content shape | Field type |
|---------------|------------|
| Short label, name, tag | `text` |
| Paragraph, teaser, bio | `textarea` |
| Long formatted body | `editor` (or `textarea` if plain text is enough) |
| Single photo | `image` |
| Multiple photos | `gallery` |
| Ordered list (nav, FAQ, features) | `number` (`order`) + `ordered by order` in template |
| On/off (featured, visible) | `checkbox` |
| External link | `url` |
| Fixed choices | `select` |

Always include `title`. Slug is generated from title unless you set it.

### Shared chrome (header, footer, hero)

**Problem:** Copy-pasting nav/footer on every HTML file.

**Approach:** One collection (any slug you choose) with **one entry per block** (e.g. entries named for their role). Include on each page:

```html
<birkly-region collection="YOUR-CHROME-COLL" entry="header"></birkly-region>
```

Or `{from 'YOUR-CHROME-COLL' get entry 'header'}…{endget}` when you need a custom wrapper.

### Sub-collections vs top-level collections

| Situation | Use |
|-----------|-----|
| Items always tied to one parent (comments on a post, toppings on a dish) | Sub-collection scoped with `.current` on detail page |
| Items that stand alone (all posts, all products) | Top-level collection + list/detail pages |
| Visitor-submitted rows (contact messages) | Top-level collection with `public_api` |

On a detail page, sub-collection loop example:

```html
{for each entry in 'SUB-SLUG' of 'PARENT-COLL'.current ordered by newest}
  …
{endfor}
```

---

## Wiring CMS to HTML (general workflow)

1. **Discover** — `get_project_info`, `list_project_files`, `list_collections` (once)
2. **Model** — decide collections, fields, which pages are list vs detail vs static
3. **Schema** — `create_collection` / `update_collection` with complete `fields`
4. **Content** — `create_entry` with `published` and realistic seed data
5. **Templates** — list loops, detail page(s), shared regions, CSS under correct URL prefix
6. **Verify** — list shows CMS data; click through to one item; no raw `{…}`; styles load

Do not stop after one collection if the site has distinct sections (marketing copy, repeatable items, forms, shared nav) — split the model to match.

---

## Common mistakes (symptom → fix)

| Symptom | Fix |
|---------|-----|
| Raw `{for each…}` visible | Add `birkly-client.js` |
| Unstyled page | Prefix assets with `site_url_prefix` from `get_project_info` |
| Blank detail page | Check slug in URL; use `?collection=&slug={slug}`; detail page must not loop the collection |
| Static cards, empty CMS | Replace hard-coded markup with loops; publish entries |
| Empty field in template | Align template field names with schema; define schema before entries |
| Click item → list again | Detail page is wrongly using a list loop |
| Same nav pasted everywhere | Use `<birkly-region>` or `{from … get …}` for shared blocks |
| `{{ }}` / `{% for %}` | Birkly uses single braces: `{for each entry in 'slug'}` |
| Invented filters (`truncate`, `strip_tags`) | Store teasers in CMS; only date `format` filter exists |

## Known bad patterns (real regressions — never generate these)

If `read_project_file` shows these, rewrite the file — they have caused broken layouts and silent form failures in production.

### Bad: detail page loops entire collection

```html
<!-- WRONG on post.html / detail.html — always shows list or wrong item -->
{for each entry in 'blog'}
  <h1>{title}</h1>
  <div>{ content }</div>
{endfor}
```

**Fix:** Detail uses URL context only:

```html
<meta name="birkly-collection" content="blog">
<h1>{ title }</h1>
<div>{ content }</div>
```

List page links: `detail.html?collection=blog&slug={slug}`.

### Bad: CSS at `/css/` instead of site prefix

```html
<!-- WRONG when site_url_prefix is /project/ -->
<link rel="stylesheet" href="/css/style.css">
```

**Fix:** `get_project_info` → use `/project/css/style.css` (or whatever prefix returns).

### Bad: hard-coded cards when collection exists

```html
<!-- WRONG after create_collection 'menu' — CMS data ignored -->
<article><h2>Sourdough</h2><p>$5</p></article>
<article><h2>Croissant</h2><p>$3</p></article>
```

**Fix:** `{for each entry in 'menu' …}` with published entries from `create_entry`.

### Bad: empty schema then rich templates

```json
{ "fields": [] }
```

Template references `{ 'excerpt' }`, `{ image }` → blank output forever.

**Fix:** Full schema first (`fieldtypes` playbook), then entries, then HTML.

### Bad: Jinja/Liquid/Blade syntax

```html
{% for item in items %}
{{ item.title }}
{% endfor %}
```

Birkly only: `{for each entry in 'slug'}` … `{endfor}`.

### Bad: custom contact form JS (overrides platform)

AI-generated sites sometimes ship hand-written fetch handlers that conflict with `{entry for}`. See **`public-forms`** → Known bad patterns — replace with `{entry for}` + `birkly-client.js`.

### Bad: entry detail layout without table structure

Admin entry-detail for sub-collections expects consistent markup when mixing main entry + sub-entries — prefer patterns from reference `project/post.html` + `smart-table` conventions in admin templates.

## Admin cross-reference

| Topic | Admin / human doc |
|-------|-------------------|
| Website integration | `admin/docs/website-integration.php` |
| Client library | `admin/docs/client-library.php` |
| Reference projects | `admin/docs/snapshots/reference-projects.md` |
| Template spec (full) | `docs/TEMPLATE_LANGUAGE_SPEC.md` |

---

## Examples (illustrative — adapt to the project)

These are **patterns**, not required slugs or file names.

### Repeatable items + detail (blog, portfolio, menu, products)

- Collection for the item type (fields: title, teaser, body, image, order as needed)
- Homepage or index: featured loop and/or link to full list page
- List page: `{for each entry in '…' ordered by …}` with links to detail
- Detail page: meta collection + `{ title }`, body fields, back link

*Example:* posts → list at `articles.html`, detail at `item.html?collection=posts&slug={slug}`.

### Mostly static marketing site

- One chrome collection for hero + footer
- One collection only where content will change often (testimonials, case studies)
- Rest: static HTML or single-entry `{from … get …}` blocks

### Business site with contact

- Collections for editable sections (services, team, etc.) as needed
- Separate collection for **inbound submissions** (`public_api`) — not mixed with marketing entries
- Contact page: `{entry for 'inquiries'}` — see `public-forms`

---

## After building

Tell the user: preview URL (`get_project_info`), which collections feed which pages, that content edits happen in admin Collections, and how list → detail URLs work.
