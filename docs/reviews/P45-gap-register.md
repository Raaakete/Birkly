# P45 — Gap register (templating & multi-stack)

**Plan:** `docs/reviews/P45-templating-and-stacks-plan.md`  
**Updated:** 2026-07-26

---

## G1 — Web component UX (product)

| ID | Gap | Severity | Target |
|----|-----|----------|--------|
| G1-01 | Repeating `{from …}` on every page for shared header/footer | **High** | P45-M3 `<birkly-region>` |
| G1-02 | `connectedCallback` + `innerHTML = static` destroys templates (Birkly-Website pattern) | **High** | P45-M5 enhance-only + M12 migration |
| G1-03 | No auto-process when component mounts after init | **High** | P45-M4 lifecycle |
| G1-04 | User expectation: define component once in JS file, one tag on pages | **High** | P45-M3 default templates in region definition |

---

## G2 — Engine / client

| ID | Gap | Severity | Target |
|----|-----|----------|--------|
| G2-01 | `processTemplates()` only processes full document | **High** | P45-M2 scoped `root` |
| G2-02 | `data-birkly-region` documented but not implemented | **Med** | P45-M4 implement or remove |
| G2-03 | Shadow DOM not scanned (undocumented policy) | **Med** | **Documented in spec v1.0** (Light DOM vs Shadow DOM); Birkly-Docs web-components.md still M8 |
| G2-04 | `ordered by oldest` sorts `created_at` not custom `order` field — confusing | **Med** | **Documented in spec v1.0** (Ordering / G2-04) |
| G2-05 | Nested regions: parent wins; behaviour undocumented | **Low** | **Documented in spec v1.0** (Nested regions; explicit region demotes ancestor) |
| G2-06 | No per-region processed event | **Low** | P45-M4 `birkly:region-processed` |

---

## G3 — Wrong AI / MCP documentation (causes broken sites)

| ID | Wrong | Correct | Target |
|----|-------|---------|--------|
| G3-01 | `{entry 'blog'.'slug'}{endentry}` | `{from 'blog' get entry 'slug'}…{endget}` | P45-M1 |
| G3-02 | `{title truncate 80}` | Not implemented — use excerpt field or doc future filter | P45-M1 |
| G3-03 | `get_project_info` cheat sheet same wrong `single_entry` | Fix in `project_site_filesystem.php` | P45-M1 |
| G3-04 | AI pack references filters not in `birkly-render.js` | Align or implement | P45-M1 / defer filters to P46 |

---

## G4 — Birkly-Docs coverage

| ID | Gap | Target |
|----|-----|--------|
| G4-01 | No web components integration page | P45-M8 |
| G4-02 | SPA guide one paragraph in website-integration | P45-M9 |
| G4-03 | No SSR / Astro / Next guide | P45-M10 |
| G4-04 | No “choose your stack” decision hub | P45-M8 |
| G4-05 | `canonical-syntax.md` claims `data-birkly-region` without implementation | P45-M4 + M8 |

---

## G5 — Spec & tooling

| ID | Gap | Target |
|----|-----|--------|
| G5-01 | `TEMPLATE_LANGUAGE_SPEC.md` still Draft v0.1 | **Resolved** — Spec v1.0 (P45-M6) |
| G5-02 | No template validator for project files | P45-M7 |
| G5-03 | `site_elements` special-case in bundle prefetch undocumented | **Resolved** — documented in Spec v1.0 (`site_elements` prefetch) |

---

## G6 — Reference implementations

| ID | Gap | Target |
|----|-----|--------|
| G6-01 | Birkly-Website uses anti-pattern header component | P45-M12 |
| G6-02 | kekaba documented as reference but no stack labels | P45-M12 notes in reference-projects |

---

## Resolved by plan (not gaps after P45)

- Nested `{from}` / `{for each}` — **already works** in engine; document in M6
- Public API for SPAs — **exists**; document in M9
- CORS / hosting URLs — **P43 complete**
