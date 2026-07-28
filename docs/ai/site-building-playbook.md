# Site building playbook (Birkly)

Use when the user asks to **build a website**, **portfolio**, **landing page**, or any **public site** in this Birkly instance.

**Also auto-loaded:** `web-project-patterns` (principles for content model, list/detail pages, wiring CMS to HTML).

## Two ways to publish

| Approach | When | Where |
|----------|------|--------|
| **Project site** (default for custom design) | Full HTML/CSS control, marketing sites, portfolios | `project/` |
| **CMS pages** | Simple admin-managed pages, no custom project shell | `pages` + templates |

Default to **project site** unless the user wants admin pages only.

## Before writing files

1. **Infer the content model** from the request — what repeats, what's shared, what's one-off (see `web-project-patterns`).
2. **Discover once** (do not loop):
   - `get_project_info` — URL prefix, paths
   - `list_project_files` — existing tree
   - `list_collections` — reuse or extend; avoid duplicate slugs

## Build order

1. **Schema** — collections + fields (never empty `fields: []`)
2. **Entries** — seed with `status: "published"`
3. **HTML** — list pages (loops), detail pages (URL context, no list loop), shared chrome, CSS
4. **Script** — `birkly-client.js` on every templated page
5. **Verify** — CMS data visible, detail links work, styles load under correct prefix

## Anti-patterns

- Templates without `birkly-client.js`
- Detail page that loops the whole collection
- Collections beside static hard-coded cards (CMS unused)
- Empty schema then templates referencing custom fields
- Re-reading project files every turn after first successful discovery

## Tools

| Intent | Tools |
|--------|--------|
| Project HTML/CSS | `write_project_file`, `read_project_file`, `list_project_files`, `get_project_info` |
| CMS data | `create_collection`, `update_collection`, `create_entry`, … |
| Admin pages | `create_page`, `list_pages` |
| Depth | Pre-loaded `web-project-patterns`, `content-model`, `templating` |

## Session memory

Note: collections created, list vs detail filenames, preview URL. Content edits happen in admin — not by editing HTML cards.
