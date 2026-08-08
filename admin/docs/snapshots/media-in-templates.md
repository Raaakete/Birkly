# Media in templates

Show images and files by outputting the **field value** (filename or URL) into normal HTML — there is no `{ media … }` helper in the public template language.

## Summary

**In short:** Store a filename or media ref on an `image` / `media` / `gallery` field, then print it with `{'featured_image'}` (quoted user field). Put that value in `<img src="…">` or `<a href="…">`. Always guard empty fields with `{if entry has 'featured_image'}`. Build public URLs from your CMS media path (often `/media/…` — confirm with the Media library).

> **Media (M4):** Media pick + `<img>` in template screenshot — `media/03-templating/media-in-templates/screenshots/`.

## Beginner

### Single image field

Collection field e.g. `featured_image` (type `image`). Entry stores a filename such as `hero-bread.jpg`.

```html
{if entry has 'featured_image'}
  <img src="/media/{'featured_image'}" alt="{title}">
{else}
  <img src="/images/placeholder.jpg" alt="">
{endif}
```

If your API already returns a full URL in the field, use:

```html
<img src="{'featured_image'}" alt="{title}">
```

Check one published entry in the public API or preview before hard-coding a prefix.

### Inside a list loop

```html
{for each entry in 'gallery' with status = published ordered by order}
  {if entry has 'image'}
    <figure>
      <img src="{'image'}" alt="{title}">
      <figcaption>{title}</figcaption>
    </figure>
  {endif}
{endfor}
```

### Gallery (array) field

```html
{if entry has 'gallery'}
  {for each item in 'gallery'}
    <img src="{item}" alt="">
  {endfor}
{endif}
```

If items are objects, your build may expose a path such as a URL property — inspect API JSON; do not invent `{ media }` helpers.

### Files / downloads

```html
{if entry has 'document'}
  <a href="{'document'}" download>Download</a>
{endif}
```

### Rich text images

Editor fields already contain `<img>` HTML. Output with `{'content'}` (HTML). Upload those assets via the Media library / editor UI — see [Content editor](../02-content/content-editor.md).

## Advanced Users

### For developers & AI

**Aligned with media playbook**

1. `list_media` / Media library — note exact filename.  
2. Set the image field on `create_entry` / `update_entry`.  
3. Template: `{'featured_image'}` — **not** `{ media featured_image }`.

**Phantoms**

| Wrong | Right |
|-------|-------|
| `{ media 'logo.svg' }` | Static path or a CMS field that holds the file |
| `{ media featured_image width=400 }` | `{'featured_image'}` + CSS / native `width` / your CDN |
| `{ image.url }` without verifying shape | Inspect API; use `{item}` or documented path |

**Empty guards** — required for clean DOM (`src=""` breaks layout and a11y).

**Performance** — prefer appropriately sized uploads; the core language does not resize on the fly.

## Related

- [Variables and output](variables-and-output.md)
- [Conditionals](conditionals.md)
- [Loops and lists](loops-and-lists.md)
- [Field types](../02-content/field-types.md)
- [Media library](../01-admin/media-library.md)
