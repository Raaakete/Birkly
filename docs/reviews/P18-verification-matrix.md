# P18 — Verification matrix

**Date:** 2026-07-07 · **Phase:** P18 ecosystem scale & developer experience  
**Spec:** `docs/reviews/P16-P19-complete-plan.md` § Phase 18  
**Handoff:** `docs/reviews/P16-P19-lead-engineer-handoff.md`  
**Closure:** P19-M8 (combined wave) — **complete**

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P18-M0** | Gap register | pass | `P18-ecosystem-gap-register.md` |
| **P18-M1** | Self-serve providers | pass | Marketplace `provider_self_serve_test.php` |
| **P18-M2** | Review votes | pass | Marketplace `review_votes_test.php` |
| **P18-M3** | Plugin health | pass | `plugin_health_test.php` |
| **P18-M4** | Theme install | pass | `marketplace_theme_install_test.php` |
| **P18-M5** | Activity stream | pass | `activity_stream_test.php` |
| **P18-M6** | Spaces stub | pass | `spaces_stub_test.php` |
| **P18-M7** | i18n + NPM go/no-go | pass | `P18-npm-docs-go-no-go.md` (NO-GO) |
| **P18-M8** | Docs + matrix | pass | Birkly-Docs marketplace provider guide + plugin security updates |

## Marketplace

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| MK-01 | Provider self-signup → QC queue | pass | `provider_self_serve_test.php` |
| MK-02 | No auto-publish without QC | pass | `provider_self_serve_test.php` |
| MK-03 | Review helpful vote | pass | `review_votes_test.php` |
| MK-04 | `provider_self_serve_test.php` | pass | PASS (2026-07-07) |

## CMS platform

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| PL-01 | Plugin health badge on unhealthy plugin | pass | `PluginHealthService`, `plugin_health_test.php` |
| PL-02 | Theme install from marketplace | pass | `marketplace_theme_install_test.php` |
| PL-03 | Activity stream records sub-entry event | pass | `activity_stream_test.php` |
| PL-04 | Spaces denylist aware | pass | `spaces_stub_test.php`, `core/spaces.php` |

## Test suites

| ID | Check | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| T-01 | Birkly `php tests/run.php` | pass | 135 suites PASS (2026-07-07) |
| T-02 | Marketplace `php tests/run.php` | pass | 17 suites PASS (2026-07-07) |
