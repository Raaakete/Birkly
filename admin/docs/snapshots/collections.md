# Collections

## Summary

Collections are content types in Birkly (e.g. “Blog posts”, “Products”, “Team members”). You define each collection once with a set of fields (title, text, image, date, dropdown, etc.) and optional settings (who can create entries: admin only or public API). You then add as many entries as you need. The collection’s **handle** (e.g. `blog`) is used in the admin, in templates, and in the API—so choose it carefully and avoid changing it after your site or integrations use it.

**Start here:** [Content model overview](content-model-overview.md) — decision tree, worked examples (blog, site elements, forms), and best practices.

## Beginner

A **collection** is the “form template” for one kind of content. You create it once; after that, every new item of that kind is just filling out that same form (with a title, body, date, image, and whatever else you defined).

**Example: “Blog posts”**

You want a blog. You create a collection named **Blog posts** (handle: `blog`) with these fields:

| Field label      | Type   | Required? | Why |
|------------------|--------|-----------|-----|
| Title            | Text   | Yes       | Every post needs a title. |
| Content          | Editor | Yes       | The main body (paragraphs, headings, images). |
| Excerpt          | Textarea | No     | Short summary for list pages. |
| Publication date | Date   | Yes       | When the post was published. |
| Featured image   | Media  | No        | One image for the post (e.g. card or header). |
| Tags             | Tags   | No        | Free-form tags (e.g. “tutorial”, “news”). |

You save the collection. From then on, “New entry” in Blog posts shows exactly these fields. You don’t have to decide the structure again for each post.

**Example: “Products”**

For a small shop you create a collection **Products** (handle: `products`):

| Field label   | Type    | Required? | Why |
|---------------|---------|-----------|-----|
| Name          | Text    | Yes       | Product name. |
| Description   | Editor  | No        | Full description with formatting. |
| Price         | Number  | Yes       | e.g. 29.99. |
| Category      | Select  | No        | e.g. “Electronics”, “Clothing”. |
| Images        | Gallery | No        | Multiple product photos. |
| In stock      | Checkbox| No        | Yes/no. |

Again: you define this once; every new product uses the same fields.

**How to create a collection**

1. Sidebar → **Content** → **Create collection** (or **New collection**).
2. **Name:** e.g. “Blog posts”. **Handle:** e.g. `blog` (often auto-filled from the name; use lowercase, no spaces). The handle is what templates and the API use—don’t change it later if you can avoid it.
3. **Add fields:** For each field set a **label** (what editors see), a **field type** (Text, Editor, Date, Media, etc.), and whether it’s **required**. See [Field types](field-types.md) for the full list and when to use each.
4. **Settings** tab (sidebar): Set slug (auto-generate from name by default), description, sort order, and **Entry method**.
   - **Admin only** — Only logged-in users can create or edit entries. Default and safest for most content.
   - **Public API** — Your website (or another app) can create entries too, e.g. a contact form or a signup form. If you enable this, you can usually set rate limits and security options.
5. **Save.** The collection appears in the Content list; click it to add entries.

**Important:** The handle (e.g. `blog`) will appear in your site’s templates and in API URLs. If you rename it later, you’ll have to update those references.

## Advanced Users

A collection is the **schema** for one content type: **name** (admin label), **handle** (unique id), **fields** (key, type, required, options), **entry method** (admin-only vs public API). Stored as config (e.g. `collection.json` + `schema.json` or equivalent).

**Create/edit:** Content → Create collection. Add fields (see [Field types](field-types.md)). In Settings set **entry method**: **Admin only** (default) or **Public API** (often with rate limit, auto-publish, CAPTCHA). Handle is used in:

- **Templating:** `{from 'blog' get entries}`, `{for each entry in 'blog'}`.
- **Collection groups:** `{for each group in collection_groups}` — tree from `groups.json`; see [Collection groups](collection-groups.md).
- **Public API:** `GET /api/public/collections/blog/entries`, `GET /api/public/collections/blog/entries/my-slug`.
- **Submit:** `POST /api/public/collections/blog/entries` (or dedicated form endpoint) when entry method is Public API.

**Example: minimal collection config (conceptual)**

```json
{
  "name": "Blog posts",
  "handle": "blog",
  "fields": [
    { "key": "title", "label": "Title", "type": "text", "required": true },
    { "key": "content", "label": "Content", "type": "editor", "required": true },
    { "key": "date", "label": "Publication date", "type": "date", "required": true },
    { "key": "featured_image", "label": "Featured image", "type": "media" }
  ],
  "entry_method": "admin"
}
```

**Best practices:** Keep handle stable. Avoid removing or renaming field keys after entries exist (orphaned data, broken templates). For public submit, use rate limiting and validation.

## Related

- [Content model overview](content-model-overview.md)
- [Collection editor](../01-admin/collection-editor.md)
- [Collection groups](collection-groups.md)
- [Entries](entries.md)
- [Sub-collections](sub-collections.md)
- [Site elements pattern](site-elements-pattern.md)
- [Field types](field-types.md)
- [Dynamic items field](dynamic-items-field.md)
- [Slugs and status](slugs-and-status.md)
- [Public API](../04-integration/public-api.md)
- [Public forms](../04-integration/public-forms.md)
