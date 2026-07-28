# Collection groups playbook

Use when organizing the admin **Content** sidebar — grouping collections, reordering, nesting groups.

## What collection groups are

Admin UI only (not public site). Groups appear in **Content → sidebar** as collapsible sections containing collection links. Stored in `settings/collection_groups.json`.

## AI tools

| Tool | Purpose |
|------|---------|
| `list_collection_groups` | Read current group tree (ids, names, collection slugs, order) |
| `save_collection_groups` | Replace entire groups tree |

**Requires:** `manage_content` permission on the AI connection.

## Workflow

1. `list_collection_groups` — capture current structure
2. Plan groups (e.g. "Marketing", "Blog", "Shop")
3. `save_collection_groups` with full tree — **replaces** all groups

## Payload shape

```json
{
  "groups": [
    {
      "id": "marketing",
      "name": "Marketing",
      "collections": ["site_elements", "about", "contact"],
      "icon": "folder"
    },
    {
      "id": "blog",
      "name": "Blog",
      "collections": ["blog"],
      "children": []
    }
  ]
}
```

- **`id`** — stable string identifier
- **`name`** — display label
- **`collections`** — array of collection **slugs** in display order
- **`children`** — optional nested sub-groups (same shape)
- **`icon`** — optional admin icon key

Always include **every collection slug** you want visible — omitted collections may fall to "ungrouped" depending on admin UI.

## Example: bakery site groups

After creating collections `site_elements`, `menu`, `blog`, `contact`:

```json
{
  "groups": [
    {
      "id": "site",
      "name": "Site content",
      "collections": ["site_elements", "about"]
    },
    {
      "id": "menu-group",
      "name": "Menu",
      "collections": ["menu"]
    },
    {
      "id": "blog-group",
      "name": "Blog",
      "collections": ["blog"]
    },
    {
      "id": "forms",
      "name": "Forms",
      "collections": ["contact"]
    }
  ]
}
```

## Anti-patterns

- Saving partial tree without reading current groups first — may drop collections from sidebar
- Using collection **names** instead of **slugs** in `collections[]`
- Expecting groups to affect public URLs — groups are admin navigation only

## Related

- `content-model` — creating collections to assign to groups
- `playbooks-index` — when to create collections vs pages
