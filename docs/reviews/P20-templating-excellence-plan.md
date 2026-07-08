# P20 — Templating excellence & reference projects

**Status:** Approved (2026-07-08, Keven)  
**Baseline:** P19 complete — **138+ test suites** green  
**Human rule:** Execute P20-M0→M12 autonomously. Notify human **once** at **P20-M12** only.

**Handoff:** `docs/reviews/P20-lead-engineer-handoff.md`  
**Gap register:** `docs/reviews/P20-templating-gap-register.md`  
**Parity matrix:** `docs/reviews/P20-js-php-parity-matrix.md`  
**Verification:** `docs/reviews/P20-verification-matrix.md`

---

## Vision

Make Birkly templating **production-ready** for template-first client sites: one canonical live client path, ≤5 API round-trips, six copy-paste reference projects, and no kekaba-style workarounds.

## Locked decisions

| ID | Decision |
|----|----------|
| P20-D8 | Default = live `birkly-client.js` ONLY on reference projects |
| P20-D9 | No hydrate, snapshots, or bake on reference projects |
| P20-D10 | Story requiring hydrate = bug — fix templating/client |
| P20-D11 | Canonical: `<script src="/birkly-client.js" data-cms-url="http://localhost:8080"></script>` |

## Milestones

| Milestone | Deliverable |
|-----------|-------------|
| **P20-M0** | Gap register + JS↔PHP parity matrix + plan docs |
| **P20-M1** | Live-path engine fixes + `page_bundle` / client fixture tests |
| **P20-M2** | `reference-projects/` kit + `scripts/install-reference-project.sh` |
| **P20-M3** | User story catalog (`docs/reference-projects/user-stories.md`) |
| **P20-M4** | Reference: **Blog** + video stub V-TPL-01 |
| **P20-M5** | Reference: **Portfolio** + V-TPL-02 |
| **P20-M6** | Reference: **Yoga** (events plugin) + V-TPL-03 |
| **P20-M7** | Reference: **Coffee** (commerce) + V-TPL-04 |
| **P20-M8** | Reference: **Social** (user + commerce) + V-TPL-05 |
| **P20-M9** | Reference: **Influencer** (all plugins) + V-TPL-06 |
| **P20-M10** | Engine sprint from gap register (sub-collection `of`, parity fixes) |
| **P20-M11** | Birkly-Docs sync + `docs/advanced/production-optimization.md` |
| **P20-M12** | Matrices green, tests green, closure + **single human notification** |

## Reference project rules

1. Templating + `data-birkly-*` only — **no custom `fetch()` JS**
2. No `<meta name="birkly-baked">`, no `birkly-hydrate.js`, no snapshot scripts
3. Slug pages use `meta[name=birkly-page]`, `meta[name=birkly-slug]` or `?slug=` + `meta[name=birkly-collection]`
4. Performance: auto `page_bundle` on init; target ≤5 RTTs (see `docs/benchmarks/kekaba-index-roundtrips.md`)

## Branch naming

`cursor/p20-m[N]-<slug>-030a`

## Dependencies

```
M0 → M1 → M2 → M3 → M4…M9 (parallel per project after M2) → M10 → M11 → M12
```
