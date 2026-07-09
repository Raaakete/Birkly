# Studio production builder (P25)

**Summary:** WYSIWYG design canvas, live client preview, template engine v2, CSS studio, section presets, and CI smoke tests.

## Design canvas

The visual canvas applies the project's compiled styles so editing looks like the live site.

- **Project CSS:** `kit.css` + `generated.css` + `custom.css` injected via `#studio-canvas-styles` (`get_canvas_styles` API)
- **Design mode:** `studio-canvas--design` class; toggle **Outlines** for optional chrome
- **Implementation:** `plugins/studio/admin/studio-production.js` (`loadCanvasStyles`)

## Live preview

Split the editor into a **Live** view that renders real collection data without leaving admin.

| View | Purpose |
|------|---------|
| Visual | Design canvas with project CSS |
| Code | Compiled HTML editor |
| Live | iframe with `birkly-client.js` + page_bundle data |
| Split | Visual + code side-by-side |

- **API:** `preview_live` compiles the page, collects bundle requests, SSR-renders with `StudioTemplateEngine`
- **Refresh:** Auto-refresh on save when Live or Split+Live is active
- **Draft:** Published pages use `/site/{slug}/`; draft preview uses `?birkly_studio_preview=1`

## Template engine v2

`StudioTemplateEngine` (P25-M3/M4) adds SSR parity for advanced bindings:

| Feature | Syntax example |
|---------|----------------|
| Conditionals | `{if 'site_elements'.'hero' exist}…{endif}` |
| Entry field checks | `{if entry has 'price' <= 10}…{endif}` |
| Sub-entry loops | `{for each comment in 'comments' of 'blog'.'first-post'}…{endfor}` |
| Template functions | `{upcoming_events}` via `page_bundle` |
| Ordered + limit | `{for each entry in 'blog' ordered by newest limit 3}…{endfor}` |

**Tests:** `tests/studio_template_engine_v2_test.php`

Binding wizard steps for conditional, sub-entry, and template-function blocks are in `studio-production.js`.

## CSS panel

Inspector tabs for global and element styles:

| Tab | Edits |
|-----|-------|
| **CSS** | Element `styles` JSON → inline CSS on compile |
| **Stylesheets** | `custom.css` editor (Custom \| Generated \| Kit read-only) |
| **Page** | Meta title, description → `<head>` on publish |

- **Save:** `save_custom_css` API writes to `styles/custom.css`
- **Class manager:** Multi-class chips on selected elements (Properties tab)

## Section presets

Pre-styled sections in `plugins/studio/sections/`:

| Preset | File |
|--------|------|
| Hero | `hero.json` |
| Features (3-col) | `features.json` |
| Blog grid | `blog-grid.json` |
| CTA banner | `cta-banner.json` |

Insert from the **Sections** sidebar. Kits ship matching CSS (e.g. `.hero`, `.product-card` in `kits/minimal/kit.css`).

## Plugin blocks v2

Reference-quality markup in `plugins/studio/studio-blocks.php`:

- `commerce_product_card` — product grid matching reference-projects/coffee
- `events_list` — upcoming events matching reference-projects/yoga

## Layout tools

- **Box model overlay:** Drag margin/padding handles on selected elements
- **Column resize:** Drag handle on `.studio-col-preview` borders
- **Align toolbar:** Left / center / right text alignment

## Content panel

Sidebar **Content** section lists collections. Expand a collection to see fields; click a field to insert `{ field }` into the selected text or binding template.

## Undo, redo, revisions

- **Undo / Redo:** Canvas history stack (Ctrl+Z / Ctrl+Shift+Z)
- **File revisions:** `.revisions/` on save; `list_revisions` API

## studio-smoke.sh

End-to-end CI smoke test (P25-M17):

```bash
./scripts/studio-smoke.sh
```

Creates a fixture project, compiles a page with a collection loop and SEO meta, SSR-renders with `StudioTemplateEngine`, and asserts:

- Compiled HTML includes `<meta name="description">`
- Rendered output has collection data
- No raw `{for each` or `{endfor}` tags remain

Included in `./scripts/ci-check.sh`.

## Related docs

- Canvas v2 schema: `plugins/studio/docs/canvas-v2-schema.md`
- Plugin README: `plugins/studio/README.md`
- Birkly-Docs: `04-studio/production-builder.md`
