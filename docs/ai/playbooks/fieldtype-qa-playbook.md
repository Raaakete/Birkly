# Fieldtype storefront QA playbook

Use when validating **all built-in fieldtypes** on a live or test CMS: MCP seeding, `project/` templates, SSR, and shelf helpers.

**Reference fixture (copy patterns, do not assume slugs exist):** `Birkly-Marketplace` → `internal/cms/fixtures/fieldtype-qa/` (collection schema + `fieldtype-qa-detail.html`).

Human shelf SSOT: [Core template shelf](https://docs.birkly.com/03-templating/core-template-shelf.html).

## When to use

- Regression after a **core CMS deploy** (not Hub plugin-only updates)
- Verifying `{media_img('field')}` / `{dynamicitems_html('blocks')}` / password masking
- Training external agents on correct template + data shape

## Preconditions

1. **`get_system_context`** — note CMS version / deploy time; stale hosts look like “template bugs”
2. **Core on `main`** with template shelf + presentation layer (#775+)
3. MCP connection with project write + collections write as needed

## QA collection checklist

| Step | Action |
|------|--------|
| Schema | One collection with **every** type you need (`checkbox` not `boolean`; `file` not bare `url` for library images) |
| Media | `upload_media` first; store **ids** on `file` / `gallery` fields |
| Relations | Target collection + published entries; relation value `{id, title, collection}` |
| **Dynamic items** | Seed `content_blocks` (or your slug) — **empty array = empty section**, not a render failure |
| Templates | Detail page: `?collection=&slug=` + `{media_img('hero')}` shelf syntax (parentheses) |
| Cache | `cms_admin_action` → `project_site.invalidate_ssr_cache` after template/entry changes |

## Template patterns (detail page)

```html
{media_img('hero_image', title)}
{gallery_html('photo_gallery')}
{map_html('location')}
{range_html('price_range', 10, 99)}
{oembed_html('promo_video')}
<p>Related: {relation_titles('related_entries')}</p>
<ul>{relation_links('related_entries')}</ul>
{dynamicitems_html('content_blocks')}
<p class="password-out">{'api_secret'}</p>
```

- **Scalars / text:** `{'teaser'}`, `{'price'}`
- **Markdown / editor:** `{'body_markdown'}`, `{'body_rich'}` (schema type drives HTML)
- **Tags / multiselect:** loop `{for each tag in 'tags_list'}` or comma via `{'tags_list'}`
- **Do not** put a media id in `<img src="{'hero'}">` — use **`{media_img('hero')}`**

## Dynamic items seed example

```json
"content_blocks": [
  {"type": "contenteditor", "name": "block_editor", "label": "Editor block", "value": "<p>Hello <strong>world</strong></p>"},
  {"type": "file", "name": "block_media", "label": "Media block", "value": "MEDIA_ID_FROM_upload_media"},
  {"type": "datetime", "name": "block_when", "label": "When", "value": "2026-09-18T16:30:00"},
  {"type": "checkbox", "name": "block_flag", "label": "Flag", "value": "1"},
  {"type": "map", "name": "block_map", "label": "Map block", "value": {"lat": "48.137", "lng": "11.575", "label": "City"}}
]
```

Use `{dynamicitems_html('content_blocks')}` — **not** `{for each entry in 'content_blocks'}` (blocks are not a collection).

## Expected storefront behavior (not admin UI)

| Field | Storefront expectation |
|-------|-------------------------|
| `file` / `gallery` | Images via shelf (SSR may render before client JS) |
| `map` | OSM embed via `{map_html('field')}` |
| `range` | `<meter>` via `{range_html('field', min, max)}` — not a draggable slider |
| `checkbox` / `toggle` | Status pills (Yes/No, On/Off) — not switch widgets |
| `password` | **`••••••`** in templates — never the raw secret |
| `oembed` | iframe via `{oembed_html('field')}` |
| `code` | Plain text; `${name}` preserved |

## MCP / API

- `read_entry` may include `_mcp_field_hints` (type + template hint); do not write hints back on `update_entry`
- Password on read: `[redacted]` in API; storefront uses bullets via presentation layer
- After `write_project_file` on templated pages, invalidate SSR cache

## Common false alarms

| Symptom | Likely cause |
|---------|----------------|
| Raw media ids / YouTube URL | Old CMS build or shelf syntax not evaluated |
| Escaped `<span class="birkly-bool">` text | Fixed in core — redeploy |
| Empty dynamic items | No blocks in entry JSON |
| Password plaintext | Host not on presentation-layer build; redeploy core |

## Related

- `fieldtypes` — schema types + shelf table
- `templating` — syntax, phantoms
- `web-project-patterns` — list/detail wiring
- `mcp-capabilities` — tools and deploy notes
