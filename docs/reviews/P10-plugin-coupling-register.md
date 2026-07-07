# P10 — Core → plugin coupling register

**Date:** 2026-07-07 · **Phase:** P10-M0 planning baseline  
**Purpose:** Inventory every hard or conditional coupling from core API/admin entry points to official plugins (`commerce`, `events`, `automation`, `user_management`). Drives P10-M1 graceful gating and release-zip unbundling.  
**Spec:** `docs/reviews/P10-marketplace-go-live-plan.md` · **Manifest:** `scripts/cms-release-manifest.json`

---

## Scope

This register covers couplings in the files named in P10-M0-T1:

| File | Role |
|------|------|
| `api/index.php` | Plugin API route router |
| `api/public.php` | Public read API + template functions |
| `api/automation.php` | Dedicated automation API shim |
| `api/content.php` | Authenticated content API |
| `api/search.php` | Admin unified search |
| `admin/index.php` | Admin MPA router |

Indirect couplings via `load_plugins()` (registry only when plugin directory exists and plugin is **active**) are documented in § Indirect registration. Couplings in other files (`api/public_sub_entry.php`, `api/admin_templates.php`, `core/automation/bootstrap.php`) are noted in § Related (out of M0 file list) for M1 implementers.

---

## Gating strategy legend

| Strategy | Meaning |
|----------|---------|
| **Registry** | Route/page exists only when `load_plugins()` includes active `plugin.php` — no core change if plugin absent |
| **State gate** | Core checks `get_plugin_state()` / `birkly_is_plugin_active()` before requiring plugin file |
| **File gate** | Core checks `file_exists()` before `require_once` |
| **Graceful null** | Missing plugin returns empty data or HTTP 404 JSON — no fatal |
| **M1 fix** | Current code can fatal or assumes path; must change in P10-M1 |

---

## Master coupling table

| ID | File | Lines | Plugin | Coupling type | Current behavior | P10-M1 gating strategy |
|----|------|-------|--------|---------------|------------------|------------------------|
| C-01 | `api/index.php` | 13, 21 | *all* | Bootstrap | `require_once core/plugins.php`; `load_plugins()` scans `plugins/` | **Registry** — no-op when `plugins/` empty or no active plugins |
| C-02 | `api/index.php` | 25–30 | *all* | Route merge | Merges `$GLOBALS['haru_api_endpoints']` from active plugins | **Registry** — empty when no plugins active |
| C-03 | `api/index.php` | 33–41 | `user_management` | Hard path fallback | Registers `user_auth`, `user_registration` if files exist under `plugins/user_management/api/` | **File gate** — keep `file_exists()`; routes absent when plugin not installed (404 on call) |
| C-04 | `api/index.php` | 52–61 | *via registry* | Route dispatch | 404 JSON when route not registered | **Graceful null** — already correct |
| C-05 | `api/public.php` | 79–80, 547, 584 | *all active* | Bootstrap | `birkly_public_load_active_plugins()` via `core/public_bootstrap.php` | **Registry** — loads only active plugins; safe when none |
| C-06 | `api/public.php` | 189–190 | commerce, events, user_management | Template functions | `birkly_public_load_plugins_for_template_function($name)` selective load | **Graceful null** — `template_function_call` returns `null` when function missing |
| C-07 | `api/public.php` | 103–105, 192–198 | *via registry* | Template discovery | Empty function list when none registered | **Graceful null** — already correct |
| C-08 | `api/public.php` | 289–297, 358–366, 370–377 | `user_management` | Entry permissions | `get_plugin_state('user_management')` then `require_once plugins/user_management/includes/EntryPermissions.php` | **State gate** — skip filter when inactive/missing; **M1 fix:** add `file_exists()` before require |
| C-09 | `api/public.php` | 587–592 | `user_management` | Delete entry auth | `file_exists()` then `EntryPermissions.php` for `delete_entry` | **File gate** — 401 when plugin absent (correct) |
| C-10 | `api/automation.php` | 11 | `automation` | **Hard require** | Unconditional `require_once plugins/automation/api/automation.php` | **M1 fix:** wrap in `file_exists()` + `automation_bootstrap()`; else HTTP 404 JSON |
| C-11 | `api/content.php` | 11 | *all* | Bootstrap | `require_once core/plugins.php` (no `load_plugins()` in file) | **Registry** — plugin collections only if plugins registered elsewhere in request |
| C-12 | `api/content.php` | 51 | *via registry* | Collections | Comment: result includes plugin-registered collections | **Registry** — no entries without active plugins |
| C-13 | `api/content.php` | 496–504 | `user_management` | Entry permissions | `get_plugin_state('user_management')` then `EntryPermissions.php` | **State gate** — **M1 fix:** add `file_exists()` before require |
| C-14 | `api/search.php` | 50–52 | *all* | Bootstrap | Conditional `require_once core/plugins.php` | Safe — core only |
| C-15 | `api/search.php` | 231–234 | commerce, events, automation, user_management | Plugin search scope | `searchPluginContent()` → `birkly_trigger_event('search_content')` | **Registry** — empty array when no plugins hook |
| C-16 | `api/search.php` | 435–442 | `user_management` | Entry permission filter | `get_plugin_state()` + `EntryPermissions.php` in entry search | **State gate** — **M1 fix:** add `file_exists()` |
| C-17 | `api/search.php` | 718–744 | *via hooks* | `search_content` event | Plugins return searchable admin rows | **Registry** — commerce/events register when active |
| C-18 | `admin/index.php` | 47, 51–55 | `user_management`, `automation` | Page permissions | `site_users`, `user_tiers`, `user_groups`, `automation` in `$pageViewPermissions` | **State gate** — permission map harmless; page render gated separately |
| C-19 | `admin/index.php` | 69–73 | *all* | Plugin permissions | `birkly_plugin_admin_page_permission($page)` | **Registry** — only when plugin registered |
| C-20 | `admin/index.php` | 92–94 | *all* | Bootstrap | `load_plugins()` on every admin request | **Registry** — no plugin pages in `$birkly_admin_pages` when inactive |
| C-21 | `admin/index.php` | 145 | *core* | Plugins UI | `'plugins' => 'plugins.html'` (marketplace tab) | Core — always available; no plugin path |
| C-22 | `admin/index.php` | 156–160 | commerce, events, automation, user_management | Admin pages | `$birkly_admin_pages` from `register_admin_page()` | **Registry** — pages appear only when plugin active |
| C-23 | `admin/index.php` | 530–551 | *all* | Plugin page render | Includes `plugins/*/admin/*.html` when `$isPluginAdminPage` | **File gate** — "Plugin Not Found" when path missing |
| C-24 | `admin/index.php` | 552–557 | `automation` | Inactive UX | `birkly_is_plugin_active('automation')` → marketplace CTA | **State gate** — **M1 fix:** extend pattern to commerce/events if direct URLs used |
| C-25 | `admin/index.php` | 181–187 | *all* | Plugin admin meta | `get_plugin_admin_meta_for_page()` breadcrumb/sidebar | **Registry** |

---

## Per-file detail

### `api/index.php`

**Plugin routes registered when active (via `plugins/*/plugin.php`):**

| Route key | Plugin | Handler path |
|-----------|--------|--------------|
| `commerce`, `payments`, `commerce_webhook`, `payments_webhook` | commerce | `plugins/commerce/api/*.php` |
| `events` | events | `plugins/events/api/events.php` |
| `user_auth`, `user_registration`, `user_relations`, `user_admin`, `user_tiers`, `user_groups`, `user_settings`, `collection_permissions`, `user_verification`, `user_2fa`, `password_reset` | user_management | `plugins/user_management/api/*.php` |

Automation does **not** use `api/index.php`; it uses dedicated `api/automation.php` (C-10).

**M1 acceptance:** HTTP GET `api/index.php?route=user_auth` returns 404 JSON when `user_management` not installed — not 500.

---

### `api/public.php`

**Template functions (registered when plugin active):**

| Function names (sample) | Plugin |
|-------------------------|--------|
| `commerce_cart_count`, `commerce_cart_url`, `commerce_buy_url`, … | commerce |
| `upcoming_events`, `event_register_url`, `events_calendar_feed_url`, … | events |
| `user_logged_in`, `user_current`, `user_can`, `user_tier`, … | user_management |

**M1 acceptance:** `template_function_call` for `user_logged_in` returns `{ result: null }` when user_management absent — not 500.

---

### `api/automation.php`

Single-line shim to plugin API. Inner handler (`plugins/automation/api/automation.php`) already calls `automation_bootstrap()` and returns 404 when inactive — but outer shim fatals if file missing.

**M1 fix (required):**

```php
// Proposed gate (P10-M1-T2)
require_once __DIR__ . '/../core/automation/bootstrap.php';
if (!automation_bootstrap()) {
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Automation plugin is not available']);
    exit;
}
require_once __DIR__ . '/../plugins/automation/api/automation.php';
```

---

### `api/content.php`

Only explicit plugin path: `user_management` entry filtering (C-13). No commerce/events/automation direct requires.

---

### `api/search.php`

Plugin scope uses event bus — commerce/events may register `search_content` listeners when active. Entry search respects user_management permissions (C-16).

---

### `admin/index.php`

**Plugin admin pages (when active):**

| Page slug | Plugin | Template path |
|-----------|--------|---------------|
| `commerce`, `payments` | commerce | `plugins/commerce/admin/commerce.html` |
| `events` | events | `plugins/events/admin/events.html` |
| `automation` | automation | `plugins/automation/admin/automation.html` |
| `site_users`, `user_tiers`, `user_groups` | user_management | `plugins/user_management/admin/*.html` |

Core `plugins.html` hosts marketplace client (P9) — not a plugin coupling.

---

## Indirect registration (via `load_plugins()`)

When `plugins/{id}/plugin.php` is included (active only):

| Plugin | Registers | Depends on |
|--------|-----------|------------|
| commerce | API routes, admin pages, Commerce fieldtype, `payment_shelf` / `commerce_shelf`, template functions, automation triggers, catalog hooks | — |
| events | API route, admin page, Events fieldtype, template functions, automation triggers | commerce shelf for paid tickets |
| automation | Admin page, automation blocks/actions, docs shelf | — |
| user_management | API routes, admin pages, User/Password fieldtypes, template functions, entry hooks | — |

**P10-D4 note:** `load_plugins()` currently defaults `automation` to **active** on first discovery (`core/plugins.php` ~471–474). P10-M1-T3 must remove that default so fresh installs have zero active plugins.

---

## Related couplings (outside M0 file list)

| File | Plugin | Notes |
|------|--------|-------|
| `api/public_sub_entry.php` | user_management | `file_exists` + EntryPermissions — same pattern as C-09 |
| `api/admin_templates.php` | automation | `getAutomationData()` uses `automation_bootstrap()` — returns `plugin_inactive` |
| `core/automation/bootstrap.php` | automation | Conditional require of `plugins/automation/*.php` |
| `core/plugins.php` | *all* | Plugin loader; scans `plugins/` directory |
| `api/marketplace.php` | *none* | Core marketplace client — installs plugins from registry |

---

## M1 verification probes

| Probe | Expected when plugins/ empty |
|-------|------------------------------|
| `GET /api/automation.php?action=list` | 404 JSON, not 500 |
| `GET /api/index.php?route=commerce` | 404 JSON |
| `GET /api/public.php?action=template_functions_list` | 200, `functions: []` |
| `GET /admin/index.php?page=automation` | Inactive / marketplace CTA (no fatal) |
| `php tests/run.php` (with `install-dev-plugins.sh`) | All suites green |

---

## Changelog

| Date | Change |
|------|--------|
| 2026-07-07 | P10-M0 initial register — 25 couplings in scope files |
