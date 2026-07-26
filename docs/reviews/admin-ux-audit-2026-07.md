# Birkly admin — UI/UX audit (2026-07-26)

**Scope:** whole CMS admin, with deep passes on Settings, the collection editor, and the entry editor.
**Method:** four parallel design reviews of `admin/`, `api/`, `fieldtypes/`, plus manual verification of every P0 claim.
**Status:** findings only — no code changed by this audit.

---

## Verdict

The admin has a coherent visual language and some genuinely good work (chrome rhythm tokens, ContentEditor mode tabs, SERP/social previews, the smart-table sticky action column). Underneath it there are **functional dead ends that look like design problems**: controls that silently do nothing, workflows with no exit, and two editors that cannot be operated by keyboard.

Fix order: **broken behaviour → data safety → accessibility floor → information architecture → performance.** Settings IA is the loudest complaint but the third-most valuable fix.

---

## P0 — broken or unsafe

| # | Finding | Where | Impact |
|---|---------|-------|--------|
| P0-1 | **Inline `onclick` is dead admin-wide.** Admin CSP is nonce-only (`script-src 'self' 'nonce-…'`); a nonce cannot apply to an attribute. 61 inline handlers remain: settings 37, plugins 5, roles 5, account 5, webhooks 4, shared modal 3, content 2. | `api/validate.php:108`; `admin/templates/*.html` | Silent dead controls. Confirmed: New Group modal Cancel (`content.html:60`), keyboard-shortcuts dialog Close (`accessibility.js:357` — Alt+/ opens a dialog that cannot be closed). |
| P0-2 | **Select/radio/multiselect option builder cannot run.** `getConfigUI()` returns a bare `<script>` (no nonce); the re-injection loop copies attributes, and the `nonce` attribute is not reflected. Fallback `window.initFieldtypeConfig` is **never defined** (only the call site exists). | `fieldtypes/Select/Select.php:176`; `collection-editor.html:2318-2334` | A newly added select field cannot be given options at all. |
| P0-3 | **Schema saves rewrite `schema.json` with no diff, warning, or migration.** Entry values are flat keys named after the field; renaming or deleting a field orphans data invisibly. Removing a top-level field has **no confirmation** (sub-collection fields do confirm). | `api/admin_collections.php:844-852`; `collection-editor.html:2382-2388` | Silent data loss. |
| P0-4 | **`action` is ignored for create vs update.** The API locates a directory from the posted slug and writes into it. | `api/admin_collections.php:352-360` | Creating a collection whose slug already exists overwrites the live collection's schema. |
| P0-5 | **Review workflow is a dead end.** Status offers `pending_review` and a reviewer picker; `initApprovalWorkflow` binds `#approve-entry`, `#reject-entry`, `#request-changes` — none of which exist in any template. | `entry-editor.html:257,285-322`; `entry-editor.js:257-259` | Content can enter review with no approve/reject affordance anywhere. |
| P0-6 | **Bulk select-all never binds.** Template emits `select-all-entrys` (prefix + `s`); JS looks up `select-all-entries`. Every guard is `if (checkbox)`, so it fails silently. | `smart-table.php:127`; `entries.js:37` | Bulk actions require ticking rows one at a time, on an unpaginated list. |
| P0-7 | **No labels on entry fields.** Field name renders as `<div class="field-title">`; fieldtypes emit controls with only a placeholder. | `entry-editor.html:183-188`; `Text.php:49-61` | Screen readers announce "edit text, blank" for every field; clicking a label does nothing. |
| P0-8 | **Fieldtype picker and field reordering are keyboard-inaccessible.** Picker modal has no dialog role/focus trap and cards are click-only `<div>`s; `MouseDragDrop` is pointer-only with no keyboard alternative and no menu fallback. | `collection-editor.html:1284-1291,2141-2152`; `mouse-drag-drop.js:70-75` | Field order drives entry layout and templates — a keyboard user cannot change it. |
| P0-9 | **`accessibility.js` re-inits on every DOM mutation.** Observes `document.body` subtree, `setTimeout(init,100)` per mutation, `init()` is not idempotent: new global keydown listener per run, new MutationObserver per modal per run, duplicate nav keydown handlers. Dashboard `replaceChildren()` triggers it constantly. | `accessibility.js:411-422` | Unbounded listener/observer growth; Alt+/ toggles N times and appears broken. |

---

## P1 — high value

**Save model and data safety**

- Settings: saving on one tab **clears dirty state for all tabs** and reloads, silently discarding unsaved edits elsewhere (`settings-page.js:2217-2246`, `725-729`). Docs contradict the code (`admin/docs/snapshots/settings.md:7-18` claims one global save; `INDEPENDENT_SAVE_TABS` is declared and never read).
- Settings: success/error toasts write into `#settings-message`, which lives inside the sticky bar that is `display:none !important` on six of nine tabs — **feedback is invisible on Domains, Project, Redirects, API, Activity, AI**.
- Collection editor: **no unsaved-changes protection at all** (no `beforeunload`), Cancel is a plain link, and save hard-navigates away with `alert()` on error.
- Entry editor: **every save leaves the page** (`entry-editor.js:1206-1209`), no autosave, no draft recovery, and a failed save **silently erases the scheduled publish date** (ISO written into a `datetime-local` input, `:236-247`).
- DB password is rendered into page source as an input value (`settings.html:886-888`).

**Scale**

- Entries list has **no pagination, no sorting, no server search**. Every request globs and decodes every entry, instantiates a fieldtype object per field per entry, and walks the filesystem per row (`admin_templates.php:1563-1571,1720-1870`). Client-side search only filters rendered rows.
- Column overload is structural: every schema field becomes a column, headers print the **raw field name before the label** (`smart-table.php:142-146`) — hence `read_time`, `author_initials`, `author_role`. No column config exists.
- Collection editor issues **one config request per existing field** on load (N+1), re-serialises the whole fields array inside the per-field loop, and appends injected scripts to `<head>` forever.
- Repeaters make **one request per block** and `eval()` the response (`DynamicItems/fieldtype.js:331-394`).
- Two independent 30s polls run on every page for every role with no `visibilitychange` guard; the notifications dropdown double-fetches on open.
- Dashboard duplicates the same `list_collections` request across two widgets, then fans out up to 6 more to render 8 rows.

**Accessibility floor**

- Focus ring is mint `--primary-500` on white ≈ **1.5:1** (WCAG needs 3:1), and `dropdowns.css:352` kills it entirely with `outline: none !important`.
- Help text uses `--text-tertiary` `#C4C6C1` ≈ **1.7:1** at 12px — the guidance that makes settings usable is the least readable text on the page.
- `.tab.active` is `--primary-600` ≈ **2.0:1**, so the selected tab is harder to read than the unselected ones.
- Tab rows have no `role="tablist"/"tab"/"tabpanel"`, no `aria-selected`, no arrow-key navigation. Entry-editor language tabs declare `aria-selected` and never update it — stale ARIA, worse than none.
- No `prefers-reduced-motion` anywhere in `admin/`, with 19 keyframe blocks and `scale: 1.02` on every button hover.
- Dark theme does not override `--gray-*` or the semantic `-50/-100/-200` steps, so **every status badge keeps a pastel chip on the dark surface**. "System" theme has only 12 of 30 dark rules mirrored.

**Other**

- Missing per-collection field capabilities: no help text, default value, or unique for collection fields — though sub-collection fields persist all three (`admin_collections.php:563-576`). `birkly_entry_reserved_field_names()` exists but is never called on save, so a field can be named `slug` or `status`.
- No preview anywhere in the product. The edit→see-result loop requires publishing.
- Alt text/caption/focal point do not exist; `File.php:30` hardcodes `alt="{filename}"` and the template generator emits `alt="{fieldLabel}"`.
- Field `help` is collected, passed to `BaseField`, and never rendered — the slot shows the literal word "Required" instead.
- Entry sidebar is not sticky and drops **below** the article below 1024px, so publish controls sit thousands of pixels down.
- `?page=users` renders the whole settings page on General; six standalone templates (activity, webhooks, audit-log, updates, roles, redirects) duplicate settings tabs.

---

## P2 — debt and polish

- `settings.css` is **3,002 lines, loaded on every admin page**, with dead blocks (`.users-table`, `.plugins-grid`, `.notification-preferences`), unscoped `.tab-pane` / `.user-info`, and formatting that changes mid-file.
- **26 references to undefined tokens** in settings CSS (`--danger-*`, `--border-radius`, `--bg-muted`) — danger zone stays light-pink in dark mode; several AI boxes render square inside rounded cards. Collection editor uses undefined `--radius` and `--info-*`, and hardcodes `#3b82f6` checkboxes against a mint brand.
- `style.css` is 84 lines of `@import` — two sequential round-trips before first paint, and **only `style.css` is cache-versioned**, so edits to any of the 33 imported files ship stale. 12 of 18 global JS files have no version at all.
- `layout_footer.php` is 806 lines, ~740 of them inline JS — never cacheable — and `moveUserMenuToHeader()` (~60 lines) plus 25 retry attempts are **provably dead** because `#admin-topbar` always exists.
- `collection-editor.html` is 3,339 lines with 628 lines of inline CSS and 1,407 of inline JS; there is no `collection-editor.js` or `.css`, unlike every comparable screen.
- Native `confirm()`/`alert()` still used in the collection editor, entry-editor fieldtypes, and logout, despite `ui-feedback.js`.
- Partial i18n throughout the shell and settings; timezone select offers five US zones only.
- Two modal systems, three loading patterns, three error patterns, three icon mechanisms, two templating syntaxes (`{translate}` and `__()`).

---

## Corrected claim

An earlier pass reported unbalanced `</div>` in the Infrastructure pane causing panes to leak across tabs. **This is a false positive** — verified by parsing `settings.html`: all nine `.tab-pane` elements are direct children of `.settings-tab-panels` at consistent depth, and the container closes correctly. No markup fix is needed there.

---

## Proposed target IA for Settings

Six pages, each 2–5 cards with one save model, no overflow menu at ≥1024px:

1. **General** — project name, description, logo, timezone, language
2. **Site & delivery** — hosting URLs, project files, domains, redirects
3. **Team & access** — users, invitations, roles
4. **Security** — session/password policy, 2FA, CSRF, plugin denylist
5. **Integrations** — API, webhooks, AI
6. **System** — storage, migration, monitoring, updates, danger zone

Move out: media defaults → content configuration; activity/audit → the existing top-level Activity page.
Add: a settings search over labels and help text; `data-help-topic` per card; lazy pane loading.

---

## Recommended sequencing

**Wave A — stop the bleeding.** Migrate inline `onclick` to delegated handlers (CI grep to prevent regression); fix the option builder; add schema diff + destructive-change confirmation; honour `action` on create; fix `select-all`; resolve or remove the review workflow; make `accessibility.js` idempotent.

**Wave B — accessibility floor.** Focus ring contrast; help-text and active-tab contrast; real `<label for>` on entry fields; ARIA tabs with arrow keys; keyboard field reordering; reduced-motion block; dark-theme badge tokens.

**Wave C — save & feedback model.** One predictable save story per screen; save-in-place with toast and "Last saved"; unsaved-changes guards everywhere; autosave/draft recovery for entries; per-field errors instead of alerts.

**Wave D — scale.** Server pagination/sort/search for entries; label-first headers plus per-collection column config; batch fieldtype config requests; server-render repeater blocks; consolidate polls.

**Wave E — IA & structure.** Settings six-page IA with `?tab=` aliases; retire duplicate standalone templates; extract collection-editor JS/CSS; split and scope `settings.css`; per-file asset versioning; entry preview.
