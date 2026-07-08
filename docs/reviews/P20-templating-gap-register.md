# P20 — Templating gap register

**Date:** 2026-07-08 · **Phase:** P20 templating excellence & reference projects  
**Plan:** `docs/reviews/P20-templating-excellence-plan.md`  
**Parity matrix:** `docs/reviews/P20-js-php-parity-matrix.md`

| ID | Gap | Current | Target | Milestone | Status |
|----|-----|---------|--------|-----------|--------|
| G-01 | Slug pages use `list_entries` scan | `getEntryBySlug()` fetches full collection | `get_entry` via `page_bundle` | M1 | **closed** |
| G-02 | Bundle omits slug entry prefetch | `collectBundleRequests()` only lists collections | Auto `get_entry` when route has slug | M1 | **closed** |
| G-03 | No `birkly-collection` meta on detail pages | Collection inferred heuristically | Explicit meta + bundle | M1 | **closed** |
| G-04 | No `page_bundle` CI tests | Only snapshot tests | `page_bundle_test.php` + fixtures | M1 | **closed** |
| G-05 | Client engine untested | No JS fixture tests | Node harness tests | M1 | **closed** |
| G-06 | Kekaba workarounds | Baked meta, hydrate, snapshots | Live client on reference sites | M4–M9 | **closed** |
| G-07 | No reference project kit | `demo-content/` only | `reference-projects/` + install script | M2 | **closed** |
| G-08 | No user story catalog | Ad-hoc demo pages | `user-stories.md` | M3 | **closed** |
| G-09 | Sub-collection `of 'parent'.slug` | Spec draft; client partial | Full PHP+JS parity | M10 | **closed** |
| G-10 | Template functions not in bundle | Separate parallel POST batch | ≤5 RTTs via parallel TF batch | M10 | **closed** (acceptable) |
| G-11 | Admin PHP parser vs client drift | Two engines | Parity matrix green | M10 | **closed** |
| G-12 | Hydrate docs over-emphasized | Benchmark mentions hydrate | `production-optimization.md` | M11 | **closed** |
| G-13 | Custom fetch() on client sites | Kekaba cache scripts | `data-birkly-*` only | M4–M9 | **closed** |

## Locked decisions (P20-D8–D11)

| Decision | Choice |
|----------|--------|
| P20-D8 | **Default delivery = live `birkly-client.js` only** on reference projects |
| P20-D9 | No hydrate, snapshots, or bake on reference projects |
| P20-D10 | Kekaba-style client workarounds are bugs — fix core client/templating |
| P20-D11 | Canonical connection: `<script src="/birkly-client.js" data-cms-url="http://localhost:8080"></script>` |

## Performance target

≤ **5 API round-trips** per page load via auto `page_bundle` + parallel template-function batches. Admin edit → browser refresh shows changes without re-bake.
