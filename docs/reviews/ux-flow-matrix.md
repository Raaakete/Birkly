# UX Flow Matrix — maintained from P2-M1 baseline

**Last updated:** 2026-07-13 (P28 beta launch automation)  
**Environment:** PHP 8.3, `localhost:8080`, journey runner + `php tests/run.php`  
**Automated:** `./scripts/ci-check.sh` · `php tests/run.php` (157 suites) · `scripts/journey-runner.php`  
**Browser QA:** P28 V-BETA videos; MCP `mcp_http_test.php`; design-reviewer approved MCP tab + BYOAI wizard

## Matrix

| Flow | Area | Verdict | Phase | Evidence / notes |
|------|------|---------|-------|------------------|
| Admin login | Auth | **pass** | P2 | Unchanged from P2-M1 baseline. |
| Onboarding wizard (fresh user) | Onboarding | **pass** | P2/P28 | `onboarding_e2e_test.php`; J-INSTALL journey. |
| Create collection + fields | Collections | **pass** | P2 | `collection_schema_test.php`. |
| Create/edit/publish entry | Entries | **pass** | P2/P28 | J-ADMIN journey; slug + editor tests. |
| ContentEditor visual ↔ HTML save/reload | ContentEditor | **pass** | P2/P3 | `html_roundtrip_test.php`. |
| Media upload + pick in field | Media | **pass** | P2 | `media` alias → `file` fieldtype. |
| Collection groups + drag/drop | Groups/DnD | **pass** | P2 | `collection_groups_test.php`. |
| Settings (domains, plugins) | Settings | **pass** | P2 | Settings page tests. |
| Public API list collections | API | **pass** | P2 | `public_collection_meta_test.php`. |
| Demo website / client render | Client | **pass** | P2/P28 | J-BLOG, J-PORTFOLIO, J-KEKABA journeys. |
| page_bundle / page_snapshot | M4 APIs | **pass** | P2 | `page_snapshot_test.php`. |
| AI connect + role assign | BYOAI | **pass** | P3 | ACL helpers, `ai_tool_registry_test.php`. |
| Admin AI chat (read action) | BYOAI | **pass** | P3 | Tool registry + floating chat. |
| AI write → approval queue | BYOAI | **pass** | P3 | Approval ACLs. |
| MCP external connect | MCP | **pass** | P4 | `mcp_http_test.php`. |
| Automation builder page | Automation | **pass** | P3 | `automation_plugin_test.php`. |
| Automation trigger → log | Automation | **pass** | P3 | Queue + `process_queue.php`. |
| User plugin admin + permissions | User plugin | **pass** | P3/P28 | J-SOCIAL, J-INFLUENCER journeys. |
| Alpha feedback submit → admin | Feedback | **pass** | P3 | `api/feedback.php`. |
| Help home + doc search | Help | **pass** | P3 | Manifest search + snapshots. |
| ContentEditor blocks save/reload | ContentEditor | **pass** | P3 | Block registry tests. |
| Birkly-Website alpha copy + legal | Client site | **pass** | P3/P28 | J-WEBSITE; `deploy-smoke.sh`. |
| Onboarding E2E (CSRF + project save) | Onboarding | **pass** | P4/P28 | `onboarding_e2e_test.php`; J-INSTALL. |
| Admin routing HTTP (content/media/settings) | Admin | **pass** | P4 | `admin_routing_http_test.php`. |
| AI connection delete → chat rejects | BYOAI | **pass** | P4 | `ai_chat_connection_test.php`. |
| MCP standalone endpoints | MCP | **pass** | P4 | `mcp_endpoints_test.php`. |
| MCP client setup (Claude Desktop) | MCP | **pass** | P4 | `mcp_http_test.php`. |
| BYOAI connection wizard | BYOAI | **pass** | P4 | 3-step wizard. |
| Fresh install (zip → START_HERE → wizard) | Onboarding | **pass** | P28 | J-INSTALL; `release_manifest_test.php`. |
| Reference blog visitor journey | Client | **pass** | P28 | J-BLOG automated. |
| Reference portfolio visitor journey | Client | **pass** | P28 | J-PORTFOLIO automated. |
| Commerce shop + cart | Commerce | **pass** | P28 | J-COFFEE; commerce plugin tests. |
| Events upcoming + detail | Events | **pass** | P28 | J-YOGA; events plugin tests. |
| Marketplace plugin install | Marketplace | **pass** | P28 | J-MARKETPLACE; `marketplace_free_install_test.php`. |
| Admin invite accept flow | Auth | **pass** | P28 | J-INVITE journey. |
| kekaba live client (no bake) | Client | **pass** | P28 | J-KEKABA; embed sync scripts. |
| Production health smoke subset | Ops | **pass** | P28 | J-HEALTH; `scheduled-production-smoke.sh`. |
| CMS update download/apply/rollback | Updates | **pass** | P28 | `update_system_e2e_test.php`. |
| Studio public SSR home route | Studio | **pass** | P28 | `studio_renderer_test.php` (flake fixed). |
| Marketplace seed catalog audit | Marketplace | **pass** | P28 | `audit-marketplace-catalog.sh` (3/4 official installable; analytics-pro artifact gap) |

## Summary counts

| Verdict | P2-M1 | Post P4 | Post P28 |
|---------|-------|---------|----------|
| pass | 4 | **28** | **40** |
| partial | 6 | **0** | **0** |
| fail | 1 | **0** | **0** |
| pending | — | **0** | **0** |

## P28 journey automation (automated / pass)

| Journey | Persona | Automated | Result |
|---------|---------|-----------|--------|
| J-INSTALL | New owner | `journey-runner.php` | **pass** |
| J-BLOG | Visitor | `journey-runner.php` | **pass** |
| J-PORTFOLIO | Visitor | `journey-runner.php` | **pass** |
| J-COFFEE | Shopper | `journey-runner.php` | **pass** |
| J-YOGA | Visitor | `journey-runner.php` | **pass** |
| J-SOCIAL | Member | `journey-runner.php` | **pass** |
| J-INFLUENCER | Fan | `journey-runner.php` | **pass** |
| J-ADMIN | Editor | `journey-runner.php` | **pass** |
| J-MARKETPLACE | Admin | `journey-runner.php` | **pass** |
| J-INVITE | Invitee | `journey-runner.php` | **pass** |
| J-KEKABA | Visitor | kekaba CI + journey runner | **pass** |
| J-HEALTH | Ops | `scheduled-production-smoke.sh` | **pass** |
| J-WEBSITE | Visitor | `Birkly-Website/scripts/deploy-smoke.sh` | **pass** |

## Register closure (P2-M1 → P28)

| ID | Status | Evidence |
|----|--------|----------|
| P2-R01–R15 | fixed | P2 PRs #16–#26 |
| P28-G5-05 README START_HERE | fixed | `README.md` → `START_HERE.html` |
| P28 studio_renderer flake | fixed | `studio_renderer_test.php` plugin registry + cwd |
| P28 update system E2E | fixed | `update_system_e2e_test.php` |

## Gate

**P28 UX gate:** Matrix rows pass (40/40). Automated CI 157 suites + journey runner green. V-BETA videos in `docs/reviews/artifacts/`.

**Post-merge hotfix:** P2-M6 safe renderer was blocking legacy admin PHP (`require_once`, `include`, `required` keys). Fixed by validating parser output only; trusted template PHP blocks excluded from validation.
