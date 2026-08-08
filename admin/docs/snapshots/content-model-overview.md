# Content model overview

## Summary

Birkly organizes site content with two primitives: **collections** (content types / schemas) and **entries** (individual items). There is no separate “singleton” or “document” type — one-off pages, shared chrome, and repeating lists all use collections and entries. Use this page as the start-here guide before creating schema in the admin.

## Beginner

### The two-level model

| Concept | What it is | Example |
|---------|------------|---------|
| **Collection** | A content type you define once: name, handle, fields, settings | “Blog posts”, “Products”, “Site elements” |
| **Entry** | One filled-in instance of that collection | One blog post, one product, one header block |

You create a collection’s fields **once**, then add as many entries as you need. Templates and the Public API use the collection **handle** (e.g. `blog`) and each entry’s **slug** (e.g. `welcome-post`).

### Decision tree — which pattern fits?

```
Need to store content?
│
├─ Many items of the same shape (posts, products, team members)?
│  └─► Top-level collection + entries
│      • List page: loop over entries
│      • Detail page: fetch one entry by slug
│
├─ Shared site chrome (header, footer, nav, global CTAs)?
│  └─► site_elements collection + named entries + <birkly-region>
│      See [Site elements pattern](site-elements-pattern.md)
│
├─ Rows that belong to one parent entry (comments, reviews, lessons)?
│  └─► Sub-collection on the parent collection
│      See [Sub-collections](sub-collections.md)
│
└─ Visitors submit data (contact form, signup, review)?
   └─► Collection with entry_method: public_api
       See [Public forms](../04-integration/public-forms.md)
```

### Worked examples

**Blog (list + detail)**

1. Create collection **Blog posts** (handle: `blog`) with Title, Content (editor), Date, Featured image.
2. Add entries — one per post.
3. Templates: `{for each entry in 'blog'}` for the list; `{from 'blog' get entry 'my-slug'}` for a single post.

**Bakery menu**

1. Collection **Menu items** (handle: `menu`) with Name, Description, Price, Category (select), Photo.
2. Many entries — one per dish.
3. Group or filter in templates by category field.

**Site header / footer (site elements)**

1. Collection **Site elements** (handle: `site_elements`) with Title + **Dynamic items** field for flexible blocks.
2. One entry per region: `header`, `footer`, `announcement-bar`.
3. Front-end: `<birkly-region collection="site_elements" entry="header" field="blocks">` injects live content.

**Contact form submissions**

1. Collection **Form submissions** (handle: `form-submissions`) with Name, Email, Message.
2. Set **Entry method** to **Public API**.
3. Your site POSTs to the public API; entries appear in admin for review.

### Best practices

1. **Define schema before entries** — add all fields (and sub-collections) before editors create content.
2. **Stable handles** — collection handle and field API names are used in templates and integrations; avoid renaming after go-live.
3. **Published vs draft** — only **Published** entries appear on the live site and in the public API by default.
4. **One shape per collection** — do not mix unrelated content types in one collection; create another collection instead.
5. **One-off pages** — use a collection such as “Pages” with one entry per page (title + body or dynamic items), not a separate document type.

### What we intentionally do not have

Birkly does **not** ship a singleton/document content primitive. A “single about page” is **one entry** in a Pages or Site elements collection. Shared layout chrome uses **named entries** in a `site_elements` collection plus `<birkly-region>` on your site.

## Advanced Users

**Storage layout (typical file-based install):**

```
content/collections/{handle}/
  collection.json    # name, handle, entry_method, sub_collections, multilanguage
  schema.json        # fields array (may be merged in collection.json)
  entries/
    {slug}.json      # field values + slug + status + timestamps
```

**API surface:**

- `GET /api/public/collections/{handle}/entries` — published entries
- `GET /api/public/collections/{handle}/entries/{slug}` — one entry
- Admin CRUD via `/api/admin_collections.php`, entry APIs, sub-entry APIs

**Multilanguage:** When enabled on a collection, entry field values are stored per language code; templates and API expose the active locale.

**Related fieldtypes:** Repeating in-entry blocks → [Dynamic items field](dynamic-items-field.md). Rich body → [Content editor](content-editor.md). Full list → [Field types](field-types.md).

## Related

- [Collections](collections.md)
- [Entries](entries.md)
- [Sub-collections](sub-collections.md)
- [Site elements pattern](site-elements-pattern.md)
- [Dynamic items field](dynamic-items-field.md)
- [Collection groups](collection-groups.md)
- [Public forms](../04-integration/public-forms.md)
- [Collection editor](../01-admin/collection-editor.md)
- [Entry editor](../01-admin/entry-editor.md)
