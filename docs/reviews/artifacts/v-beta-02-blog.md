# V-BETA-02 — Blog reference site

**Date:** 2026-07-13 · **Phase:** P28-M17  
**Journey:** J-BLOG

Public blog visitor browses posts using blog reference project.

## Prerequisites

- Birkly dev server: `php -S localhost:8080 -t . server-router.php`
- Blog reference project installed: `./scripts/install-reference-project.sh blog`

## Steps

1. **Blog homepage**
   - Navigate to `/reference-projects/blog/website/index.html`
   - Hero section with site title
   - Recent posts grid loaded via `page_bundle` API
   - Each post shows title, excerpt, date, author

2. **Blog listing page**
   - `/reference-projects/blog/website/blog.html`
   - Full blog archive with filtering
   - Posts dynamically loaded from `blog` collection
   - `birkly-client.js` handles API binding

3. **Single post view**
   - `/reference-projects/blog/website/post.html?slug=welcome-to-birkly`
   - Full post content rendered
   - Meta tags populated from entry data
   - No raw template syntax in HTML source

4. **API verification**
   - `page_bundle` API returns blog entries
   - `welcome-to-birkly` slug present in collection
   - Entry data includes title, content, published_date

## Automated verification

```bash
./scripts/install-reference-project.sh blog
./scripts/journey-runner.php --journey J-BLOG
```

Journey verifies all pages HTTP 200, birkly-client.js present, page_bundle API returns entries.

## Status

✅ Automated journey passes (J-BLOG)
