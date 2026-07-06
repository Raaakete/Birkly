# P7 — Verification matrix

**Date:** 2026-07-06 · **Branch:** `cursor/p7-m6-automation-nodes-24b5` → `main`  
**Handoff:** `docs/reviews/P7-lead-engineer-handoff.md` · **Spec:** `docs/reviews/P7-plugin-platform-plan.md`

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P7-M0** | Plugin admin shell | pass | `tests/plugin_admin_shell_test.php` · `docs/PLUGIN_ADMIN_UI_GUIDE.md` · `register_plugin_admin_meta()` in plugins |
| **P7-M1** | Plugin docs — all plugins | pass | `register_plugin_docs` in automation, commerce, events, user_management · `tests/plugin_docs_shelf_test.php` |
| **P7-M2** | Remove payments + shopping_cart | pass | `plugins/payments/` and `plugins/shopping_cart/` absent from repo · sidebar uses Commerce shelf |
| **P7-M3** | User Management repair | pass | `tests/user_plugin_admin_test.php` · `site_users.html` shell + tab panes |
| **P7-M4** | Plugin UI migration | pass | Commerce, Automation, Events, User use plugin admin meta; no Settings breadcrumb in plugin bodies |
| **P7-M5** | Events v2 | pending | Event fieldtype WIP on branch — `events_v2_test.php` not yet on `main` |
| **P7-M6** | Automation node editor + closure | pass | Node canvas UI · form/canvas toggle · palette · serializer round-trip · this matrix |

## Integration flows

| Flow | Target | Result | Evidence |
|------|--------|--------|----------|
| Commerce tabs → breadcrumb stays "Commerce" | pass | `register_plugin_admin_meta('commerce')` · shell contract |
| `user_tiers` sub-page → "Site Users › Tiers" | pass | `user_management` `sub_pages` in `register_plugin_admin_meta` |
| All plugins in Help Plugin extensions + AI topics | pass | `plugin_docs_shelf_test.php` |
| payments + shopping_cart removed | pass | `ls plugins/` — only automation, commerce, events, user_management, sample-plugin |
| Site Users content renders | pass | `user_plugin_admin_test.php` |
| Recurring event on calendar indexer | pending | Events v2 not merged |
| iCal / webcal feed | pending | Events v2 not merged |
| Automation node editor saves + runs | pass | `automation_node_editor_test.php` serialize round-trip; runtime schema unchanged (`trigger` / `conditions` / `actions`) |
| Form editor fallback toggle | pass | `automation.html` `setAutomationEditorMode` · `automation.js` |
| Node palette: Commerce, Events, User, Webhooks | pass | `AutomationNodeSerializer.PALETTE_SECTIONS` · User triggers in `user_management/plugin.php` |
| Full harness | pass | `php tests/run.php` — **91 suites** |

## P7-M6 — Automation node editor detail

| Check | Result | Notes |
|-------|--------|-------|
| Canvas UI (DOM nodes + SVG edges) | pass | `plugins/automation/admin/automation_node_editor.js` |
| Toggle form ↔ canvas | pass | Modal toolbar in `automation.html` |
| Serialize canvas → workflow JSON | pass | `automation_workflow_from_canvas()` |
| Deserialize workflow → canvas | pass | `automation_canvas_from_workflow()` |
| Acyclic validation | pass | `automation_validate_canvas()` · cycle detection |
| Import / export canvas JSON | pass | Export / Import buttons in canvas toolbar |
| Runtime executor unchanged | pass | No changes to `executor.php` or storage schema |
| Smoke test round-trip | pass | `tests/automation_node_editor_test.php` |

## Test commands

```bash
php tests/automation_node_editor_test.php
php tests/plugin_admin_shell_test.php
php tests/plugin_docs_shelf_test.php
php tests/user_plugin_admin_test.php
php tests/events_plugin_test.php
php tests/automation_plugin_test.php
php tests/run.php
```

## Deferred (out of P7 scope)

- Birkly Marketplace (P7.0)
- Live Shopify/Woo import (P7.1)
- Full tax/refund/subscription lifecycle UI
