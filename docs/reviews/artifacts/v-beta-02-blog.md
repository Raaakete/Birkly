# V-BETA-02 — Blog: read a full post

**Journey:** J-BLOG · **Date:** 2026-07-13

## Prerequisites

```bash
./scripts/prepare-reference-demo.sh blog
php -S localhost:8080 -t . server-router.php
```

## Browser steps (recorded)

1. Open `/reference-projects/blog/website/blog.html`
2. Wait for `birkly-client.js` hydration — post titles must appear (not raw `{for each`)
3. Click **Welcome to Birkly** (or any post title)
4. Verify full article loads — title, body paragraphs, tags (**not** "Post Not Found")
5. Scroll through article content
6. Click **← Back to Blog**

## Automated gate

```bash
php scripts/journey-runner.php --journey J-BLOG
```

Asserts: post `get_entry` returns body text, list includes `welcome-to-birkly`.

## Video

`v-beta-blog-yoga-interaction-demos.mp4` (blog segment)
