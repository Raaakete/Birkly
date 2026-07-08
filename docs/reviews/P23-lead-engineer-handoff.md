# Phase 23 — Lead-engineer handoff (Studio IDE)

**Status:** Approved (2026-07-08, Keven)  
**Baseline:** P22 complete — `plugins/studio` v1, SSR, 145 test suites  
**Human rule:** Execute **P23-M0→M16** autonomously. Notify human **once** at **P23-M16** only.

**Master plan:** `docs/reviews/P23-studio-pro-plan.md`  
**Matrix:** `docs/reviews/P23-verification-matrix.md`

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 23 — Studio IDE (advanced builder + project workspace).

Human approved P23 (2026-07-08). Execute P23-M0→M16 autonomously in one wave.
Notify human ONCE at P23-M16 closure only.

READ FIRST:
1. docs/reviews/P23-lead-engineer-handoff.md
2. docs/reviews/P23-studio-pro-plan.md
3. docs/PROJECT_VALUES.md
4. plugins/studio/ (extend v1 — replace single-page UI with IDE)
5. docs/reviews/P22-studio-gap-register.md
6. fieldtypes/ContentEditor/layout/
7. plugins/commerce/, plugins/events/, plugins/user_management/

MISSION:
Transform Studio into a full PROJECT IDE inside Birkly:
- FILE SYSTEM sidebar (real project tree on disk)
- FILE TABS (multi-file editing, dirty state, session restore)
- VISUAL VIEW (canvas per *.studio.json page)
- CODE VIEW (HTML/CSS/JSON any project file)
- SPLIT VIEW (visual + code side-by-side)
- OPEN PROJECT ↗ button → home page in NEW BROWSER TAB (SSR or preview URL)

PLUS pro builder: DnD canvas, multi-column, responsive, tokens, global components,
plugin blocks, section library, undo/revisions, SSR parity, export CSS optimizer.

HUMAN REQUIREMENTS (non-negotiable):
- Visual view AND code view — both first-class, not afterthought
- File system — browse/create/rename/delete project files
- File tabs — multiple open files with tab bar
- Open project — toolbar button opens index in new browser tab

KEEP BIRKLY CONTRACT:
- content/studio-projects/{id}/ on disk
- Public = compiled HTML + CSS + Birkly template tags
- SSR /site/{slug}/ · export ZIP · no JSON runtime on public pages
- No npm at runtime · P21 perf tests must stay green

LOCKED DECISIONS (P23-D1–D17) — see master plan

MILESTONES:
P23-M0  Gap register + IDE UX spec + canvas v2 schema
P23-M1  IDE workspace shell + Open project ↗ button
P23-M2  File system API + tree UI (sandboxed CRUD)
P23-M3  File tabs (open/close/dirty/sessionStorage)
P23-M4  Code view (syntax highlight, save)
P23-M5  Visual view + split pane sync
P23-M6  Canvas v2 DnD + layers
P23-M7  Multi-column layout
P23-M8  Responsive breakpoints
P23-M9  Style panel + design tokens
P23-M10 Global components
P23-M11 Media + inline WYSIWYG
P23-M12 Advanced Birkly blocks
P23-M13 register_studio_block() + plugin blocks
P23-M14 Section library + templates + v1 upgrade
P23-M15 Undo/revisions + SSR sprint + CSS optimizer + docs + V-STU-02
P23-M16 Closure

Branches: cursor/p23-m[N]-<slug>-030a

DELEGATION:
cms-developer:     M0, M2, M12, M13, M15 (SSR), filesystem API, compiler
frontend-developer:  M1, M3, M4, M5, M6–M11, M14 — IDE UI, canvas, tabs, tree
design-reviewer:     M1 shell, M5 split view, M9 inspector
docs-writer:         M15
qa-verifier:         M16

## Closure (P23-M16)

- [x] STU-IDE-01…10 + STU-PRO-01…15 all pass
- [x] Smoke: file tree → 3 tabs → visual+code split → Open project ↗ → SSR publish
- [x] Filesystem path traversal blocked
- [x] P22 v1 project opens in IDE without data loss
- [x] performance_budget_test.php green
- [x] V-STU-02 evidence
- [x] Single digest notification

ANTI-PATTERNS:
- Keeping v1 single-page dropdown UI as primary — IDE replaces it
- Virtual file tree not backed by disk — tree must mirror content/studio-projects/
- Open project in iframe only — must be new browser tab (target=_blank)
- Public pages loading .studio.json
- npm in plugins/studio runtime
```

---

## Execution order

1. **P23-M0** — Planning artifacts
2. **P23-M1** — IDE shell + Open project button
3. **P23-M2** → **M3** → **M4** → **M5** — Filesystem → tabs → code → visual/split (**foundation track**)
4. **P23-M6** → **M7** → **M8** → **M9** — Canvas pro features (**visual track**, after M5)
5. **P23-M10**, **M11** — Components, media
6. **P23-M12** → **M13** — Birkly + plugin blocks
7. **P23-M14** — Library + templates
8. **P23-M15** — SSR, history, docs
9. **P23-M16** — Closure

---

## Human approval checklist

- [x] P23-D1–D17 accepted (IDE: tree, tabs, visual/code/split, open project ↗)
- [x] P23-D13 `register_studio_block()` accepted
- [x] M0–M16 single-wave execution approved (2026-07-08, Keven)
