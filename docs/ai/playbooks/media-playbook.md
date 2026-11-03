# Media library playbook

Use for: uploads, folders, referencing images/files in entries and templates.

## Storage

Files in `media/` with sidecar `.metadata.json`. Organized in folders.

## AI tools

| Tool | Purpose |
|------|---------|
| `list_media` | Browse library (filename, folder, URL) |
| `upload_media` | Upload file (approval may apply) |
| `create_media_folder` | New folder |
| `move_media` | Organize files |
| `media.bulk_create_variants` (via `cms_admin_action`) | Web-optimize many raster images (max 50 ids) |
| `delete_media` | Remove file |

## Workflow: image in entry

1. User uploads via admin **Media**, or AI calls `upload_media` (needs `manage_media`)
2. `list_media` — note exact **filename** and folder
3. `create_entry` / `update_entry` — set image field to media reference per fieldtype (often filename string)
4. Template — output field: `{'featured_image'}` or `{featured_image}` depending on field name

### Example: blog post with featured image

Schema field:

```json
{"name": "featured_image", "label": "Featured image", "type": "image", "required": false}
```

After upload `hero-bread.jpg`:

```json
{
  "collection": "blog",
  "title": "Morning batch",
  "featured_image": "hero-bread.jpg",
  "status": "published"
}
```

Template:

```html
{for each entry in 'blog'}
  <article>
    <img src="/media/hero-bread.jpg" alt="{title}">
    <!-- Prefer field output when client renders media URLs: -->
    <div>{'featured_image'}</div>
  </article>
{endfor}
```

Use `list_media` response URLs when hard-coding is needed.

## Field types

| type | Use |
|------|-----|
| `image` | Single image |
| `gallery` | Multiple images (array) |
| `media` | Generic file (PDF, etc.) |

See **`fieldtypes`** playbook for schema JSON.

## SVG & rules

SVG upload may be restricted by settings. Check admin media settings for allowed types.

## AI tips

- Prefer describing required assets; user may upload via Media modal
- `upload_media` when connection has `manage_media`
- Reference exact filename from `list_media` response — do not guess paths
- Public site URLs often `/media/{folder}/{filename}` — confirm via `list_media`

## Related

- `fieldtypes` — image/gallery/media field definitions
- `content-model` — entries with media fields
- `content-editor` — inline images in rich HTML
- Human docs: https://docs.birkly.com/01-admin/media-library.html
