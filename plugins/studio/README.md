# Studio Plugin

Project IDE and visual site builder for Birkly (Phase 22–23). Projects live at `content/studio-projects/{id}/`.

## Features

### IDE workspace (P23)

- **Filesystem sidebar:** real disk tree — browse, create, rename, delete project files
- **File tabs:** multi-file editing with dirty state + sessionStorage restore
- **Views:** Visual canvas, Code editor, Split (side-by-side)
- **Open project ↗:** opens home page in a **new browser tab** (SSR or draft preview)
- **Canvas v2:** DnD sections, multi-column, responsive breakpoints, design tokens
- **Plugin blocks:** `register_studio_block()` from commerce, events, etc.
- **Section library:** insert pre-built sections from `plugins/studio/sections/`
- **Undo + revisions:** canvas undo stack; file revisions on save

### Core (P22)

- **Projects:** create, duplicate, delete, dashboard list
- **Style kits:** minimal, bootstrap (vendored subset), tailwind-lite
- **Birkly bindings:** collection loops, filtered lists, site elements
- **SSR:** published sites at `/site/{project-slug}/`
- **Export:** ZIP with optimized `website/` mirror

## Quick start

1. Activate **Studio** in Admin → Settings → Plugins
2. Open **Studio** in the sidebar → **New project**
3. Use the file tree, tabs, and visual/code/split views to edit
4. Click **Open project ↗** to preview in a new tab
5. **Publish** for SSR, or **Export** for static deploy

## Storage layout

```
content/studio-projects/{project-id}/
├── project.json
├── tokens.json
├── components/
├── styles/kit.css, custom.css, generated.css
├── pages/{slug}.studio.json, {slug}.html
├── .revisions/
└── website/   # export mirror
```

## Public URLs

| URL | Purpose |
|-----|---------|
| `/site/{slug}/` | Home page (SSR) |
| `/site/{slug}/{page}/` | Inner page |
| `/site/{slug}/styles.css` | Kit + generated + custom CSS |
| `/site/{slug}/?birkly_studio_preview=1` | Draft preview (admin session) |
| `/api/studio_view.php?slug={slug}` | **Reliable viewer** — use when `/site/` rewrite fails (LiteSpeed, etc.) |

## Production deploy (Apache / nginx)

Published Studio sites need **two things** on the server:

1. **`api/studio_public.php`** — must exist in the deployed Birkly tree (included in current releases).
2. **URL routing** — requests to `/site/*` must reach that file.

### Apache

Shipped `.htaccess` already contains:

```apache
RewriteRule ^site(/.*)?$ api/studio_public.php [L,QSA]
```

Ensure `AllowOverride All` (or at least `FileInfo`) so `.htaccess` rewrites apply.

### nginx

`.htaccess` is ignored on nginx. Add one of:

```nginx
# Option A — direct rewrite
location ~ ^/site(/.*)?$ {
    try_files $uri /api/studio_public.php$is_args$args;
}

# Option B — front controller (index.php also routes /site/* when studio_public.php exists)
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

After deploy, verify:

```bash
curl -sI https://your-cms.example/api/studio_public.php   # should NOT be 404
curl -sI https://your-cms.example/site/your-project-slug/ # 200 after publish
```

`GET /api/health.php` includes a `studio` block (`route_ready`, `hints`) for monitoring.

## Plugin blocks

Plugins register blocks via `studio-blocks.php`:

```php
register_studio_block('my_block', [
    'label' => 'My block',
    'bindingType' => 'loop',
    'collection' => 'blog',
    'itemTemplate' => '<article>{title}</article>',
]);
```

## Production builder (P25)

Design canvas with project CSS, live preview pane, template engine v2, CSS studio, section presets, and layout pro tools. See **`plugins/studio/docs/production-builder.md`**.

## Deactivation

When Studio is deactivated, `/site/*` returns **503** — project files remain on disk.

## Values

Self-contained plugin. No npm/webpack at runtime. Compiled HTML uses Birkly template tags only on public pages — no `.studio.json` at runtime.
