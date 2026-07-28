# Public forms & outside submission playbook

Use for: contact forms, newsletter signup, feedback, visitor-submitted content, sub-entry forms from the public site.

## Two submission surfaces

| Surface | Entry method | Endpoint / template |
|---------|--------------|---------------------|
| **Parent collection** | `entry_method: public_api` on collection | `{entry for 'slug'}`, `/api/public_submit.php` |
| **Sub-collection** | `entry_method: public_api` on sub-collection | `/api/public_sub_entry.php` + parent refs |

Runtime value is **`public_api`** (not `public_submit` — older docs may use that name).

## Setup (AI + admin)

AI connections with **manage_content** can configure public submission via tools:

1. `create_collection` or `update_collection` with `entry_method: "public_api"`
2. API key is **auto-generated** when switching to public_api (returned in `read_collection`)
3. Optional `api_settings`: `auto_publish`, `rate_limit`, `notification_email`, captcha fields
4. For sub-collections: pass `sub_collections[]` with `entry_method: "public_api"` on the parent collection update

Then `write_project_file` — HTML with `{entry for 'slug'}` in `project/`.

## AI workflow

1. `create_collection` / `update_collection` with form fields (name, email, message, etc.) **and** `entry_method: "public_api"`
2. `read_collection` to confirm `api_key` if needed for external integrations (template forms use `{entry for}` without exposing the key in HTML)
3. `write_project_file` — contact page with `{entry for 'your-collection-slug'}`

## Method 1: Template-first form (recommended)

```html
<div class="contact-form">
{entry for 'contact'}
  <label>Name <input type="text" name="{name}" required /></label>
  <label>Email <input type="email" name="{email}" required /></label>
  <label>Message <textarea name="{message}" required></textarea></label>
  <button type="submit">Send</button>
{endentry}
</div>
<script src="/birkly-client.js"></script>
```

- `{entry for 'contact'}` wraps custom HTML
- Field `name="{slug}"` must match collection field slugs exactly
- Client fetches schema + key, wraps in `<form>`, posts to `/api/public_submit.php`

## Method 2: Auto-generated form

```html
{public_form 'contact'}
<script src="/birkly-client.js"></script>
```

Generates fields from collection schema automatically.

## Secure forms (CAPTCHA, honeypot)

When collection has CAPTCHA enabled, client handles token flow. Optional hardening via `api/secure_forms.php` patterns for custom backends.

## Sub-entry public submission

For comments/reviews visitors submit on a blog post:

1. Parent collection `blog`, sub-collection `comments` with `entry_method: public_api`
2. Public POST to `/api/public_sub_entry.php` with parent collection, entry id, sub-collection slug
3. Often wired via custom JS or automation webhook — template loops use `{for each entry in 'comments' of 'blog'.'post-slug'}`

See `sub-collections` playbook for schema and AI CRUD tools.

## Use cases

| Use case | Collection | Fields | Notes |
|----------|------------|--------|-------|
| Contact | `contact` | name, email, message | Draft + email notify common |
| Newsletter | `newsletter` | email | Auto-publish or double opt-in external |
| Job application | `applications` | name, email, cv (file) | File fields via public submit |
| Product review | sub-collection on `products` | author, rating, text | `public_api` on sub-collection |
| Event RSVP (free) | Events plugin | — | See `events` playbook |

## Workflow order

1. `read_birkly_docs` `public-forms` + `content-model`
2. `create_collection` with fields (AI)
3. User enables Public API in admin (AI instructs)
4. `write_project_file` form HTML in `project/`
5. Test submission; optional Automation workflow on webhook/entry trigger

## Anti-patterns

- Expecting AI to generate API keys
- Wrong field names in `name="{...}"` vs schema slugs
- Using `admin_only` collection but embedding `{entry for}` — form will fail
- Looping on `list_collections` instead of building the form

## Tools

| Task | Tool |
|------|------|
| Schema | `create_collection`, `update_collection` |
| Form HTML | `write_project_file` |
| Verify entries | `list_entries` |
| Sub-entry CRUD | `create_sub_entry`, etc. — `sub-collections` |
