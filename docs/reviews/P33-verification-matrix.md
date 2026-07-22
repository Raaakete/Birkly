# P33 — Verification matrix

**Date:** 2026-07-22 · **Closure target:** P33-M19

Manual verification at breakpoints **1280**, **1024**, **768**, and **480** (px viewport width). Mark each cell `pass` when behavior matches locked decisions P33-D1–D8.

**Legend:** `—` = not applicable at this width · `pending` = not yet verified

## P32 closure (M0)

| ID | Check | 1280 | 1024 | 768 | 480 | Status |
|----|-------|------|------|-----|-----|--------|
| CL-01 | P32 matrix green on `main` | pass | pass | pass | pass | pending |
| CL-02 | P32 closure checklist complete | — | — | — | — | pending |
| CL-03 | ROADMAP records P32 complete, P33 active | — | — | — | — | pending |

## Entry tables (M1)

| ID | Check | 1280 | 1024 | 768 | 480 | Status |
|----|-------|------|------|-----|-----|--------|
| ET-01 | All columns visible; long text ellipsis (P33-D3) | pending | pending | pending | pending | pending |
| ET-02 | Horizontal scroll only in `.table-viewport` | pending | pending | pending | pending | pending |
| ET-03 | `.fields-region` flex `1 1 0`; no row blowout | pending | pending | pending | pending | pending |
| ET-04 | Sticky row actions remain visible while scrolling | pending | pending | pending | pending | pending |
| ET-05 | No double vertical scroll (page vs table) | pending | pending | pending | pending | pending |

## Shell & drawer (M2, M6, M7)

| ID | Check | 1280 | 1024 | 768 | 480 | Status |
|----|-------|------|------|-----|-----|--------|
| SH-01 | Persistent sidebar at ≥1280; drawer below (P33-D2) | pending | pending | pending | pending | pending |
| SH-02 | Hamburger opens/closes drawer; overlay dismisses | — | pending | pending | pending | pending |
| SH-03 | Sidebar scrolls with page — no inner `.sidebar-body` scroll (P33-D1) | pending | pending | pending | pending | pending |
| SH-04 | Collapsed icon rail only at ≥1280 when user prefers collapsed | pending | — | — | — | pending |
| SH-05 | Main content padding scales; no header overlap | pending | pending | pending | pending | pending |

## Tab v2 (M3, M4, M10)

| ID | Check | 1280 | 1024 | 768 | 480 | Status |
|----|-------|------|------|-----|-----|--------|
| TB-01 | All tabs visible inline | pending | — | — | — | pending |
| TB-02 | Scroll + **More** overflow menu (P33-D4) | — | pending | — | — | pending |
| TB-03 | Picker `<select>` below 1024px; no link cloud | — | — | pending | pending | pending |
| TB-04 | Settings page uses Tab v2 | pending | pending | pending | pending | pending |
| TB-05 | Plugins / commerce / editor tabs use Tab v2 | pending | pending | pending | pending | pending |
| TB-06 | Keyboard focus order and `aria-selected` correct | pending | pending | pending | pending | pending |

## Page layouts (M4–M5, M8–M11)

| ID | Check | 1280 | 1024 | 768 | 480 | Status |
|----|-------|------|------|-----|-----|--------|
| PG-01 | Settings panels readable; forms do not clip | pending | pending | pending | pending | pending |
| PG-02 | Account: settings cards + sticky save (P33-D6) | pending | pending | pending | pending | pending |
| PG-03 | Media: actions bar + grid/list usable | pending | pending | pending | pending | pending |
| PG-04 | Help: nav + content readable | pending | pending | pending | pending | pending |
| PG-05 | Entry editor two columns usable @ 1280 | pending | — | — | — | pending |
| PG-06 | Collection editor tabs + field list usable | pending | pending | pending | pending | pending |

## i18n & discoverability (M12–M15)

| ID | Check | 1280 | 1024 | 768 | 480 | Status |
|----|-------|------|------|-----|-----|--------|
| I18-01 | Account page strings from `lang/*.json` | pending | pending | pending | pending | pending |
| I18-02 | Locale files synced (da, de, es, fr, hi, ja, zh) | — | — | — | — | pending |
| I18-03 | Trash link visible in sidebar | pending | pending | pending | pending | pending |
| I18-04 | Global search modal fully translated | pending | pending | pending | pending | pending |

## Polish (M16)

| ID | Check | 1280 | 1024 | 768 | 480 | Status |
|----|-------|------|------|-----|-----|--------|
| PL-01 | FAB does not overlap save bar or table actions | — | — | pending | pending | pending |
| PL-02 | FAB contrast correct in dark mode | pending | pending | pending | pending | pending |

## Regression (M17)

| ID | Check | 1280 | 1024 | 768 | 480 | Status |
|----|-------|------|------|-----|-----|--------|
| REG-01 | `admin-polish-smoke.sh` extended for P33 | — | — | — | — | pending |
| REG-02 | `./scripts/ci-check.sh` green | — | — | — | — | pending |
| REG-03 | `php tests/run.php` green | — | — | — | — | pending |
| REG-04 | P31/P32 dashboard + chrome smokes still pass | pending | pending | pending | pending | pending |

## Docs (M18)

| ID | Check | Owner | Status |
|----|-------|-------|--------|
| DOC-01 | Birkly-Docs responsive admin guide published | Docs | pending |
| DOC-02 | Breakpoint table documents 1280 drawer + Tab v2 behavior | Docs | pending |
