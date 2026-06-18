# Kekaba index — API round-trip benchmark (M4)

Reference pattern for measuring client rendering performance on kekaba-class template-first pages.

## Page profile

Kekaba `index.html` is typically **baked** (`<meta name="birkly-baked" content="1">`) and uses `birkly-hydrate.js` for incremental hydration. This benchmark models the **unbaked** (live CMS) path that `birkly-client.js` handles — the pattern that caused N+1 storms before M4.

### Typical live-read surface (demo-website index analogue)

| Data need | Mechanism |
|-----------|-----------|
| `site_elements` | Always loaded |
| `services` | `{for each entry in 'services' …}` |
| `blog` | `{for each entry in 'blog' …}` |
| `tags` | Nested loop inside blog cards |
| Template functions (0-arg) | `{if user_logged_in}` etc. when plugins active |
| Prefetch nodes | `data-birkly-prefetch` for sub-entries / filtered lists |

Collections referenced in template loops: **3** (`services`, `blog`, `tags`) plus **site_elements**.

## Before M4 (serial client)

| Phase | Requests | Notes |
|-------|----------|-------|
| `detectApiEndpoint` | 0–1 | Probe secure vs public |
| `loadTemplateFunctionsMeta` | 1 | `template_functions_list` |
| Per-collection `list_entries` | **3 serial** | One await per collection |
| Zero-arg template functions | **N serial** | One POST per function |
| `preprocessTemplateFunctionCalls` | **M serial** | One POST per distinct call |
| Prefetch nodes | **P serial** | One request per prefetch attribute |

**Example (3 collections, 2 zero-arg functions, 4 preprocess calls, 1 prefetch):**  
`1 + 3 + 2 + 4 + 1 = 11` round-trips, dominated by collection + template-function latency stacked serially.

Worst case grows as **O(collections + template_functions + prefetches)** with each adding full RTT.

## After M4 (batched + parallel)

| Phase | Requests | Notes |
|-------|----------|-------|
| `detectApiEndpoint` | 0–1 | Unchanged |
| **`loadPageBundle`** | **1** | POST `api/page_bundle.php` — all `list_entries` + static prefetches coalesced |
| `loadTemplateFunctionsMeta` | 1 | Unchanged |
| Per-collection fetch in `processTemplates` | **0** | Served from `Birkly.pageBundle` cache via `fetchContent()` |
| Zero-arg template functions | **1 wall-clock** | `Promise.all` — parallel POSTs |
| `preprocessTemplateFunctionCalls` | **1 wall-clock** | Batched via `scheduleTemplateCall` + `Promise.all` |
| Prefetch nodes | **1 wall-clock** | `performPrefetches` uses `Promise.all` |

**Same example:**  
`1 (bundle) + 1 (meta) + 1 (parallel TF batch) + 1 (parallel preprocess batch) + 1 (parallel prefetch) ≈ 5` round-trips, with collections folded into the single bundle.

### Reduction

| Metric | Before | After | Change |
|--------|--------|-------|--------|
| Collection list reads | 3 serial | 1 bundled | **−2 RTTs**, parallelized wall-clock |
| Template-function wall-clock | serial stack | parallel batches | **~N→1** wall-clock per phase |
| Total example RTTs | ~11 | ~5 | **~55% fewer** |

## How to measure locally

1. Serve CMS: `php -S localhost:8080 -t . server-router.php` from Birkly root.
2. Open a template-first page with `?__dbg=1` (enables `Birkly.log`).
3. In DevTools → Network, filter `api/` and count requests between navigation and `birkly:render-complete` (if dispatched) or DOM settle.
4. Compare branch `main` (pre-M4) vs `cursor/m4-client-performance-402e`.

### Bundle endpoint smoke

```bash
curl -s -X POST http://localhost:8080/api/page_bundle.php \
  -H 'Content-Type: application/json' \
  -d '{"language":"en","requests":[
    {"type":"list_entries","into":"blog","collection":"blog"},
    {"type":"list_entries","into":"site_elements","collection":"site_elements"}
  ]}' | jq '.status, (.data | keys)'
```

On localhost, domain auth is bypassed via `birkly_request_is_local_dev()`.

### Hydrate path (baked pages)

Baked pages (`<meta name="birkly-baked" content="1">`) should load `birkly-hydrate.js` instead of the full client. Hydrate keeps publish-time HTML and skips live API round-trips. When no snapshot exists, hydrate falls back to `birkly-client.js`.

The full client also calls `tryLoadPageSnapshot()` before `loadPageBundle()` so snapshot cache is used when available.

## Acceptance mapping (M4)

- **M4-T1** — Parallel collection fetch + bundle pre-load in `init()`
- **M4-T2** — `Promise.all` for prefetches, hydrate, and preprocess TF calls
- **M4-T4** — `page_bundle.php` / `page_snapshot.php` with domain auth + collection permission checks
- **M4-T5** — This document
