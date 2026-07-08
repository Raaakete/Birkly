# P21 — Performance excellence (live path)

**Status:** Approved (2026-07-08, Keven)  
**Baseline:** P20 complete — live `birkly-client.js`, `page_bundle`, six reference projects, 141+ test suites  
**Human rule:** Execute P21-M0→M10 autonomously. Notify human **once** at **P21-M10** only.

**Handoff:** `docs/reviews/P21-lead-engineer-handoff.md`  
**Gap register:** `docs/reviews/P21-performance-gap-register.md`  
**Verification:** `docs/reviews/P21-verification-matrix.md`

---

## Vision

Make Birkly **measurably above average** for a no-build, template-first CMS: fast first paint, minimal API chatter, strong repeat-visit performance — **without** asking users to run bake scripts, install Node, or change their workflow.

Upload HTML → add one script tag → publish in admin → refresh. That stays the entire user story.

## Principles (non-negotiable)

| Principle | P21 rule |
|-----------|----------|
| Dependency-free core | PHP + vanilla JS only; no npm/composer in Birkly |
| No extra user steps | No mandatory bake, hydrate, CI render, or terminal scripts for site owners |
| Platform-first | All wins land in `birkly-client.js` + `api/page_bundle.php`; no per-site fetch hacks |
| Live client default | Canonical: `<script src="/birkly-client.js" data-cms-url="…"></script>` |
| Admin edit → refresh | Content changes visible on browser refresh without re-bake |
| Security by default | Cache keys respect domain auth + collection permissions |

Hydrate/snapshot/bake remain **documented optional advanced** paths (`docs/advanced/production-optimization.md`) — never required for performance targets.

## Performance budgets (replace “≤5 RTTs”)

Measure **RTTs**, **payload KB**, and **repeat-visit cache hits** — not RTTs alone.

| Profile | Cold load (content) | Cold load (total) | Repeat visit (content) | Bundle size (typical index) |
|---------|---------------------|-------------------|------------------------|----------------------------|
| **Blog index** (3 collections) | ≤1 RTT | ≤2 RTTs | 0 RTTs (cache hit) | ≤150 KB JSON |
| **Slug detail** (get_entry) | ≤1 RTT | ≤2 RTTs | 0 RTTs | ≤30 KB JSON |
| **Plugin index** (events/commerce TF) | ≤1 RTT | ≤3 RTTs | 0 RTTs | ≤200 KB JSON |
| **Influencer** (all plugins) | ≤2 RTTs | ≤3 RTTs | 0–1 RTTs | ≤250 KB JSON |

**Total RTT** includes endpoint discovery (0–1) on first visit only. **Content RTT** = data needed to render templates (bundle + nothing else on the happy path).

## Locked decisions

| ID | Decision |
|----|----------|
| P21-D1 | **Zero new user steps** — performance is automatic from core; no bake/hydrate/build required |
| P21-D2 | **Bundle v2** — one `page_bundle` request carries collections, `get_entry`, sub-entries, template-function calls, and TF metadata |
| P21-D3 | **Snapshot probe opt-in** — `tryLoadPageSnapshot()` only when page opts in; live default skips |
| P21-D4 | **GET + ETag** for cacheable bundle reads (POST remains for mutations/write paths) |
| P21-D5 | **Client session cache** — `sessionStorage` keyed by page + lang + content revision; bust on publish |
| P21-D6 | **CI performance gate** — regression tests enforce RTT/payload budgets on reference projects |
| P21-D7 | **Video evidence** — at minimum V-TPL-01 + V-PERF-01 (network panel proof); stubs → recordings |
| P21-D8 | Single human notification at **P21-M10** only |

## Milestones

| Milestone | Deliverable |
|-----------|-------------|
| **P21-M0** | Performance gap register + budget doc + benchmark v2 outline |
| **P21-M1** | **Bundle v2** — `template_function` + `template_functions_meta` in `page_bundle` runner + API |
| **P21-M2** | **Unified template scan** — `collectBundleRequests()` gathers collections, slug entries, prefetches, and TF calls before any network |
| **P21-M3** | **Snapshot probe opt-in** — skip wasted RTT on live default path |
| **P21-M4** | **GET `page_bundle` + ETag** — browser/CDN cache for repeat visits; revision hash in URL |
| **P21-M5** | **Client session cache** — instant back-navigation; bust on content revision |
| **P21-M6** | **Lean payloads** — strip unused entry fields in bundle responses (server-side, safe defaults) |
| **P21-M7** | **CI performance gate** — `tests/performance_budget_test.php` + harness RTT count |
| **P21-M8** | **Docs + benchmark v2** — update `kekaba-index-roundtrips.md`, Birkly-Docs, reference README |
| **P21-M9** | **Video evidence** — record V-TPL-01 + V-PERF-01 (DevTools network proof) |
| **P21-M10** | Closure matrix green, tests green, single human notification |

## Technical outline

### Bundle v2 request shape (M1)

```json
{
  "language": "en",
  "page": "index",
  "requests": [
    { "type": "list_entries", "into": "blog", "collection": "blog" },
    { "type": "get_entry", "into": "__entry__:blog:slug", "collection": "blog", "slug": "welcome" },
    { "type": "sub_entries", "into": "comments", "parent_collection": "blog", "parent_entry_id": "…", "sub_collection": "comments" },
    { "type": "template_function", "into": "upcoming", "name": "upcoming_events", "args": [6] },
    { "type": "template_functions_meta" }
  ]
}
```

Response includes `revision` (content hash) for cache busting.

### Init waterfall (target after P21)

```
detectApiEndpoint (0–1, cache endpoint in sessionStorage)
  → loadPageBundle (1)     ← everything above
  → render from bundle cache
  → (no separate TF list, no snapshot probe on live default)
```

### What we explicitly do NOT do

- Mandatory bake/hydrate pipelines
- npm/webpack in core
- Per-site performance scripts (kekaba-style workarounds)
- GraphQL or new protocol dependencies
- Service worker as requirement (optional future enhancement)

## Dependencies

```
M0 → M1 → M2 → M3 ∥ M4 → M5 → M6 → M7 → M8 → M9 → M10
```

M3 and M4 can run in parallel after M2.

## Branch naming

`cursor/p21-m[N]-<slug>-030a`

## Success criteria (M10)

- All reference-project index pages meet cold **content ≤1–2 RTT** budgets in CI harness
- Repeat-visit harness shows **0 content RTTs** when revision unchanged
- No new user-facing setup steps in README / START_HERE / reference-projects
- `docs/benchmarks/kekaba-index-roundtrips.md` updated to v2 targets
- At least one recorded walkthrough with Network panel evidence (V-PERF-01)
