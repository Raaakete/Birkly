# Media Library

## Summary

The Media Library is the central place in Birkly where you upload, store, and manage files (images, PDFs, documents, and sometimes video or audio) used in your content. You upload once; then when editing an entry you pick from the library for Media or Gallery fields. Use clear filenames and sensible image sizes so the library stays easy to use and your site stays fast.

## Beginner

The **Media Library** is where all the files that appear in your content live: hero images, product photos, PDF downloads, logos, and so on. Instead of uploading a new file every time you need it, you upload once to the library and then **choose** that file when you’re editing an entry.

**Why use the Media Library**

- **One place for everything** — You can see all your images and files in one list (often with thumbnails and names). That makes it easier to reuse the same image (e.g. your logo) in many entries and to find files later.
- **No duplicate uploads** — If you need the same “Team photo” in three different pages, you upload it once, then pick it three times from the library when editing those entries.
- **Consistent naming** — You can name files clearly (e.g. `hero-homepage.jpg`, `product-widget-blue.png`) so you and your team know what each file is.

**How to upload files**

1. In the sidebar, click **Media** (or “Media Library”).
2. Use **Upload** (or “Add file”) or **drag and drop** files into the upload area.
3. Select one or more files from your computer. Wait for the upload to finish.
4. The new files appear in the library. You can then use them when editing any entry that has a Media or Gallery field.

**Example**

You need a photo for a blog post. You go to **Media**, click **Upload**, and select `office-team-2025.jpg`. After the upload, the file appears in the list. You open your “Blog posts” collection, edit the post, and in the “Featured image” field you click “Choose from library.” You select `office-team-2025.jpg` and save. The post now shows that image. Next week you write another post and want the same photo—you choose it again from the library; no second upload.

**What file types are supported**

Typically: **images** (JPG, PNG, GIF, SVG, WebP), **PDFs**, and common **documents**. Some setups also allow video or audio. The exact list and maximum file size depend on your server configuration. If an upload fails, the file might be too large or in a type the server doesn’t allow.

**Using media in entries**

- A **Media** field = one file (e.g. one featured image, one PDF).
- A **Gallery** field = multiple files (e.g. several product images in order). You pick several from the library and can usually reorder them.
- When you’re in the entry editor, clicking the Media or Gallery field opens a **picker** that shows the library. You select the file(s) and confirm; they’re linked to that entry. On the live site, your template will show the image or a link to the file.

**Good habits**

- **Names** — Use descriptive filenames (`summer-sale-banner.jpg` instead of `IMG_1234.jpg`) so you can search and recognize files later.
- **Size** — Compress images before uploading when possible. Large images slow down your site. Use JPG for photos and PNG when you need transparency.
- **Cleanup** — From time to time remove files you no longer use so the library doesn’t get cluttered.

## Advanced Users

**Storage:** Media files are stored in a dedicated directory (e.g. `media/` or under a content path). Entry JSON references them by filename or by an ID; the Public API and templating resolve these to URLs (e.g. `/media/filename.jpg` or via a media endpoint).

**Upload flow:** Admin upload goes through the upload API (multipart/form-data); file is validated (type, size), stored, and metadata (filename, size, maybe dimensions) is recorded. Optional: thumbnails, image variants (resize/crop) if the stack supports it.

**Usage in entries:** Media field stores a single reference; Gallery stores an ordered array. In templates, use the appropriate helper (e.g. `{ media field_name }` or loop over gallery) to output `<img src="...">` or `<a href="...">`. See [Media in templates](../03-templating/media-in-templates.md).

**Public uploads:** If the Public API or a form endpoint allows file uploads, submissions can add files to the same media store; config may restrict types/size and require auth or rate limiting. See [Public API](../04-integration/public-api.md), [Public forms](../04-integration/public-forms.md).

**Best practices:** Enforce allowed MIME types and max size server-side; use descriptive filenames; consider image optimization (client or server) before storage.

## Related

- [Entries](../02-content/entries.md)
- [Field types](../02-content/field-types.md)
- [Media in templates](../03-templating/media-in-templates.md)
