# Site building playbook (Birkly)

Use this when the user asks to **build a website**, **portfolio**, **landing page**, or **public site** in this Birkly instance.

**Also read (auto-loaded with site builds):** `web-project-patterns` — list vs detail pages, schema recipes, mandatory `birkly-client.js`, and the mistakes that cause raw `{…}` text or broken article links.

## Two ways to publish a public site

| Approach | When to use | Where files live |
|----------|-------------|------------------|
| **Project site (preferred for custom HTML/CSS)** | Full design control, static pages, portfolio, marketing site | `project/` at site root |
| **CMS pages** | Simple content pages managed in admin, blog-style | `pages` collection + templates in `project/` |

For “build me a portfolio website”, default to **project site** unless the user explicitly wants admin-managed pages only.

## Before you write anything

1. **Plan the content model first** (collections + fields) — see `web-project-patterns` recipes for blog, portfolio, bakery, etc.
2. **Discovery once** — do not repeat in a loop:
   - `get_project_info` — see if `project/` exists, URL, index file
   - `list_project_files` with `path: ""` — see current tree
   - `list_collections` — note existing slugs; extend schema instead of duplicating

## Recommended build order (project site)

Execute in order:

1. **Content model**
   - `create_collection` or `update_collection` with **full fields array** (never empty `fields: []`)
   - `create_entry` with `status: "published"` and realistic seed content

2. **HTML structure**
   - `write_project_file` — list pages (`blog.html`, homepage sections) with `{for each entry in 'slug'}` loops
   - `write_project_file` — **detail page** (`article.html` or `post.html`) with meta tags + `{ title }` / `{ content }` — **no list loop**
   - `write_project_file` — `css/style.css`
   - Every templated page: `<script src="/birkly-client.js"></script>` before `</body>`

3. **Shared chrome**
   - Collection `site_elements` with entries `header`, `footer`, `hero`
   - `<birkly-region collection="site_elements" entry="header"></birkly-region>` on each page

4. **Verify**
   - List page shows entries from CMS
   - Click through to detail — single article, not list again
   - No raw `{for each…}` visible (means missing client script)

## Content structure tips

- **Blog / stories:** collection `blog` → `blog.html` (list) + `article.html` (detail with `?collection=blog&slug={slug}`)
- **Portfolio:** collection `projects` with image, description, order → grid on index + detail page
- **Landing:** single `index.html` + optional `site_elements` for hero copy
- **Multi-page:** shared CSS; link list cards to detail URLs with `{slug}` in query string

## Anti-patterns (causes loops and user-visible bugs)

- HTML with template tags but **no** `birkly-client.js` → raw `{…}` in browser
- Collections created with empty schema → templates reference fields that don't exist
- Detail page uses `{for each entry in 'blog'}` → clicking "Read" shows list again
- Static hard-coded cards while collections exist beside them
- Calling `list_project_files` on the same path repeatedly
- Saying “I'll proceed” without emitting `write_project_file` / `create_entry` actions

## Tool choice

| Intent | Tools |
|--------|--------|
| Custom site in project folder | `write_project_file`, `read_project_file`, `list_project_files`, `get_project_info` |
| Editable content blocks | `create_collection`, `update_collection`, `create_entry`, `update_entry` |
| Admin-managed pages | `create_page`, `list_pages` |
| Patterns & recipes | Pre-loaded `web-project-patterns`, `content-model`, `templating` |

## Approval flow

- Writes require user approval — batch schema + seed entries + HTML files when possible.
- After approval, summarize collections, pages, and preview URL.

## Session memory

After building, note: collections + fields created, list vs detail file names, and that content edits happen in admin Collections — not by editing HTML cards.
