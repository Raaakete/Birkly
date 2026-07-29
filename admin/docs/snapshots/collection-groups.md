# Collection groups

## Summary

**Collection groups** organize your content types in the admin Content overview and on your public site. Groups can be nested up to **three levels** deep (group → subgroup → collections). Each group has a label, optional emoji icon, and a list of collection handles. The tree is stored in `content/collections/groups.json`, exposed via the public API, and available in templates as `collection_groups`.

## Beginner

In the admin **Content** area, collections are not just a flat list — you can arrange them into **groups** (for example “Marketing”, “Shop”, “Company”). Groups help editors find collections faster and let you build site navigation that mirrors the same structure.

**What you see in the admin**

- A **groups panel** on the Content overview page.
- Each group shows its name, optional emoji icon, and the collections inside it.
- Groups can contain **subgroups** (nested groups) as well as collections.
- Collections that are not in any custom group appear under **Ungrouped**.
- Drag-and-drop reorders groups, subgroups, and collection membership. Changes are saved to `groups.json`.

**Example structure**

| Group | Contains |
|-------|----------|
| Marketing | Blog, News |
| Shop | Products (subgroup: Sale items → `sale-products`) |
| Ungrouped | Contact |

**On your website**

Groups are not only an admin convenience. When you use the Birkly client or public API, the same tree is available so you can render a sitemap, footer links, or sectioned index pages that match how content is organized in the CMS. See [Loops and lists — collection groups](../03-templating/loops-and-lists.md#collection-groups) and [Public API — list collection groups](../04-integration/public-api.md#list-collection-groups).

**Good practices**

- Keep group **ids** stable if templates or integrations reference them.
- Use short, clear **names** — they appear in admin and can be shown on the public site.
- Put rarely used collections in **Ungrouped** rather than creating empty groups.

## Advanced Users

Collection groups are persisted in **`content/collections/groups.json`** as a JSON array of tree nodes. The CMS normalizes legacy flat group files on load (P39-M7).

**Node shape (normalized)**

| Field | Type | Description |
|-------|------|-------------|
| `id` | string | Stable group identifier (auto-generated if missing). Special value: `ungrouped`. |
| `name` | string | Display label (legacy `label` is migrated to `name`). |
| `icon` | string | Optional emoji shown in admin and available in templates. |
| `collections` | string[] | Collection handles (slugs) directly in this group, in display order. |
| `children` | object[] | Optional nested subgroups (same shape, without ungrouped). |

**Constraints**

- Maximum nesting depth: **3** levels (root group → child → grandchild).
- A collection handle may appear in only one group (including Ungrouped).
- Saving invalid depth returns an error from the admin content API.

**Example `groups.json`**

```json
[
  {
    "id": "marketing",
    "name": "Marketing",
    "icon": "📣",
    "collections": [],
    "children": [
      {
        "id": "blog-group",
        "name": "Blog",
        "collections": ["blog", "news"]
      }
    ]
  },
  {
    "id": "shop",
    "name": "Shop",
    "icon": "🛒",
    "collections": ["products"]
  },
  {
    "id": "ungrouped",
    "name": "Ungrouped",
    "collections": ["contact"]
  }
]
```

**Admin API (authenticated)**

Used by the Content overview UI — not for public sites.

| Action | Endpoint | Auth |
|--------|----------|------|
| `list_groups` | `POST /api/content.php` with `{ "action": "list_groups" }` | `manage_content` |
| `save_groups` | `POST /api/content.php` with `{ "action": "save_groups", "groups": [...] }` | `manage_content` + CSRF |

**Public read**

- `list_collection_groups` on `api/public.php` — see [Public API](../04-integration/public-api.md#list-collection-groups).
- Page bundle request type `list_collection_groups` — see [Client library](../04-integration/client-library.md).

**Templating**

- Global variable / loop source: `collection_groups`
- `{for each group in collection_groups}` — see [Loops and lists](../03-templating/loops-and-lists.md#collection-groups).

## Related

- [Content overview](../01-admin/content-overview.md)
- [Collections](collections.md)
- [Loops and lists — collection groups](../03-templating/loops-and-lists.md#collection-groups)
- [Public API](../04-integration/public-api.md)
