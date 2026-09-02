# Conditionals

Conditionals show or hide parts of the page: existence of an entry, presence of a field, comparisons, and `or` / `else` branches.

## Summary

**In short:** Wrap optional UI in `{if}…{endif}`. Check that an entry exists with `{if 'site_elements'.'hero' exist}`. Check a field with `{if entry has 'excerpt'}`. Compare values with `=`, `!=`, `<`, `>`, `<=`, `>=`. Use `{else}` for fallbacks (placeholder image, empty-state copy).

> **Media (P2):** screenshot deferred — empty-field branch vs placeholder.

## Beginner

### Field presence

```html
{if entry has 'excerpt'}
  <p class="excerpt">{'excerpt'}</p>
{endif}
```

Multiple fields:

```html
{if entry has 'price' and 'value' and 'size'}
  …
{endif}
```

### Existence of a named entry

```html
{if 'site_elements'.'hero' exist}
  {from 'site_elements' get entry 'hero'}
    <section class="hero">{'content'}</section>
  {endget}
{endif}
```

Both collection and entry are **quoted**. Prefer this over the older unquoted `site_elements.hero` form.

### Fallback image

```html
{if entry has 'featured_image'}
  <img src="{'featured_image'}" alt="{title}">
{else}
  <img src="/images/placeholder.jpg" alt="">
{endif}
```

### Comparisons

```html
{if entry has 'price' <= 15}
  <span class="badge">Deal</span>
{endif}

{if entry 'price' <= 15}
  …
{endif}

{if current.language = 'de'}
  <p>Willkommen!</p>
{else}
  <p>Welcome!</p>
{endif}
```

Prefer single `=` for equality.

### if … or … else

```html
{if entry has 'price' <= 15 or entry has 'discount'}
  <p>On sale</p>
{else}
  <p>Standard price</p>
{endif}
```

### Inside loops

```html
{for each entry in 'blog' with status = published ordered by newest}
  <h2>{title}</h2>
  {if entry has 'excerpt'}
    <p>{'excerpt'}</p>
  {endif}
{endfor}
```

## Advanced Users

### For developers & AI

**Operators**

| Operator | Meaning |
|----------|---------|
| `=` / `==` | Equals (prefer `=`) |
| `!=` | Not equals |
| `<` `>` | Less / greater |
| `<=` / `=<` | Less or equal |
| `>=` / `=>` | Greater or equal |

**Existence vs has**

| Form | Checks |
|------|--------|
| `{if 'coll'.'entry' exist}` | Named entry present in data |
| `{if entry has 'field'}` | Current entry has non-empty field |

**Related patterns**

- Missing `{from … get entry}` blocks are removed entirely when the entry is missing (no need for existence check if the whole block can vanish).  
- Language: `{if current.language = 'de'}` — [Multilingual](multilingual-templating.md).  
- Deeper reference: [From…get / existence](from-get-and-existence.md).

**Avoid**

- Invented words like `{if 'blog' has entries}` unless verified in your engine build — prefer looping + empty UI, or existence of a known entry.  
- Jinja `{% if %}`.

## Related

- [From…get / existence](from-get-and-existence.md)
- [Variables and output](variables-and-output.md)
- [Loops and lists](loops-and-lists.md)
- [Media in templates](media-in-templates.md)
- [Canonical syntax](canonical-syntax.md)
