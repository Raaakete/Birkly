# Studio Plugin

Visual site builder for Birkly (Phase 22). Projects live at `content/studio-projects/{id}/`.

## Features

- **Projects:** create, rename, duplicate, delete, dashboard list
- **Pages:** add, reorder, delete, set home page
- **Style kits:** minimal, bootstrap (vendored subset), tailwind-lite
- **Layout:** sections → rows → columns; element palette
- **Birkly bindings:** collection loops, filtered lists, site elements
- **Dual editor:** visual canvas ↔ code tab
- **Preview:** admin iframe with `birkly-client.js` (P21 bundle path)
- **SSR:** published sites at `/site/{project-slug}/`
- **Export:** ZIP with `website/` mirror (in-CMS copy remains source of truth)

## Quick start

1. Activate **Studio** in Admin → Settings → Plugins
2. Open **Studio** in the sidebar
3. Create a project (pick a style kit)
4. Add sections and a blog loop binding
5. **Publish** for SSR, or **Export ZIP** for static deploy

## Storage layout

```
content/studio-projects/{project-id}/
├── project.json
├── styles/kit.css, custom.css
├── pages/{slug}.studio.json, {slug}.html
└── website/   # export mirror
```

## Public URLs

| URL | Purpose |
|-----|---------|
| `/site/{slug}/` | Home page (SSR) |
| `/site/{slug}/{page}/` | Inner page |
| `/site/{slug}/styles.css` | Combined kit + custom CSS |

## Deactivation

When Studio is deactivated, `/site/*` returns **503** — project files remain on disk.

## Values

Self-contained plugin (P22-D1). No npm/webpack at runtime (P22-D5). Compiled HTML uses Birkly template tags only on public pages (P22-D4).
