# Media in templates

Show images and files from **file** / **gallery** fields using core **template shelf** helpers or loops — not `{ media … }` phantoms.

## Summary

**In short:** Store a **media library id** on a `file` field (aliases `image`, `media`) or an array of ids on `gallery`. In templates use `{media_url('hero')}`, `{media_img('hero', 'Alt')}`, or `{gallery_html('photos')}`. For custom markup, loop ids and call `{media_img(id)}`. Guard empty fields with `{if entry has 'hero'}`. Full shelf reference: [Core template shelf](core-template-shelf.md).

> **Media (P2):** screenshot deferred — media pick + shelf output in template.

## Beginner

### Single image (`file` / `image` / `media`)

Collection field e.g. `featured_image` (type `file`). Entry stores a media library **id** (not a bare filename path).

```html
{if entry has 'featured_image'}
  {media_img('featured_image', title)}
{else}
  <img src="/images/placeholder.jpg" alt="">
{endif}
```

URL only:

```html
<a href="{media_url('brochure')}">Download</a>
```

### Inside a list loop

```html
{for each entry in 'gallery' with status = published ordered by order}
  {if entry has 'image'}
    <figure>
      {media_img('image', title)}
      <figcaption>{title}</figcaption>
    </figure>
  {endif}
{endfor}
```

### Gallery (array) field

Built-in grid:

```html
{if entry has 'gallery'}
  {gallery_html('gallery')}
{endif}
```

Custom layout — loop scalar ids:

```html
{if entry has 'gallery'}
  {for each id in 'gallery'}
    {media_img(id)}
  {endfor}
{endif}
```

Inside the loop you can use `{entry}` or `{.}` for the current id.

### Files / downloads

```html
{if entry has 'document'}
  <a href="{media_url('document')}" download>Download</a>
{endif}
```

### Rich text images

Editor fields already contain `<img>` HTML. Output with `{'content'}` (HTML). Upload those assets via the Media library / editor UI — see [Content editor](../02-content/content-editor.md).

## Advanced Users

### For developers & AI

**Workflow**

1. `list_media` / `upload_media` — note media id.  
2. Set the field on `create_entry` / `update_entry`.  
3. Template: `{media_url('hero')}` · `{media_img('hero', 'Alt')}` · `{gallery_html('photos')}`.

**Phantoms**

| Wrong | Right |
|-------|-------|
| `{ media 'logo.svg' }` | Static path or `{media_url('logo')}` |
| `{ media featured_image width=400 }` | `{media_img('featured_image', '…')}` + CSS |
| `{'featured_image'}` alone in `<img src>` when value is an id | `{media_img('featured_image', '…')}` |
| `{ image.url }` without verifying shape | Shelf helpers or inspect API JSON |

**Empty guards** — required for clean DOM (`src=""` breaks layout and a11y).

**Performance** — prefer appropriately sized uploads; the core language does not resize on the fly.

**External URLs** — use a `url` field for partner links, not `file` / `gallery`.

## Related

- [Core template shelf](core-template-shelf.md)
- [Variables and output](variables-and-output.md)
- [Conditionals](conditionals.md)
- [Loops and lists](loops-and-lists.md)
- [Field types](../02-content/field-types.md)
- [Media library](../01-admin/media-library.md)
