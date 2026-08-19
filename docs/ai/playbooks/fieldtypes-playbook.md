# Field types playbook

Use when defining collection schemas, fixing validation errors, or choosing the right `type` in `create_collection` / `update_collection`.

**Always `read_collection` after schema changes** — templates and forms must use exact field **names** (slugs), not labels.

## Schema JSON shape

```json
{
  "fields": [
    {
      "name": "teaser",
      "label": "Teaser",
      "type": "textarea",
      "required": false
    }
  ]
}
```

- **`name`** — slug used in templates (`{'teaser'}`), forms (`name="{teaser}"`), and API payloads
- **`label`** — human label in admin UI
- **`type`** — one of the types below (runtime string, case-sensitive)
- **`required`** — boolean; public forms reject empty required fields

## Text & content

| type | Use for | Template output | Notes |
|------|---------|-----------------|-------|
| `text` | Title, name, short line | `{name}` or `{'name'}` | Single line |
| `textarea` | Plain multi-line | `{'body'}` | No HTML formatting |
| `editor` | Rich HTML articles | `{'content'}` | Renders HTML; see `content-editor` playbook |
| `markdown` | Markdown source | `{'body'}` | Rendered client-side if configured |
| `email` | Email addresses | `{'email'}` | Validated on public submit |
| `url` | Links | `{'website'}` | |
| `slug` | URL slug | `{slug}` | Often auto-generated on create_entry |

**Common mistake:** using `richtext` or `wysiwyg` — use **`editor`**.

## Media

**Prefer native media fieldtypes over URL/text for assets stored in Birkly.**

| type | Use for | Entry value | Template |
|------|---------|-------------|----------|
| `image` | Single featured/hero image | Media filename / ref | Fieldtype renders `<img>` in admin; in templates use field output |
| `gallery` | Multiple images (portfolio, product photos) | Array of media refs | Loop or gallery fieldtype output — **not** several `url` fields |
| `media` | Generic file (PDF, audio, download) | File metadata object | Upload via media library; **not** a bare URL string |

**Anti-pattern:** `text` or `url` fields holding `/media/...` paths or external image URLs when the asset should live in the media library. Those bypass uploads, folders, responsive variants, and admin pickers.

**When `url` is correct:** external links (partner site, social profile) — not CMS-managed images.

Workflow: `list_media` / `upload_media` → reference filename in `create_entry` → template displays field. See **`media`** playbook.

## Numbers & dates

| type | Use for |
|------|---------|
| `number` | Price, quantity, rating |
| `date` | Publish date, event date |
| `datetime` | Scheduled publish, event start |
| `boolean` | Featured flag, visible toggle |

## Selection & structure

| type | Use for |
|------|---------|
| `select` | Single choice from options — include `options: [{value, label}]` |
| `multiselect` | Multiple tags/categories |
| `tags` | Tag list |
| `color` | Hex color picker |
| `json` | Structured data (advanced) |

## Example: blog post collection

```json
{
  "name": "Blog",
  "slug": "blog",
  "fields": [
    {"name": "title", "label": "Title", "type": "text", "required": true},
    {"name": "slug", "label": "Slug", "type": "slug", "required": false},
    {"name": "excerpt", "label": "Excerpt", "type": "textarea", "required": false},
    {"name": "content", "label": "Content", "type": "editor", "required": true},
    {"name": "featured_image", "label": "Featured image", "type": "image", "required": false},
    {"name": "published_at", "label": "Published", "type": "date", "required": false}
  ]
}
```

## Example: contact form collection (public_api)

```json
{
  "name": "Contact",
  "slug": "contact",
  "entry_method": "public_api",
  "fields": [
    {"name": "name", "label": "Name", "type": "text", "required": true},
    {"name": "email", "label": "Email", "type": "email", "required": true},
    {"name": "message", "label": "Message", "type": "textarea", "required": true}
  ]
}
```

Template form field names must be `{name}`, `{email}`, `{message}` — not `{title}`.

## Example: comments sub-collection

On parent `blog` via `update_collection` → `sub_collections`:

```json
{
  "slug": "comments",
  "name": "Comments",
  "entry_method": "public_api",
  "auto_publish": true,
  "fields": [
    {"name": "author", "label": "Author", "type": "text", "required": true},
    {"name": "content", "label": "Comment", "type": "textarea", "required": true}
  ]
}
```

## Composing fieldtypes

| Pattern | Approach |
|---------|----------|
| Repeating rows (specs, FAQs, team members) | `sub_collections` with typed fields — not parallel `text` fields |
| Tags / categories | `tags`, `multiselect`, or `select` with `options[]` |
| Sellable product | `commerce` fieldtype on entry or variant sub-entry — see `commerce` |
| Marketing copy + assets | `editor`/`textarea` + `image` or `gallery` — never `url` for library images |
| Arbitrary JSON | `json` only when no built-in fieldtype fits |

After schema changes: `read_collection` → match template `name="{slug}"` and `{'slug'}` exactly.

## Related

- `content-model` — collections, publishing, slugs
- `public-forms` — `{entry for}` and form field mapping
- `sub-collections` — nested schemas
- `content-editor` — Editor field HTML tables/grids
- Human docs: https://docs.birkly.com/02-content/field-types.html (offline: `admin/docs/snapshots/field-types.md`)
