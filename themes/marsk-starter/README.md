# Marsk starter pack (P37)

Flagship onboarding demo — editorial slow-living journal converted from `Starter-Source_Marsk-Blog-Temaplte/`.

## Collections

| Slug | Purpose |
|------|---------|
| `blog` | Journal posts (title, slug, date, category, excerpt, content, author, art_class) |
| `site_elements` | Singletons: hero, marquee, statement, navigation, footer |
| `about` | About page (`about-page` entry) |
| `gallery` | Gallery tiles (title, image, caption, category, order) |

## Project templates

- `project/index.html` — homepage with featured + latest posts
- `project/blog.html` — filterable entry list
- `project/post.html` — single post (`?collection=blog&slug=…`)
- `project/about.html` — about page
- `project/gallery.html` — masonry gallery grid

Assets: `project/css/marsk.css`, `project/js/marsk.js`

## Ops publish

```bash
php scripts/seed-ops-starters.php
```

Pack id: `marsk` · channel: `stable` · `onboarding_enabled`: true
