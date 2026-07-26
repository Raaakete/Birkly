# Content Editor

## Summary

The **Editor** field type (also called Content editor or Rich text) is Birkly’s built-in WYSIWYG editor for long-form content. Editors use a toolbar for headings, lists, links, images, and **layout blocks** — tables and CSS grids — with a visual layout studio for resizing, spacing, and cell styling. Saved content is portable HTML suitable for your website templates; editor-only metadata is removed when you save.

## Beginner

When a collection field is type **Editor**, you see a rich text area with two tabs:

- **Visual** — edit with the toolbar (like a word processor)
- **HTML** — view or edit the raw HTML source

**Basic formatting**

Use the toolbar for bold, italic, headings, bullet lists, links, and colors. Place the cursor where you want a change, then click the toolbar button or pick from a dropdown (alignment, heading level, text/background color, insert media).

**Inserting a table**

1. Place the cursor where the table should go (even in the middle of a paragraph).
2. Open the toolbar **Table** menu → choose row/column count (or use the insert dialog).
3. Click inside the table to select it — a **Layout panel** appears beside the editor.
4. Drag **column handles** on the table edge to change column widths.
5. Click a cell to style it: background color, border, padding, corner radius, vertical alignment.
6. Use table-level options for full width, striped rows, or a header row.
7. Save the entry. The table appears on your site as normal HTML.

**Inserting a grid**

1. Place the cursor where the grid should go.
2. Open the toolbar **Grid** menu → pick a preset (e.g. 2 equal columns or responsive auto-fit).
3. Click the grid block to open the Layout panel.
4. Adjust **gap** (space between cells), **column tracks**, and **auto flow** (row, column, or dense packing).
5. Click a grid cell to span it across multiple columns or rows, or to style padding and background.
6. Save the entry.

**Tips**

- Layout blocks insert at your cursor — you do not need to put them at the end of the document.
- Switch to **HTML** tab anytime to inspect the saved markup; switch back to **Visual** to keep editing.
- Responsive grids reflow on smaller screens using fluid column presets — no separate “mobile layout” step in the editor.

## Advanced Users

**Storage:** Editor fields store an HTML string in the entry JSON. No proprietary JSON layout blob. Inline `style` attributes are allowed for layout (tables, grids, cell styling). `<script>` tags are stripped on save.

**HTML output contract**

Tables use `table.content-editor-table` with optional `<colgroup>` / `<col style="width:…">` for column widths:

```html
<table class="content-editor-table" style="width:100%;border-collapse:collapse">
  <colgroup><col style="width:33%"><col style="width:33%"><col style="width:34%"></colgroup>
  <tbody>
    <tr>
      <td style="background:#f3f4f6;border-radius:8px;padding:12px">Cell content</td>
    </tr>
  </tbody>
</table>
```

Grids use `div.content-editor-grid` with inline CSS Grid properties. Responsive layouts use fluid track presets (`auto-fit` / `minmax`) — **no `@media` blocks** inside entry HTML:

```html
<div class="content-editor-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));grid-auto-flow:row;gap:16px">
  <div class="content-editor-grid-item" style="grid-column:span 2;padding:12px">Spanning cell</div>
  <div class="content-editor-grid-item">Normal cell</div>
</div>
```

**Stripped on save:** Editor-only attributes and chrome (`data-ce-*`, resize handles, inspector wrappers) are removed by `ContentEditorSync` and PHP `processValue` before persistence.

**Templates:** Output editor HTML unescaped in templates, e.g. `{'content'}` or `{from 'blog' get entry 'slug'} … {'body'} {endget}`. Ensure site CSS does not override layout inline styles unintentionally. Optional field option `css_url` loads project CSS in the admin preview only — it is not embedded in saved HTML.

**Layout studio modules** (platform): `fieldtypes/ContentEditor/layout/` — shared inspector, resize controller, table-layout, grid-layout. Tests: `layout_roundtrip_test.php`, `table_layout_test.php`, `grid_layout_test.php`, `caret_insert_test.php`.

**Out of scope (Phase 5.5):** nested tables/grids, spreadsheet formulas, cell merge/split (deferred to a future phase).

## Related

- [Field types](field-types.md)
- [Entries](entries.md)
- [Variables and output](../03-templating/variables-and-output.md)
