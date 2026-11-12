# CMS pages playbook

Use when the user wants **admin-managed pages** editable in the CMS without editing raw `project/` HTML files.

**Admin doc (human UI):** https://docs.birkly.io/02-content/entries.html (pages are entry-like records)  
**Help topic id:** `entries` (pages share content model concepts)

---

## Pages vs project site (decision)

| Approach | Best for | AI tools | User edits via |
|----------|----------|----------|----------------|
| **`project/` files** | Custom design, portfolio, marketing sites | `write_project_file`, `get_project_info` | Admin Collections + HTML files (AI) |
| **CMS pages** | Simple copy, legal/FAQ, admin-only editing | `create_page`, `list_pages`, `update_page` | Admin **Pages** UI |

**Default for “build me a website”** → **`project/`** + **`site-building`**.  
Choose CMS pages only when user explicitly wants admin-managed pages without custom project HTML.

---

## When CMS pages fit

- Client edits About/FAQ/Privacy copy in admin without touching files
- Simple content pages with optional collection loops in body
- Hybrid: static shell in `project/index.html` linking to `/pages/about` (routing-dependent)

## When project site fits

- Full brand design, custom CSS, portfolio layouts
- List/detail URL patterns (`?collection=&slug=`)
- `{entry for}` contact forms, `{birkly-region}` chrome

See **`site-building`** decision tree.

---

## AI tools

| Tool | Purpose |
|------|---------|
| `list_pages` | Existing pages, slugs |
| `create_page` | New page with title, slug, HTML content |
| `update_page` | Edit body, title, slug |
| `delete_page` | Remove page (if permitted) |

Paths and routing depend on instance config — `list_pages` first.

---

## Create a page (example)

```json
{
  "type": "create_page",
  "title": "About Us",
  "slug": "about",
  "content": "<h1>About</h1><p>We build great things.</p>"
}
```

### Page with dynamic collection block

Content field supports Birkly templating:

```html
<h1>Our team</h1>
{for each entry in 'team' with status = published ordered by newest}
  <div class="member">
    <h2>{title}</h2>
    <p>{'bio'}</p>
  </div>
{endfor}
```

Requires:
1. `team` collection exists with published entries
2. Public page view runs through Birkly templating + **`birkly-client.js`** if rendered as client-side template (depends on delivery mode — verify with `list_pages` / project routing)

---

## Hybrid pattern (common)

| Part | Where |
|------|-------|
| Header, footer, CSS | `project/` (`write_project_file`) |
| Editable legal/About body | CMS page (`create_page`) |
| Repeatable content (blog, products) | Collections + `project/` list/detail |

Link from project nav:

```html
<a href="/pages/about">About</a>
```

Use `get_project_info` for correct URL prefix if instance uses subpath.

---

## Workflow checklist

1. `list_pages` — avoid duplicate slugs
2. Confirm user wants **pages** not **project** (if ambiguous, ask once — prefer project for “website”)
3. `create_page` or `update_page` with complete HTML
4. If page loops collections: ensure schema + published entries exist first (**`content-model`**)
5. Link from `project/` nav if hybrid site

---

## Worked example: “Add About page editable in admin”

1. User confirms admin-managed (not full custom site)
2. `list_pages` — slug `about` free?
3. `create_page` slug `about`, content with headings and copy
4. If site uses `project/` shell: `write_project_file` nav link to page URL
5. Tell user: edit copy under Admin → Pages

---

## Anti-patterns

| Mistake | Why bad | Do instead |
|---------|---------|------------|
| `create_page` for full portfolio site | No custom layout control | `site-building` + `project/` |
| Duplicate content in page AND collection | Sync drift | One source of truth |
| Page loops without published entries | Blank sections | `create_entry` first |
| Skipping templating rules | Raw `{for each}` visible | Client render or server delivery per config |

---

## Common failure modes

| Symptom | Cause | Fix |
|---------|-------|-----|
| Page 404 | Wrong slug or routing | `list_pages`; check site delivery settings |
| Raw template tags on page | Missing client render | Add `birkly-client.js` if client-rendered |
| User wanted custom design | Wrong approach | Switch to `project/` playbook |

---

## Related playbooks

- **`site-building`** — default website workflow
- **`web-project-patterns`** — list/detail in project/
- **`content-model`** — collections feeding page loops
- **`templating`** — tags in page content

## Admin cross-reference

| Topic | Location |
|-------|----------|
| Pages UI | Admin → Pages |
| Entries model (similar concepts) | Admin → Content |
| Human docs | https://docs.birkly.io/02-content/entries.html |
