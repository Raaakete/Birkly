# P33 — Admin responsive refinement gap register

**Date:** 2026-07-22 · **Reporter:** Keven (post-P32 live admin review)

Open gaps from P32 feedback and post-P32 UX issues. Close each row in the milestone column during P33 execution.

## Entry tables

| ID | Gap | Current | P33 target | Milestone | Status |
|----|-----|---------|------------|-----------|--------|
| G1-01 | `.fields-region` does not flex-shrink; row blows out horizontally | Fixed pixel widths / overflow on wrong container | `flex: 1 1 0`; scroll only in `.table-viewport` | M1 | open |
| G1-02 | Long field text wraps or pushes columns off-screen | No consistent ellipsis on list cells | Truncate with ellipsis; keep all columns (P33-D3) | M1 | open |
| G1-03 | Row actions scroll away on wide tables | Actions not sticky | Sticky actions column in smart-table | M1 | open |
| G1-04 | Double scrollbars (page + table + sidebar) | Nested overflow containers | Single horizontal scroll in `.table-viewport` only | M1 | open |

## Shell & breakpoints

| ID | Gap | Current | P33 target | Milestone | Status |
|----|-----|---------|------------|-----------|--------|
| G2-01 | Drawer still tied to 768px in some code paths | Mixed `--bp-md` and `--bp-xl` usage | Drawer below 1280px only (P33-D2) | M2, M6 | open |
| G2-02 | Sidebar has independent scroll (`.sidebar-body overflow-y: auto`) | Nav scrolls inside sidebar while content scrolls | Sidebar scrolls with page (P33-D1) | M7 | open |
| G2-03 | Collapsed icon rail rules conflict with drawer below xl | `@media` blocks disagree on breakpoint | Single 1280px source of truth in `theme.js` + CSS | M2 | open |
| G2-04 | Hamburger / overlay z-index clashes at tablet widths | Occasional header overlap | Audit header + drawer stacking at 1024–1279 | M6 | open |

## Tab navigation

| ID | Gap | Current | P33 target | Milestone | Status |
|----|-----|---------|------------|-----------|--------|
| G3-01 | Settings tabs wrap into unreadable rows at laptop | `.settings-tabs .tabs` flex-wrap | Tab v2 scroll + **More** @ 1024–1279 | M3, M4 | open |
| G3-02 | Editor / collection tabs overflow with no overflow UX | Inline `.tab-nav` buttons | Tab v2 on all multi-tab admin pages | M3, M10 | open |
| G3-03 | Mobile tab fallback is ad-hoc or missing | Some pages stack tabs as link clouds | Picker `<select>` below 1024px (P33-D4) | M3 | open |
| G3-04 | Plugins and Commerce settings use legacy tab markup | Inconsistent with settings pattern | Tab v2 + responsive panels | M10 | open |

## Page layouts

| ID | Gap | Current | P33 target | Milestone | Status |
|----|-----|---------|------------|-----------|--------|
| G4-01 | Account page layout unlike settings (no cards / sticky save) | Standalone form sections | Settings cards + one sticky save + i18n (P33-D6) | M5 | open |
| G4-02 | Media page cramped at 1024–1279 | Grid and detail panel compete for width | Responsive grid + collapsible detail | M8 | open |
| G4-03 | Help page sidebar + content not usable on tablet | Fixed two-column help layout | Stacked nav + readable body @ md/sm | M9 | open |
| G4-04 | Entry editor still tight at 1280 with both sidebars | P31 LAY-01 follow-up | Two usable columns through xl range | M11 | open |
| G4-05 | Settings form fields overflow on narrow viewports | Long labels / inputs clip | Responsive field grid in settings panels | M4 | open |

## i18n & discoverability

| ID | Gap | Current | P33 target | Milestone | Status |
|----|-----|---------|------------|-----------|--------|
| G5-01 | Account page strings hard-coded in template | English-only literals | `lang/en.json` keys + `data-i18n` | M5, M12 | open |
| G5-02 | Locale files out of sync after P32 feature strings | Missing keys in da/de/es/fr/hi/ja/zh | Full sync from English baseline | M13 | open |
| G5-03 | Trash not linked in sidebar (P32 soft-delete) | Trash only reachable from entries UI | Sidebar **Trash** nav item | M14 | open |
| G5-04 | Global search modal labels partially untranslated | Mixed English in search UI | i18n pass on search copy | M15 | open |

## Polish

| ID | Gap | Current | P33 target | Milestone | Status |
|----|-----|---------|------------|-----------|--------|
| G6-01 | FAB overlaps content or save bar on mobile | Fixed bottom-right without safe-area | Breakpoint-aware FAB position | M16 | open |
| G6-02 | FAB / floating controls wrong contrast in dark mode | Token drift on `.fab` | Dark mode token fix | M16 | open |
| G6-03 | No automated responsive smoke for P33 surfaces | P31/P32 smoke only | Extended `admin-polish-smoke.sh` | M17 | open |

## Deferred

| Item | Disposition |
|------|-------------|
| P28 journey automation | Remains paused until P33-M20 (P33-D7) |
| P26 Studio creative | Remains paused (P33-D7) |
| Widget drag-and-drop personalization | Out of scope |
