# P33 — Admin responsive refinement & UX polish

**Status:** Approved (2026-07-22, Keven) — execution ready  
**Baseline:** P32 complete on `main` · P28 paused · P26 paused  
**Human rule:** Execute P33-M0→M20 autonomously. Notify once at P33-M20.

**Gap register:** `docs/reviews/P33-gap-register.md`  
**Handoff:** `docs/reviews/P33-lead-engineer-handoff.md`  
**Matrix:** `docs/reviews/P33-verification-matrix.md`

---

## Vision

Close the remaining responsive and UX polish gaps in the admin shell after P31 (breakpoint tokens, dashboard grid) and P32 (action chrome, core features). Every admin page should behave intentionally from 480px through wide desktop, with consistent tab navigation, table scrolling, drawer behavior, and i18n coverage.

**Scope:** `admin/css/`, `admin/js/`, `admin/templates/`, `lang/*.json`, tests, `scripts/admin-polish-smoke.sh`, and Birkly-Docs responsive admin guide.

**Out of scope:** P28 journey runner, P26 Studio creative features, marketplace commerce scope changes, kekaba live path, widget drag-and-drop personalization.

## Locked decisions

| ID | Decision |
|----|----------|
| P33-D1 | Sidebar scrolls **with** the page — remove `.sidebar-body { overflow-y: auto }`; no inner sidebar scroll container |
| P33-D2 | Tablet drawer activates at **1280px** (`--bp-xl`), **not** 768px — persistent sidebar only at ≥1280px |
| P33-D3 | Entry long text **truncates** with ellipsis; do **not** hide columns at laptop widths |
| P33-D4 | Tab v2: horizontal scroll + **More** overflow menu at 1024–1279px; full picker/dropdown below 1024px; no link-cloud fallback |
| P33-D5 | `.fields-region` uses `flex: 1 1 0`; horizontal scroll lives **only** in `.table-viewport` |
| P33-D6 | Account page uses settings-style cards + one sticky save bar + full i18n keys |
| P33-D7 | P28 and P26 remain **paused** until P33-M20 closure |
| P33-D8 | Single human notification at **P33-M20** only |

## Pillars

### A — Entry tables & data views (M1, M5 partial)

- Fix `.fields-region` flex sizing so columns share space without blowing out the row.
- Truncate long field text with ellipsis (P33-D3); keep all columns visible below xl.
- Sticky row actions column; scroll confined to `.table-viewport`.
- Reference: `admin/css/components/tables.css`, `admin/js/smart-table-scroll.js`, `admin/templates/components/smart-table.html`.

### B — Breakpoint & shell alignment (M2, M6, M7)

- Audit all admin CSS/JS for drawer breakpoint consistency at `--bp-xl` (1280px).
- Drawer overlay + hamburger below 1280px (`theme.js`, `header.css`, `sidebar.css`).
- Sidebar body scrolls with page content (P33-D1).
- Reference: `admin/css/core/variables.css`, `admin/css/layout/sidebar.css`, `admin/css/layout/header.css`, `admin/js/theme.js`.

### C — Tab v2 component (M3, M4, M10)

- New `admin/js/tabs-v2.js` + `admin/css/components/tabs-v2.css`.
- 1024–1279px: horizontal scroll with overflow **More** menu.
- Below 1024px: compact tab picker (dropdown), not a link cloud.
- Roll out to Settings (M4), Plugins/Commerce/Editor tabs (M10).

### D — Page-level responsive passes (M4–M5, M8–M11)

| Page | Focus |
|------|-------|
| Settings | Tab v2 + responsive panels and forms |
| Account | Settings cards, sticky save, i18n (P33-D6) |
| Media | Actions bar, grid/list, detail panel at laptop widths |
| Help | Readable doc layout and nav at md/sm |
| Plugins / Commerce / Editor | Tab v2 migration, editor two-column layout @ 1280px |

### E — i18n & discoverability (M12–M15)

- English baseline audit for new/changed admin strings.
- Sync `lang/da.json`, `de.json`, `es.json`, `fr.json`, `hi.json`, `ja.json`, `zh.json`.
- Trash sidebar link for soft-delete discoverability (P32-M19 follow-up).
- Global search modal polish + translated labels.

### F — Polish & regression (M16–M20)

- FAB positioning fixes across breakpoints and dark mode.
- Extend `admin-polish-smoke.sh` and PHPUnit smoke coverage.
- Birkly-Docs responsive admin guide.
- Verification matrix green, gap register closure, ROADMAP update, single notification.

## Tab v2 contract

```html
<nav class="tabs-v2" data-tabs-v2>
  <div class="tabs-v2__scroll" role="tablist">…</div>
  <button type="button" class="tabs-v2__more" hidden>More</button>
  <select class="tabs-v2__picker" hidden aria-label="Section">…</select>
</nav>
```

- **≥1280px:** all tabs visible inline.
- **1024–1279px:** scrollable row; overflow tabs move to **More** menu.
- **&lt;1024px:** picker `<select>` replaces inline tabs; no link cloud.
- JS: `Birkly.TabsV2.init(container)` — idempotent, keyboard-accessible, respects `data-tab` / `aria-selected`.

## Milestones

| ID | Focus |
|----|-------|
| P33-M0 | Plan, gap register, matrix, handoff (this wave) |
| P33-M1 | Entry tables — fields-region fix, ellipsis, sticky actions, viewport scroll only |
| P33-M2 | Breakpoint alignment audit (1280 drawer baseline) |
| P33-M3 | Tab v2 component (`tabs-v2.js` / `tabs-v2.css`) |
| P33-M4 | Settings page responsive + Tab v2 |
| P33-M5 | Account page cards + sticky save + i18n |
| P33-M6 | Drawer @ 1280px (`theme.js`, `header.css`, `sidebar.css`) |
| P33-M7 | Sidebar scroll-with-page |
| P33-M8 | Media page responsive |
| P33-M9 | Help page responsive |
| P33-M10 | Plugins / commerce / editor tabs responsive |
| P33-M11 | Editor layout responsive pass @ 1280px |
| P33-M12 | i18n English baseline audit |
| P33-M13 | i18n sync all `lang/*.json` (da, de, es, fr, hi, ja, zh) |
| P33-M14 | Trash sidebar link |
| P33-M15 | Global search polish + i18n |
| P33-M16 | FAB positioning + dark mode fixes |
| P33-M17 | Tests + `admin-polish-smoke.sh` extensions |
| P33-M18 | Birkly-Docs responsive admin guide |
| P33-M19 | Verification matrix green + gap register closure |
| P33-M20 | ROADMAP update + single human notification |

**Branches:** `cursor/p33-m[N]-<slug>-8350`

## Primary files

| Area | Files |
|------|-------|
| Tables | `admin/css/components/tables.css`, `admin/js/smart-table-scroll.js`, `admin/templates/components/smart-table.html` |
| Shell | `admin/css/layout/sidebar.css`, `header.css`, `main-content.css`, `admin/js/theme.js` |
| Tabs v2 | `admin/js/tabs-v2.js`, `admin/css/components/tabs-v2.css` |
| Settings / Account | `admin/templates/settings.html`, `account.html`, `admin/css/pages/settings.css`, `admin/js/settings-page.js` |
| Media / Help | `admin/templates/media.html`, `help.html`, `admin/css/pages/media.css` |
| Editors | `admin/templates/entry-editor.html`, `collection-editor.html`, `admin/css/pages/content.css` |
| i18n | `lang/en.json`, `lang/da.json`, `lang/de.json`, `lang/es.json`, `lang/fr.json`, `lang/hi.json`, `lang/ja.json`, `lang/zh.json` |
| Tests | `scripts/admin-polish-smoke.sh`, `tests/` responsive smoke extensions |
| Docs | `Birkly-Docs/01-admin/admin-responsive-refinement.md` |

## After P33

Resume P28 beta-launch automation per roadmap governance.
