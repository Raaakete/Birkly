# Phase 52 — Verification matrix

**Plan:** `P52-php-native-ssr-plan.md`  
**Status:** Pending — proposed 2026-08-01

Legend: **pass** | **fail** | **skip** | **manual** | **pending**

---

## V-PHP — PHP template engine (M1)

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-PHP-01 | `birkly_template_engine_render($html, $data, $options)` exists | Unit | cms-developer | pending |
| V-PHP-02 | Loops + filters + limit match JS fixtures | PHP + JS dual | cms-developer | pending |
| V-PHP-03 | Ordering: newest / oldest / last-updated / order | Dual | cms-developer | pending |
| V-PHP-04 | from…get block + inline | Dual | cms-developer | pending |
| V-PHP-05 | if / else / has / exist / comparisons | Dual | cms-developer | pending |
| V-PHP-06 | Date `format` filter | Dual | cms-developer | pending |
| V-PHP-07 | Sub-collections `of` / `.current` | Dual | cms-developer | pending |
| V-PHP-08 | System vars: language, query, current_year | Dual | cms-developer | pending |
| V-PHP-09 | HTML escaping + trusted content/html paths | Dual | cms-developer | pending |
| V-PHP-10 | `{entry for}` / public form → placeholders (not executed as PHP) | Dual | cms-developer | pending |
| V-PHP-11 | `<style>` / `<script>` brace bodies preserved | Dual | cms-developer | pending |
| V-PHP-12 | Byte-identical (or approved normalize) PHP vs JS on full fixture set | Parity harness | cms-developer | pending |

---

## V-CUT — SSR cutover (M2)

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-CUT-01 | Serve path calls PHP engine (no Node on happy path) | Code + integration | cms-developer | pending |
| V-CUT-02 | `X-Birkly-Render: server` with Node absent from PATH | Integration | cms-developer | pending |
| V-CUT-03 | Engine exception → client-fallback + static HTML | Integration | cms-developer | pending |
| V-CUT-04 | Cache HIT/MISS headers unchanged | Integration | cms-developer | pending |
| V-CUT-05 | Entry save still invalidates SSR cache | Hook test | cms-developer | pending |
| V-CUT-06 | Draft/unpublished not leaked | Security | cms-developer | pending |
| V-CUT-07 | External / client mode never SSR | Integration | cms-developer | pending |
| V-CUT-08 | `project_ssr_test.php` green without Node | CI | cms-developer | pending |

---

## V-DEF — Defaults (M3)

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-DEF-01 | Fresh defaults: `render_mode=server` | Unit | cms-developer | pending |
| V-DEF-02 | Existing saved `client` not overwritten | Migration test | cms-developer | pending |
| V-DEF-03 | Admin UI presents Server as default/recommended | UI | frontend-developer | pending |
| V-DEF-04 | `auto` prefers server when PHP engine available | Integration | cms-developer | pending |
| V-DEF-05 | Birkly hosting + project mode → SSR on by default | Smoke | qa-verifier | pending |
| V-DEF-06 | External hosting still cannot SSR | API + UI | cms-developer | pending |

---

## V-OPS — Ops / Docker / docs (M3)

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-OPS-01 | Dockerfile has no `nodejs` package | File review | cms-developer | pending |
| V-OPS-02 | Admin runtime status has no Node requirement | UI + API | frontend-developer | pending |
| V-OPS-03 | Docs state PHP-only SSR for localhost + Docker | Docs | docs-writer / cms | pending |
| V-OPS-04 | `node_missing` fallback reason gone from happy diagnostics | Code | cms-developer | pending |

---

## V-DEL — Delete Node surface (M4)

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-DEL-01 | No `proc_open` to Node on SSR path | Code grep | cms-developer | pending |
| V-DEL-02 | `birkly-ssr-cli.js` removed or clearly non-runtime | Repo | cms-developer | pending |
| V-DEL-03 | `BIRKLY_NODE_BIN` unused / undocumented for SSR | Code + docs | cms-developer | pending |
| V-DEL-04 | CI SSR jobs do not install Node for render | CI config | cms-developer | pending |

---

## V-CI — Closure (M5)

| ID | Check | Method | Owner | Status |
|----|-------|--------|-------|--------|
| V-CI-01 | Full matrix green | qa-verifier | qa-verifier | pending |
| V-CI-02 | Localhost PHP built-in server SSR smoke (no Node) | Manual/script | qa-verifier | pending |
| V-CI-03 | Perf note: PHP MISS vs prior Node spawn | Doc | lead-engineer | pending |
| V-CI-04 | Security note refresh (escape, drafts, cache) | Doc | lead-engineer | pending |
| V-CI-05 | ROADMAP marks P52 complete | ROADMAP | lead-engineer | pending |
