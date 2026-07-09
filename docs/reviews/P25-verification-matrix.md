# P25 — Verification matrix

**Date:** 2026-07-09 · **Closure:** P25-M18 — **complete**

## Canvas & preview (STU-CANVAS)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| STU-CANVAS-01 | Design canvas applies project CSS | pass | `plugins/studio/admin/studio-production.js` (`loadCanvasStyles`, `get_canvas_styles`) |
| STU-CANVAS-02 | Live preview pane with birkly-client.js | pass | `plugins/studio/admin/studio-production.js` (`refreshLivePreview`), `plugins/studio/admin/studio.html` |
| STU-CANVAS-03 | Preview refreshes on save | pass | `plugins/studio/admin/studio-production.js` (`patchSave`) |
| STU-CANVAS-04 | Box model margin/padding drag | pass | `plugins/studio/admin/studio-production.js` (`updateBoxOverlay`, `bindBoxHandles`) |
| STU-CANVAS-05 | Column resize drag | pass | `plugins/studio/admin/studio-production.js` (`attachColumnResizeHandles`) |
| STU-CANVAS-06 | Align/distribute toolbar | pass | `plugins/studio/admin/studio.html` (`#studio-align-group`), `studio-production.js` |

## Template engine (STU-ENGINE)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| STU-ENGINE-01 | Conditional blocks SSR | pass | `tests/studio_template_engine_v2_test.php`, `plugins/studio/includes/StudioTemplateEngine.php` |
| STU-ENGINE-02 | Sub-entry loop SSR | pass | `tests/studio_template_engine_v2_test.php` |
| STU-ENGINE-03 | Template function block SSR | pass | `tests/studio_template_engine_v2_test.php` (`commerce_cart_count`) |
| STU-ENGINE-04 | Ordered + limit loops SSR | pass | `tests/studio_template_engine_test.php`, `tests/studio_ssr_curl_test.php` |

## CSS & content (STU-CSS)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| STU-CSS-01 | custom.css rules panel | pass | `plugins/studio/admin/studio-production.js` (`renderStylesheetsInspector`) |
| STU-CSS-02 | Class manager autocomplete | pass | `plugins/studio/admin/studio-production.js` (`appendClassManager`) |
| STU-CSS-03 | Stylesheet tabs | pass | `plugins/studio/admin/studio-production.js` (Custom \| Generated \| Kit tabs) |
| STU-CSS-04 | Rich text inline format | pass | `plugins/studio/admin/studio.html` (`#studio-rich-toolbar`), `studio-production.js` |
| STU-CSS-05 | Section preset hero styled | pass | `plugins/studio/sections/hero.json`, `plugins/studio/kits/minimal/kit.css` |

## Site (STU-SITE)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| STU-SITE-01 | Page SEO meta in compiled head | pass | `plugins/studio/includes/StudioCompiler.php`, `scripts/studio-smoke.php` |
| STU-SITE-02 | Content panel field inserter | pass | `plugins/studio/admin/studio-production.js` (`renderContentPanel`, `insertField`) |
| STU-SITE-03 | Commerce block v2 markup | pass | `plugins/studio/studio-blocks.php` (`commerce_product_card`) |
| STU-SITE-04 | Events block v2 markup | pass | `plugins/studio/studio-blocks.php` (`events_list`) |
| STU-SITE-05 | Redo + revision restore | pass | `plugins/studio/admin/studio-ide.js` (redo), `plugins/studio/includes/StudioRevisionStore.php` |

## Regression

| ID | Check | Result | Evidence |
|----|-------|--------|----------|
| PR-01 | P24 STU-RENDER rows still pass | pass | `tests/studio_ssr_curl_test.php`, `tests/studio_compiler_canonical_test.php`, `tests/studio_template_engine_test.php` |
| PR-02 | `performance_budget_test.php` | pass | `tests/performance_budget_test.php` (CI green) |
| PR-03 | `scripts/studio-smoke.sh` | pass | `scripts/studio-smoke.sh`, `scripts/ci-check.sh` |

## Video

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| V-STU-04 | Design canvas + live preview + publish | pass | `docs/reviews/artifacts/v-stu-04-evidence.md` |

**Status:** green (2026-07-09)
