# Dynamic items field

## Summary

The **Dynamic items** field type (`dynamicitems`) lets editors build repeating **blocks** inside a single entry. Each block has its own field type (text, textarea, number, media, etc.). Use it for flexible page sections, FAQ lists, feature grids, and the **`site_elements`** pattern where one entry holds many named blocks.

## Beginner

### When to use Dynamic items

| Use case | Example |
|----------|---------|
| Flexible page body | Homepage sections: hero, features, testimonial |
| FAQ | Each block: question (text) + answer (textarea) |
| Site chrome | Header entry: logo block, nav links block, CTA block |
| Repeating specs | Product entry: multiple “spec row” blocks |

Prefer **Dynamic items** when block **types and order** vary per entry. Prefer a **sub-collection** when many child rows share one schema and belong to a parent entry (e.g. comments on a blog post).

### Adding Dynamic items to a collection

1. Open the collection in the **Collection editor**.
2. Add a field; choose type **Dynamic items**.
3. Save the collection.
4. When editing an entry, click **+ Add Block**, pick a field type, set label and API name, and add the block.

Each block’s **API name** must be unique within that Dynamic items field (lowercase letters, numbers, underscores).

### Add Block workflow (entry editor)

1. Open an entry that has a Dynamic items field.
2. Click **+ Add Block**.
3. In the modal: select **Field type**, enter **Label** and **API name**, optionally mark **Required**.
4. Click **Add Block** — the inner field renders inline.
5. Fill block values and **Save** the entry.

On **multilingual** collections, add blocks separately per language tab; values are stored per locale.

### Site elements pattern

For shared header/footer content:

1. Create collection **Site elements** (handle: `site_elements`).
2. Add fields: **Title** (text) + **Blocks** (Dynamic items).
3. Create entries: `header`, `footer`, etc.
4. Bind on your site with `<birkly-region>` — see [Site elements pattern](site-elements-pattern.md).

## Advanced Users

**Stored value shape** (JSON array on the entry field):

```json
[
  {
    "type": "text",
    "name": "headline",
    "label": "Headline",
    "required": true,
    "settings": {},
    "value": "Welcome"
  },
  {
    "type": "textarea",
    "name": "intro",
    "label": "Intro",
    "required": false,
    "settings": {},
    "value": "Short intro copy."
  }
]
```

**Allowed block types:** Most built-in fieldtypes except nested Dynamic items, Gallery, Map, and Relation (to avoid excessive nesting). **ContentEditor** is available for rich body blocks. The admin discovers available types automatically.

**Templates (project HTML):**

- **Shelf (recommended):** `{dynamicitems_html('blocks')}` — renders blocks (markdown and editor output as HTML).
- **Manual loop:** iterate stored items when you need custom markup; each item exposes `{ type }`, `{ value }`, and dot paths on `value` when it is an object.
- **Regions:** `<birkly-region>` can hydrate Dynamic items HTML on the client.

See [Core template shelf](../03-templating/core-template-shelf.md).

**Multilanguage:** Field container IDs and block handlers are scoped per language tab (`field_en_blocks`, etc.); Add Block must target the active tab’s field name.

## Related

- [Content model overview](content-model-overview.md)
- [Site elements pattern](site-elements-pattern.md)
- [Field types](field-types.md)
- [Collections](collections.md)
- [Entry editor](../01-admin/entry-editor.md)
