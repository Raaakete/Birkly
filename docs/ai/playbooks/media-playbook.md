# Media library playbook

Use for: uploads, folders, referencing images/files in entries and templates.

## Storage

Files in `media/` with sidecar `.metadata.json`. Organized in folders.

## AI tools

| Tool | Purpose |
|------|---------|
| `list_media` | Browse library |
| `upload_media` | Upload file (approval may apply) |
| `create_media_folder` | New folder |
| `move_media` | Organize files |
| `delete_media` | Remove file |

## Workflow: image in entry

1. `list_media` or user uploads via admin
2. `create_entry` with image field = filename or media reference per fieldtype rules
3. Template: image/gallery fields render via client or SSR

## Field types

- **image** — single file
- **gallery** — multiple images
- **media** — generic file attachment

## SVG & rules

SVG upload may be restricted by settings. Check admin media settings for allowed types.

## AI tips

- Prefer describing required assets; user may upload via Media modal
- `upload_media` when connection has `manage_media`
- Reference exact filename from `list_media` response

## Related

- `content-model` — image/gallery fields on entries
- `content-editor` — inline images in rich HTML

## Admin reference

Snapshot: `admin/docs/snapshots/media.md`
