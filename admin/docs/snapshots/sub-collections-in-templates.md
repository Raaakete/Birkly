# Sub-collections in templates

Loop child entries (comments, variants, line items) that belong to a parent entry using `of 'parent'.current` or `of 'parent'.'slug'`.

## Summary

**In short:** Sub-collections are configured on the parent collection in admin. On a detail page, list children with `{for each entry in 'comments' of 'blog'.current ordered by newest}`. Do not invent `sub_entries` helpers. Content model guide: [Sub-Collections](../02-content/sub-collections.md).

> **Video (follow-along):** optional — comments loop on detail (`of 'blog'.current`)

## Beginner

### Comments on a blog post

Detail page already resolves the post from the URL:

```html
<h1>{title}</h1>
<div>{'content'}</div>

<section class="comments">
  <h2>Comments</h2>
  {for each entry in 'comments' of 'blog'.current ordered by newest}
    <article>
      <strong>{'author'}</strong>
      <p>{'content'}</p>
    </article>
  {endfor}
</section>
```

### Explicit parent slug

```html
{for each entry in 'comments' of 'blog'.'hello-world' ordered by newest}
  …
{endfor}
```

Use **slug or id**, not the human title.

### Nested under a list (less common)

```html
{for each entry in 'blog' with status = published ordered by newest}
  <h2>{title}</h2>
  {for each entry in 'comments'}
    <p>{'author'}: {'content'}</p>
  {endfor}
{endfor}
```

Prefer `.current` on detail pages for clarity and correct parent scope.

### Product variants

```html
{for each entry in 'variants' of 'products'.current ordered by order}
  <h3>{title}</h3>
{endfor}
```

See [Storefront patterns](storefront-patterns.md).

### Public comment forms

Visitor-submitted sub-entries use `/api/public_sub_entry.php` (not `{entry for}` on the parent). See [Public forms templating](public-forms-templating.md) and the integration [Public forms](../04-integration/public-forms.md) page.

## Advanced Users

### For developers & AI

**Grammar**

```text
{for each entry in '<sub>' of '<parent>'.<slug|id|current|newest> [with …] [ordered by …] [limit N]}
```

**Newest parent**

```html
{for newest entry in 'comments' of 'blog'.newest}
{for each entry in 'comments' of 'blog'.newest ordered by newest}
```

**Model checklist**

1. Sub-collection defined on parent (`read_collection`).  
2. Field slugs match template `{'author'}` etc.  
3. `entry_method` / `auto_publish` set if public submission.  
4. Detail page has URL context before `.current` is meaningful.

**Phantoms**

| Wrong | Right |
|-------|-------|
| `{for each variant in sub_entries variants}` | `of 'products'.current` |
| Parent title as identifier | slug or id |

## Related

- [Detail pages & URL context](detail-pages-and-url-context.md)
- [Loops and lists](loops-and-lists.md)
- [Sub-Collections (content)](../02-content/sub-collections.md)
- [Commerce functions](commerce-functions.md)
- [Public forms templating](public-forms-templating.md)
