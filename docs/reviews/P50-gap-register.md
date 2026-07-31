# Phase 50 — Gap register (content model clarity & admin quality)

**Plan:** `P50-content-model-clarity-plan.md`  
**Status:** Closed 2026-07-29 (P50 execution)

| ID | Area | Gap | Severity | Milestone | Status |
|----|------|-----|----------|-----------|--------|
| G50-01 | Docs | No single “content model overview” in Birkly-Docs | High | M1 | **Closed** — `02-content/content-model-overview.md` |
| G50-02 | Docs | `collections.md` exists but no dedicated Help topic `collections` in CMS manifest | Medium | M1/M2 | **Closed** — help-manifest topic + sync |
| G50-03 | Docs | CMS `docs/help-manifest.json` drifts from Birkly-Docs | High | M2 | **Closed** — sync copies live manifest |
| G50-04 | Docs | `collection-groups` Help topic snapshot = `entries.md` | High | M2 | **Closed** — per-topic `collection-groups.md` snapshot |
| G50-05 | Docs | `sync-birkly-docs-manifest.php` drops `ai_topic` in offline manifest | Medium | M2 | **Closed** |
| G50-06 | Docs | Legacy `admin/docs/*.php` duplicates Birkly-Docs; playbooks still cite paths | Medium | M2 | **Closed** — README + playbook links to docs.birkly.com |
| G50-07 | Docs | AI playbooks say “singleton entry” without user overview link | Low | M1/M2 | **Closed** — site-elements pattern + playbook update |
| G50-08 | DynamicItems | Multilang HTML rewrite breaks container id vs onclick | **Blocker** | M3 | **Closed** — `birkly_admin_multilang_field_html` |
| G50-09 | DynamicItems | Duplicate `initDynamicItemsField` in PHP bundle | Medium | M3 | **Closed** |
| G50-10 | DynamicItems | Shim selector `.dynamic-items-field` vs `.block-field` | Low | M3 | **Closed** |
| G50-11 | DynamicItems | No `getConfigUI()` for allowed blocks | Low | M3 | **Deferred** — non-blocking |
| G50-12 | DynamicItems | `showAddBlockModal` silent fail on bad config | Low | M3 | **Deferred** — non-blocking |
| G50-13 | Collection UX | Post-create save: toast only, no CTA | High | M4 | **Closed** — success modal |
| G50-14 | Collection UX | Submit blocked in language input — no message | High | M4 | **Closed** — warning toast |
| G50-15 | Collection UX | `update_sub_collection` wrong index (`$index`) | Medium | M4 | **Closed** — `$targetIndex` |
| G50-16 | Sub-collections | Standalone editor nested subs not saved | High | M4 | **Closed** — POST nested processing |
| G50-17 | Sub-collections | Removed subs preserved on save | High | M4 | **Closed** — `sub_collections_replace` |
| G50-18 | Sub-collections | Inline save wrong `parent_field` for nested | Medium | M4 | **Deferred** — verify in production QA |
| G50-19 | Sub-collections | No field reorder in sub-collection editor | Low | M4 | **Closed** — reorderContainerId wired |
| G50-20 | Fieldtypes | Empty `getJavaScript()` for several types | Medium | M5 | **Deferred** — shim loads fieldtype.js where present |
| G50-21 | Fieldtypes | ContentEditor alias inconsistency | Medium | M5 | **Closed** — aliases in `getFieldtypeClass` |
| G50-22 | Fieldtypes | `api/fieldtypes.php get_all` excludes ContentEditor | Low | M5 | **Deferred** |
| G50-23 | Fieldtypes | No automated fieldtype matrix in CI | Medium | M5 | **Partial** — DynamicItems PHPUnit; manual V-FT |
| G50-24 | Fieldtypes | Haru legacy naming | Low | — | **Deferred** |
| G50-25 | Docs | Site-elements pattern not in user docs | Medium | M1 | **Closed** |
| G50-26 | Docs | Dynamic items not in field-types page | Medium | M1 | **Closed** — dedicated `dynamic-items-field.md` |

## Resolved (pre-P50 baseline)

| ID | Note |
|----|------|
| — | Live fetch from `docs.birkly.com/help-manifest.json` already merges at runtime (`core/help_docs.php`) |
| — | `scripts/sync-birkly-docs.sh` exists for offline snapshots |
