# UX Flow Matrix — maintained from P2-M1 baseline

**Last updated:** 2026-06-25 (P4-M2 complete)  
**Environment:** PHP 8.3.6, `localhost:8080`, demo content from `create-demo-content.php`  
**Automated:** `./scripts/ci-check.sh` PASS · `php tests/run.php` PASS (26 suites after P4-M2)  
**Browser QA:** Phase 2–3 flows PASS; P4 onboarding/routing/AI connection — HTTP E2E in CI (P4-M2)

## Matrix

| Flow | Area | Verdict | Phase 2 | Evidence / notes |
|------|------|---------|---------|------------------|
| Admin login | Auth | **pass** | — | Unchanged from P2-M1 baseline. |
| Onboarding wizard (fresh user) | Onboarding | **pass** | P2-M5 | `reset-onboarding.php --keep-entries` → wizard loads; skip/complete sets dashboard checklist (#25). |
| Create collection + fields | Collections | **pass** | P2-M2 | `collection_schema_test.php`; create_collection accepts multiple schema shapes. |
| Create/edit/publish entry | Entries | **pass** | P2-M2/M3 | Select options, slug preservation, legacy `editor`/`media` aliases (#16–#17). |
| ContentEditor visual ↔ HTML save/reload | ContentEditor | **pass** | P2-M3 | Visual/HTML tabs; `html_roundtrip_test.php`; browser QA on blog entry (#19–#24). |
| Media upload + pick in field | Media | **pass** | P2-M3 | `media` alias → `file` fieldtype (#16). |
| Collection groups + drag/drop | Groups/DnD | **pass** | P2-M4 | `collection_groups_test.php`; browser QA reorder groups after refresh (#20). |
| Settings (domains, plugins) | Settings | **pass** | — | Unchanged from P2-M1 baseline. |
| Public API list collections | API | **pass** | P2-M6 | `public_collection_meta_test.php` — contact `id` fallback (#26). |
| Demo website / client render | Client | **pass** | P2-M6/M7 | `page_snapshot_test.php`; browser/curl `page_snapshot.php?page=index` → `status: ok`. |
| page_bundle / page_snapshot | M4 APIs | **pass** | P2-M6 | Snapshots generated via `scripts/generate-page-snapshots.php`; dev smoke documented in P2-M7 notes. |
| AI connect + role assign | BYOAI | **pass** | P3-M2/M3 | ACL helpers, dynamic role picker, `require_approval` default true |
| Admin AI chat (read action) | BYOAI | **pass** | P3-M4 | Tool registry + floating chat; `ai_tool_registry_test.php` |
| AI write → approval queue | BYOAI | **pass** | P3-M2/M4 | Approval ACLs; owner/shared reviewers |
| MCP external connect | BYOAI | **pass** | P3-M5 | Per-connection `mcp_api_key`; `mcp-bridge.js` BIRKLY_* env |
| Automation builder page | Automation | **pass** | P3-M6 | `plugins/automation/`; `?page=automation` via plugin admin page |
| Automation trigger → log | Automation | **pass** | P3-M7 | Queue + CLI `process_queue.php`; `automation_plugin_test.php` |
| User plugin admin + permissions | User plugin | **pass** | P3-M8 | `site_users` page; `user_plugin_permissions_test.php` |
| Alpha feedback submit → admin | Feedback | **pass** | P3-M10 | `api/feedback.php`; widget + admin list |
| Help home + doc search | Help | **pass** | P3-M11 | Manifest search + snapshots; contextual `?` links |
| ContentEditor blocks save/reload | ContentEditor | **pass** | P3-M12 | Block registry (divider, table); `html_roundtrip_test.php` |
| Birkly-Website alpha copy + legal | Client site | **pass** | P3-M13 | Seed script + `beta_legal`; DEV.md preview |
| Onboarding E2E (CSRF + project save) | Onboarding | **pass** | P4-M2 | `onboarding_e2e_test.php` — create user → CSRF → settings → skip |
| Admin routing HTTP (content/media/settings) | Admin | **pass** | P4-M2 | `admin_routing_http_test.php` — pages render distinct templates |
| AI connection delete → chat rejects | BYOAI | **pass** | P4-M2 | `ai_chat_connection_test.php`; header picker PR #35 |
| MCP standalone endpoints | MCP | **pending** | P4-M3/M4 | `settings/mcp_endpoints.json` not yet shipped |
| MCP client setup (Claude Desktop) | MCP | **pending** | P4-M6 | Video V16 |
| BYOAI connection wizard | BYOAI | **pending** | P4-M7 | Video V17 |

## Summary counts

| Verdict | P2-M1 | Post P2-M2 | Post Phase 2 | Post Phase 3 |
|---------|-------|------------|--------------|--------------|
| pass | 4 | 9 | **11** | **22** | **25** |
| partial | 6 | 2 | 0 | **0** | **0** |
| fail | 1 | 0 | 0 | **0** | **0** |
| pending | — | — | — | — | **3** |

## Register closure (P2-M1 → P2-M6)

| ID | Status | PR |
|----|--------|-----|
| P2-R01–R05 | fixed | #16–#24 (ContentEditor) |
| P2-R06–R07 | fixed | #25 (onboarding) |
| P2-R08–R10 | fixed | #20 (groups DnD) |
| P2-R11–R12 | fixed | #16–#17 |
| P2-R13–R15 | fixed | #26 (snapshots, metadata) |

## Gate

**Phase 3 UX gate:** All matrix rows pass (22/22). Automated CI 21 suites PASS. Walkthrough videos V7–V14 in `docs/reviews/artifacts/`.

**Post-merge hotfix:** P2-M6 safe renderer was blocking legacy admin PHP (`require_once`, `include`, `required` keys). Fixed by validating parser output only; trusted template PHP blocks excluded from validation.
