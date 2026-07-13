# V-BETA-06 — Admin publish workflow

**Date:** 2026-07-13 · **Phase:** P28-M17  
**Journey:** J-ADMIN

Content editor creates draft blog post and publishes via admin API.

## Prerequisites

- Birkly dev server running
- Blog reference project installed (provides `blog` collection)
- Clean `users/` directory for test isolation

## Steps

1. **Admin account creation**
   - Create first admin: `journey-admin-{pid}@birkly.test`
   - Password: `SecurePass123!`
   - Display name: `Journey Admin`
   - Skip onboarding wizard

2. **Get CSRF token**
   - Admin session established via cookie
   - Fetch CSRF token from admin dashboard
   - Token required for state-changing API calls

3. **List collections**
   - API: `GET /api/content.php?action=list_collections`
   - Find `blog` collection in response
   - Extract collection ID for entry creation

4. **Create draft entry**
   - API: `POST /api/content.php?action=create_entry`
   - Collection: `blog`
   - Data: 
     - `title`: "Journey Admin Post {pid}"
     - `content`: "<p>Journey admin draft content.</p>"
   - CSRF token in request body
   - Response includes `id` and `slug`

5. **Publish entry**
   - API: `POST /api/content.php?action=update_entry`
   - Entry ID from create response
   - Update data: `status: published`, `published_date: now()`
   - CSRF token included

6. **Verify public visibility**
   - API: `GET /api/content.php?action=get_entry&collection=blog&slug={slug}`
   - No authentication required (public endpoint)
   - Entry status: `published`
   - Slug matches create response
   - Content rendered in public API response

## Automated verification

```bash
./scripts/install-reference-project.sh blog
./scripts/journey-runner.php --journey J-ADMIN
```

Journey creates admin, fetches CSRF, creates draft entry, publishes, verifies public API returns published entry.

## Status

✅ Automated journey passes (J-ADMIN)
