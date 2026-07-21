# P30 — Admin form system & navigation UX

**Status:** Approved (2026-07-21, Keven) — execute now  
**Baseline:** P29 complete · P28 **paused** until P30-M18 · P26 **paused**  
**Human rule:** Execute P30-M0→M18 autonomously. Notify human **once** at **P30-M18**.

**Gap register:** `docs/reviews/P30-gap-register.md`  
**Handoff:** `docs/reviews/P30-lead-engineer-handoff.md`  
**Matrix:** `docs/reviews/P30-verification-matrix.md`

---

## Vision

Polish **admin navigation and forms** for real laptop viewports — after P29 fixed editor sidebars and slug UX.

Human-reported issues:

1. **Breadcrumbs** overflow the topbar; need smart **left truncation** keeping the most recent segments.
2. **Field labels** should sit **above** controls (not left), system-wide — especially for ContentEditor width.

**Scope:** `admin/css/`, `admin/js/`, `admin/templates/`, `admin/partials/layout_footer.php`, fieldtype wrappers, tests, Birkly-Docs.

**Out of scope:** Studio P26, P28 journeys, marketplace, kekaba migration.

---

## Locked decisions (approved)

| ID | Decision |
|----|----------|
| **P30-D1** | **Pause P28** until P30-M18 |
| **P30-D2** | Breadcrumb collapse **from the left** when topbar space is insufficient |
| **P30-D3** | Always show **minimum last 2 segments** (e.g. `Collection › Entry`); earlier segments collapse to `…` |
| **P30-D4** | `…` is not a link; full path in `aria-label` on `<nav>` + `title` on collapsed prefix |
| **P30-D5** | Re-run truncation on resize (ResizeObserver) and sidebar collapse toggle |
| **P30-D6** | **All admin `.field-block` forms** use **label-above-control** (column layout) — default at all breakpoints |
| **P30-D7** | Align `.field-block--error` / `--success` with `.form-group` tokens in `forms.css` |
| **P30-D8** | Fieldtypes render full-width in `.field-control`; ContentEditor unchanged in behavior, wider layout |
| **P30-D9** | Entry save API errors map to field-level inline messages where field name is known |
| **P30-D10** | Single notification at **P30-M18** |

---

## Breadcrumb algorithm (canonical)

Given segments `[S0, S1, …, Sn]` (last is current page):

1. Render full trail if it fits in `#admin-topbar-breadcrumbs` available width.
2. If overflow: collapse `S0 … S(k-2)` to `…` (linked segments before collapse remain in tooltip only).
3. Always render **at least** `S(n-1) › Sn` (last two segments).
4. If still overflow: ellipsize **current** segment text with CSS `text-overflow` (never drop it entirely).
5. On `…` hover/focus: show full path tooltip.

Implement in `admin/js/breadcrumb-truncate.js`; call after `moveBreadcrumbsToTopbar()` in `layout_footer.php`.

---

## Milestones (18 — one wave)

| ID | Focus |
|----|--------|
| **P30-M0** | Gap register + matrix |
| **P30-M1** | `breadcrumb-truncate.js` + integration in layout_footer |
| **P30-M2** | Breadcrumb CSS — topbar font scale, flex min-width, segment classes |
| **P30-M3** | ResizeObserver + sidebar collapse re-trigger |
| **P30-M4** | Tooltip / aria full-path accessibility |
| **P30-M5** | Tests: `tests/breadcrumb_truncate_test.php` or JS unit in smoke |
| **P30-M6** | `.field-block` column layout in `content.css` (remove 250px row default) |
| **P30-M7** | `.field-block--error` / `--success` in `forms.css` |
| **P30-M8** | `entry-editor.html` + `collection-editor.html` template alignment |
| **P30-M9** | Fieldtype full-width audit (ContentEditor, Media, Relation, etc.) |
| **P30-M10** | `entry-editor.js` — map API field errors to `.field-block--error` |
| **P30-M11** | Settings + plugin admin forms audit (stacked labels) |
| **P30-M12** | Editor layout laptop breakpoint tuning (`editor-layout` grid) |
| **P30-M13** | Remove dead `page-header` spacing after breadcrumb hoist |
| **P30-M14** | Entries/collection table long-title truncation (optional same pattern) |
| **P30-M15** | Extend `admin-content-smoke.sh` |
| **P30-M16** | Birkly-Docs admin UX page |
| **P30-M17** | `ux-flow-matrix.md` refresh |
| **P30-M18** | Matrix green, closure, ROADMAP, single notification |

**Branches:** `cursor/p30-m[N]-<slug>-030a`

---

## Files (primary)

| Area | Files |
|------|--------|
| Breadcrumbs | `admin/js/breadcrumb-truncate.js`, `admin/partials/layout_footer.php`, `admin/css/layout/header.css` |
| Forms | `admin/css/pages/content.css`, `admin/css/components/forms.css` |
| Templates | `entry-editor.html`, `collection-editor.html` |
| JS | `entry-editor.js`, `accessibility.js` |
| Tests | `tests/breadcrumb_truncate_test.php`, extend sidebar tests |

---

## After P30

Resume **P28** beta launch automation (journeys + videos).
