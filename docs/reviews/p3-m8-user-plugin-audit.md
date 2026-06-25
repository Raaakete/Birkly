# P3-M8 — User plugin audit

**Date:** 2026-06-25  
**Scope:** `plugins/user_management/`, admin routing, entry write paths  
**Refs:** P3-A20 (alpha register), `docs/USER_PLUGIN_DESIGN.md`

## Executive summary

The user_management plugin is feature-rich but had three production blockers: **admin UI unreachable** (slug collision with CMS Users), **permission hooks never fired** on entry create/delete, and **EntryPermissions** edit/delete checks not wired to the tier/collection permission model. This milestone addresses those paths; verification email, automation triggers, and registration rate limits remain follow-ups (P3-M8-T4–T6).

## Findings

| ID | Severity | Area | Finding | Resolution (M8) |
|----|----------|------|---------|-----------------|
| M8-F01 | P0 | Admin routing | Plugin registered `page=users` but `admin/index.php` maps `users` → CMS settings template (`settings.html`). Sidebar linked to plugin UI that never loaded. | Rename plugin page to `site_users`; resolve plugin pages from `$birkly_admin_pages`. |
| M8-F02 | P0 | Admin routing | Plugin admin HTML registered via `register_admin_page()` but `admin/index.php` ignored the registry (hardcoded `community_users` / `sample_plugin` only). | Merge `$birkly_admin_pages` into allowed pages; generic include handler. |
| M8-F03 | P0 | Permissions | `before_entry_create` / `before_entry_delete` registered in plugin but **never called** from `api/admin_entries.php` or `api/public_submit.php`. | Fire hooks (with strict exception propagation for guards). |
| M8-F04 | P0 | Permissions | `EntryPermissions::canEditEntry` / `canDeleteEntry` only checked author ownership; ignored collection tier matrix and `UserPermissions::userCan()`. | Delegate edit/delete to `UserPermissions` + collection tier metadata. |
| M8-F05 | P1 | Permission model | `UserPermissions` default roles (`admin`/`editor`/`author`/`user`) did not match site roles (`member`/`subscriber`/`premium`/`vip`). `userCan()` returned false for all site users. | Add site role defaults; read `collection_permissions` metadata by user tier. |
| M8-F06 | P1 | Hooks | `before_entry_save` registered via `register_hook` but `admin_entries.php` uses `apply_filters('before_entry_save')` — action hook path is dead; filter path works. | Documented; plugin already uses `add_filter` for profile auto-create. |
| M8-F07 | P1 | Hooks | `do_action` swallows exceptions — permission throws in guard hooks would not block writes without explicit enforcement. | Add `do_action_strict`; enforce permissions in `public_submit` before write. |
| M8-F08 | P1 | CMS vs site | Guard hooks use site-scoped `UserAuth`; CMS admin entry API uses admin session. Unconditional hooks would break CMS saves. | Skip site permission enforcement when CMS admin already passed `requireAuth`. |
| M8-F09 | P2 | Dead code | `community_users` plugin page hardcoded but no `plugins/community_users/` package in repo. | Removed in favour of registry-driven routing. |
| M8-F10 | P2 | Follow-up | Registration endpoint lacks dedicated rate limit (P3-M8-T4). | Deferred |
| M8-F11 | P2 | Follow-up | Verification email send path incomplete (P3-M8-T5). | Deferred |
| M8-F12 | P2 | Follow-up | Automation triggers `user_registered` / `user_login` not bridged (P3-M8-T6). | Deferred |

## Architecture notes

- **CMS users** (`admin/index.php?page=users`) — core RBAC via `users/` JSON and `list_users` permission.
- **Site users** (`page=site_users`) — plugin data under `data/plugins/user_management/`, tier/collection matrix in metadata.
- **Entry writes** — CMS admin API (`admin_entries.php`) for staff; `public_submit.php` for `entry_method=public_api` collections. Site permission enforcement targets the public path; CMS path bypasses site guards after `requireAuth`.

## Test coverage added

- `tests/user_plugin_permissions_test.php` — site role defaults, collection tier matrix, `EntryPermissions` edit/delete delegation.

## Sign-off checklist

- [x] Audit documented
- [x] `site_users` admin page reachable
- [x] Entry create/delete hooks fired
- [x] EntryPermissions uses UserPermissions
- [x] Automated tests registered in `tests/run.php`
