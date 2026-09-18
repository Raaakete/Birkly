# Core template shelf

Built-in helpers for fieldtypes that need more than a raw stored value (media URLs, embeds, relations, Dynamic items blocks). Available on every site — no plugin required.

## Summary

**In short:** Use `{media_url('hero')}`, `{media_img('hero', 'Alt')}`, `{gallery_html('photos')}`, `{oembed_html('video')}`, `{relation_titles('related')}`, `{relation_links('related')}`, and `{dynamicitems_html('blocks')}` instead of inventing `{ media … }` phantoms or hand-building HTML from opaque JSON.

## Beginner

### When to use shelf helpers

| Field type | Stored value | Shelf helper |
|------------|--------------|--------------|
| `file` (aliases `image`, `media`) | Media library id | `{media_url('hero')}` · `{media_img('hero', 'Alt text')}` |
| `gallery` | Array of media ids | `{gallery_html('photos')}` or loop ids + `{media_img(id)}` |
| `oembed` | Embed URL | `{oembed_html('video')}` |
| `relation` | Array of `{id, title?, collection?}` | `{relation_titles('related')}` · `{relation_links('related')}` |
| `dynamicitems` | Block list with `type` + `value` | `{dynamicitems_html('blocks')}` |

**Markdown** and **editor** body fields still use `{'body'}` or `{'content'}` — markdown is rendered to HTML when the collection schema type is `markdown` (trusted HTML via the page bundle).

### Examples

```html
{if entry has 'hero'}
  {media_img('hero', title)}
{endif}

{gallery_html('product_photos')}

{oembed_html('promo_video')}

<p>Related: {relation_titles('related_posts')}</p>
<ul>{relation_links('related_posts')}</ul>

<section>{dynamicitems_html('page_blocks')}</section>
```

Loop a gallery field manually when you need custom markup:

```html
{for each id in 'gallery'}
  {media_img(id)}
{endfor}
```

## Advanced Users

### Shelf model

Fieldtypes own admin UI, validation, storage, and optional `register_template_function` helpers. Core templating resolves stored JSON and exposes these **core shelf** functions from `core/fieldtype_template_shelf.php` — it does not embed gallery grids, oEmbed players, or relation cards unless you call the shelf helper (or a plugin registers its own).

Plugins load **before** core; core wins on name conflicts. Plugin helpers use prefixes (`commerce_`, `event_`) — see [Plugin language extensions](plugin-language-extensions.md).

### Phantoms

| Wrong | Right |
|-------|-------|
| `{ media featured_image }` | `{media_url('featured_image')}` or `{media_img('featured_image', '…')}` |
| `{ image.url }` without verifying API shape | Shelf helpers or inspect public API JSON |
| String-replace hacks for embed HTML | `{oembed_html('field')}` |

### MCP / AI

`read_entry` may include `_mcp_field_hints` (per-field `type` + template hint). Do not send `_mcp_field_hints` back on `update_entry`. Prefer `file` / `gallery` for CMS images — see [MCP connections](../01-admin/mcp-connections.md#content-tools-for-connected-agents).

## Related

- [Media in templates](media-in-templates.md)
- [Field types](../02-content/field-types.md)
- [Dynamic items field](../02-content/dynamic-items-field.md)
- [Loops and lists](loops-and-lists.md)
- [Plugin language extensions](plugin-language-extensions.md)
- [Cheat sheet](cheat-sheet.md)
