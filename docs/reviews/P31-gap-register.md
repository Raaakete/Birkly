# P31 — Admin responsive & dashboard gap register

**Date:** 2026-07-21 · **Reporter:** Keven (live admin review)

## Dashboard styling

| ID | Gap | Current | P31 target |
|----|-----|---------|------------|
| G1-01 | Dashboard uses bespoke `.stat-card`, `.dashboard-section` | Different radius, hover lift, top accent bars | Unified `.card.dash-widget` from `cards.css` |
| G1-02 | Analytics uses inline `style=""` in JS | Hardcoded `#f3f4f6`, `#6b7280` | Design tokens + widget mount |
| G1-03 | Four large stat cards dominate above fold | Redundant with activity/entries | Compact **content snapshot** widget |
| G1-04 | No quick actions row | Scattered CTAs in onboarding only | **Quick actions** widget always visible |
| G1-05 | Plugin widgets section `display:none` until load | Often never appears | Widget grid always renders; empty plugins omitted |

## Widget system

| ID | Gap | Current | P31 target |
|----|-----|---------|------------|
| G2-01 | Only Commerce registers a widget | Other plugins have no hook | Events, Studio, Automation widgets |
| G2-02 | Commerce widget opt-in default `false` | Hidden for most installs | Default **on** for `admin` (P31-D10) |
| G2-03 | `eval(widget.javascript)` in dashboard.js | Unsafe, brittle | Named `mount` + `endpoint` API v2 |
| G2-04 | Hardcoded loaders for commerce/payments IDs | Not extensible | `Birkly.Dashboard.mountWidget()` |
| G2-05 | Core sections not widgets | Stats, health, activity separate | Single composable widget registry |
| G2-06 | Fake 10GB storage limit in health widget | Misleading metric | Honest storage display or omit limit bar |

## Responsive

| ID | Gap | Current | P31 target |
|----|-----|---------|------------|
| G3-01 | Dashboard 3-col until 768px only | Cramped at 1024–1280 | 3 → 2 → 1 col (P31-D2) |
| G3-02 | Inconsistent breakpoints across admin | 480/600/640/768/960/1024/1080/1200 | Canonical tokens (P31-D1) |
| G3-03 | Main padding only drops at 768/480 | Laptop waste | Scale at `lg` / `md` (P31-D4) |
| G3-04 | Sidebar expanded eats laptop width | No hint to collapse | Suggest collapse below `xl` (P31-D3) |
| G3-05 | `payments-dash-grid` fixed 3-col | No intermediate breakpoint | Responsive within widget |

## P30 carryover

| ID | Gap | Current | P31 target |
|----|-----|---------|------------|
| G4-01 | P30 closure docs pending | Matrix shows pending | Close in P31-M0 |
| G4-02 | Editor at 1280px with sidebars (LAY-01) | May still be tight | P31-M15 final pass |

## Paused

| ID | Item |
|----|------|
| — | P28 journey automation (resume after P31-M18) |
| — | P26 Studio creative |
| — | Widget drag-and-drop personalization (defer) |
