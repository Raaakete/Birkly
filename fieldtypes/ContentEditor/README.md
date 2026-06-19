# ContentEditor fieldtype

Self-contained rich text fieldtype for Birkly admin. Stored value is **pure HTML** — no proprietary JSON blob.

## Directory layout

```
ContentEditor/
├── ContentEditor.php          # PHP field class (render, validate, asset bundling)
├── editor.js                  # Editor lifecycle and initialization
├── editor.css                 # Editor + toolbar styles
├── commands.js                # Legacy commands (media, tables, components, …)
├── selection.js               # Selection save/restore utilities
├── link-metadata.php          # Link preview metadata proxy
├── commands/
│   ├── inline-format.js       # Bold, italic, underline, strikethrough logic
│   ├── block-format.js        # Headings, paragraphs, lists
│   ├── base-command.js        # Command factory helpers
│   ├── registry.js            # Command dispatcher (selection + history wrapper)
│   └── bootstrap.js           # Registers Phase 2 modular commands
├── components/
│   ├── ComponentRegistry.php  # Bundled + user component storage
│   └── bundled/               # Shipped components (callout, quote, cta-button)
├── core/
│   ├── dom-utils.js           # Block detection, text nodes
│   ├── sync-manager.js        # Visual ↔ HTML round-trip
│   ├── preview-css.js         # Project CSS preview (scoped injection)
│   ├── component-registry.js  # Components API client
│   ├── event-manager.js       # Toolbar and editor events
│   ├── toolbar-manager.js     # Toolbar active-state updates
│   ├── keyboard-handler.js    # Shortcuts (routes via command registry)
│   └── history-manager.js     # Undo/redo stack
├── preview/
│   └── demo-preview.css       # Default preview stylesheet
└── tests/                     # Fieldtype-local test suites
```

See `ARCHITECTURE_PROPOSAL.md` for the longer-term modularization plan.

## PHP configuration

| Key | Purpose |
|-----|---------|
| `name` | Form field name (required) |
| `label` | Admin label |
| `css_url` | Project stylesheet URL for visual preview (editor only; not saved in entry HTML) |
| `required` | Validation flag |

Aliases: `editor` and `media` field types resolve to `content-editor` via `bootstrap_fieldtypes.php`.

`getJavaScript()` concatenates modules in dependency order (core utilities → commands → editor). `getCSS()` returns `editor.css`.

## Visual ↔ HTML modes

- **Visual tab:** `contenteditable` area with toolbar
- **HTML tab:** raw source textarea; `ContentEditorSync` keeps both panels consistent
- Round-trip tests: `tests/html_roundtrip_test.php`

## Command system

**Modular commands** (Phase 2, registered in `commands/bootstrap.js`):

- `bold`, `italic`, `underline`, `strikethrough`
- `formatBlock` (headings, paragraph, blockquote, pre)
- `insertUnorderedList`, `insertOrderedList`

Toolbar and keyboard shortcuts call `ContentEditorCommandRegistry.execute()`, which saves selection, runs the command, updates history, then clears the saved selection.

**Legacy commands** remain in `commands.js` (links, tables, media embeds, components, colors, etc.). The registry falls back to `ContentEditorCommands.execute()` for unregistered commands.

To add a modular command:

1. Implement logic in `commands/inline-format.js` or `commands/block-format.js`, or a new module
2. Register via `ContentEditorBaseCommand.create()` in `commands/bootstrap.js`
3. Add toolbar button with `data-command="yourCommand"` in `ContentEditor.php` if needed

## Project CSS preview

When `css_url` is set on the field (collection schema or demo content), `ContentEditorPreview.load()` fetches and scopes the stylesheet to `.content-editor-area` only. A toolbar badge shows load status. Bundled fallback: `preview/demo-preview.css`.

## Components

Reusable HTML blocks with optional CSS, JS, and Birkly template variables.

| Location | Purpose |
|----------|---------|
| `components/bundled/` | Built-in components (read-only in admin Manage tab) |
| `content/components/` | User-created components (via admin or API) |

API: `/api/components.php` delegates to `ComponentRegistry.php`. JS client: `ContentEditorComponentRegistry`.

Bundled types: **callout**, **quote**, **cta-button**. Insert via toolbar → Components.

## Tests

Run all fieldtype suites via the M5 harness:

```bash
php tests/run.php
```

ContentEditor-specific suites:

| File | Covers |
|------|--------|
| `tests/html_roundtrip_test.php` | Visual/HTML tabs, `processValue` |
| `tests/preview_css_test.php` | CSS preview assets and markup |
| `tests/component_registry_test.php` | Bundled components, PHP registry |
| `tests/commands_registry_test.php` | Modular command modules and bundle order |

## Values check

- All editor code, CSS, JS, components, and tests live under `fieldtypes/ContentEditor/`
- No runtime dependencies beyond browser APIs and optional `window.Birkly.render` for component templates
- Stored field value is HTML suitable for public templates

## Related docs

- `ARCHITECTURE_PROPOSAL.md` — future command/block modularization (Phase 3+)
- `docs/roadmap/ROADMAP.md` — P2-M3 milestone tracking
