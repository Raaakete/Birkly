# P15 — Verification matrix

**Date:** 2026-07-07 · **Phase:** P15 alpha closure & UX  
**Spec:** `docs/reviews/P13-P15-complete-plan.md` § Phase 15  
**Handoff:** `docs/reviews/P13-P15-lead-engineer-handoff.md`  
**Closure:** P15-M8 — single human notification

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P15-M0** | Gap register, UX audit | pending | `P15-alpha-closure-gap-register.md` |
| **P15-M1** | Dark mode + responsive | pending | design-reviewer sign-off |
| **P15-M2** | kekaba content | pending | `check-production.sh` |
| **P15-M3** | Help → Birkly-Docs | pending | `tests/help_docs_wiring_test.php` |
| **P15-M4** | Legal + feedback | pending | admin feedback + website legal |
| **P15-M5** | Plugin scaffold CLI | pending | `tests/scaffold_plugin_test.php` |
| **P15-M6** | Birkly-Website | pending | Birkly-Website `DEV.md` |
| **P15-M7** | Full docs sync | pending | Birkly-Docs P13–P15 |
| **P15-M8** | Combined closure | pending | `P13-P15-phase-closure-checklist.md` |

## Admin UX

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| UX-01 | Theme toggle light/dark/system | pending | |
| UX-02 | Theme persists in localStorage | pending | |
| UX-03 | Dark mode readable on Commerce admin | pending | |
| UX-04 | Dark mode readable on Settings | pending | |
| UX-05 | Responsive layout at 768px (dashboard, entries, commerce) | pending | |
| UX-06 | design-reviewer approved | pending | |

## kekaba content

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| K-01 | New collection(s) created | pending | |
| K-02 | New public pages render locally | pending | |
| K-03 | Baked HTML in publish/ | pending | |
| K-04 | `check-production.sh` passes | pending | |

## Help wiring

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| H-01 | Live manifest fetch opens Birkly-Docs topic | pending | |
| H-02 | Offline fallback shows bundled stub | pending | |
| H-03 | `help_docs_wiring_test.php` | pending | |

## Legal + feedback

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| L-01 | Admin footer legal links resolve | pending | |
| L-02 | Feedback submission stored | pending | |
| L-03 | Website legal pages synced | pending | |

## Plugin scaffold

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| SC-01 | `scaffold-plugin.php` creates valid tree | pending | |
| SC-02 | Scaffolded plugin activates | pending | |
| SC-03 | `--privileged` prints checklist | pending | |
| SC-04 | `scaffold_plugin_test.php` | pending | |

## Combined closure (P15-M8)

| ID | Check | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| C-01 | P13 verification matrix all pass | pending | |
| C-02 | P14 verification matrix all pass | pending | |
| C-03 | P15 verification matrix all pass | pending | |
| C-04 | Birkly tests green | pending | |
| C-05 | Marketplace tests green | pending | |
| C-06 | Roadmap § P13–P15 complete | pending | |
| C-07 | Human notified once | pending | digest + closure report |
