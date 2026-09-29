# Content Editor playbook

Use when entries use **Editor** fieldtype — rich HTML layouts, tables, grids.

## Rules for AI-generated HTML

1. Use semantic HTML; **no `<script>`** in saved content
2. Tables: class `content-editor-table`; use native `colspan` / `rowspan`
3. Grids: class `content-editor-grid` for multi-column layouts
4. Do **not** use `data-ce-*` attributes — runtime strips them
5. Preserve inline styles when updating existing editor content
6. Field name in entry JSON matches schema (often `content` or `body`)

## Read before write

1. `read_entry` — inspect existing HTML structure
2. Match surrounding patterns (tables, headings, classes)

## Example table fragment

```html
<table class="content-editor-table">
  <tr><th>Feature</th><th>Plan A</th><th>Plan B</th></tr>
  <tr><td>Storage</td><td>10 GB</td><td>100 GB</td></tr>
</table>
```

## Example grid

```html
<div class="content-editor-grid">
  <div class="content-editor-grid-cell"><h3>Column 1</h3></div>
  <div class="content-editor-grid-cell"><h3>Column 2</h3></div>
</div>
```

## Public output

Editor HTML stored in entry JSON; templates output with `{'content'}` or field slug. Client renders in page body.

## Anti-patterns

- Stripping classes on update
- Injecting scripts or iframes without user request
- Plain markdown when field expects HTML editor content

## Related

- `content-model` — editor field in schema
- `site-building` — displaying `{ 'content' }` in templates

## Reference

Context pack §ContentEditor; https://docs.birkly.com/02-content/content-editor.html
