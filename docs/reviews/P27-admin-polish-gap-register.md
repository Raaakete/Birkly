# P27 — Admin polish gap register

**Date:** 2026-07-09 · **Source:** Human feedback + codebase audit  
**Phase:** P27 — Admin polish & platform fixes (Studio P26 **paused**)

## Human-reported (priority)

| ID | Area | Issue | Suspected cause |
|----|------|-------|-----------------|
| H1 | Security | Admin option to **force 2FA** for all users; 30-day grace; block login after deadline until 2FA set | No org-level 2FA policy in settings/users API |
| H2 | Breadcrumbs | Broken in Studio plugin, entry forms; hamburger too close to breadcrumb | Topbar breadcrumb hoist + plugin shell; missing gap in `admin-topbar-left` |
| H3 | Sidebar | Full sidebar has white bg; only **nav item boxes** should be white | `.sidebar { background: var(--bg-elevated) }` in `sidebar.css` |
| H4 | Topbar | Theme switcher size, search, notifications, account feel off-brand; stray blue | `theme-switcher` + `topbar-icon-btn` inconsistent tokens |
| H5 | Legal footer | "Open beta · invite only · ToS · Privacy · Request invite" too prominent in content | `admin-legal-footer.php` in main content flow |
| H6 | Program label | Should say **Closed alpha**, not Open beta | `birkly_program_status_label()` default `open_beta` |
| H7 | Media | Media library does not load | Hardcoded `/api/admin_media.php` in `media.html` (subdir installs) |
| H8 | Dashboard | Template error `//core/birkly_paths.php` | `dashboard.html` line 16: fragile `dirname(dirname(__DIR__))` in template context |
| H9 | Help / docs | Help too thin; ship latest Birkly-Docs with each CMS release; drop plugin insert section | Snapshot manifest stale; no sync script in CI |
| H10 | Avatar | Uploaded avatar not shown in top-right circle | Topbar user menu not refreshed after account save; URL/path bug |
| H11 | Sidebar | Collapsible sidebar — icons only; toggle at **bottom** | Only mobile `sidebar-toggle` in topbar today |
| H12 | FABs | AI + feedback floating buttons don't match Birkly style | `ai-chat-button.js`, `feedback-widget.php` use legacy blue |

## Codebase / prior register (added)

| ID | Area | Issue | Reference |
|----|------|-------|-----------|
| R1 | URLs | Hardcoded `/api/` paths across admin JS | P5.2/P5.4 live issues pattern |
| R2 | Breadcrumbs | Plugin pages need consistent trail (Studio, commerce, etc.) | `birkly_render_plugin_page_breadcrumbs`, `layout_footer.php` hoist |
| R3 | Entry detail | Breadcrumb does not reflect collection → entry hierarchy | `entry-detail.html` |
| R4 | Hygiene | `plugins/` + `fieldtypes/` TODO pass deferred from P13 | `P13-deferred-todos.md` |
| R5 | Responsive | P15 audit: sidebar collapse incomplete at 768px | `P15-admin-ux-audit.md` |

## Out of scope (P27)

- Studio P26 creative suite (animation, Figma, popups) — **paused**, resume after P27
- Multi-user realtime, marketplace block store, Tailwind JIT
- New fieldtypes or plugin features
