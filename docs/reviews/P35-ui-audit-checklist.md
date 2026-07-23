# P35 — UI audit checklist (M0)

**Status:** Baseline captured 2026-07-23  
**Plan:** `docs/reviews/P35-marketplace-ops-excellence-plan.md`

Use this checklist when closing **P35-M6** (responsive + dark mode pass).

---

## Ops (`ops.birkly.cloud/admin/*`)

| # | Check | M1–M6 target |
|---|-------|--------------|
| O-01 | Sidebar navigation with Birkly mint accent | M2–M3 |
| O-02 | Topbar with page title + user logout | M2–M3 |
| O-03 | Cards / stat tiles on dashboard | M3, M5 |
| O-04 | Data tables: padding, hover, header row | M5 |
| O-05 | Forms: labels, inputs, primary/secondary buttons | M5 |
| O-06 | Alerts (success/error) use message tokens | M5 |
| O-07 | Tablet drawer sidebar (<1280px) | M6 |
| O-08 | Login page uses auth card (no sidebar) | M3 |

## Provider (`providers.birkly.cloud/provider/*`)

| # | Check | M1–M6 target |
|---|-------|--------------|
| P-01 | Same shell family as Ops | M4 |
| P-02 | Provider nav: Dashboard, Offers, GitHub, Agreement | M4 |
| P-03 | Offer new/edit forms match CMS form spacing | M5 |
| P-04 | Signup/login use auth card | M4 |

## Public (unchanged in Wave 1)

| # | Check | Note |
|---|-------|------|
| U-01 | Catalog/checkout keep `marketplace.css` | API-first catalog in M19–M21 |

## Regression

| # | Check |
|---|-------|
| R-01 | `surface_test` — three-host isolation |
| R-02 | `production-smoke.sh` after deploy |

---

## Sign-off

| Milestone | Owner | Date | Pass |
|-----------|-------|------|------|
| M6 UI parity | frontend-developer | | pending |
