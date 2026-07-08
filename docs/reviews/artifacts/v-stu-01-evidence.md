# V-STU-01 — Studio walkthrough evidence

**Date:** 2026-07-08 · **Phase:** P22-M11

Reproducible steps (no terminal required for site owner after CMS is running).

## Prerequisites

- Birkly dev server: `php -S localhost:8080 -t . server-router.php`
- Studio plugin **activated** in Admin → Settings → Plugins
- Blog reference project installed (optional, for loop demo): `./scripts/install-reference-project.sh blog`

## Steps

1. **Admin → Studio → New project**
   - Name: `Demo Studio Site`
   - Style kit: **Bootstrap**
   - Click **Create**

2. **Build 3-section layout**
   - Click **Add section** twice (3 sections total)
   - In section 1: add **Text** element — "Welcome"
   - In section 2: add **Collection loop** — collection `blog`, template `<article><h2>{title}</h2><p>{excerpt}</p></article>`
   - In section 3: add **Button** — "Contact"

3. **Live preview**
   - Click **Preview**
   - iframe loads compiled HTML + `birkly-client.js`
   - Blog entries render when blog collection exists

4. **Publish SSR**
   - Click **Publish**
   - Open `http://localhost:8080/site/demo-studio-site/` (slug from project)
   - Page returns server-rendered HTML (view source — no `.studio.json`)

5. **Export ZIP**
   - Click **Export ZIP**
   - ZIP contains `website/index.html`, `website/styles.css`, `README.md`
   - In-CMS project unchanged under `content/studio-projects/`

6. **Deactivate plugin**
   - Admin → Settings → Plugins → deactivate Studio
   - `GET /site/demo-studio-site/` → **503 Studio unavailable**
   - `content/studio-projects/` files still on disk

## Performance note

Preview uses P21 bundle path — simple page ≤2 cold RTTs per `performance_budget_test.php`.
