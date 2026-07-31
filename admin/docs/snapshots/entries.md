# Entries

## Summary

Entries are the actual content items in a collection: one blog post, one product, one team member. You create and edit them in the admin by filling in the collection’s fields (title, content, date, image, etc.). Each entry has a **slug** (URL-friendly identifier) and a **status** (Draft or Published). Only published entries are normally shown on your website and in the Public API.

**Collections vs entries:** A **collection** is the schema (the form template); an **entry** is one filled-in instance. See [Content model overview](content-model-overview.md) for the full mental model and decision tree.

## Beginner

An **entry** is one filled-in “form” for a collection. If the collection is “Blog posts,” each entry is one post; if it’s “Products,” each entry is one product.

**Example: one blog entry**

For a “Blog posts” collection with fields Title, Content, Excerpt, Publication date, Featured image, Tags, you might create an entry like this:

| Field             | Value |
|-------------------|--------|
| Title             | Welcome to our blog |
| Content           | (Rich text: “We’re excited to launch…”) |
| Excerpt           | A short summary for the homepage. |
| Publication date  | 2025-02-05 |
| Featured image    | (Choose “hero-blog.jpg” from Media Library) |
| Tags              | news, welcome |
| **Slug**          | welcome-to-our-blog |
| **Status**        | Published |

After you save, this entry is stored. Your website can show it in a list of posts and on a single post page using the slug in the URL (e.g. `/blog/welcome-to-our-blog`).

**How to create an entry**

1. **Content** → open the collection (e.g. “Blog posts”) → **New entry** (or **Add entry**).
2. Fill every field:
   - **Text / Textarea** — Type in the box.
   - **Editor** — Use the toolbar for bold, lists, links, images.
   - **Media / Gallery** — Click the field → choose from the Media Library (or upload). For Gallery you can pick several and reorder.
   - **Date / DateTime** — Use the calendar (and time picker if the field has time).
   - **Select / Radio** — Pick one option. **MultiSelect / Checkbox** — Pick multiple or yes/no as the field allows.
   - **Tags** — Type a word and press Enter; repeat for more tags.
3. **Sidebar → General** — Set **Slug** (auto-generates from title by default; see [Entry editor](../01-admin/entry-editor.md)) and **Status** (**Draft** = not on site; **Published** = visible).
4. Click **Save** or **Save & Publish**.

**Editing:** Open the collection, click the entry in the list (or the edit icon), change any fields, and save. **Deleting:** Use the delete option from the list or the entry screen—usually permanent, so be sure. **Duplicate:** If available, “Duplicate” creates a new entry with the same content (and a new slug) so you can tweak it for a similar post or product.

**Important:** Only **Published** entries are shown on the live site (and in the public API by default). Keep entries as **Draft** until they’re ready to go live; you can switch back to Draft to “unpublish” without deleting.

## Advanced Users

An entry is one **record** in a collection: field values plus system fields (**slug**, **status**, optional **id**, **created**, **updated**). Stored as one JSON file per entry (e.g. `content/collections/blog/entries/welcome-to-our-blog.json`) or as a DB row depending on setup.

**Create:** Content → collection → New entry; fill fields; set slug (often derived from title) and status; save. **Edit/delete:** From list or editor; save updates the file/row. **Duplicate:** Clone record with new slug/id.

**System fields:** `slug` (unique per collection, used in URLs and single-entry fetch), `status` ("draft" | "published"). Draft entries excluded from public API and from template loops that filter by published. Publish/unpublish = set status and save.

**Example: entry JSON (conceptual)**

```json
{
  "slug": "welcome-to-our-blog",
  "status": "published",
  "title": "Welcome to our blog",
  "content": "<p>We're excited to launch…</p>",
  "excerpt": "A short summary.",
  "date": "2025-02-05",
  "featured_image": "hero-blog.jpg",
  "tags": ["news", "welcome"]
}
```

**API:** `GET /api/public/collections/blog/entries` returns published entries; `GET /api/public/collections/blog/entries/welcome-to-our-blog` returns this entry by slug. Template: `{from 'blog' get entry 'welcome-to-our-blog'}` then `{ title }`, `{ content }`, etc.

## Related

- [Content model overview](content-model-overview.md)
- [Entry editor](../01-admin/entry-editor.md)
- [Collections](collections.md)
- [Sub-collections](sub-collections.md)
- [Field types](field-types.md)
- [Dynamic items field](dynamic-items-field.md)
- [Slugs and status](slugs-and-status.md)
- [Loops and lists](../03-templating/loops-and-lists.md)
- [Variables and output](../03-templating/variables-and-output.md)
