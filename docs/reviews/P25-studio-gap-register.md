# P25 — Studio gap register

**Date:** 2026-07-09 · **Phase:** P25-M0 · **Status:** Active

## Purpose

Track gaps between P24 delivery and P25 production-builder goals. Updated as milestones close.

## Gap register

| ID | Area | P24 state | P25 target | Owner milestone | Status |
|----|------|-----------|------------|-----------------|--------|
| GAP-01 | Canvas fidelity | Dashed preview boxes | Kit + custom + generated CSS in editor | M1 | open |
| GAP-02 | Live preview | Open tab only | In-IDE pane with `birkly-client.js` | M2 | open |
| GAP-03 | Conditionals SSR | `entry has` only | `exist`, `does not exist`, numeric `has` | M3 | **closed** |
| GAP-04 | Sub-entry SSR | Client only | `{for … in 'sub' of 'parent'.'slug'}` | M4 | **closed** |
| GAP-05 | Template functions SSR | Client prefetch | `page_bundle` + `__tf__:` keys | M4 | **closed** |
| GAP-06 | Binding wizard | Loop / filtered / site_element | Conditional, TF, sub-entry steps | M5 | open |
| GAP-07 | Box model | JSON styles only | Margin/padding drag overlay | M6 | open |
| GAP-08 | Column resize | Gap steppers | Drag resize + align toolbar | M7 | open |
| GAP-09 | CSS rules panel | Element styles JSON | `custom.css` rules editor | M8 | open |
| GAP-10 | Class manager | Manual class chips | Kit autocomplete | M9 | open |
| GAP-11 | Stylesheet tabs | Code tab only | Custom / Generated / Tokens / Kit | M10 | open |
| GAP-12 | Rich text | Plain text elements | Inline B/I/link/list toolbar | M11 | open |
| GAP-13 | Section presets | Hero + features | Hero, blog grid, CTA (styled) | M12 | **closed** |
| GAP-14 | Page SEO | Title from page only | Meta description, og:title in compiler | M13 | **closed** |
| GAP-15 | Content panel | None | Collection browser + field inserter | M14 | open |
| GAP-16 | Plugin blocks | Basic shelf | Commerce + events reference markup | M15 | **closed** |
| GAP-17 | Redo / revisions | Undo canvas only | Redo + publish snapshot UI | M16 | open |
| GAP-18 | CI smoke | P24 tests only | `studio-smoke.sh` | M17 | **closed** |

## Backend deliverables (this wave)

- `StudioTemplateEngine` v2 — conditionals, count, sub-entry loops, template functions
- `StudioCompiler` — SEO meta in `<head>`
- `studio.php` — `get_canvas_styles`, `preview_live`, `get_page_settings`, `save_page_settings`
- Section presets: `blog-grid.json`, `cta-banner.json`
- `kits/minimal/kit.css` — hero, blog, CTA, header styles
- Core blocks: `commerce_product_card`, `events_list`
- Tests: `studio_template_engine_v2_test.php`, `scripts/studio-smoke.sh`

## Out of scope (deferred)

Figma import, animation timeline, popup builder, multi-user realtime, npm/Tailwind JIT, marketplace block store UI.

## References

- `docs/reviews/P25-studio-production-builder-plan.md`
- `docs/reviews/P25-canvas-fidelity-spec.md`
- `docs/reviews/P20-js-php-parity-matrix.md`
