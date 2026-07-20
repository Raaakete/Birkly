# P29 — Collections & entries excellence

**Status:** Complete (2026-07-20, P29-M15)  
**Baseline:** P27 complete · P28 **paused** until P29-M15 · P26 Studio **paused**  
**Human rule:** Execute P29-M0→M15 autonomously. Notify human **once** at **P29-M15**.

**Gap register:** `docs/reviews/P29-gap-register.md`  
**Handoff:** `docs/reviews/P29-lead-engineer-handoff.md`  
**Matrix:** `docs/reviews/P29-verification-matrix.md`

---

## Vision

Restore and polish the **core content model** — collections, entries, and their editors — before beta automation (P28) or Studio creative work (P26).

Human-reported blocker: **right sidebar tabs empty** on collection and entry create/edit pages. Slug must live in the sidebar with predictable auto-generation.

**Scope:** `admin/templates/` (entry-editor, collection-editor, entries), `admin/css/`, `admin/js/`, `api/admin_entries.php`, `api/admin_collections.php`, `core/entry_slug.php`, tests, Birkly-Docs.

**Out of scope:** Studio P26, P28 journeys, marketplace, kekaba live-path.

---

## Locked decisions (approved)

| ID | Decision |
|----|----------|
| **P29-D1** | **Pause P28** until P29-M15 closure |
| **P29-D2** | Fix sidebar CSS: `.sidebar-tabs .tab-content` must **not** inherit hidden state from global `tabs.css` |
| **P29-D3** | Tab switching JS scoped to `.sidebar-tabs` only |
| **P29-D4** | **Slug in sidebar only** — never in main field loop |
| **P29-D5** | **Reserved entry fields** in sidebar only: `slug`, `status`, `published_at`, `expires_at`, `reviewer`, `meta_title`, `meta_description` |
| **P29-D6** | **Slug auto-generation:** from title when title is set |
| **P29-D7** | **No title:** assign auto title = **entry ID** (create) or **collection ID**; slug = same ID (normalized) |
| **P29-D8** | **Custom slug:** user can type a custom slug at any time |
| **P29-D9** | **“Auto-generate slug from title” checkbox** below slug field — **checked by default**; when checked, slug follows title/ID rules; when unchecked, slug is manual until re-checked |
| **P29-D10** | Collection editor: same slug checkbox pattern in Settings tab |
| **P29-D11** | API `birkly_resolve_entry_slug()` extended for no-title → ID fallback; preserve custom slug when checkbox off |
| **P29-D12** | Sub-entry editors inherit same sidebar + slug rules |
| **P29-D13** | Single notification at **P29-M15** |

---

## Slug behavior (canonical)

### Entry create

1. User types title → slug auto-updates (if auto checkbox checked).
2. User saves without title → server assigns entry ID; title defaults to ID; slug = normalized ID.
3. User unchecks auto checkbox → slug field editable; changes do not track title until re-checked.
4. User checks auto checkbox → slug immediately regenerates from current title (or ID if title empty).

### Entry update

1. Auto checkbox **on** + title changes → slug updates only if entry had no established custom slug **or** user re-enabled auto (same as create UX).
2. Auto checkbox **off** → slug preserved unless user edits slug field (P2-R11: no silent slug change on title-only save).
3. Custom slug in POST always wins when auto is off.

### Collection create/edit

Same pattern using collection ID as fallback when name/title empty.

---

## Milestones (15 — one wave)

| ID | Focus |
|----|--------|
| **P29-M0** | Gap register + matrix shell (this doc) |
| **P29-M1** | **Sidebar CSS fix** — `tabs.css` scope + `content.css` editor overrides |
| **P29-M2** | **Tab JS** — scope to `.sidebar-tabs`; entry + collection editors |
| **P29-M3** | **Entry slug UX** — sidebar only, auto checkbox, title/ID fallback, remove duplicate from main |
| **P29-M4** | **Collection slug UX** — Settings tab parity with entry editor |
| **P29-M5** | **API slug logic** — `entry_slug.php` + `admin_entries.php` + collection save paths |
| **P29-M6** | **Reserved fields** — filter system fields from main `$fields` loop |
| **P29-M7** | **Entries list** — status filter, bulk actions verification + fixes |
| **P29-M8** | **Collection editor** — field management polish; sub-collection sidebar parity |
| **P29-M9** | **Sub-entries** — breadcrumb + sidebar consistency |
| **P29-M10** | **Entry detail** — slug/status display; link to editor General tab |
| **P29-M11** | **Tests** — `entry_editor_sidebar_test.php`, `collection_editor_sidebar_test.php`, slug fallback tests |
| **P29-M12** | **`scripts/admin-content-smoke.sh`** wired in CI |
| **P29-M13** | **Birkly-Docs** — entry/collection editor sidebar + slug docs |
| **P29-M14** | **ux-flow-matrix.md** refresh |
| **P29-M15** | Matrix green, closure checklist, ROADMAP, single notification |

**Branches:** `cursor/p29-m[N]-<slug>-030a`

---

## Files (primary)

| Area | Files |
|------|--------|
| CSS | `admin/css/components/tabs.css`, `admin/css/pages/content.css` |
| Templates | `admin/templates/entry-editor.html`, `collection-editor.html` |
| JS | `admin/js/entry-editor.js`, collection editor inline JS |
| API | `api/admin_entries.php`, `api/admin_collections.php`, `core/entry_slug.php` |
| Tests | `tests/entry_editor_sidebar_test.php`, `tests/entry_slug_test.php` (extend) |

---

## Regression targets

- P27 `admin-polish-smoke.sh` still green
- P25 `studio-smoke.sh` still green
- `studio_renderer_test` flake may be fixed opportunistically in M11 if trivial

---

## After P29

Resume **P28** beta launch automation (journeys + videos) unless human reprioritizes.
