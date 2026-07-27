# Sub-collections & sub-entries

## What is a sub-collection?

A **sub-collection** is a named group of child entries attached to each parent entry.
For example: `comments` on `blog`, `line-items` on `orders`, or `reviews` on `products`.

Sub-collections are configured on the **parent collection** and stored on each individual parent entry.

---

## Data model

Sub-collections are defined in `content/collections/{parent-slug}/collection.json`:

```json
{
  "slug": "blog",
  "name": "Blog",
  "sub_collections": [
    {
      "slug": "comments",
      "name": "Comments",
      "entry_method": "public_api",
      "fields": [
        {"name": "author", "type": "text",     "label": "Author"},
        {"name": "content","type": "textarea", "label": "Content"}
      ]
    }
  ]
}
```

### `entry_method` values

| Value | Who can add sub-entries |
|-------|------------------------|
| `admin_only` | Admin users only (default) |
| `public_api` | Anyone via public form / public API |
| `user_submit` | Logged-in site users (User Management plugin) |

---

## Disk layout

```
content/collections/
  {parent-slug}/
    collection.json          ← contains sub_collections[] config
    schema.json              ← parent entry schema
    entries/
      {entry-id}.json        ← parent entry data
      {entry-id}/
        sub_entries/
          {sub-slug}/
            {sub-entry-id}.json   ← sub-entry data
            .order.json           ← optional drag-drop order
```

Example — `comments` on a `blog` entry `hello-world`:
```
content/collections/blog/entries/hello-world/sub_entries/comments/comment-abc123.json
```

---

## Admin URL params

| Action | URL |
|--------|-----|
| List parent entries | `/admin/?page=entries&collection={parent-slug}` |
| View entry (sub-entries shown below) | `/admin/?page=entry-detail&collection={parent-slug}&entry={entry-id}` |
| Add new sub-entry | `/admin/?page=entry-editor&collection={parent-slug}&entry={entry-id}&sub_collection={sub-slug}` |
| Edit sub-entry | `/admin/?page=entry-editor&collection={parent-slug}&entry={entry-id}&sub_collection={sub-slug}&sub_entry={sub-entry-id}` |
| View sub-entry detail | `/admin/?page=entry-detail&collection={parent-slug}&entry={entry-id}&sub_collection={sub-slug}&sub_entry={sub-entry-id}` |
| Edit sub-collection schema | `/admin/?page=collection-editor&collection={parent-slug}&sub_collection={sub-slug}` |
| Add nested sub-collection | `/admin/?page=collection-editor&collection={parent-slug}&parent_sub_collection={existing-sub-slug}` |

---

## Admin workflow — add comments to a blog post

**Goal:** Add a `comments` sub-collection to `blog`, then create a comment on "Hello World"

1. Open the `blog` collection editor: `/admin/?page=collection-editor&collection=blog`
2. Click "Add sub-collection" → set slug `comments`, name "Comments", fields: `author` (text), `content` (textarea)
3. Save → stored in `blog/collection.json`
4. Navigate to a blog entry: `/admin/?page=entry-detail&collection=blog&entry={hello-world-id}`
5. Scroll to the Comments panel → click "Add comment"
   → URL: `/admin/?page=entry-editor&collection=blog&entry={hello-world-id}&sub_collection=comments`
6. Fill in Author and Content → Save
7. Return to entry-detail to see the comment list
8. Edit a comment: `...&sub_entry={comment-id}`
9. Delete: use the delete button on the entry-detail panel

---

## MCP tools

| Tool | Category | Parameters |
|------|----------|------------|
| `list_sub_entries` | read | `collection`, `entry_id`, `sub_collection`, optional `limit` |
| `read_sub_entry` | read | `collection`, `entry_id`, `sub_collection`, `sub_entry_id` |
| `create_sub_entry` | write (approval-gated) | `collection`, `entry_id`, `sub_collection`, `data` |
| `update_sub_entry` | write (approval-gated) | `collection`, `entry_id`, `sub_collection`, `sub_entry_id`, `data` |
| `delete_sub_entry` | write (approval-gated) | `collection`, `entry_id`, `sub_collection`, `sub_entry_id` |

Write tools queue an approval (visible in Admin → AI → Approvals) unless
`require_approval` is disabled on the connection, or the source is an inbound MCP client
(which handles approval UX on its own side).

---

## Client-side template syntax

```html
{for each entry in 'blog'}
  <h2>{title}</h2>
  {for each entry in 'comments'}
    <p>{'author'}: {'content'}</p>
  {endfor}
{endfor}

{{! Scoped to one parent entry: }}
{for each entry in 'comments' of 'blog'.'hello-world'}
  {'author'}: {'content'}
{endfor}
```

---

## PHP core functions

```php
getSubEntries($parentCollection, $parentEntryId, $subCollection, $filters, $options)
getSubEntry($parentCollection, $parentEntryId, $subCollection, $subEntryId)
createSubEntry($parentCollection, $parentEntryId, $subCollection, $data)
updateSubEntry($parentCollection, $parentEntryId, $subCollection, $subEntryId, $data)
deleteSubEntry($parentCollection, $parentEntryId, $subCollection, $subEntryId)
getSubCollections($collectionSlug)
getSubCollectionConfig($collectionSlug, $subCollectionSlug)
```

All functions live in `core/sub_entries.php`.
