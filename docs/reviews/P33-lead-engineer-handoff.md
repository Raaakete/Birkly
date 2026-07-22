# Phase 33 — Lead-engineer handoff

**Status:** Approved (2026-07-22, Keven)  
**Human rule:** Execute P33-M0→M20 autonomously. Notify once at P33-M20.

**Master plan:** `docs/reviews/P33-admin-responsive-refinement-plan.md`  
**Gap register:** `docs/reviews/P33-gap-register.md`  
**Matrix:** `docs/reviews/P33-verification-matrix.md`

## M0 boundary

P32 is on `main`. M0 creates P33 docs and verifies P32 closure. Do not start P28 or P26.

## P33 implementation contract

- Drawer and persistent sidebar boundary at **1280px** (`--bp-xl`); no 768px drawer regression.
- Entry tables: ellipsis truncation, all columns visible, scroll only in `.table-viewport`.
- Tab v2: scroll + **More** at 1024–1279px; picker below 1024px; no link cloud.
- Sidebar scrolls with the page — no `.sidebar-body` inner scroll.
- Account page matches settings card pattern with one sticky save bar and i18n keys.
- All new user-facing strings go through `lang/en.json` and sync to all locale files in M13.

## Milestone table

| ID | Deliverable |
|----|-------------|
| M0 | Plan, gap register, matrix, handoff (this wave) |
| M1 | Entry tables — fields-region flex, ellipsis, sticky actions, viewport-only scroll |
| M2 | Breakpoint alignment audit (1280 drawer baseline) |
| M3 | Tab v2 component (`tabs-v2.js`, `tabs-v2.css`) |
| M4 | Settings page responsive + Tab v2 |
| M5 | Account page cards + sticky save + i18n |
| M6 | Drawer @ 1280px (`theme.js`, `header.css`, `sidebar.css`) |
| M7 | Sidebar scroll-with-page |
| M8 | Media page responsive |
| M9 | Help page responsive |
| M10 | Plugins / commerce / editor tabs responsive |
| M11 | Editor layout responsive pass @ 1280px |
| M12 | i18n English baseline audit |
| M13 | i18n sync all `lang/*.json` (da, de, es, fr, hi, ja, zh) |
| M14 | Trash sidebar link |
| M15 | Global search polish + i18n |
| M16 | FAB positioning + dark mode fixes |
| M17 | Tests + `admin-polish-smoke.sh` extensions |
| M18 | Birkly-Docs responsive admin guide |
| M19 | Verification matrix green + gap register closure |
| M20 | ROADMAP update + single human notification |

## Ownership

| Agent | Scope |
|-------|-------|
| Lead / CMS developer | M1–M7, M10–M16 implementation |
| Frontend developer | Tab v2, page responsive passes, FAB/dark mode |
| Design reviewer | Breakpoint matrix walkthrough, tab overflow UX |
| Docs writer | M18 Birkly-Docs responsive admin guide |
| QA verifier | M17 regression + M19 matrix at 1280/1024/768/480 |

## Dependency order

1. M1–M2 (tables + breakpoint audit) before page passes that depend on shell behavior.
2. M3 (Tab v2) before M4, M10.
3. M6–M7 (drawer + sidebar scroll) before M8–M11 shell-dependent layouts.
4. M12 before M13 (English baseline before locale sync).
5. M17–M19 before M20 closure.
