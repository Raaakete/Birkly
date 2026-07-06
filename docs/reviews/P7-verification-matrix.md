# P7 — Verification matrix

**Date:** 2026-07-06 · **Branch:** `main` @ `74e69a7`  
**Handoff:** `docs/reviews/P7-lead-engineer-handoff.md` · **Spec:** `docs/reviews/P7-plugin-platform-plan.md`

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P7-M0** | Plugin admin shell | pass | `tests/plugin_admin_shell_test.php` · `docs/PLUGIN_ADMIN_UI_GUIDE.md` · `register_plugin_admin_meta()` |
| **P7-M1** | Plugin docs — all plugins | pass | `register_plugin_docs` in automation, commerce, events, user_management · `tests/plugin_docs_shelf_test.php` |
| **P7-M2** | Remove payments + shopping_cart | pass | `plugins/payments/` and `plugins/shopping_cart/` absent · Commerce shelf only |
| **P7-M3** | User Management repair | pass | `tests/user_plugin_admin_test.php` · tab panes + `Birkly.url()` API paths |
| **P7-M4** | Plugin UI migration | pass | `{plugin_page_header}` on Commerce, Automation, Events; no Settings breadcrumb |
| **P7-M5** | Events v2 | pass | Event fieldtype · `EventIndexer` · recurring · iCal/webcal · `tests/events_v2_test.php` |
| **P7-M6** | Automation node editor + closure | pass | Node canvas · form toggle · serializer round-trip · `tests/automation_node_editor_test.php` |

## Integration flows

| Flow | Target | Result | Evidence |
|------|--------|--------|----------|
| Commerce tabs → breadcrumb stays "Commerce" | pass | `register_plugin_admin_meta('commerce')` |
| `user_tiers` sub-page → "Site Users › Tiers" | pass | `user_management` `sub_pages` |
| All plugins in Help Plugin extensions + AI topics | pass | `plugin_docs_shelf_test.php` |
| payments + shopping_cart removed | pass | Legacy plugin dirs deleted |
| Site Users content renders | pass | `user_plugin_admin_test.php` |
| Recurring event on calendar indexer | pass | `events_v2_test.php` |
| iCal / webcal feed | pass | `events_v2_test.php` |
| Automation node editor saves + runs | pass | `automation_node_editor_test.php` |
| Full harness | pass | `php tests/run.php` — **92 suites** |

## Deferred (post-P7)

- Birkly Marketplace (P7.0)
- Live Shopify/Woo API import (P7.1)
- Full tax/refund/subscription lifecycle UI
