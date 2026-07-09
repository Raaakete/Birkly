# P27 — Verification matrix

**Date:** 2026-07-09 · **Closure:** P27-M16 — **complete**

## Critical fixes (ADM-BUG)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| ADM-BUG-01 | Dashboard renders without birkly_paths fatal | pass | `admin/templates/dashboard.html`, `tests/admin_dashboard_paths_test.php` |
| ADM-BUG-02 | Media library loads files | pass | `admin/templates/media.html` (`ADMIN_MEDIA_API`) |
| ADM-BUG-03 | Media API uses birkly_url on subdir install | pass | `admin/js/birkly-paths.js`, `fieldtypes/*/fieldtype.js` |

## Navigation (ADM-NAV)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| ADM-NAV-01 | Studio breadcrumb trail correct | pass | `layout_footer.php` hoist, `plugin_page_header` |
| ADM-NAV-02 | Entry detail breadcrumb hierarchy | pass | `admin/templates/entry-detail.html` |
| ADM-NAV-03 | Hamburger ↔ breadcrumb spacing | pass | `admin/css/layout/header.css` |
| ADM-NAV-04 | Sidebar bg global; nav cards elevated | pass | `admin/css/layout/sidebar.css` |
| ADM-NAV-05 | Collapsed sidebar icons-only | pass | `body.sidebar-collapsed`, `sidebar.css` |
| ADM-NAV-06 | Collapse toggle at sidebar bottom | pass | `admin/partials/sidebar.php` |
| ADM-NAV-07 | Collapse preference persists | pass | `admin/js/theme.js` (`birkly_sidebar_collapsed`) |

## Chrome (ADM-CHROME)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| ADM-CHROME-01 | Topbar controls unified sizing | pass | `admin/css/layout/header.css` (36px) |
| ADM-CHROME-02 | Theme switcher matches design language | pass | `admin/partials/theme-switcher.php` |
| ADM-CHROME-03 | Avatar shows in topbar after upload | pass | `admin/js/account-settings.js` (`refreshTopbarAvatars`) |
| ADM-CHROME-04 | Closed alpha label in sidebar footer only | pass | `sidebar.php`, `core/platform.php` default tier |
| ADM-CHROME-05 | Legal links in account dropdown | pass | `admin/partials/user-menu.php` |
| ADM-CHROME-06 | No legal footer in content area | pass | `layout_footer.php` (legal include removed) |
| ADM-CHROME-07 | AI + feedback FABs use design tokens | pass | `admin/css/ai-chat-button.css`, `feedback-button.css` |

## Security (ADM-SEC)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| ADM-SEC-01 | Admin can enable require_2fa | pass | `admin/templates/settings.html`, `api/settings.php` |
| ADM-SEC-02 | Admin-configurable grace days (default 30) | pass | `core/security_policy.php` |
| ADM-SEC-03 | Grace banner visible in admin | pass | `admin/partials/2fa-grace-banner.php` |
| ADM-SEC-04 | Login blocked after grace without 2FA | pass | `admin/login.php`, `tests/security_policy_2fa_test.php` |
| ADM-SEC-05 | 2FA setup restores access | pass | `admin/auth_middleware.php`, `api/users.php` `extend_2fa_grace` |

## Docs (ADM-DOCS)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| ADM-DOCS-01 | sync-birkly-docs.sh copies sibling Birkly-Docs | pass | `scripts/sync-birkly-docs.sh` |
| ADM-DOCS-02 | help-offline-manifest regenerated | pass | `admin/docs/help-offline-manifest.json` (30 topics) |
| ADM-DOCS-03 | Help search finds synced topics | pass | `admin/js/help.js` |
| ADM-DOCS-04 | Plugin-insert help section removed | pass | `admin/templates/help.html`, `tests/plugin_docs_shelf_test.php` |

## Regression

| ID | Check | Result | Evidence |
|----|-------|--------|----------|
| PR-01 | P25 studio-smoke.sh still green | pass | `scripts/ci-check.sh` |
| PR-02 | scripts/admin-polish-smoke.sh | pass | `scripts/admin-polish-smoke.sh` |
| PR-03 | 155 test suites PASS | pass | `tests/run.php` |

## Video

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| V-ADM-01 | Sidebar collapse + breadcrumbs + 2FA grace demo | pass | `docs/reviews/P27-phase-closure-checklist.md` |

**Status:** green (2026-07-09)
