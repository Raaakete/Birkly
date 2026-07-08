# V-TPL-01 — Blog reference site

**Status:** Stub — record walkthrough when ready.

## Story

A content editor publishes blog posts in the admin. Visitors browse the homepage teaser, open the blog index, and read individual posts via slug URLs.

## Pages to demo

1. `/reference-projects/blog/website/index.html` — hero + recent posts
2. `/reference-projects/blog/website/blog.html` — full post list
3. `/reference-projects/blog/website/post.html?slug=welcome-to-birkly` — post detail

## Talking points

- Live `birkly-client.js` only (no hydrate)
- `{for each entry in 'blog'}` collection loops
- `{from 'site_elements' get entry 'hero'}` singleton blocks
- Slug meta: `birkly-page`, `birkly-collection`

## Prerequisites

```bash
./scripts/install-reference-project.sh blog
```
