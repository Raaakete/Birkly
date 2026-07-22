# P33 — Admin responsive refinement & UX polish

**Status:** Approved (2026-07-22, Keven) — execute now  
**Baseline:** P32 complete on `main` · P28 **paused** until P33-M20 · P26 **paused**  
**Human rule:** Execute P33-M0→M20 autonomously. Notify human **once** at **P33-M20**.

**Gap register:** `docs/reviews/P33-gap-register.md`  
**Handoff:** `docs/reviews/P33-lead-engineer-handoff.md`  
**Matrix:** `docs/reviews/P33-verification-matrix.md`

---

## Vision

Fix **broken responsive behavior** and **inconsistent admin UX** left after P32 — especially laptop/tablet (1024–1280px), tab navigation, entry tables, account settings, media, sidebar chrome, and i18n.

Human-reported issues (Keven, 2026-07-22):

1. Settings not properly responsive on smaller desktop screens.
2. Account settings layout/design does not match settings — needs **one save button** and unified cards.
3. Hamburger/drawer should activate at **tablet resolutions (1280px)**.
4. Media page not properly responsive yet.
5. Sidebar inner scrollbar sits on the seam — **sidebar should scroll with the page**.
6. Account languages need audit and update.
7. Tab layout stacks into an unreadable **link cloud** — need obvious sub-page navigation.
8. Entry overview tables: long field content **not truncated**; actions require scrolling the whole window.

Plus all additional fixes identified in planning review (see gap register).

**Scope:** `admin/`, `core/i18n.php`, `lang/`, tests, Birkly-Docs.

**Out of scope:** P28 journey runner, P26 Studio creative, new fieldtypes, kekaba live-path.

---

## Locked decisions (approved 2026-07-22)

| ID | Decision |
|----|----------|
| **P33-D1** | **Pause P28** until P33-M20 |
| **P33-D2** | **Tablet drawer at `--bp-xl` (1280px)** — hamburger + off-canvas sidebar below 1280px |
| **P33-D3** | **Sidebar scrolls with page** — remove `.sidebar-body { overflow-y: auto }`; sticky sidebar, no inner scrollbar |
| **P33-D4** | **Tab system v2** — scrollable strip + “More” overflow on laptop; current-tab picker below 1280px; **no wrap-to-cloud** |
| **P33-D5** | **Entry table long text** — truncate with ellipsis in visible columns; **do not hide** content/excerpt fields |
| **P33-D6** | **Smart-table layout fix** — `fields-region` flexes; actions column always visible without page horizontal scroll |
| **P33-D7** | **Account settings** — settings-stack cards + **one sticky save** for all sections |
| **P33-D8** | **Settings responsive** at 1024–1279px — tabs v2 + card stacks |
| **P33-D9** | **Media responsive** at `lg`/`xl` — collapsible folder panel, stacked detail |
| **P33-D10** | **i18n audit** — sync `lang/*.json` to `en.json`; translate account page hardcoded strings |
| **P33-D11** | Single notification at **P33-M20** |

---

## Tab system v2 (canonical)

| Viewport | Behavior |
|----------|----------|
| **≥1280px** (`xl+`) | Horizontal tab bar (current style, no wrap) |
| **1024–1279px** (`lg`) | Horizontal **scroll** strip + left/right fade + **More ▼** overflow menu for off-screen tabs |
| **<1280px** (tablet drawer zone) | **Current-tab picker** (select or compact dropdown) showing active section; full list in picker — not a wrapped link cloud |

Apply to: settings, commerce, plugins, help topics sidebar/nav, entry editor tabs, collection editor tabs, plugin admin pages.

Files: `admin/css/components/tabs-v2.css`, `admin/js/tabs-v2.js`.

---

## Sidebar scroll-with-page (P33-D3)

Remove independent scroll container on `.sidebar-body`.

- Sidebar: `position: sticky; top: 0; align-self: flex-start; height: auto; max-height: none`
- `.sidebar-body`: `overflow: visible` (not `overflow-y: auto`)
- Whole `.admin-layout` scrolls in the viewport — no scrollbar on sidebar/content seam

---

## Entry smart-table truncation (P33-D5–D6)

**Bug:** `.fields-region { width: fit-content; flex-shrink: 0 }` expands to full content width.

**Fix:**
- `.fields-region`: `flex: 1 1 0; min-width: 0; overflow: hidden`
- `.cell-field`: `max-width: var(--col-field)` + `text-overflow: ellipsis; white-space: nowrap; overflow: hidden`
- Long text fields (`content`, `excerpt`, `textarea`, etc.) remain **visible as columns** but **truncated** — never hidden by default
- `.cell-actions`: stays `flex-shrink: 0; position: sticky; right: 0` — always on screen within `.table-viewport` horizontal scroll if needed, not whole-page scroll
- Deduplicate **title column** when collection also shows `title` as a field column

---

## Account settings redesign (P33-D7)

Migrate `admin/templates/account.html` to match settings UX:

- `.settings-stack` + `.card` sections (profile, password, language, cloud, 2FA, notifications)
- **One sticky save bar** — saves all dirty sections or dispatches per-section via unified bar (profile + language + notifications batch; password remains separate submit for security — **one visible sticky primary** with section-aware logic documented)
- Remove inline `style=""` — use design tokens
- i18n all hardcoded English strings
- Remove redundant per-form save buttons from card footers when sticky bar active

---

## Milestones (21 — one wave, M0→M20)

| ID | Focus |
|----|--------|
| **P33-M0** | Gap register + matrix + responsive audit checklist |
| **P33-M1** | Smart-table `fields-region` fix + cell truncation for all field types |
| **P33-M2** | Duplicate title column dedup in entries table |
| **P33-M3** | Tab system v2 component (`tabs-v2.css`, `tabs-v2.js`) |
| **P33-M4** | Settings tabs v2 + responsive layout 1024–1279px |
| **P33-M5** | Account settings redesign + single sticky save + i18n strings |
| **P33-M6** | Tablet drawer at **1280px** (`theme.js`, `header.css`, `sidebar.css`) |
| **P33-M7** | Sidebar scroll-with-page (remove inner scrollbar) |
| **P33-M8** | Media responsive `lg`/`xl` — folder panel collapse, detail drawer, grid density |
| **P33-M9** | Help page — remove double padding, topics nav v2, layout at laptop |
| **P33-M10** | Commerce + plugins + entry/collection editor tabs v2 |
| **P33-M11** | Shell alignment pass (help, media, account vertical offset with sidebar nav) |
| **P33-M12** | i18n audit — sync `da/de/es/fr/hi/ja/zh.json` to `en.json` |
| **P33-M13** | User-menu language selector sync with account language |
| **P33-M14** | Settings sub-pages polish — users table, redirects tab, trash discoverability |
| **P33-M15** | P32 regression sweep — global search UX, trash UI, field templates re-eval, FAB overlap, sticky save edge cases |
| **P33-M16** | Dark mode pass on tabs v2, sidebar, media panels |
| **P33-M17** | `admin-polish-smoke.sh` + `tests/entries_table_layout_test.php` |
| **P33-M18** | Birkly-Docs responsive admin + i18n guide |
| **P33-M19** | `ux-flow-matrix` P33 section |
| **P33-M20** | Matrix green, closure, ROADMAP, single notification |

**Branches:** `cursor/p33-m[N]-<slug>-030a`

---

## Additional fixes (included in milestones above)

| Item | Milestone |
|------|-----------|
| Settings tab link cloud on laptop | M4 |
| Commerce/plugin tab cloud | M10 |
| Help topics flat link list | M9 |
| Notification prefs untranslated | M5, M12 |
| Cloud account hardcoded English | M5, M12 |
| Global search keyboard/result grouping polish | M15 |
| Trash link discoverability from entries | M14 |
| Field templates P32 regression check | M15 |
| FAB (+) overlap on mobile | M15, M16 |
| Collection editor sticky save scroll | M10, M15 |
| Plugin admin action bar consistency | M10 |
| Duplicate `title` column in entry table | M2 |
| `help-page { padding: 2rem }` double inset | M9, M11 |

---

## Files (primary)

| Area | Files |
|------|--------|
| Tables | `admin/css/components/tables.css`, `smart-table.html`, `.php` |
| Tabs v2 | `admin/css/components/tabs-v2.css`, `admin/js/tabs-v2.js` |
| Sidebar | `admin/css/layout/sidebar.css`, `admin/js/theme.js` |
| Header | `admin/css/layout/header.css`, `admin/partials/admin-topbar.php` |
| Account | `admin/templates/account.html`, `admin/js/account-settings.js` |
| Settings | `admin/templates/settings.html`, `admin/css/pages/settings.css`, `settings-page.js` |
| Media | `admin/templates/media.html`, `media.css`, `media-page.js` |
| Help | `admin/templates/help.html`, `help.css`, `help.js` |
| i18n | `lang/*.json`, `core/i18n.php` |
| Tests | `tests/entries_table_layout_test.php`, `scripts/admin-polish-smoke.sh` |

---

## After P33

Resume **P28** beta launch automation (journeys + videos).
