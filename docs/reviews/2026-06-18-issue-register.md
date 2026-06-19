# P2-M1 Issue & Opportunity Register — 2026-06-18

Findings from system review gate. Severity: **P0** blocker · **P1** high · **P2** medium · **P3** low.

| ID | Type | Area | Finding | Severity | Phase 2 |
|----|------|------|---------|----------|---------|
| P2-R01 | bug | ContentEditor / Entries | Demo collections (`blog`, `services`, `about`) schema uses legacy fieldtype `editor`, which is **not registered**. Entry editor shows `Fieldtype "editor" not found` — content body cannot be edited in admin. | P1 | P2-M3 |
| P2-R02 | bug | ContentEditor / Entries | Demo schemas use `media` fieldtype — **not registered** (only `file`/`gallery` exist). Featured image fields fail to render on blog entry editor. | P1 | P2-M3 |
| P2-R03 | bug | Entries | Blog `status` select field throws PHP error: `Cannot access offset of type string on string`. Demo schema stores `options` as `[{label,value}]` objects but some entries may store flat strings — Select renderer assumes object shape. | P1 | P2-M2 |
| P2-R04 | regression | ContentEditor | `entry-editor.js` references `window.EditorModes.advanced` / `.block` for legacy editor sync, but no `EditorModes` script is loaded. Dead code path after ContentEditor migration. | P2 | P2-M3 |
| P2-R05 | ux | ContentEditor | No visual ↔ HTML source tab in ContentEditor; handoff flow cannot be completed. Save/reload is WYSIWYG → hidden input only (`sync-manager.js`). HTML round-trip editing not exposed in UI. | P2 | **P2-M3** |
| P2-R06 | ux | Onboarding | After bootstrap, `onboarding.completed=false` while `is_first_time=false`. Wizard not forced on dashboard (good) but completion state never set — `setup_wizard_completed` stays false; may confuse first-run analytics. | P2 | **P2-M5** |
| P2-R07 | ux | Onboarding | Fresh-install redirect only triggers when `is_first_time && !completed`. Pre-seeded demo content skips forced onboarding entirely — first-run UX untested without wiping `content/`. | P3 | **P2-M5** |
| P2-R08 | bug | Groups/DnD | No dedicated `list_groups` API action; unknown actions hit content.php default handler returning misleading `{message:"Content API is working"}`. Groups load from server-rendered JSON only. | P2 | **P2-M4** |
| P2-R09 | ux | Groups/DnD | `content-groups.js` uses `MouseDragDropManager` with nested group + collection selectors; `simple-drag-drop.js` contains extensive debug `console.log` and cross-container guards suggesting known reorder bugs. Cross-group drop needs hardening. | P2 | **P2-M4** |
| P2-R10 | ux | Groups/DnD | Group collapse state stored in `localStorage` per group ID — clearing browser storage resets UI but not server order; no conflict handling. | P3 | P2-M4 |
| P2-R11 | bug | Entries | `update_entry` regenerated slug from title during review test (`welcome-to-birkly` → `review-title-test-*`), breaking public URLs without explicit slug edit. | P1 | P2-M2 |
| P2-R12 | ux | Collections | `create_collection` API expects `schema` object, not `fields` array at top level. Silent empty schema when mis-keyed (review-test collection created with `[]`). | P2 | P2-M2 |
| P2-R13 | perf | Client / M4 | `page_snapshot.php` returns 404 for all pages — publish pipeline does not generate snapshots in dev bootstrap. Clients fall back to serial public API reads. | P2 | P2-M6 |
| P2-R14 | opportunity | Client / M4 | `page_bundle.php` works when requests include `type` + `into`; demo site uses `birkly-client.js` serial fetches. Bundle adoption would cut round-trips (see kekaba benchmark docs). | P3 | P2-M6 |
| P2-R15 | ux | API | Public `list_collections` returns `id: null` for `contact` collection — inconsistent metadata for client routing. | P3 | P2-M6 |

## Priority themes for Phase 2

1. **ContentEditor (P2-M3):** Alias `editor` → `content-editor`, fix demo schemas, add HTML source mode, remove EditorModes dead code.
2. **Collection groups DnD (P2-M4):** Stabilize cross-group moves, add `list_groups`, reduce debug noise.
3. **Onboarding (P2-M5):** Mark completion after bootstrap or demo install; test true fresh-user path.

## P0 status

**None identified.** Auth boundaries pass automated tests; admin login and public read APIs functional.
