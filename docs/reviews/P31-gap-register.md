# P31 — Admin responsive & dashboard gap register

**Date:** 2026-07-21 · **Reporter:** Keven (live admin review)

## Dashboard styling

| ID | Gap | Current | P31 target |
|----|-----|---------|------------|
| G1-01 | Dashboard uses bespoke `.stat-card` and `.dashboard-section` | Different radius, hover lift, and accent bars | Unified `.card.dash-widget` from `cards.css` |
| G1-02 | Analytics uses inline styles | Hard-coded colours | Design tokens and widget mount |
| G1-03 | Four stat cards dominate above the fold | Redundant with activity and entries | Compact content snapshot widget |
| G1-04 | No quick-actions row | CTAs scattered in onboarding | Always-visible Quick actions widget |
| G1-05 | Plugin widgets are hidden before loading | They often never appear | Always render the grid; omit unavailable plugin widgets |

## Widget system

| ID | Gap | Current | P31 target |
|----|-----|---------|------------|
| G2-01 | Only Commerce registers a widget | Other plugins have no hook | Events, Studio, and Automation widgets |
| G2-02 | Commerce widget default is off | Hidden for most installs | Default on for admin |
| G2-03 | `eval(widget.javascript)` in dashboard JS | Unsafe and brittle | Named `mount` plus `endpoint` API v2 |
| G2-04 | Hard-coded Commerce/payment loaders | Not extensible | `Birkly.Dashboard.mountWidget()` |
| G2-05 | Core sections are not widgets | Stats, health, and activity are separate | One composable widget registry |
| G2-06 | Health widget implies a 10GB storage limit | Misleading metric | Honest storage display or no limit bar |

## Responsive

| ID | Gap | Current | P31 target |
|----|-----|---------|------------|
| G3-01 | Dashboard remains three columns until 768px | Cramped at 1024–1280px | 3 → 2 → 1 columns |
| G3-02 | Admin breakpoints are inconsistent | Multiple unrelated pixel values | Canonical breakpoint tokens |
| G3-03 | Main padding changes only at narrow widths | Laptop space is wasted | Scale at lg and md |
| G3-04 | Expanded sidebar consumes laptop width | No collapse hint | Suggest collapse below xl |
| G3-05 | Payments grid is fixed at three columns | No intermediate layout | Responsive inside the widget |

## P30 carryover

| ID | Gap | Current | P31 target |
|----|-----|---------|------------|
| G4-01 | P30 formal closure was pending | Implementation is on `main` | Close matrix, checklist, and roadmap in P31-M0 |
| G4-02 | Editor at 1280px with sidebars may still be tight | P30 LAY-01 passes with follow-up note | Final responsive pass in P31-M15 |

## Deferred

| Item | Disposition |
|------|-------------|
| P28 journey automation | Resume after P31-M18 |
| P26 Studio creative | Remains paused |
| Widget drag-and-drop personalization | Defer beyond P31 |
