# P24 — Verification matrix (Studio visual excellence)

**Date:** 2026-07-08 · **Phase:** P24  
**Plan:** `docs/reviews/P24-studio-visual-excellence-plan.md`  
**Closure:** P24-M20 — pending

## Render stories (STU-RENDER) — P0

| ID | Story | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| STU-RENDER-01 | Compiler never emits `{end}` | `{endfor}`/`{endget}` only | pending | `studio_compiler_canonical_test.php` |
| STU-RENDER-02 | `{from … get entry …}` uses `{endget}` | Canonical block | pending | compiler test |
| STU-RENDER-03 | `studio_view.php` renders entry titles | No raw `{for each` | pending | integration curl |
| STU-RENDER-04 | `/site/{slug}/` SSR renders bindings | Real HTML content | pending | `studio_renderer_test.php` |
| STU-RENDER-05 | Open project ↗ shows rendered site | No template literals visible | pending | manual + smoke |
| STU-RENDER-06 | StudioTemplateEngine parity | P20 matrix Studio rows | pending | parity tests |
| STU-RENDER-07 | Preview pane uses birkly-client.js | Live data in IDE | pending | network check |
| STU-RENDER-08 | Field refs in loops use `{ title }` syntax | Canonical spacing | pending | compile fixture |

## UX / layout stories (STU-UX)

| ID | Story | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| STU-UX-01 | UI redesign — professional panels | design-reviewer pass | pending | |
| STU-UX-02 | Visual \| Code \| Split toolbar | Prominent toggle | pending | |
| STU-UX-03 | Column resize drag | Width % updates | pending | |
| STU-UX-04 | Box model margin/padding drag | Canvas overlay | pending | |
| STU-UX-05 | Align/distribute toolbar | Flex/grid CSS | pending | |
| STU-UX-06 | CSS rules panel | Add/edit `custom.css` | pending | |
| STU-UX-07 | Class manager autocomplete | Kit classes | pending | |
| STU-UX-08 | Stylesheet tabs | Custom/generated/tokens | pending | |
| STU-UX-09 | Binding wizard v2 | Field picker | pending | |
| STU-UX-10 | Navigation builder | `project.json` nav | pending | |
| STU-UX-11 | Page SEO settings | meta title/desc | pending | |
| STU-UX-12 | Canvas selection chrome | Ring + handles | pending | |

## Regression

| ID | Check | Result | Evidence |
|----|-------|--------|----------|
| PR-01 | P23 STU-IDE-01…10 | pending | |
| PR-02 | `performance_budget_test.php` | pending | |

## Video

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| V-STU-03 | Fix render bug + build page with layout/CSS tools | pending | |
