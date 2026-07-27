# CMS pages playbook

Use when user wants **admin-managed pages** rather than raw `project/` HTML files.

## Pages vs project site

| Approach | Best for | AI tools |
|----------|----------|----------|
| **project/** files | Custom design, portfolio, marketing | `write_project_file` — see `site-building` |
| **CMS pages** | Editable pages in admin, simple content | `create_page`, `list_pages`, `update_page` |

Default **site build** requests → `project/` unless user says "CMS pages" or "admin-managed pages only".

## Create a page

```json
{
  "type": "create_page",
  "title": "About Us",
  "slug": "about",
  "content": "<h1>About</h1><p>Welcome.</p>"
}
```

Content can include Birkly templating for dynamic blocks:

```html
{for each entry in 'team'}
  <div>{title} — {'role'}</div>
{endfor}
```

## Workflow

1. `list_pages` — existing pages
2. `create_page` or `update_page`
3. Link from `project/index.html` or rely on pages routing if configured
4. Often combined: static shell in `project/`, dynamic body from collections

## When to choose pages

- Client edits copy in admin without touching HTML files
- Simple legal/About/FAQ pages
- Blog page records (if not using collection-only pattern)

## Anti-patterns

- Using `create_page` for full portfolio site when user wanted custom `project/` design
- Duplicating same content in pages AND collection entries without reason

## Related

- `site-building` — decision tree
- `templating` — tags in page content
