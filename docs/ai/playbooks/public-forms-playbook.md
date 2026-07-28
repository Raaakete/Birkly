# Public forms & outside submission playbook

Use for: contact forms, newsletter signup, feedback, visitor-submitted content, sub-entry forms from the public site.

## Two submission surfaces

| Surface | Entry method | Endpoint / template |
|---------|--------------|---------------------|
| **Parent collection** | `entry_method: public_api` on collection | `{entry for 'slug'}`, `/api/public_submit.php` |
| **Sub-collection** | `entry_method: public_api` on sub-collection | `/api/public_sub_entry.php` + parent refs |

Runtime value is **`public_api`** (not `public_submit` — older docs may use that name).

## Critical rules (read first)

1. **`read_collection` before writing form HTML** — field slugs in `name="{slug}"` must match the schema exactly. A contact collection with fields `name`, `email`, `message` must use `{name}`, not `{title}`.
2. **`{entry for 'slug'}` is supported** — `birkly-client.js` turns it into a working `<form data-birkly-url="/api/public_submit.php">`. Do not replace it with custom fetch JS unless you know the exact payload.
3. **No client-visible API key** — template forms post via `/api/public_submit.php` with the collection slug; the key stays server-side.
4. **Comments are sub-entries** — use `/api/public_sub_entry.php`, not `public_submit.php`.
5. **`auto_publish`** — when off (default), submissions land as drafts/pending and may not appear on the public site until approved. Set `auto_publish: true` in `api_settings` (collection) or `auto_publish: true` on sub-collections for instant visibility.

## Setup (AI + admin)

AI connections with **manage_content** can configure public submission via tools:

1. `create_collection` or `update_collection` with `entry_method: "public_api"`
2. API key is **auto-generated** when switching to public_api (returned in `read_collection`)
3. Optional `api_settings`: `auto_publish`, `rate_limit`, `notification_email`, captcha fields
4. For sub-collections: pass `sub_collections[]` with `entry_method: "public_api"` and matching field slugs

Then `write_project_file` — HTML with `{entry for 'slug'}` or explicit sub-entry forms in `project/`.

## AI workflow

1. `create_collection` / `update_collection` with form fields **and** `entry_method: "public_api"`
2. **`read_collection`** — note exact field slugs before writing HTML
3. `write_project_file` — contact page with `{entry for 'your-collection-slug'}`

## Method 1: Template-first form (recommended)

```html
<div class="contact-form-wrapper">
{entry for 'contact'}
  <div class="form-group">
    <label for="name">Name</label>
    <input type="text" name="{name}" id="name" required />
  </div>
  <div class="form-group">
    <label for="email">Email</label>
    <input type="email" name="{email}" id="email" required />
  </div>
  <div class="form-group">
    <label for="message">Message</label>
    <textarea name="{message}" id="message" required></textarea>
  </div>
  <div class="birkly-form-status" data-birkly-form-status hidden aria-live="polite"></div>
  <button type="submit">Send</button>
{endentry}
</div>
<script src="/birkly-client.js"></script>
```

- `{entry for 'contact'}` wraps custom HTML; client wraps it in a form and posts to `/api/public_submit.php`
- Field `name="{slug}"` must match collection field slugs from `read_collection`
- Optional `<div data-birkly-form-status>` shows inline success/error (no custom JS needed)

## Method 2: Auto-generated form

```html
{public_form 'contact'}
<script src="/birkly-client.js"></script>
```

Generates fields from collection schema automatically.

## Sub-entry public submission (comments)

On a blog post detail page (`post.html?collection=blog&slug=...`):

```html
<meta data-birkly-prefetch="sub_entries" data-birkly-into="comments"
      data-parent-collection="blog" data-parent-entry-id="{id}" data-sub-collection="comments" />

<form data-birkly-url="/api/public_sub_entry.php" data-birkly-success="reload">
  <input type="hidden" name="parent_collection" value="blog" />
  <input type="hidden" name="parent_entry_id" value="{id}" />
  <input type="hidden" name="sub_collection" value="comments" />
  <input type="text" name="author" required />
  <textarea name="content" required></textarea>
  <button type="submit">Post comment</button>
</form>

{for each comment in 'comments' of 'blog'.current}
  <p><strong>{ comment.author }</strong> { comment.content }</p>
{endfor}
```

Field names (`author`, `content`) must match the comments sub-collection schema from `read_collection`.

Set `auto_publish: true` on the comments sub-collection when comments should appear immediately without admin approval.

## Validation errors

`Validation failed` from `/api/public_submit.php` means a required schema field was missing or misnamed. Compare form `name="..."` attributes to `read_collection` field slugs.

## Anti-patterns

- Using `{title}` when the schema field is `{name}` — causes "Validation failed"
- Custom fetch JS with wrong payload shape — use `{entry for}` or `data-birkly-url` forms
- Replacing `{entry for}` because you think it is unsupported — it is wired in `birkly-client.js`
- Expecting comments via `public_submit.php` — use `public_sub_entry.php`
- Forgetting `auto_publish` then wondering why submissions are invisible on the site

## Tools

| Task | Tool |
|------|------|
| Schema | `create_collection`, `update_collection`, `read_collection` |
| Form HTML | `write_project_file` |
| Verify entries | `list_entries`, `list_sub_entries` |
| Sub-entry CRUD | `create_sub_entry`, etc. — `sub-collections` |
