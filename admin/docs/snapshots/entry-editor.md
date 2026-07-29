# Entry Editor

## Summary

The **entry editor** is where you create and edit individual items in a collection. The main area shows your collection fields (title, content, images, and so on). The **right sidebar** holds system settings in three tabs — **General**, **Meta Data**, and **Templating**. Slug, status, scheduling, and SEO fields live in the sidebar only; they never appear in the main field list.

## Beginner

Open a collection from **Content**, then click **New entry** or open an existing entry to edit.

**Layout**

- **Main area (left)** — Collection fields only: title, editor, media, dates, and any custom fields you defined. Labels sit **above** each control (full-width fieldtypes).
- **Topbar breadcrumb** — Long trails collapse from the left; the collection and entry names stay visible (`… / Collection / Entry`). See [Forms and navigation](admin-forms-navigation.md).
- **Sidebar (right)** — Three tabs:
  - **General** — Slug, status, publish/expire dates, reviewer, and entry metadata (ID, created/updated times).
  - **Meta Data** — SEO and social sharing: meta title, meta description, keywords, Open Graph fields.
  - **Templating** — Field slug reference for templates and links to templating docs.

**Slug (General tab)**

The slug is the URL-friendly identifier for this entry (e.g. `welcome-to-our-blog`). It appears **only** in the sidebar General tab — not among your content fields.

- **Auto-generate slug from title** — Checkbox below the slug field; **on by default**. When checked, the slug updates as you type the title (lowercase, spaces become hyphens).
- **No title** — If you save without a title, Birkly assigns the entry **ID** as the title and uses the normalized ID as the slug.
- **Manual slug** — Uncheck **Auto-generate slug from title** to type your own slug. While unchecked, changing the title does not change the slug. Check the box again to regenerate from the current title (or ID if the title is empty).
- You can edit the slug field directly at any time.

**Status (General tab)**

Choose **Unpublished**, **Published**, **Scheduled**, or **Pending for Review**. Additional fields appear when relevant (e.g. **Publish At** for scheduled, **Unpublish At** for published, **Notify Reviewer** for pending review).

**Saving**

Use **Save** or **Save & Publish** at the top. Slug and status are read from the sidebar when you save.

## Advanced Users

**Reserved fields (sidebar only):** `slug`, `status`, `published_at`, `expires_at`, `reviewer`, `meta_title`, `meta_description` (plus related OG fields in Meta Data). These are filtered out of the main `$fields` render loop in `admin/templates/entry-editor.html`.

**Slug resolution:** Client-side auto-generation in `admin/js/entry-editor.js`; server-side `birkly_resolve_entry_slug()` in `core/entry_slug.php` on create/update via `api/admin_entries.php`.

| Mode | Behavior |
|------|----------|
| Auto checkbox **on** (default) | Slug follows title on input; empty title → entry ID fallback on save |
| Auto checkbox **off** | Slug preserved on title-only save (P2-R11); POST `slug` wins |
| Re-check auto | Slug regenerates immediately from current title or ID |

**Sidebar tabs:** Scoped under `.sidebar-tabs` so tab panes are not hidden by global `tabs.css`. Tab JS is scoped to `.sidebar-tabs` only.

**Sub-entries:** Sub-entry editors use the same sidebar layout and slug rules.

## Related

- [Entries](../02-content/entries.md)
- [Slugs and status](../02-content/slugs-and-status.md)
- [Collection editor](collection-editor.md)
- [Content overview](content-overview.md)
