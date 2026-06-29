# Phase 5 — integration test matrix

**Date:** 2026-06-18  
**Status:** CI green; manual Claude/ChatGPT connector QA where HTTPS available

| # | Test | Command / path | Expected | Result |
|---|------|----------------|----------|--------|
| 1 | AI delete — owner | `php tests/ai_permissions_test.php` | Owner can manage connection | **PASS** |
| 2 | AI delete — manage_settings | `php tests/ai_permissions_test.php` | Settings manager can delete others' connections | **PASS** |
| 3 | MCP Streamable — GET 405 | `php tests/mcp_streamable_test.php` | GET not allowed on `/mcp` | **PASS** |
| 4 | MCP Streamable — auth 401 | `php tests/mcp_streamable_test.php` | 401 + WWW-Authenticate | **PASS** |
| 5 | MCP Streamable — initialize | `php tests/mcp_streamable_test.php` | protocolVersion 2025-03-26 | **PASS** |
| 6 | MCP Streamable — tools/list | `php tests/mcp_streamable_test.php` | tools array returned | **PASS** |
| 7 | OAuth resource URL | `php tests/mcp_streamable_test.php` | Protected resource → `/mcp` | **PASS** |
| 8 | CIMD metadata flag | `php tests/mcp_cimd_test.php` | `client_id_metadata_document_supported` | **PASS** |
| 9 | CIMD authorize flow | `php tests/mcp_cimd_test.php` | Cached CIMD → 302 + code | **PASS** |
| 10 | DCR still works | `php tests/mcp_cimd_test.php` | POST /oauth/register 201 | **PASS** |
| 11 | Legacy MCP HTTP | `php tests/mcp_http_test.php` | `/api/mcp_server.php` API key path | **PASS** |
| 12 | MCP OAuth bearer | `php tests/mcp_oauth_test.php` | DCR → PKCE → bearer tools/list | **PASS** |
| 13 | AI agent create | `php tests/ai_agents_test.php` | Agent + OAuth endpoint + `/mcp` URL | **PASS** |
| 14 | AI agent role sync | `php tests/ai_agents_test.php` | Update agent updates endpoint role | **PASS** |
| 15 | CI full harness | `php tests/run.php` | 38 suites PASS | **PASS** |
| 16 | Admin AI tab — agents UI | Manual / design-reviewer | Agents list, create, copy URL | **PASS** (code) |
| 17 | Empty OAuth fields copy | Manual | Setup steps say leave Client ID/Secret empty | **PASS** (code) |
| 18 | Claude remote connector E2E | Manual HTTPS install | Paste `/mcp` URL only → OAuth completes | **PENDING** (needs HTTPS deploy) |
| 19 | ChatGPT connector E2E | Manual HTTPS install | Paste URL only → OAuth completes | **PENDING** (needs HTTPS deploy) |

**Notes**

- Rows 18–19 require a public HTTPS Birkly install; local HTTP CI cannot fully simulate Claude broker.
- Stdio bridge (`mcp-bridge.js`) unchanged; uses legacy `/api/mcp_server.php` with API key.
