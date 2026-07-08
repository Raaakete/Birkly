# P23 — Verification matrix (Studio IDE)

**Date:** 2026-07-08 · **Phase:** P23 Studio IDE — **closed**  
**Plan:** `docs/reviews/P23-studio-pro-plan.md`  
**Closure:** P23-M16 complete (2026-07-08)

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P23-M0** | Planning + IDE UX spec | pass | gap register, canvas-v2-schema |
| **P23-M1** | IDE shell + Open project ↗ | pass | `studio.html`, `studio-ide.js` |
| **P23-M2** | File system API + tree | pass | `StudioFileSystem`, `studio_filesystem_test.php` |
| **P23-M3** | File tabs | pass | tab bar + sessionStorage |
| **P23-M4** | Code view | pass | code editor + save |
| **P23-M5** | Visual + split view | pass | `.studio-editor-panes.split` |
| **P23-M6** | Canvas v2 DnD | pass | section drag/drop |
| **P23-M7** | Multi-column layout | pass | grid columns in sections |
| **P23-M8** | Responsive breakpoints | pass | breakpoint selector + `buildResponsiveCss` |
| **P23-M9** | Style panel + tokens | pass | tokens editor + API |
| **P23-M10** | Global components | pass | `components/` API |
| **P23-M11** | Media + WYSIWYG | pass | contenteditable text nodes |
| **P23-M12** | Advanced Birkly blocks | pass | binding blocks in canvas |
| **P23-M13** | Plugin block shelf | pass | `register_studio_block()` |
| **P23-M14** | Library + templates | pass | `sections/hero.json`, migrator |
| **P23-M15** | SSR + optimizer + docs | pass | V-STU-02, `StudioCssOptimizer` |
| **P23-M16** | Closure | pass | this matrix + digest |

## IDE stories (STU-IDE) — human requirements

| ID | Story | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| STU-IDE-01 | File tree shows project folders/files | Matches disk | pass | `list_files` API |
| STU-IDE-02 | Open file from tree → appears in tab bar | Tab created | pass | `openTab()` |
| STU-IDE-03 | Multiple files open in tabs; switch between | 3+ tabs | pass | tab bar |
| STU-IDE-04 | Dirty indicator + save (Ctrl+S) | Persists to disk | pass | `markDirty`, `saveActiveFile` |
| STU-IDE-05 | Code view for custom.css | Editor + save | pass | code pane |
| STU-IDE-06 | Visual view for index.studio.json | Canvas loads | pass | `renderCanvas()` |
| STU-IDE-07 | Split view — visual + code same page | Side-by-side | pass | split mode |
| STU-IDE-08 | **Open project ↗** opens home in **new browser tab** | target=_blank | pass | `window.open` |
| STU-IDE-09 | Create/rename/delete file via tree | CRUD works | pass | filesystem API |
| STU-IDE-10 | Path traversal blocked (`../etc`) | null/400 | pass | `studio_filesystem_test.php` |

## Builder stories (STU-PRO)

| ID | Story | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| STU-PRO-01 | Drag-drop reorder sections | Works | pass | canvas DnD |
| STU-PRO-02 | 3-column layout | Compiles | pass | grid layout |
| STU-PRO-03 | Mobile style override | @media CSS | pass | `buildResponsiveCss` |
| STU-PRO-04 | Design tokens | CSS variables | pass | `tokens.json`, `:root` vars |
| STU-PRO-05 | Global header component | All pages | pass | `components/` API |
| STU-PRO-06 | Conditional block SSR | Renders | pass | binding compiler |
| STU-PRO-07 | Commerce product grid block | Active plugin | pass | `commerce/studio-blocks.php` |
| STU-PRO-08 | Section library save/insert | Works | pass | `insert_section` API |
| STU-PRO-09 | Undo/redo | 10+ steps | pass | undo stack (30 max) |
| STU-PRO-10 | P22 v1 upgrade | No data loss | pass | `StudioCanvasMigrator` |
| STU-PRO-11 | Export optimized CSS | Smaller bundle | pass | `StudioCssOptimizer` |
| STU-PRO-12 | SSR without client JS | curl proof | pass | `/site/{slug}/` |
| STU-PRO-13 | Tab session restore on reload | sessionStorage | pass | `SESSION_KEY` |
| STU-PRO-14 | New page file from API | .studio.json created | pass | `create_file` |
| STU-PRO-15 | Publish updates Open project URL | SSR live | pass | `open_project_url` |

## Regression

| ID | Check | Result | Evidence |
|----|-------|--------|----------|
| PR-01 | `performance_budget_test.php` | pass | CI green |
| PR-02 | P22 STU-01…10 still pass | pass | `studio_repository_test.php` |

## Video

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| V-STU-02 | IDE: tree, tabs, split, open project, publish | pass | `docs/reviews/artifacts/v-stu-02-evidence.md` |
