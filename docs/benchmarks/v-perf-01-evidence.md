# V-PERF-01 — Live client performance evidence

**Status:** Recorded via reproducible benchmark (P21-M9)  
**Story:** Prove typical reference blog index meets P21 cold-load budget (≤2 total RTTs, ≤1 content RTT).

## Setup

```bash
cd /path/to/Birkly
./scripts/install-reference-project.sh blog
php -S localhost:8080 -t . server-router.php
```

Open: `http://localhost:8080/reference-projects/blog/website/index.html?__dbg=1`

## Expected Network profile (cold load)

| # | Request | Purpose |
|---|---------|---------|
| 1 | `api/page_bundle.php` (GET or POST) | All collections + `__tf_meta__` in one bundle |
| 0–1 | `api/public.php?action=…` | Endpoint probe (first visit only) |

**Not expected on live default path:**

- `page_snapshot.php` (snapshot opt-in only)
- `template_functions_list` (included in bundle)
- Per-collection `list_entries` calls

## Repeat visit

1. Reload same URL within session  
2. Expect: **no full bundle download** — sessionStorage cache and/or **304** on `If-None-Match`

## CI reproduction

```bash
php tests/performance_budget_test.php
php tests/client_bundle_fixture_test.php
```

## Manual recording checklist (video)

1. Show reference blog index URL  
2. Open DevTools → Network, disable cache  
3. Hard reload — count `page_bundle` requests (should be **1**)  
4. Reload again — show 304 or zero bundle bytes  
5. Edit a blog post in admin → refresh → content updates without bake  

## curl evidence (headless)

```bash
# Bundle v2 with revision
curl -s -X POST http://localhost:8080/api/page_bundle.php \
  -H 'Content-Type: application/json' \
  -d '{"language":"en","lean":true,"requests":[
    {"type":"list_entries","into":"blog","collection":"blog"},
    {"type":"list_entries","into":"services","collection":"services"},
    {"type":"list_entries","into":"site_elements","collection":"site_elements"},
    {"type":"template_functions_meta","into":"__tf_meta__"}
  ]}' | jq '{status, revision, keys: (.data|keys)}'
```

Save screen recording as `docs/benchmarks/artifacts/v-perf-01-demo.mp4` when capturing manually.
