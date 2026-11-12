# Client templating playbook (Birkly)

Use for: template syntax, loops, ordering, dates, `{from…get}`, detail URL context, regions, and phantoms.

**Canonical engine SSOT:** `docs/TEMPLATE_LANGUAGE_SPEC.md` (v1.0).  
**Human docs:** https://docs.birkly.io/03-templating/templating-overview.html  
**Do not invent syntax.** If unsure, match the spec — not Jinja/Liquid/Blade.

## Load this when

- User asks how to display CMS content on a site
- Templates show raw `{for each…}` or wrong fields
- Ordering / dates / detail pages are wrong
- AI is about to write `project/*.html` with `{…}` tags

For full site wiring (schema → list/detail → forms), also load `web-project-patterns` + `site-building`. For contact forms specifically, load `public-forms`.

## Client script (required)

Every templated HTML page:

```html
<script src="/birkly-client.js"></script>
```

External site (different origin than CMS):

```html
<script src="https://your-cms.example/birkly-client.js"
        data-cms-url="https://your-cms.example"></script>
```

Optional enhance-only helpers: `birkly-components.js` — **never** wipe `innerHTML` of CMS-rendered regions.

## Core rules

| Rule | Canonical form |
|------|----------------|
| Collections quoted | `'blog'`, `'site_elements'` |
| System fields bare | `{title}`, `{slug}`, `{created_at}` |
| User fields quoted | `{'excerpt'}`, `{'price'}`, `{'content'}` |
| Loops | `{for each entry in 'blog'}…{endfor}` |
| Filters | `with status = published` (repeat `with` to AND-combine; checkbox fields match `'1'`/`'true'`/`'yes'`) |
| Ordering | `ordered by newest` / `oldest` / `last-updated` / `order` |
| Limit | trailing `limit 5` (space, not `limit=`) |
| Dates | `{created_at format "F j, Y"}` |
| Single entry | `{from 'blog' get entry 'slug'}…{endget}` |
| Existence | `{if 'site_elements'.'hero' exist}` |
| Shared chrome | `<birkly-region collection="site_elements" entry="header">` |
| Site chrome handle | Prefer **`site_elements`** (underscore) — client prefetches this slug |

## List vs detail (critical)

**List** — loop the collection:

```html
{for each entry in 'blog' with status = published ordered by newest}
{for each entry in 'blog' with status = published with 'featured' = '1' ordered by newest}
  <a href="post.html?collection=blog&slug={slug}">{title}</a>
{endfor}
```

**Detail** — URL context only (no collection loop):

```html
<meta name="birkly-collection" content="blog">
<h1>{title}</h1>
<div>{'content'}</div>
```

Sub-collections on a detail page:

```html
{for each entry in 'comments' of 'blog'.current ordered by newest}
  <p><strong>{'author'}</strong> {'content'}</p>
{endfor}
```

## Field arrays (tags, multiselect, gallery)

Not collections — loop the **field** on the current entry:

```html
{for each tag in 'tags_list'}<span>{tag}</span>{endfor}
{for each id in 'test_gallery'}<img src="/media/{id}.jpg" alt="">{endfor}
```

- Use a named loop variable (`tag`, `id`, `cat`). `{value}` and `{.}` are not scalar-loop aliases.
- `{entry}` as loop variable works for scalar lists but is easy to confuse with collection loops; prefer explicit names.
- There is no string-replace filter for oEmbed URLs or dynamic relation lookups — use **shelf** helpers with **parentheses**: `{media_img('hero')}`, `{oembed_html('video')}`, `{dynamicitems_html('blocks')}` (see `fieldtypes`; full QA matrix: `fieldtype-qa`).
- `{'field'}` on detail pages outputs formatted scalars when schema type is known; **media ids**, maps, ranges, relations, and dynamic item blocks still need shelf helpers — not raw `{'hero'}` in `<img src>`.

## Regions

```html
<birkly-region collection="site_elements" entry="header"></birkly-region>
<birkly-region collection="blog" list ordered="newest">
  <article><h2>{title}</h2><p>{'excerpt'}</p></article>
</birkly-region>
```

`ordered="…"` words: `newest`, `oldest`, `last-updated`, `order` — **not** `date:desc`.

Light DOM = template language. Shadow DOM = fetch API + `Birkly.render(html, data)` only.

## Public forms vs phantoms

| Form | Status |
|------|--------|
| `{entry for 'contact'}…{endentry}` | **Valid** form scaffold — see `public-forms` |
| `{entry 'blog'.'slug'}…{endentry}` | **Phantom** — use `{from 'blog' get entry 'slug'}…{endget}` |
| `{ date "…" }` | **Phantom** — use `{created_at format "…"}` |
| `{title truncate 80}` / `strip_tags` | **Not implemented** — use excerpt / plain fields |
| `{{ }}` / `{% for %}` | **Wrong language** — Birkly uses single braces |

## Quick anti-patterns

- Missing `birkly-client.js`
- Detail page loops `{for each entry in 'blog'}`
- Unquoted user fields (`{excerpt}` instead of `{'excerpt'}`)
- `ordered="date:desc"` or `sort="date:desc"`
- `{from 'blog' get entries limit=5}` — use a `{for each}` loop
- Chrome collection handle mismatch (`site-elements` vs `site_elements`)

## Tools

| Intent | Tool / topic |
|--------|----------------|
| Depth (this file) | `read_birkly_docs` → `templating` |
| Full grammar | Also in `docs/TEMPLATE_LANGUAGE_SPEC.md` when debugging engine |
| Site wiring | `web-project-patterns`, `site-building` |
| Forms | `public-forms` |
| Sub-collections | `sub-collections` |

## Admin / human cross-reference

| Topic | Help id / docs |
|-------|----------------|
| Overview | `templating-overview` |
| Canonical syntax | `canonical-syntax` |
| Loops | `loops-and-lists` |
| Detail / `.current` | `detail-pages-and-url-context` |
| Forms | `public-forms-templating` / `public-forms` |
| Spec (CMS repo) | `docs/TEMPLATE_LANGUAGE_SPEC.md` |
