# Variables and output

Variables are placeholders that become real values from the current entry or context. Write `{title}` for system fields and `{'field_name'}` for fields you defined on the collection.

## Summary

**In short:** `{title}` and `{slug}` need no quotes. Your own fields — excerpt, price, body, image — use **quoted** form: `{'excerpt'}`, `{'price'}`, `{'content'}`. Dates use `{created_at format "Y-m-d"}` (see [Dates and formatting](dates-and-formatting.md)). Empty fields: wrap with `{if entry has '…'}` so you don’t print blanks.

## Beginner

### Basic pattern

```html
<article>
  <h1>{title}</h1>
  <time>{created_at format "F j, Y"}</time>
  <div class="body">{'content'}</div>
</article>
```

- On a **detail page**, these refer to the entry from the URL (`?collection=&slug=`).  
- **Inside a loop**, they refer to the current loop entry.

### Inside a list loop

```html
{for each entry in 'blog' with status = published ordered by newest}
  <h2><a href="post.html?collection=blog&slug={slug}">{title}</a></h2>
  {if entry has 'excerpt'}
    <p>{'excerpt'}</p>
  {endif}
{endfor}
```

### Text vs rich HTML

| Field type | Typical output |
|------------|----------------|
| Text, Textarea | Escaped plain text |
| Editor (rich text) | HTML (headings, links, lists) |
| Number, URL, Email | String value |
| Image / media | Filename or URL string — use in `<img src="…">` (see [Media](media-in-templates.md)) |
| Checkbox | Truthy/empty for `{if entry has '…'}` |

### System vs user fields

| Kind | Examples | Syntax |
|------|----------|--------|
| System | `title`, `slug`, `id`, `status`, `created_at`, `updated_at`, `published_at` | `{title}`, `{created_at}` |
| User-defined | `excerpt`, `content`, `price`, `hero` | `{'excerpt'}`, `{'price'}` |

### Query and language

```html
{query.'slug'}
{current.language}
{current_year}
```

See [Multilingual templating](multilingual-templating.md).

## Advanced Users

### For developers & AI

**Resolution order**

1. Current loop / `{from … get entry}` block scope  
2. URL-resolved current entry (detail pages)  
3. Page / system data (`site_elements`, `query.*`, `current.language`)

**Quoted fields are required** for user-defined API names. Bare `{excerpt}` is unreliable; prefer `{'excerpt'}`.

**No speculative filters**

Not implemented: `truncate`, `strip_tags`, `default=`, `{ media … }`. Use CMS fields, conditionals, or CSS.

**Array fields**

```html
{if entry has 'tags'}
  {for each item in 'tags'}
    <span>{item}</span>
  {endfor}
{endif}
```

Inline picks: `{from 'tags' get item.1}`, `item.newest`, `item.oldest`, `item.'cms'`.

**Nested paths** (when API shape provides them): `{'content'.0.'value'}` inside site_elements blocks.

## Related

- [Templating overview](templating-overview.md)
- [Dates and formatting](dates-and-formatting.md)
- [Conditionals](conditionals.md)
- [Media in templates](media-in-templates.md)
- [Detail pages & URL context](detail-pages-and-url-context.md)
- [Field types](../02-content/field-types.md)
