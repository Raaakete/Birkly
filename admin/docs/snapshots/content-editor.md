# ContentEditor field

ContentEditor is Birkly's rich text fieldtype. Stored values are **pure HTML** — no proprietary JSON.

## Features

- Visual editing with toolbar (bold, headings, lists, links).
- **Block components** — callout, quote, CTA button (bundled; more via component registry).
- **Preview CSS** — optional project stylesheet for WYSIWYG preview in admin only.
- Undo/redo and keyboard shortcuts.

## Configuration

In collection schema, use field type `content-editor` (aliases: `editor`, `media`).

| Option | Purpose |
|--------|---------|
| `css_url` | Project CSS for visual preview in editor |
| `required` | Validation flag |

## Architecture

- `editor.js` — lifecycle and initialization
- `commands/` — modular formatting commands
- `components/bundled/` — shipped block components

## Related

- Entries
- Fieldtype README: `fieldtypes/ContentEditor/README.md`
