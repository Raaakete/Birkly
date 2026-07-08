# P22 — Verification matrix

**Date:** 2026-07-08

Legend: **pass** · **pending** · **n/a**

## Studio features (STU)

| ID | Check | Status | Notes |
|----|-------|--------|-------|
| STU-01 | Create project with bootstrap kit | pass | API + admin |
| STU-02 | 3-section layout in visual editor | pass | sections/rows/columns |
| STU-03 | Blog loop binding in canvas | pass | `{for each entry in 'blog'}` |
| STU-04 | Live preview shows entries | pass | P21 bundle iframe |
| STU-05 | Publish → SSR URL returns HTML | pass | `/site/{slug}/` |
| STU-06 | Export ZIP with website/ | pass | ZipArchive |
| STU-07 | Deactivate plugin → SSR 503 | pass | files remain |
| STU-08 | Dual editor code ↔ visual | pass | round-trip |
| STU-09 | Style kit + custom.css | pass | inspector |
| STU-10 | Duplicate/delete project | pass | repository |

## Style kits (SK)

| ID | Kit | Status |
|----|-----|--------|
| SK-01 | minimal | pass |
| SK-02 | bootstrap | pass |
| SK-03 | tailwind-lite | pass |

## Performance (PR)

| ID | Check | Status |
|----|-------|--------|
| PR-01 | Preview ≤2 cold RTTs | pass |
| PR-02 | `performance_budget_test.php` green | pass |
| PR-03 | No mandatory bake/hydrate | pass |

## Video

| ID | Check | Status |
|----|-------|--------|
| V-STU-01 | Walkthrough evidence | pass | `docs/reviews/artifacts/v-stu-01-evidence.md` |
