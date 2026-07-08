# Kekaba index — API round-trip benchmark v2 (P21)

Reference pattern for measuring client rendering performance on template-first pages.

**Supersedes:** v1 “≤5 RTTs” model — P21 targets **≤2 total cold RTTs** for typical index pages via bundle v2.

## Page profile (blog index analogue)

| Data need | P20 (v1) | P21 (v2 bundle) |
|-----------|----------|-------------------|
| `site_elements` | In `page_bundle` | In `page_bundle` |
| `services`, `blog` | In `page_bundle` | In `page_bundle` |
| Template functions meta | Separate GET | **In `page_bundle`** (`__tf_meta__`) |
| Prefetch TF (`upcoming_events`) | Separate POST | **In `page_bundle`** |
| Snapshot probe | Always tried | **Opt-in only** (`meta name=birkly-snapshot`) |
| Repeat visit | Re-fetch | **sessionStorage + ETag 304** |

## Cold load (after P21)

| Phase | Requests | Notes |
|-------|----------|-------|
| `detectApiEndpoint` | 0–1 | Cached in session after first visit |
| **`loadPageBundle`** | **1** | GET or POST — collections + TF meta + prefetch TF |
| `loadTemplateFunctionsMeta` | **0** | Served from bundle |
| Per-collection `list_entries` | **0** | Bundle cache via `fetchContent()` |
| Template function calls (prefetched) | **0** | Bundle cache |
| Snapshot probe (live default) | **0** | Skipped unless opt-in |

**Typical blog index:** `1 (bundle) + 0–1 (endpoint probe) ≈ 1–2 total RTTs`

**Plugin index (yoga/events):** `1 (bundle includes upcoming_events) ≈ 1–2 total RTTs`

## Repeat visit

| Phase | Requests |
|-------|----------|
| sessionStorage hit | **0** content RTTs (instant render) |
| Background revalidate with `If-None-Match` | **0–1** (304 Not Modified) |

## Performance budgets (CI)

See `tests/performance_budget_test.php` and `docs/reviews/P21-verification-matrix.md` § PB-01…PB-06.

| Profile | Content RTTs (cold) | Total RTTs (cold) |
|---------|---------------------|-------------------|
| Blog index | ≤1 | ≤2 |
| Slug detail | ≤1 | ≤2 |
| Plugin index | ≤1 | ≤3 |
| Influencer | ≤2 | ≤3 |

## How to measure locally

1. Serve CMS: `php -S localhost:8080 -t . server-router.php` from Birkly root.
2. Open `reference-projects/blog/website/index.html?__dbg=1`
3. DevTools → Network → filter `page_bundle` — expect **one** request on cold load.
4. Reload — expect **304** or **0** bundle requests (session cache).

### Bundle v2 smoke

```bash
curl -s -X POST http://localhost:8080/api/page_bundle.php \
  -H 'Content-Type: application/json' \
  -d '{"language":"en","lean":true,"requests":[
    {"type":"list_entries","into":"blog","collection":"blog"},
    {"type":"template_functions_meta","into":"__tf_meta__"}
  ]}' | jq '.status, .revision, (.data | keys)'
```

### ETag revalidate

```bash
REV=$(curl -s -X POST http://localhost:8080/api/page_bundle.php \
  -H 'Content-Type: application/json' \
  -d '{"language":"en","requests":[{"type":"list_entries","into":"blog","collection":"blog"}]}' \
  | jq -r '.revision')
curl -s -o /dev/null -w "%{http_code}" -X POST http://localhost:8080/api/page_bundle.php \
  -H "If-None-Match: $REV" \
  -H 'Content-Type: application/json' \
  -d '{"language":"en","requests":[{"type":"list_entries","into":"blog","collection":"blog"}]}'
# Expect: 304
```

## Video evidence

- **V-PERF-01:** `docs/benchmarks/v-perf-01-evidence.md`
- **V-TPL-01:** `reference-projects/blog/VIDEO.md`

## Optional advanced path

Baked/hydrate pages remain optional for teams that need static-first SEO — see `docs/advanced/production-optimization.md`. **Not required** for P21 budgets.
