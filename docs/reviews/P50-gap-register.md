# Phase 50 — Gap register (content model clarity & admin quality)

**Plan:** `P50-content-model-clarity-plan.md`  
**Status:** Open at planning — close items during M1–M6 execution

| ID | Area | Gap | Severity | Milestone | Status |
|----|------|-----|----------|-----------|--------|
| G50-01 | Docs | No single “content model overview” in Birkly-Docs | High | M1 | Open |
| G50-02 | Docs | `collections.md` exists but no dedicated Help topic `collections` in CMS manifest | Medium | M1/M2 | Open |
| G50-03 | Docs | CMS `docs/help-manifest.json` (17 topics) drifts from Birkly-Docs (60+ topics) | High | M2 | Open |
| G50-04 | Docs | `collection-groups` Help topic snapshot = `entries.md` (wrong body in admin) | High | M2 | Open |
| G50-05 | Docs | `sync-birkly-docs-manifest.php` drops `ai_topic` in offline manifest | Medium | M2 | Open |
| G50-06 | Docs | Legacy `admin/docs/*.php` duplicates Birkly-Docs; playbooks still cite paths | Medium | M2 | Open |
| G50-07 | Docs | AI playbooks say “singleton entry” without pointing to user-facing overview | Low | M1/M2 | Open |
| G50-08 | DynamicItems | Multilang `birkly_admin_multilang_field_html` breaks container id vs onclick field name | **Blocker** | M3 | Open |
| G50-09 | DynamicItems | Duplicate `initDynamicItemsField` in PHP `getJavaScript()` bundle | Medium | M3 | Open |
| G50-10 | DynamicItems | `fieldtypes-shim.js` uses `.dynamic-items-field` but render uses `.block-field` | Low | M3 | Open |
| G50-11 | DynamicItems | No `getConfigUI()` — cannot restrict allowed blocks at schema time | Low | M3 | Open |
| G50-12 | DynamicItems | `showAddBlockModal` silent fail on bad `data-field-config` | Low | M3 | Open |
| G50-13 | Collection UX | Post-create save: toast only, no redirect / CTA | High | M4 | Open |
| G50-14 | Collection UX | Submit blocked when focus in language input — no user message | High | M4 | Open |
| G50-15 | Collection UX | `update_sub_collection` returns wrong index (`$index` undefined) | Medium | M4 | Open |
| G50-16 | Sub-collections | Standalone sub-editor does not save nested `sub_collections` from form | High | M4 | Open |
| G50-17 | Sub-collections | Removing sub-collection in UI does not delete on save (preserve merge) | High | M4 | Open |
| G50-18 | Sub-collections | Inline save sets wrong `parent_field` for nested subs | Medium | M4 | Open |
| G50-19 | Sub-collections | No field reorder UI in sub-collection editor mode | Low | M4 | Open |
| G50-20 | Fieldtypes | Checkbox, Select, Radio, Date, DateTime, Number, Textarea — empty `getJavaScript()` | Medium | M5 | Open |
| G50-21 | Fieldtypes | ContentEditor: `content-editor` vs `contenteditor` alias inconsistency | Medium | M5 | Open |
| G50-22 | Fieldtypes | `api/fieldtypes.php get_all` excludes ContentEditor (no fieldtype.js) | Low | M5 | Open |
| G50-23 | Fieldtypes | No automated fieldtype matrix in CI | Medium | M5 | Open |
| G50-24 | Fieldtypes | Haru legacy naming (`HaruFieldtypes`) — doc only, no rename in P50 | Low | — | Deferred |
| G50-25 | Docs | `site-elements` pattern not documented for end users (only in playbooks) | Medium | M1 | Open |
| G50-26 | Docs | Dynamic items usage not in Birkly-Docs field-types page | Medium | M1 | Open |

## Resolved (pre-P50 baseline)

| ID | Note |
|----|------|
| — | Live fetch from `docs.birkly.com/help-manifest.json` already merges at runtime (`core/help_docs.php`) |
| — | `scripts/sync-birkly-docs.sh` exists for offline snapshots |
