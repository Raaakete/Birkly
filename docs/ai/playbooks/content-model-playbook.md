# Content model playbook

Use for: collections, entries, schema design, publishing, bulk content, slug rules.

## Concepts

- **Collection** = content type (blog, products, team). Config in `content/collections/{slug}/collection.json` + `schema.json`.
- **Entry** = one item (a blog post, a team member). JSON file under `entries/`.
- **Slug** = URL-safe id. Rules: lowercase, `[a-z0-9-]`, underscores normalized to hyphens.

## Before you write

1. `list_collections` once — note existing slugs and fields.
2. `read_collection` for target slug if updating schema.
3. `read_birkly_docs` `content-model` (this doc) if multi-step.

## Create a new collection

```json
{"type":"create_collection","slug":"blog","name":"Blog","fields":[
  {"name":"title","type":"text","label":"Title","required":true},
  {"name":"content","type":"textarea","label":"Content"},
  {"name":"image","type":"image","label":"Hero image"}
]}
```

Field types: `text`, `textarea`, `number`, `date`, `image`, `gallery`, `media`, `select`, `checkbox`, `url`, `email`, `editor` (rich HTML — see `content-editor` playbook), plugin fieldtypes when active (e.g. `commerce`).

## Add or change schema

Use `update_collection` with full `fields` array (merge carefully — include existing fields you want to keep):

```json
{"type":"update_collection","slug":"blog","fields":[...]}
```

## Create entries

Always include `collection` as slug string. Use **exact field names** from schema:

```json
{"type":"create_entry","collection":"blog","title":"Hello world","content":"First post.","status":"published"}
```

- `status: published` — visible on public API and templates
- `status: draft` — admin only

## Update / delete

```json
{"type":"update_entry","collection":"blog","slug":"hello-world","title":"Updated title"}
{"type":"delete_entry","collection":"blog","slug":"hello-world"}
```

Use `read_entry` or `list_entries` first to get slug/id.

## Entry method (who can create entries)

| Value | Meaning |
|-------|---------|
| `admin_only` | Default — admin panel only |
| `public_api` | External websites / `{entry for}` forms — see `public-forms` playbook |

Sub-collections use the same values on the sub-collection config — see `sub-collections`.

## Modeling content (decision guide)

Ask for each piece of the site:

| Question | If yes → |
|----------|----------|
| Many items of the same shape? | Top-level **collection** + list (and maybe detail) templates |
| Same block on every page? | **Singleton entry** or chrome collection + `<birkly-region>` |
| Rows that belong to one parent entry? | **Sub-collection** (see `sub-collections`) |
| Visitors submit data? | Collection with `entry_method: public_api` (see `public-forms`) |
| Rarely changes, no structure? | Static HTML or one `{from … get entry …}` block |

**Principles:**

1. Define all fields **before** first entry — empty `"fields": []` breaks templates.
2. Match **field type to content shape** (short → `text`, teaser → `textarea`, body → `editor`, photo → `image`, sortable → `number` + `ordered by order`).
3. **One collection per content type** — don't mix unrelated fields in one schema.
4. **Reuse shared chrome** — don't duplicate header/footer HTML on every file.

Deeper patterns and examples: `web-project-patterns` (auto-loaded on site builds).

## Examples (optional — adapt names and fields)

| Kind of site | Typical collections (you choose slugs) |
|--------------|----------------------------------------|
| Blog / news | Repeatable “posts” + optional chrome for hero/nav |
| Portfolio | Repeatable “works” or “projects” with image + description |
| Business / bakery | Services or menu items, team, about copy, contact submissions |
| Landing | Chrome collection + one loop for testimonials or features if needed |

These are illustrations — design fields from what the user actually asked for.

## Workflow order

1. Design schema (fields, slugs)
2. `create_collection` or `update_collection`
3. Seed with `create_entry` (batch in one approval)
4. Wire templates in `project/` — `read_birkly_docs` `templating` or `site-building`
5. Set `published` for live content

## Anti-patterns

- Nested `collection` object instead of slug string in actions
- Re-running `list_collections` every turn
- Forgetting `status: published` for nav/menu content
- Inventing field names not in schema

## Tools

| Action | Tool |
|--------|------|
| List / read schema | `list_collections`, `read_collection` |
| CRUD collection | `create_collection`, `update_collection`, `delete_collection` |
| CRUD entries | `list_entries`, `read_entry`, `create_entry`, `update_entry`, `delete_entry` |
