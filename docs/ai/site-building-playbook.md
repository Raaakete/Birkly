# Site building playbook (Birkly)

Use this when the user asks to **build a website**, **portfolio**, **landing page**, or **public site** in this Birkly instance.

## Two ways to publish a public site

| Approach | When to use | Where files live |
|----------|-------------|------------------|
| **Project site (preferred for custom HTML/CSS)** | Full design control, static pages, portfolio, marketing site | `project/` at site root |
| **CMS pages** | Simple content pages managed in admin, blog-style | `pages` collection + templates in `project/` |

For “build me a portfolio website”, default to **project site** unless the user explicitly wants admin-managed pages only.

## Before you write anything

1. **Read once** — do not repeat these in a loop:
   - `get_project_info` — see if `project/` exists, URL, index file
   - `list_project_files` with `path: ""` — see current tree
   - `read_project_file` for `index.html` (or main template) if it exists
   - `read_birkly_docs` topic `site-building` (this doc) and `templating` if using Birkly tags

2. **Plan in one message** (brief):
   - Site goal (portfolio, business, landing)
   - Pages/sections (hero, about, work, contact)
   - Content model: static HTML only vs collections + entries for repeatable items (projects, testimonials)

3. **Confirm schema only if needed** — if portfolio items should be editable in admin, ensure a collection exists (`list_collections` once). Do not re-list collections every turn.

## Recommended build order (project site)

Execute in order; **do not re-read** files you already loaded this session unless the user changed something.

1. **Structure**
   - `write_project_file` — `index.html` (main page)
   - `write_project_file` — `css/style.css` (or linked stylesheet path you choose)
   - Optional: `js/main.js` for interactions

2. **Content model (if dynamic)**
   - `create_collection` only when user wants CMS-managed items (projects, blog posts)
   - `create_entry` for seed content — batch in one approval when possible

3. **Templating**
   - Use Birkly templating in HTML where needed: `{{ page.title }}`, `{% for item in collection %}`, etc.
   - Read `templating` topic once for tag syntax; do not re-fetch every message.

4. **Verify**
   - `get_project_info` once after writes to confirm index path
   - Tell user the public URL from project info

## Content structure tips

- **Portfolio**: hero + featured work grid + about + contact; work items as collection `projects` with fields: title, description, image, link, order.
- **Landing**: single `index.html` with sections; minimal collections.
- **Multi-page**: `about.html`, `contact.html` in `project/` with shared CSS; link from index.

## Anti-patterns (causes loops)

- Calling `list_project_files` or `read_project_file` on the same path repeatedly
- Using `create_page` / CMS page flow when user asked for a site in **project/**
- Saying “I'll proceed” without emitting `write_project_file` actions
- Re-discovering collections every turn — cache mentally from first `list_collections`
- Second follow-up round that only re-reads project files — stop after first successful read batch

## Tool choice

| Intent | Tools |
|--------|--------|
| Custom site in project folder | `write_project_file`, `read_project_file`, `list_project_files`, `get_project_info` |
| Editable content blocks | `create_collection`, `create_entry`, `update_entry` |
| Admin-managed pages | `create_page`, `list_pages` |
| Learn syntax | `read_birkly_docs` — topics: `site-building`, `templating` |

## Approval flow

- Writes require user approval — include all planned files in one batch when the UI supports multiple actions.
- After approval, summarize what was created and the URL to preview.

## Session memory

After building, the conversation should note: project path, index file, collections created, and main CSS path — so follow-up turns do not re-scan the tree.
