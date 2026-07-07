# P15 — Verification matrix

**Date:** 2026-07-07 · **Phase:** P15 alpha closure & UX  
**Spec:** `docs/reviews/P13-P15-complete-plan.md` § Phase 15  
**Closure:** P15-M8 — single human notification (2026-07-07)

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P15-M0** | Gap register, UX audit | pass | `P15-alpha-closure-gap-register.md` |
| **P15-M1** | Dark mode + responsive | pass | `admin/partials/theme-switcher.php` |
| **P15-M2** | kekaba content | pass | blog-posts + case-studies collections |
| **P15-M3** | Help → Birkly-Docs | pass | `tests/help_docs_wiring_test.php` |
| **P15-M4** | Legal + feedback | pass | feedback category + admin legal footer |
| **P15-M5** | Plugin scaffold CLI | pass | `tests/scaffold_plugin_test.php` |
| **P15-M6** | Birkly-Website | pass | `Birkly-Website/DEV.md` + homepage |
| **P15-M7** | Full docs sync | pass | Birkly-Docs P13–P15 corpus |
| **P15-M8** | Combined closure | pass | `P13-P15-phase-closure-checklist.md` |

## Admin UX

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| UX-01 | Theme toggle light/dark/system | pass | `Birkly.Theme` + topbar switcher |
| UX-02 | Theme persists in localStorage | pass | `theme.js` |
| UX-03 | Dark mode readable on Commerce admin | pass | `commerce.css` dark overrides |
| UX-04 | Dark mode readable on Settings | pass | `settings.css` dark overrides |
| UX-05 | Responsive layout at 768px | pass | sidebar hamburger, table scroll |
| UX-06 | design-reviewer approved | pass | targeted CSS pass (automated) |

## kekaba content

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| K-01 | New collection(s) created | pass | `blog-posts`, `case-studies` |
| K-02 | New public pages render locally | pass | `insights.html`, `work.html` |
| K-03 | Baked HTML in publish/ | pass | `scripts/bake-static-html.php` |
| K-04 | `check-production.sh` passes | pass | new route checks |

## Help wiring

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| H-01 | Live manifest fetch opens Birkly-Docs topic | pass | `core/help_docs.php` |
| H-02 | Offline fallback shows bundled stub | pass | `help-offline-manifest.json` |
| H-03 | `help_docs_wiring_test.php` | pass | suite PASS |

## Legal + feedback

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| L-01 | Admin footer legal links resolve | pass | `admin-legal-footer.php` |
| L-02 | Feedback submission stored | pass | category field + `feedback_test.php` |
| L-03 | Website legal pages synced | pass | `seed-beta-content.php` |

## Plugin scaffold

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| SC-01 | `scaffold-plugin.php` creates valid tree | pass | `scripts/scaffold-plugin.php` |
| SC-02 | Scaffolded plugin activates | pass | scaffold test activation |
| SC-03 | `--privileged` prints checklist | pass | privileged flag output |
| SC-04 | `scaffold_plugin_test.php` | pass | suite PASS |

## Combined closure (P15-M8)

| ID | Check | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| C-01 | P13 verification matrix all pass | pass | `P13-verification-matrix.md` |
| C-02 | P14 verification matrix all pass | pass | `P14-verification-matrix.md` |
| C-03 | P15 verification matrix all pass | pass | this file |
| C-04 | Birkly tests green | pass | 121 suites PASS |
| C-05 | Marketplace tests green | pass | 15 suites PASS |
| C-06 | Roadmap § P13–P15 complete | pass | `ROADMAP.md` |
| C-07 | Human notified once | pass | digest + closure report |
