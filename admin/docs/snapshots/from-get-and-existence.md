# From…get and existence checks

Load one named entry with `{from … get …}`, and guard optional chrome with `{if 'collection'.'entry' exist}`.

## Summary

**In short:** Use `{from 'site_elements' get entry 'header'}…{endget}` for a single known entry (header, footer, one-off page block). If that entry is missing, the whole block disappears. Use `{if 'site_elements'.'hero' exist}` when you need an explicit branch. This is **not** the same as `{entry for}` (forms) or the phantom `{entry 'x'.'y'}…{endentry}`.

> **Media (P2):** screenshot deferred — before/after missing `{from … get …}` block.

## Beginner

### Block form (multiple fields)

```html
{from 'site_elements' get entry 'blog_headline'}
  <h1>{'content'.0.'value'}</h1>
  <p class="page-subtitle">{'content'.1.'value'}</p>
{endget}
```

- First quoted name: **collection**.  
- After `entry`: **slug or id** (not the title).  
- Inside the block, fields refer to that entry.

### Inline form (one value)

```html
{from 'site_elements'.'blog_headline' get 'content'.0.'value'}
```

### Existence check

```html
{if 'site_elements'.'hero' exist}
  {from 'site_elements' get entry 'hero'}
    <section class="hero">{'content'}</section>
  {endget}
{else}
  <!-- optional fallback -->
{endif}
```

### Array field picks on the current entry

```html
{from 'tags' get item.1}
{from 'tags' get item.newest}
{from 'tags' get item.oldest}
{from 'tags' get item.'cms'}
```

## Advanced Users

### For developers & AI

**Canonical rules (TEMPLATE_LANGUAGE_SPEC)**

- Block close tag is `{endget}` (not `{endentry}`).  
- Phantom rejected by validator: `{entry 'blog'.'slug'}…{endentry}`.  
- Forms use `{entry for 'contact'}…{endentry}` — different directive.  
- Missing entry → entire `{from}…{endget}` block removed.  
- `site_elements` is always prefetched on `processTemplates`; other collections add `get_entry` / `list_entries` via the page bundle.

**Newest shorthand**

```html
{from 'blog' get newest entry.title}
```

**Vs URL detail pages**

| Need | Use |
|------|-----|
| Entry from link / query | [Detail pages](detail-pages-and-url-context.md) — `{title}` at top level |
| Named chrome / singleton | `{from 'coll' get entry 'slug'}` |
| Visitor form | `{entry for 'coll'}` |

## Related

- [Conditionals](conditionals.md)
- [Variables and output](variables-and-output.md)
- [Site elements pattern](../02-content/site-elements-pattern.md)
- [Web components](web-components.md)
- [Canonical syntax](canonical-syntax.md)
