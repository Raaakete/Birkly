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
| `markdown` | Markdown source | `{'body'}` | Rendered to HTML on SSR/client when schema type is `markdown` |
| `contenteditor` | Rich HTML | `{'content'}` | Same as `editor` / `content-editor` aliases |
| `email` | Email addresses | `{'email'}` | Validated on public submit |
| `url` | Links | `{'website'}` | |
| `slug` | URL slug | `{slug}` | Often auto-generated on create_entry |

**Common mistake:** using `richtext` or `wysiwyg` — use **`editor`**.

## Media

**Prefer native media fieldtypes over URL/text for assets stored in Birkly.**

| type | Use for | Entry value | Template (storefront) |
|------|---------|-------------|------------------------|
| `file` / `image` / `media` | Hero, download, single asset | **Media library id** string | `{media_url('hero')}` · `{media_img('hero', 'Alt')}` — see shelf below |
| `gallery` | Multiple images | Array of media ids | `{gallery_html('photos')}` or `{for each id in 'photos'}{media_img(id)}{endfor}` |

**Anti-pattern:** `text` or `url` fields holding `/media/...` paths or external image URLs when the asset should live in the media library. Those bypass uploads, folders, responsive variants, and admin pickers.

**When `url` is correct:** external links (partner site, social profile) — not CMS-managed images.

Workflow: `list_media` / `upload_media` → reference filename in `create_entry` → template displays field. See **`media`** playbook.

## Numbers & dates

| type | Use for |
|------|---------|
| `number` | Price, quantity, rating |
| `date` | Publish date, event date |
| `datetime` | Scheduled publish, event start |
| `checkbox` | On/off stored as `0`/`1` (filters use `'1'`) |
| `toggle` | Boolean-style on/off |

**Not a type:** `boolean` — use `checkbox` or `toggle`.

## Selection & structure

| type | Use for |
|------|---------|
| `select` | Single choice from options — include `options: [{value, label}]` |
| `multiselect` | Multiple tags/categories |
| `tags` | Tag list |
| `color` | Hex color picker |
| `json` | Structured data (advanced) |

## Core template shelf (storefront)

Use **parenthesis** shelf calls (documented in human help). Legacy space form `{media_img 'hero'}` still works.

| Helper | Field types | Example |
|--------|-------------|---------|
| `media_url` / `media_img` | `file`, `image`, `media` | `{media_img('hero', title)}` |
| `gallery_html` | `gallery` | `{gallery_html('photos')}` |
| `map_html` | `map` | `{map_html('location')}` — OSM embed; dot paths `{'location'.'lat'}` are fallback text only |
| `range_html` | `range` | `{range_html('band', 0, 100)}` — scalar stored value + min/max args or field config |
| `oembed_html` | `oembed` | `{oembed_html('video')}` |
| `relation_titles` / `relation_links` | `relation` | `{relation_titles('related')}` · `<ul>{relation_links('related')}</ul>` |
| `dynamicitems_html` | `dynamicitems` | `{dynamicitems_html('blocks')}` |

Full table: https://docs.birkly.io/03-templating/core-template-shelf.html

**Anti-pattern:** `{'hero_image'}` inside `<img src>` when the field stores a **media id** — use `{media_img('hero_image', '…')}`.

## Structured fieldtypes (storefront)

| type | Stored shape (public API) | Template access |
|------|---------------------------|-----------------|
| `map` | `{ lat, lng, label?, address? }` | Prefer `{map_html('field')}`; optional `{'field'.'lat'}` for text |
| `range` | Number (slider value); min/max in **field config** | `{range_html('field', min, max)}` — not `{min,max}` object unless you modeled it that way |
| `markdown` | Markdown source | `{'body'}` → HTML when schema type is `markdown` |
| `contenteditor` / `editor` | HTML string | `{'content'}` trusted HTML |
| `password` | Hash at rest | Storefront `{'secret'}` → **`••••••`** only; MCP/API `[redacted]`; blank on edit keeps hash |
| `code` | Plain string (or JSON mode) | `{'snippet'}` — `$` / `${name}` preserved |
| `relation` | `[{id, title?, collection?, slug?}]` | `{relation_titles('related')}` · `{relation_links('related')}` |
| `oembed` | Watch/embed URL | `{oembed_html('video')}` |
| `dynamicitems` | `[{ type, name?, label?, value }]` | `{dynamicitems_html('blocks')}` — **not** a collection loop |
| `checkbox` / `toggle` | `0` / `1` | `{'in_stock'}` → Yes/No or On/Off pills on storefront (display only) |

### Array fields (`tags`, `multiselect`, `gallery`)

Loop the **field name** (quoted reads from current `entry`):

```html
{for each tag in 'tags_list'}<span class="chip">{tag}</span>{endfor}
{for each cat in multi_categories}<span>{cat}</span>{endfor}
```

- Prefer loop variables like `tag`, `cat`, `id` — or `{entry}` / `{.}` for scalar lists.
- Direct `{'tags'}` outputs a comma-separated list when the field type is `tags` / `multiselect` (schema-driven via page bundle `_field_types`).

**Anti-pattern:** Hardcoding `/media/…` ids on the page while claiming the gallery field is tested — loop the field or register a fieldtype template function.

### Shelf model (vision)

Fieldtypes are self-contained: admin UI, validation, storage, and **optional** template-shelf helpers (`register_template_function`) live in the fieldtype folder. Core templating only resolves stored JSON — it does not embed gallery maps, oEmbed players, or relation cards unless the fieldtype (or plugin) places them on the shelf.

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

- `fieldtype-qa` — end-to-end QA collection, seed data, deploy checklist
- `content-model` — collections, publishing, slugs
- `templating` — loops, phantoms, shelf vs `{'field'}`
- `public-forms` — `{entry for}` and form field mapping
- `sub-collections` — nested schemas
- `content-editor` — Editor field HTML tables/grids
- Human docs: https://docs.birkly.io/02-content/field-types.html
