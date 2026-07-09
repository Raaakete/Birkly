# P25 — Canvas fidelity spec

**Date:** 2026-07-09 · **Phase:** P25-M0 · **Status:** Approved

## Goal

The Studio design canvas and live preview must match the published site: same typography, spacing, colors, and section presets — not dashed placeholder boxes.

## CSS stack (canonical order)

When rendering canvas, live preview, or published pages, apply styles in this order:

1. **Kit CSS** — `projects/{id}/styles/kit.css` (copied from `plugins/studio/kits/{kit}/kit.css` at project creation)
2. **Generated CSS** — `projects/{id}/styles/generated.css` (tokens + responsive rules from `StudioCompiler::writeGeneratedStyles`)
3. **Custom CSS** — `projects/{id}/styles/custom.css` (user global rules)

Published URL bundles all three at `/site/{project-slug}/styles.css`.

## API: `get_canvas_styles`

Returns for IDE injection:

| Field | Description |
|-------|-------------|
| `kit` | Kit CSS file content |
| `generated` | Generated CSS content |
| `custom` | Custom CSS content |
| `urls.bundle` | Public combined stylesheet URL (with revision query) |

IDE should inject `kit + generated + custom` into canvas iframe/shadow root for WYSIWYG fidelity (P25-D3).

## Section class contract

Section presets must use kit-defined classes so insert → publish looks professional without extra CSS:

| Preset | Classes | Kit selectors |
|--------|---------|---------------|
| Hero | `studio-section`, `hero` | `.hero`, `.hero h1/h2`, `.hero-subtitle` |
| Blog grid | `studio-section`, `blog-section` | `.blog-section`, `.post-card`, `.read-more` |
| CTA banner | `studio-section`, `cta-banner` | `.cta-banner`, `.cta-title`, `.btn-primary` |
| Nav header | `studio-header` (compiler) | `.studio-header`, `.studio-nav-link` |

## Live preview (`preview_live`)

1. `StudioCompiler::compilePage` — canvas → HTML with Birkly binding tags
2. `StudioTemplateEngine::collectBundleRequests` — list/get/sub_entries/template_function
3. `birkly_run_page_bundle_requests` — hydrate data
4. `StudioTemplateEngine::render` — SSR final HTML for iframe

No raw `{for each` or `{endget}` in response.

## SEO compile (`canvas.page.meta` or `canvas.meta`)

| Field | Compiled output |
|-------|-----------------|
| `title` | `<title>` override |
| `description` | `<meta name="description" content="…">` |
| `og_title` / `ogTitle` | `<meta property="og:title" content="…">` |

## Fidelity checklist

- [ ] Canvas iframe loads project CSS (M1)
- [x] Kit includes hero, blog, CTA, header styles (M12 backend)
- [x] `preview_live` API returns SSR HTML (M2 backend)
- [x] Section presets use styled classes (M12)
- [ ] Box-model overlay does not alter published CSS (M6)
- [x] `studio-smoke.sh` asserts `studio-section` + no raw tags (M17)

## Reference targets

- `reference-projects/blog/website/` — hero, blog grid, post cards
- `reference-projects/coffee/website/` — product cards
- `reference-projects/yoga/website/` — events list

## Values alignment

Self-contained in `plugins/studio/`; live preview uses canonical `page_bundle` path (P25-D2, platform-first).
