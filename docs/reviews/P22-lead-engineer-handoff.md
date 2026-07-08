# P22 — Lead engineer handoff

**Approved:** 2026-07-08 · **Execute:** P22-M0→M12 autonomously · **Notify:** once at M12

## Read order

1. This file · 2. `P22-studio-plugin-plan.md` · 3. `PROJECT_VALUES.md` · 4. P20 parity matrix · 5. P21 performance plan · 6. `reference-projects/README.md`

## Mission

Ship `plugins/studio` — visual site builder with dual editor, style kits, Birkly bindings, SSR publish, export ZIP.

## Branches

`cursor/p22-m[N]-<slug>-030a`

## Delegation

| Agent | Milestones |
|-------|------------|
| cms-developer | M0, M1, M2, M6, M9, M10 |
| frontend-developer | M3, M4, M5, M7, M8 |
| docs-writer | M11 |
| qa-verifier | M12 |

## Closure gate

All rows in `P22-verification-matrix.md` pass · `tests/run.php` green · `performance_budget_test.php` green · V-STU-01 evidence · single digest.
