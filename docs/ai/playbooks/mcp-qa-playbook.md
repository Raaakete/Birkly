# MCP full-tool QA playbook

Use when auditing **inbound MCP** on a staging or production CMS: exercise `tools/list`, named tools, `cms_admin_action`, and plugin `*_admin_action` surfaces; record PASS/FAIL without destructive calls.

**Live reference (test host):** `project/mcp-qa.html` + `project/mcp-qa-matrix.json` on `test.birkly.cloud` (written via MCP). Ops write-up: `Birkly-Marketplace` → `internal/planning/reviews/P91-mcp-full-tool-audit-*.md`.

## When to use

- After a **core CMS deploy** that touches `core/cms_mcp_handlers.php`, `api/search.php`, `api/hosting.php`, or admin-action dispatch
- Before closing a phase that claims “full MCP parity”
- When users ask whether **analytics** or **delivery** are reachable via MCP

## Preconditions

1. **`get_system_context`** — note `deployed_at` and `mcp_tool_count`; reconnect MCP after upgrade so `tools/list` is fresh
2. **`list_plugins`** — Commerce / Automation extend tool count and admin actions
3. Admin MCP connection (personal or service agent with sufficient role)

## Harness (optional but repeatable)

| Piece | Purpose |
|-------|---------|
| Collection `mcp-qa-harness` | Parent entry for CRUD smoke |
| Sub-collection `mcp-qa-notes` | `create_sub_entry` / `list_sub_entries` |
| `project/mcp-qa.html` | Human-readable matrix + links |
| `project/mcp-qa-matrix.json` | Machine-readable results for CI or agents |

Do not run destructive tools (`delete_*`, `automation_run_live`, bulk ship, plugin deactivate) on shared hosts without a disposable instance.

## Core smoke checklist

| Area | Tools / actions | Expect |
|------|-----------------|--------|
| Discovery | `get_project_info`, `list_collections`, `read_birkly_docs` | JSON envelopes |
| Content | `create_entry`, `read_entry`, `update_entry` | Published entries visible when `allow_public_read` |
| Sub-entries | `create_sub_entry`, `read_sub_entry` | Scoped to parent id |
| Media (named) | `list_media`, `read_media` | Always prefer for simple reads |
| Media (admin) | `cms_admin_action` → `media.list` | JSON with `media` key (not HTML) — CMS **#784+**; **#785+** moves `admin_media.php` HTTP router off the include path |
| Search | `cms_admin_search` or `search.admin_search` | Collections/entries keys — CMS **#784+** |
| Hosting | `cms_admin_action` → `hosting.get` | `hosting` payload — CMS **#784+** |
| Delivery (named) | `get_site_delivery_status`, `run_site_delivery_probe` | Work even when `hosting.get` lags on old builds |
| Analytics (site) | `cms_admin_action` → `analytics.data`, `analytics.get_settings` | No separate `analytics_*` tool in `tools/list` |
| Commerce analytics | `commerce_admin_action` → `analytics_summary` | When Commerce active |
| Project site | `write_project_file`, `cms_admin_action` → `project_site.invalidate_ssr_cache` | After template changes |

## Analytics (indirect access)

Visitor analytics (Settings → Analytics) is available only through **`cms_admin_action`**:

- `analytics.data` — report payload (page views, top pages, range)
- `analytics.get_settings`, `analytics.presets`, `analytics.save_settings`

Commerce ops dashboards use **`commerce_admin_action`** → `analytics_summary`, `analytics_export_csv`.

**Gap (optional product):** first-class `analytics_*` MCP tool aliases; **Studio** has no MCP tools today.

## Known gaps (2026-09-18 audit)

| Gap | Workaround |
|-----|------------|
| No `analytics_*` named tool | `cms_admin_action` as above |
| Studio plugin | Admin UI only |
| `cms_admin_search` 401 on builds before #784 | Upgrade CMS; local test: `php tests/cms_mcp_admin_test.php` |

## Automated regression (repo)

```bash
php tests/cms_mcp_admin_test.php
```

Asserts `hosting.get`, `search.admin_search`, and `media.list` via the same internal dispatch path MCP uses.

## Related

- `mcp-capabilities` — tool families and `cms_list_admin_actions` catalog
- `mcp-troubleshooting` — OAuth and stale `tools/list`
- `fieldtype-qa` — storefront fieldtypes (separate matrix)
