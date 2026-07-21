# P31 — Admin responsive & dashboard gap register

**Date:** 2026-07-21 · **Reporter:** Keven (live admin feedback)

## Dashboard

| ID | Gap | Current | P31 target |
|----|-----|---------|------------|
| G1-01 | Fragmented dashboard layout | Separate `stats-grid`, 3-column sections, plugin section | Single `.dash-widget-grid` with `.card.dash-widget` |
| G1-02 | Unsafe plugin widget JS | `eval(widget.javascript)` in `dashboard.js` | `mount` handlers via `Birkly.Dashboard` |
| G1-03 | No widget priority / size API | Ad-hoc HTML from plugins | Schema v2: `size`, `priority`, `endpoint`, `mount`, `refresh` |
| G1-04 | Commerce widget off by default | `enable_dashboard_widget` default `false` | Default **on** for admin (opt-out via setting) |
| G1-05 | Events/Studio/Automation missing dashboard widgets | No callbacks | Plugin widgets with mount handlers |

## Responsive shell

| ID | Gap | Current | P31 target |
|----|-----|---------|------------|
| G2-01 | Inconsistent breakpoint tokens | Only `--breakpoint-mobile` / `--breakpoint-tablet` | Full sm→2xl token set |
| G2-02 | Main padding fixed at 2.5rem on laptop | Large padding wastes space | Scale padding at xl/lg |
| G2-03 | Sidebar collapse not hinted below xl | Collapse toggle easy to miss | Visual hint @ max-xl |
| G2-04 | Entry table tight on laptop | Columns overflow before mobile | Laptop column pass @ 1280px |
| G2-05 | Editor grid at 1280px | P30 tuned 1200px stack | Further 1280px pass |

## Paused

| ID | Item |
|----|------|
| — | P28 beta journeys |
| — | P26 Studio creative |
