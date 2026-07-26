# P45 — Templating, web components, and multi-stack integration

**Status:** Approved (2026-07-26, Keven) — **next active phase**  
**Repos:** Birkly (engine + client + AI pack) · Birkly-Docs (integration guides) · Birkly-Website (reference migration)  
**Handoff:** `docs/reviews/P45-lead-engineer-handoff.md`  
**Matrix:** `docs/reviews/P45-verification-matrix.md`  
**Gap register:** `docs/reviews/P45-gap-register.md`

---

## Problem

Birkly’s **headless promise** (“any stack, HTML-first”) is undermined by three gaps:

1. **Web components feel broken** — Users expect `<birkly-header></birkly-header>` once per page, with CMS content defined inside the component file. Today they must repeat `{from …}` template blocks on every page, or use components that wipe `innerHTML` and destroy templates.

2. **AI and docs teach wrong syntax** — `docs/ai/birkly-context-pack.md` and `get_project_info` document `{entry …}{endentry}`, `{title truncate 80}`, and `data-birkly-region` without matching engine support. AI-built sites fail silently.

3. **No stack map** — Birkly-Docs covers static HTML + client well, but lacks clear paths for web components, React/Vue SPAs, and SSR (PHP, Astro, Next). Developers guess; non-developers get contradictory advice.

---

## Vision

**One mental model, multiple integration paths — all documented and tested.**

| User | Primary path | What they write |
|------|--------------|-----------------|
| HTML editor | Static HTML in `project/` | `{for each …}` in page HTML |
| Component author | One-line tags | `<birkly-region collection="site_elements" entry="header"></birkly-region>` |
| React / Vue dev | Public API + optional helpers | `useBirklyEntry('blog', slug)` or `fetch` + render |
| SSR / build-time | Public API or `page_bundle.php` | Same template syntax server-side or JSX from JSON |

**Product rule:** No pattern is “the real one” and others are hacks. Each path is official, with docs and a reference snippet.

---

## Locked decisions (P45-D1–D12) — propose for approval

| ID | Decision |
|----|----------|
| **P45-D1** | Promote `docs/TEMPLATE_LANGUAGE_SPEC.md` to **v1.0** at P45-M12; single syntax for public client |
| **P45-D2** | Ship **`<birkly-region>`** in the client bundle — one tag per CMS block, no per-page template repetition |
| **P45-D3** | Region attributes: `collection`, `entry` (slug), optional `template` (inline fallback), optional `field` (single field mode) |
| **P45-D4** | `Birkly.processTemplates({ root?: Element, silent?: boolean })` — scoped re-render; default root = `document` |
| **P45-D5** | Auto-run `processTemplates({ root: element })` when a `birkly-region` (or `data-birkly-region`) connects |
| **P45-D6** | **Light DOM** = template language; **Shadow DOM** = fetch API + `Birkly.render()` only (document explicitly) |
| **P45-D7** | Remove or fix all **phantom syntax** in AI pack, MCP cheat sheet, and Birkly-Docs before M2 engine work |
| **P45-D8** | Implement `data-birkly-region` as alias for explicit region roots (or deprecate doc-only mention) |
| **P45-D9** | Optional `birkly-components.js` (or module in client): enhance-only helpers — **never** replace children in `connectedCallback` |
| **P45-D10** | Birkly-Docs: **“Choose your stack”** hub + 4 integration guides (HTML, web components, SPA, SSR) |
| **P45-D11** | Migrate **Birkly-Website** header/footer to `<birkly-region>` or enhance-only pattern — reference implementation |
| **P45-D12** | Notify human **once** at P45-M12 closure; qa-verifier runs V-TPL matrix |

---

## `<birkly-region>` — target behaviour

### Single entry (header, hero, footer)

```html
<birkly-region collection="site_elements" entry="header"></birkly-region>
```

Component file (once) defines default markup with template slots; CMS entry fields fill `{ logo }`, `{ nav_html }`, etc. Page HTML has **no** `{from …}` duplication.

### List (blog index)

```html
<birkly-region collection="blog" list ordered="order"></birkly-region>
```

Uses bundled list template inside the component definition (or project-level partial).

### Custom markup in page (still supported)

```html
<birkly-region collection="beta_hero" entry="main">
  <h1>{h1}</h1>
  <p>{subtitle}</p>
</birkly-region>
```

Inner HTML is the template; region fetches entry and renders **children only** (enhance, do not replace wrapper).

---

## Engine changes (Birkly repo)

| Area | Change |
|------|--------|
| `birkly-client.js` | Scoped `processTemplates`, connect lifecycle, `<birkly-region>` |
| `birkly-client-modules/` | Keep render engine shared; optional split `birkly-region.js` |
| `discoverPageRegions` | Respect `data-birkly-region`; skip elements already processed |
| Events | `birkly:region-processed` per element; keep `birkly:templates-processed` global |
| AI | `docs/ai/birkly-context-pack.md` — canonical syntax only |
| MCP | `core/project_site_filesystem.php` `get_project_info` templating cheat sheet |
| Admin | Optional template validator in Project tab (M7) |
| Tests | `tests/template_regions_test.js`, new `birkly_region_test.js`, stack smoke |

---

## Documentation changes (Birkly-Docs)

| Doc | Purpose |
|-----|---------|
| `04-integration/choose-your-stack.md` | Decision tree: HTML vs components vs SPA vs SSR |
| `04-integration/html-and-templates.md` | Default path — expand templating overview link |
| `04-integration/web-components.md` | `<birkly-region>`, enhance-only custom elements |
| `04-integration/spa-react-vue.md` | Public API, CORS, no `{for each}` in JSX |
| `04-integration/ssr-and-build-time.md` | PHP, Astro, Next — `page_bundle`, build fetch |
| `03-templating/canonical-syntax.md` | Fix `data-birkly-region`; document region component |
| `03-templating/web-components.md` | Patterns, anti-patterns (innerHTML wipe) |

Sync HTML exports after markdown changes (existing docs pipeline).

---

## Milestones

| ID | Focus | Owner | Status |
|----|--------|-------|--------|
| **P45-M0** | Plan, gap register, matrix, handoff, roadmap | product-planner | **this PR** |
| **P45-M1** | Docs + AI truth — remove phantom syntax | docs-writer + cms-developer | pending |
| **P45-M2** | `processTemplates({ root })` + region discovery hardening | cms-developer | pending |
| **P45-M3** | `<birkly-region>` element — entry + list modes | cms-developer | pending |
| **P45-M4** | Connect lifecycle + `data-birkly-region` | cms-developer | pending |
| **P45-M5** | `birkly-components.js` enhance-only helpers (nav active state) | frontend-developer | pending |
| **P45-M6** | Template language spec v1.0 | docs-writer | pending |
| **P45-M7** | Template validator (CLI or Project tab) | cms-developer | pending |
| **P45-M8** | Birkly-Docs: HTML + web components guides | docs-writer | pending |
| **P45-M9** | Birkly-Docs: SPA guides + optional `birkly-client` helpers doc | docs-writer | pending |
| **P45-M10** | Birkly-Docs: SSR / build-time guide | docs-writer | pending |
| **P45-M11** | Automated tests + V-TPL matrix | qa-verifier | pending |
| **P45-M12** | Birkly-Website reference migration + closure | lead-engineer | pending |

**Depends on:** P44 complete  
**Parallel OK:** M8–M10 docs can start after M1 (syntax locked); engine M2–M5 before reference migration M12.

---

## Out of scope (P45)

- Admin PHP template parser rewrite (`eval` removal) — separate track (`admin-template-eval-replacement-plan.md`)
- New npm build step for `project/` sites
- Shadow DOM templating inside closed components
- Studio P26 · P42 marketing template pack

---

## Success criteria (phase closure)

1. Non-developer can use `<birkly-region collection="…" entry="…">` on every page with **one line** — no repeated `{from}` blocks.
2. AI context pack and MCP cheat sheet match engine — zero phantom directives.
3. Birkly-Docs “Choose your stack” page exists; four integration paths documented with working examples.
4. V-TPL matrix green in CI (`tests/run` + node region tests).
5. Birkly-Website demonstrates official pattern (no `innerHTML` wipe of CMS content).

---

## Branch template

`cursor/p45-m[N]-<slug>-030a`
