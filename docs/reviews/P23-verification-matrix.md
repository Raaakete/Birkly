# P23 — Verification matrix (Studio IDE)

**Date:** 2026-07-08 · **Phase:** P23 Studio IDE  
**Plan:** `docs/reviews/P23-studio-pro-plan.md`  
**Closure:** P23-M16 — pending approval

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P23-M0** | Planning + IDE UX spec | pending | |
| **P23-M1** | IDE shell + Open project ↗ | pending | |
| **P23-M2** | File system API + tree | pending | |
| **P23-M3** | File tabs | pending | |
| **P23-M4** | Code view | pending | |
| **P23-M5** | Visual + split view | pending | |
| **P23-M6** | Canvas v2 DnD | pending | |
| **P23-M7** | Multi-column layout | pending | |
| **P23-M8** | Responsive breakpoints | pending | |
| **P23-M9** | Style panel + tokens | pending | |
| **P23-M10** | Global components | pending | |
| **P23-M11** | Media + WYSIWYG | pending | |
| **P23-M12** | Advanced Birkly blocks | pending | |
| **P23-M13** | Plugin block shelf | pending | |
| **P23-M14** | Library + templates | pending | |
| **P23-M15** | SSR + optimizer + docs | pending | |
| **P23-M16** | Closure | pending | |

## IDE stories (STU-IDE) — human requirements

| ID | Story | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| STU-IDE-01 | File tree shows project folders/files | Matches disk | pending | |
| STU-IDE-02 | Open file from tree → appears in tab bar | Tab created | pending | |
| STU-IDE-03 | Multiple files open in tabs; switch between | 3+ tabs | pending | |
| STU-IDE-04 | Dirty indicator + save (Ctrl+S) | Persists to disk | pending | |
| STU-IDE-05 | Code view for custom.css | Syntax highlight | pending | |
| STU-IDE-06 | Visual view for index.studio.json | Canvas loads | pending | |
| STU-IDE-07 | Split view — visual + code same page | Side-by-side | pending | |
| STU-IDE-08 | **Open project ↗** opens home in **new browser tab** | target=_blank | pending | |
| STU-IDE-09 | Create/rename/delete file via tree | CRUD works | pending | |
| STU-IDE-10 | Path traversal blocked (`../etc`) | 403/400 | pending | |

## Builder stories (STU-PRO)

| ID | Story | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| STU-PRO-01 | Drag-drop reorder sections | Works | pending | |
| STU-PRO-02 | 3-column layout | Compiles | pending | |
| STU-PRO-03 | Mobile style override | @media CSS | pending | |
| STU-PRO-04 | Design tokens | CSS variables | pending | |
| STU-PRO-05 | Global header component | All pages | pending | |
| STU-PRO-06 | Conditional block SSR | Renders | pending | |
| STU-PRO-07 | Commerce product grid block | Active plugin | pending | |
| STU-PRO-08 | Section library save/insert | Works | pending | |
| STU-PRO-09 | Undo/redo | 10+ steps | pending | |
| STU-PRO-10 | P22 v1 upgrade | No data loss | pending | |
| STU-PRO-11 | Export optimized CSS | Smaller bundle | pending | |
| STU-PRO-12 | SSR without client JS | curl proof | pending | |
| STU-PRO-13 | Tab session restore on reload | sessionStorage | pending | |
| STU-PRO-14 | New page wizard from tree | .studio.json created | pending | |
| STU-PRO-15 | Publish updates Open project URL | SSR live | pending | |

## Regression

| ID | Check | Result | Evidence |
|----|-------|--------|----------|
| PR-01 | `performance_budget_test.php` | pending | |
| PR-02 | P22 STU-01…10 still pass | pending | |

## Video

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| V-STU-02 | IDE: tree, tabs, split, open project, publish | pending | |
