# Sub-collections & nested content playbook

Use for: comments, reviews, order line items, nested child content, public visitor submissions on parent entries.

## What it is

A **sub-collection** is a named group of child entries on each **parent entry** (e.g. `comments` on each `blog` post).

## Configuration

On parent collection `collection.json`:

```json
{
  "slug": "blog",
  "sub_collections": [{
    "slug": "comments",
    "name": "Comments",
    "entry_method": "public_api",
    "fields": [
      {"name": "author", "type": "text", "label": "Author"},
      {"name": "content", "type": "textarea", "label": "Content"}
    ]
  }]
}
```

### entry_method (runtime values)

| Value | Who adds sub-entries |
|-------|---------------------|
| `admin_only` | Admin only (default) |
| `public_api` | Anyone via public form / API — see `public-forms` |
| `user_submit` | Logged-in site users (User Management plugin) |

## Nested sub-collections

Sub-collections can have their own sub-collections (e.g. `replies` on `comments`). Admin URL: `collection-editor&collection={parent}&parent_sub_collection={existing-sub}`.

## Disk layout

```
content/collections/blog/entries/{entry-id}/sub_entries/comments/{sub-id}.json
```

## AI workflow

1. `read_collection` on parent — check existing `sub_collections`
2. `update_collection` — add/update `sub_collections[]` with slug, fields, entry_method
3. Ensure parent entry exists (`create_entry` on parent collection)
4. `create_sub_entry`:

```json
{
  "type": "create_sub_entry",
  "collection": "blog",
  "entry_id": "hello-world-id",
  "sub_collection": "comments",
  "data": {"author": "Jane", "content": "Great post!"}
}
```

5. `list_sub_entries` / `read_sub_entry` to verify

## Template syntax

**Prefer on detail pages** (URL already resolved the parent):

```html
<meta name="birkly-collection" content="blog">
<h1>{title}</h1>
<div>{'content'}</div>

{for each entry in 'comments' of 'blog'.current ordered by newest}
  <p><strong>{'author'}</strong>: {'content'}</p>
{endfor}
```

Explicit parent slug/id (not title):

```html
{for each entry in 'comments' of 'blog'.'hello-world' ordered by newest}
  <p>{'author'}: {'content'}</p>
{endfor}
```

Nested under a list (less common):

```html
{for each entry in 'blog' with status = published ordered by newest}
  <h2>{title}</h2>
  {for each entry in 'comments'}
    <p>{'author'}: {'content'}</p>
  {endfor}
{endfor}
```

Visitor-submitted comments use `/api/public_sub_entry.php` — not `{entry for}` on the parent. See `public-forms`.

## Common patterns

| Pattern | Parent | Sub-collection | entry_method |
|---------|--------|----------------|--------------|
| Blog comments | blog | comments | public_api |
| Product reviews | products | reviews | public_api |
| Order lines | orders | line-items | admin_only |
| Course modules | courses | modules | admin_only |
| Member posts | groups | posts | user_submit |

## Automation integration

Entry-created triggers include sub-entries: payload has `is_sub_entry`, `parent_collection`, `parent_entry_id`, `sub_collection`. Filter workflow to specific sub-collection in Automation admin.

## Anti-patterns

- Using `public_submit` in JSON — use **`public_api`**
- Creating sub-entries before parent entry exists
- Forgetting parent `entry_id` (not slug) in tool calls — use `read_entry` to get id

## Tools

| Tool | Parameters |
|------|------------|
| `list_sub_entries` | collection, entry_id, sub_collection, limit? |
| `read_sub_entry` | collection, entry_id, sub_collection, sub_entry_id |
| `create_sub_entry` | collection, entry_id, sub_collection, data |
| `update_sub_entry` | + sub_entry_id, data |
| `delete_sub_entry` | + sub_entry_id |

Writes may require approval in built-in chat.
