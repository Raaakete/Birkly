# V-STU-04 — Studio production builder walkthrough evidence

**Date:** 2026-07-09 · **Phase:** P25-M17

Reproducible steps for design canvas, live preview, and styled publish.

## Prerequisites

- Birkly dev server: `php -S localhost:8080 -t . server-router.php`
- Studio plugin **activated** in Admin → Settings → Plugins
- Logged in as admin with `manage_studio` permission

## Steps

1. **Admin → Studio → New project**
   - Name: `Production Demo`
   - Style kit: **Minimal**
   - Click **Create** — IDE workspace opens

2. **Design canvas (kit CSS applied)**
   - Open `pages/index.studio.json` in **Visual** view
   - Canvas shows styled typography (not dashed preview boxes only)
   - Toggle **Outlines** to switch chrome vs design mode
   - Insert **Hero** from Sections sidebar — hero classes render with kit CSS

3. **Live preview pane**
   - Click **Live** in the view bar
   - iframe loads compiled HTML with `birkly-client.js` and project styles
   - Edit canvas and **Save** — preview auto-refreshes

4. **Layout pro tools**
   - Select a text element — box model overlay appears
   - Drag margin/padding handles to adjust spacing
   - Select a column — drag resize handle on column border
   - Use **Align** toolbar (left / center / right)

5. **CSS studio**
   - Inspector → **Stylesheets** tab → edit `custom.css`, save
   - Inspector → **Properties** tab → class manager chips
   - Rich text toolbar on selected heading (B, I, U, link)

6. **Binding wizard v2**
   - Insert binding → choose **Conditional**, **Sub-entry**, or **Template function**
   - Preview canonical syntax in wizard

7. **Page SEO**
   - Inspector → **Page** tab — set meta title and description
   - **Publish** — view source shows `<title>` and `<meta name="description">`

8. **Publish SSR**
   - Click **Publish**
   - Open `/site/production-demo/` — server-rendered, styled page
   - No raw `{for each` tags in output

## Automated gates

```bash
./scripts/studio-smoke.sh
./scripts/ci-check.sh
php tests/run.php
php tests/studio_template_engine_v2_test.php
php tests/performance_budget_test.php
```

All green at P25 closure.
