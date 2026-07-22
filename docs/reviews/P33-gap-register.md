# P33 — Admin responsive refinement gap register

**Date:** 2026-07-22 · **Reporter:** Keven (live admin + screenshots)

## Entry tables (critical)

| ID | Gap | Current | P33 target |
|----|-----|---------|------------|
| G1-01 | Long field cells expand table width | `.fields-region { width: fit-content }` | Flex shrink + ellipsis; actions always visible |
| G1-02 | Page-level horizontal scroll | Whole window scrolls | Scroll contained in `.table-viewport` only |
| G1-03 | `content` / `excerpt` show full text | No effective truncation | Truncated with ellipsis; columns remain visible |
| G1-04 | Duplicate title column | Title + `title` field column | Dedup in smart-table render |

## Tabs & navigation

| ID | Gap | Current | P33 target |
|----|-----|---------|------------|
| G2-01 | Settings tabs wrap to link cloud | `flex-wrap` at 768px only | Tab v2: scroll + More menu (lg); picker (<1280) |
| G2-02 | Commerce/plugin tabs same issue | Shared `.tabs` | Tab v2 applied |
| G2-03 | Help topics unreadable when stacked | Grid collapse at 1024px | Topics nav v2 |
| G2-04 | Entry/collection editor tabs | Same cloud pattern | Tab v2 in editor sidebars |

## Responsive / layout

| ID | Gap | Current | P33 target |
|----|-----|---------|------------|
| G3-01 | Hamburger only below 768px | `max-width: 767px` | Drawer below **1280px** |
| G3-02 | Sidebar scrollbar on seam | `.sidebar-body overflow-y: auto` | Scroll with page |
| G3-03 | Settings broken at 1024–1279px | Tables overflow; tabs wrap | Tabs v2 + card stack |
| G3-04 | Media only responsive at 768px | Single breakpoint | `lg`/`xl` panel collapse |
| G3-05 | Content below sidebar nav top | Extra padding on help/account | Shell alignment M11 |
| G3-06 | Help double padding | `.help-page { padding: 2rem }` + main-content | Single inset |

## Account settings

| ID | Gap | Current | P33 target |
|----|-----|---------|------------|
| G4-01 | Legacy layout vs settings | `.settings-section` + inline styles | `.settings-stack` + `.card` |
| G4-02 | Multiple save buttons | 4 separate saves | One sticky save bar |
| G4-03 | Hardcoded English | Notification/cloud copy | i18n keys |
| G4-04 | No sticky save | Per-form footers | Sticky bar like settings |

## i18n

| ID | Gap | Current | P33 target |
|----|-----|---------|------------|
| G5-01 | Lang files out of sync | 8 locales vs `en.json` | Full key parity audit |
| G5-02 | User-menu language vs account | May diverge | M13 sync |

## P32 regression / polish

| ID | Gap | Current | P33 target |
|----|-----|---------|------------|
| G6-01 | Global search UX rough | Basic results | Grouping + keyboard polish |
| G6-02 | Trash discoverability | Filter-only? | Prominent link in entries bar |
| G6-03 | Field templates regression | Removed in P32 hotfix | Re-evaluate + fix or document |
| G6-04 | FAB overlaps content mobile | z-index/spacing | M15 fix |
| G6-05 | Sticky save edge cases | Editors/collection | M15 sweep |
| G6-06 | Plugin admin action bars | Inconsistent | M10 audit |
| G6-07 | Dark mode on new P32 surfaces | Partial | M16 pass |

## Paused

| ID | Item |
|----|------|
| — | P28 journey automation (resume after P33-M20) |
| — | P26 Studio creative |
