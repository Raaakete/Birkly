# P45 — Verification matrix (templating & multi-stack)

**Plan:** `docs/reviews/P45-templating-and-stacks-plan.md`  
**Status:** Pending execution

---

## Scoped processing (engine)

| ID | Scenario | Steps | Expected | Milestone | Status |
|----|----------|-------|----------|-----------|--------|
| V-TPL-01 | Scoped root | Call `processTemplates({ root: el })` on subtree with `{title}` | Only `el` updated | M2 | pending |
| V-TPL-02 | Late mount | Inject `<div>{for each…}</div>` after init, call scoped process | Renders correctly | M2 | pending |
| V-TPL-03 | data-birkly-region | Element with attribute + templates inside | Region discovered | M4 | pending |
| V-TPL-04 | No double process | Process same region twice | Stable output, no duplicate fetches | M4 | pending |

---

## `<birkly-region>` component

| ID | Scenario | Steps | Expected | Milestone | Status |
|----|----------|-------|----------|-----------|--------|
| V-TPL-05 | Single entry | `<birkly-region collection="site_elements" entry="header">` | Fetches entry, renders default or child template | M3 | pending |
| V-TPL-06 | Entry + child template | Region with inner `{h1}` etc. | Child template rendered with entry data | M3 | pending |
| V-TPL-07 | List mode | `<birkly-region collection="blog" list>` | All published entries rendered | M3 | pending |
| V-TPL-08 | Ordered list | `ordered="order"` attribute | Sorts by `order` field | M3 | pending |
| V-TPL-09 | One line per page | Same header tag on 3 HTML files, no `{from}` in pages | Identical header from CMS | M3 | pending |
| V-TPL-10 | Connect lifecycle | Region added via `document.body.appendChild` | Auto-processes without manual call | M4 | pending |

---

## Anti-patterns (must fail docs / pass after fix)

| ID | Scenario | Steps | Expected | Milestone | Status |
|----|----------|-------|----------|-----------|--------|
| V-TPL-11 | innerHTML wipe | Custom element replaces children after Birkly ran | Docs warn; reference site fixed | M5, M12 | pending |
| V-TPL-12 | Phantom syntax | Run validator on `{entry 'x'.'y'}{endentry}` | Error reported | M7 | pending |
| V-TPL-13 | Phantom truncate | `{title truncate 80}` | Error or documented filter | M7 | pending |

---

## AI / MCP truth

| ID | Scenario | Steps | Expected | Milestone | Status |
|----|----------|-------|----------|-----------|--------|
| V-TPL-14 | Context pack | Grep AI pack for `endentry` | Zero matches | M1 | pending |
| V-TPL-15 | get_project_info | MCP resource templating section | Uses `{from…get}` only | M1 | pending |

---

## Stack guides (manual + link check)

| ID | Scenario | Steps | Expected | Milestone | Status |
|----|----------|-------|----------|-----------|--------|
| V-TPL-16 | Choose stack hub | Open Birkly-Docs choose-your-stack | 4 paths linked | M8 | pending |
| V-TPL-17 | Web components doc | Follow web-components.md example | Page renders with one-line region | M8 | pending |
| V-TPL-18 | SPA doc | React example uses API, not `{for each}` in JSX | Clear separation | M9 | pending |
| V-TPL-19 | SSR doc | PHP or Astro snippet fetches public API | Runnable pattern | M10 | pending |

---

## Reference site

| ID | Scenario | Steps | Expected | Milestone | Status |
|----|----------|-------|----------|-----------|--------|
| V-TPL-20 | Birkly-Website | Load index.html locally | Hero + header from CMS, no static wipe | M12 | pending |
| V-TPL-21 | kekaba shop | `shop.html` still works | No regression | M12 | pending |

---

## Automated tests

| ID | Command | Expected | Milestone | Status |
|----|---------|----------|-----------|--------|
| V-TPL-22 | `node tests/template_regions_test.js` | PASS | M11 | pending |
| V-TPL-23 | `node tests/birkly_region_test.js` (new) | PASS | M11 | pending |
| V-TPL-24 | `./scripts/ci-check.sh` | PASS | M11 | pending |

---

## Closure

| ID | Scenario | Expected | Milestone | Status |
|----|----------|----------|-----------|--------|
| V-TPL-25 | Spec version | `TEMPLATE_LANGUAGE_SPEC.md` = v1.0 | M6 | **pass** |
| V-TPL-26 | Human notify | Single closure message with matrix summary | M12 | pending |
