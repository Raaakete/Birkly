# P32 — Admin chrome polish & core platform UX

**Status:** Approved (2026-07-21, Keven) — execute now  
**Baseline:** P31 complete on `main` · P28 **paused** until P32-M24 · P26 **paused**  
**Human rule:** Execute P32-M0→M24 autonomously. Notify human **once** at **P32-M24**.

**Gap register:** `docs/reviews/P32-gap-register.md`  
**Handoff:** `docs/reviews/P32-lead-engineer-handoff.md`  
**Matrix:** `docs/reviews/P32-verification-matrix.md`

---

## Vision

One wave of **many small, high-impact improvements** across the admin and core CMS — fixing layout inconsistencies, standardizing action placement, upgrading media, adding fieldtypes, and shipping missing core platform functions humans expect daily.

Human-reported issues (Keven, 2026-07-21):

1. Entry list **Edit / Delete** should be **icon buttons**, not word buttons.
2. **Reduce vertical gap** between topbar (breadcrumb, theme, account) and content — content should start at the **same height** as the first sidebar nav card (Dashboard).
3. **Primary actions top-left** in the content area — dashboard Customize and settings Save are wrong; audit **all admin pages**.
4. **Media library** feels weak — needs a proper polish pass.
5. **Sticky save bars** for settings (approved) and **generally** for entry editor, collection editor, and similar long forms.
6. Ship **all recommended fieldtypes** and **all missing core functions** from planning review.

**Scope:** `admin/`, `api/`, `core/`, `fieldtypes/`, tests, Birkly-Docs.

**Out of scope:** P28 journey runner, P26 Studio creative features, marketplace commerce, kekaba live-path migration.

---

## Locked decisions (approved 2026-07-21)

| ID | Decision |
|----|----------|
| **P32-D1** | **Pause P28** until P32-M24 |
| **P32-D2** | Align main content vertical start with **sidebar nav-links** top via `--admin-shell-offset-top` |
| **P32-D3** | When breadcrumbs are hoisted to topbar, **collapse in-page `page-header`** (zero dead height) |
| **P32-D4** | **Primary actions** live in **`main-actions-bar` left** — deprecate `page-header-actions` for primary CTAs |
| **P32-D5** | Topbar right = chrome only (theme, account, notifications); **not** page primary actions |
| **P32-D6** | Entry/collection smart-table row actions = **icon buttons** with `aria-label` + `title` |
| **P32-D7** | **Sticky save bar** component for settings, entry editor, collection editor (and plugin admin pages where save applies) |
| **P32-D8** | Media: action bar pattern + detail panel + alt text + focal point + bulk ops + list/grid toggle |
| **P32-D9** | **New fieldtypes:** Phone, Toggle, Markdown, oEmbed/Video, Code/JSON |
| **P32-D10** | **Core features:** entry duplicate, global admin search, soft delete/trash, bulk field edit, collection field templates, entry revision history, redirect manager, workflow polish (pending-review inbox) |
| **P32-D11** | Single notification at **P32-M24** |

---

## Admin chrome rules (canonical)

### Vertical alignment

Sidebar nav starts at: `sidebar padding-top` + `sidebar-header (64px)` + `margin-bottom` → match in `.main-content` by reducing stacked top chrome (topbar margin, hoisted page-header, excessive padding).

### Action zones

```
┌─ topbar ─────────────────────────────────────────────┐
│ [breadcrumb …]              [theme] [account]        │
├─ main-actions-bar ───────────────────────────────────┤
│ [Primary CTA] [Secondary]     [filters ▼] [⋯]      │  ← left-aligned primary
├─ sticky-save-bar (when editing) ─────────────────────┤
│ [Save] [Save & close]              [status toast]    │  ← sticks on scroll
├─ page content ───────────────────────────────────────┤
```

### Icon row actions (smart-table)

Use `.btn-icon.btn-icon--edit` and `.btn-icon.btn-icon--danger` with inline SVG — no visible text label; `aria-label` required.

---

## Fieldtypes (P32 deliverables)

| Type | Purpose |
|------|---------|
| **Phone** | `tel` input, basic validation, optional country hint |
| **Toggle** | On/off switch UI (replaces checkbox for boolean settings-style fields) |
| **Markdown** | Lightweight markdown editor + preview; stored as markdown or HTML per config |
| **oEmbed/Video** | YouTube/Vimeo URL → embed preview; stored URL + optional iframe HTML |
| **Code/JSON** | Syntax-highlighted textarea; JSON validation when `format: json` |

All self-contained under `fieldtypes/{Name}/` per project values.

---

## Core platform features (P32 deliverables)

| Feature | Behavior |
|---------|----------|
| **Entry duplicate** | Clone entry + field values (+ slug suffix `-copy`); permission-gated |
| **Global admin search** | Topbar search queries entries, collections, media, settings shortcuts; keyboard `/` focus |
| **Soft delete / trash** | Delete moves to trash store; Trash admin view + restore + permanent delete |
| **Bulk field edit** | From entries list: select N entries → edit one field value for all |
| **Collection field templates** | Presets when creating collection ("Blog post", "Product", "Landing page") seed fields |
| **Entry revision history** | Store snapshots on save; diff view + restore revision (flat-file, per entry) |
| **Redirect manager** | Settings or dedicated admin: from-path → to-path, 301/302; runtime router hook |
| **Workflow polish** | Pending-review inbox widget + filter; optional email/webhook on submit-for-review |

---

## Milestones (25 — one wave, M0→M24)

| ID | Focus |
|----|--------|
| **P32-M0** | Gap register + matrix + admin page action audit |
| **P32-M1** | Shell alignment CSS (`--admin-shell-offset-top`, topbar/content gap) |
| **P32-M2** | Hoisted-breadcrumb pages: zero-height `page-header` everywhere |
| **P32-M3** | Icon button variants in `buttons.css` (edit, delete, customize, duplicate) |
| **P32-M4** | `main-actions-bar` partial/pattern + migration guide |
| **P32-M5** | **Sticky save bar** component + settings integration |
| **P32-M6** | Sticky save on entry editor + collection editor |
| **P32-M7** | Smart-table icon edit/delete + sub-entry rows |
| **P32-M8** | Dashboard + settings action bar (Customize left; Save in sticky bar) |
| **P32-M9** | Page audit: content, plugins, help, webhooks, updates, roles, plugin pages |
| **P32-M10** | Media: `main-actions-bar` + toolbar restructure |
| **P32-M11** | Media: detail panel, alt text, focal point, URL copy |
| **P32-M12** | Media: bulk select/move/delete, empty state, drag-drop, list/grid toggle |
| **P32-M13** | Fieldtype: Phone |
| **P32-M14** | Fieldtype: Toggle |
| **P32-M15** | Fieldtype: Markdown |
| **P32-M16** | Fieldtypes: oEmbed/Video + Code/JSON |
| **P32-M17** | Core: entry duplicate (API + UI) |
| **P32-M18** | Core: global admin search |
| **P32-M19** | Core: soft delete / trash + restore |
| **P32-M20** | Core: bulk field edit |
| **P32-M21** | Core: collection field templates |
| **P32-M22** | Core: entry revision history |
| **P32-M23** | Core: redirect manager + workflow polish (pending-review inbox) |
| **P32-M24** | Smoke tests, Birkly-Docs, matrix green, closure, single notification |

**Branches:** `cursor/p32-m[N]-<slug>-030a`

---

## Files (primary)

| Area | Files |
|------|--------|
| Shell | `admin/css/layout/main-content.css`, `header.css`, `sidebar.css`, `layout_footer.php` |
| Actions | `admin/partials/main-actions-bar.php`, `sticky-save-bar.php`, `header.css` |
| Tables | `admin/templates/components/smart-table.html`, `.php`, `tables.css` |
| Media | `admin/templates/media.html`, `media.css`, `media-page.js`, `api/media.php` |
| Fieldtypes | `fieldtypes/Phone/`, `Toggle/`, `Markdown/`, `OEmbed/`, `Code/` |
| Core | `core/trash.php`, `core/revisions.php`, `core/redirects.php`, `api/search.php`, `api/admin_entries.php` |
| Editors | `entry-editor.html`, `collection-editor.html`, `settings.html` |
| Tests | `tests/*_test.php`, `scripts/admin-polish-smoke.sh` |

---

## After P32

Resume **P28** beta launch automation (journeys + videos).
