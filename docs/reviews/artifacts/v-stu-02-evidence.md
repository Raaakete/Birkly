# V-STU-02 — Studio IDE walkthrough evidence

**Date:** 2026-07-08 · **Phase:** P23-M16

Reproducible steps for the Studio IDE (project workspace).

## Prerequisites

- Birkly dev server: `php -S localhost:8080 -t . server-router.php`
- Studio plugin **activated** in Admin → Settings → Plugins
- Logged in as admin with `manage_studio` permission

## Steps

1. **Admin → Studio → New project**
   - Name: `IDE Demo`
   - Style kit: **Minimal**
   - Click **Create** — IDE workspace opens (not v1 dropdown)

2. **File system sidebar**
   - Tree shows `pages/`, `styles/`, `components/`, `tokens.json`, `project.json`
   - Click **↻** to refresh tree

3. **Open 3 file tabs**
   - Click `pages/index.studio.json` (visual canvas)
   - Click `styles/custom.css` (code view)
   - Click `tokens.json` (code view)
   - Tab bar shows 3 tabs; edit `custom.css` — tab shows dirty `•`

4. **Visual + split view**
   - Click **Visual** — canvas with sections
   - Click **+ Section** twice (3 sections)
   - Insert **Hero** from Sections sidebar
   - Switch breakpoint to **Desktop**
   - Click **Split** — visual left, compiled HTML code right

5. **Plugin blocks**
   - With commerce plugin active: click **Product grid** in Blocks sidebar
   - Binding appears in canvas

6. **Save & undo**
   - Press **Ctrl+S** (or blur code tab) to save
   - Click **Undo** to revert last canvas change

7. **Open project ↗**
   - Click **Open project ↗** in toolbar
   - **New browser tab** opens `/site/ide-demo/?birkly_studio_preview=1` (draft) or `/site/ide-demo/` (published)
   - View source — no `.studio.json` loaded

8. **Publish SSR**
   - Click **Publish**
   - Open `/site/ide-demo/` — server-rendered HTML
   - `/site/ide-demo/styles.css` includes tokens + responsive generated CSS

9. **Export ZIP**
   - Click **Export** — ZIP with optimized `website/styles.css`

10. **v1 upgrade**
    - Open an existing P22 project — canvas auto-migrates to v2 (`version: 2`, `responsive` on sections)

## Automated gates

```bash
./scripts/ci-check.sh
php tests/run.php
php tests/performance_budget_test.php
php tests/studio_filesystem_test.php
```

All green at P23 closure.
