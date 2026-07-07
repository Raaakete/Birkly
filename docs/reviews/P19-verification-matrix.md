# P19 — Verification matrix

**Date:** 2026-07-07 · **Phase:** P19 first-run & onboarding excellence  
**Spec:** `docs/reviews/P16-P19-complete-plan.md` § Phase 19  
**Handoff:** `docs/reviews/P16-P19-lead-engineer-handoff.md`  
**Closure:** P19-M8 — single human notification

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P19-M0** | Gap register + UX audit | pending | `P19-onboarding-gap-register.md` |
| **P19-M1** | START_HERE in release zip | pending | `release_manifest_test.php` |
| **P19-M2** | START_HERE v2 | pending | design-reviewer sign-off |
| **P19-M3** | Install path router | pending | `onboarding_install_path_test.php` |
| **P19-M4** | Wizard v2 | pending | `onboarding_e2e_test.php` |
| **P19-M5** | Optional AI mini-wizard | pending | `onboarding_ai_step_test.php` |
| **P19-M6** | Dashboard checklist v2 | pending | `onboarding_checklist_test.php` |
| **P19-M7** | Docs + testing checklist | pending | `docs/testing-onboarding.md` |
| **P19-M8** | Combined closure | pending | `P16-P19-phase-closure-checklist.md` |

## First-run (START_HERE)

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| FR-01 | README says open START_HERE first | pending | |
| FR-02 | Release zip contains START_HERE.html | pending | |
| FR-03 | Right-folder detection works | pending | |
| FR-04 | Wrong-folder (nested unzip) shows fix message | pending | |
| FR-05 | Local path links to start-server steps | pending | |
| FR-06 | Cloud/FTP path shows upload checklist | pending | |
| FR-07 | design-reviewer approved START_HERE | pending | |

## Install path router

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| IR-01 | `?install=local` tailors onboarding copy | pending | |
| IR-02 | `?install=cloud` tailors onboarding copy | pending | |
| IR-03 | `install_path` persisted in settings | pending | |
| IR-04 | `onboarding_install_path_test.php` | pending | |

## Onboarding wizard v2

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| WZ-01 | First user creation works | pending | |
| WZ-02 | Use-case step skippable | pending | |
| WZ-03 | Resume partial wizard | pending | |
| WZ-04 | Marketplace hint shown | pending | |
| WZ-05 | `onboarding_e2e_test.php` green | pending | |

## Optional AI step (P19-D4)

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| AI-01 | Provider pick → key → test → role completes | pending | |
| AI-02 | Skip leaves no AI connection | pending | |
| AI-03 | `require_approval` default true | pending | |
| AI-04 | AI NOT in dashboard essential checklist | pending | |
| AI-05 | Dashboard shows AI item only if skipped in wizard | pending | |
| AI-06 | `onboarding_ai_step_test.php` | pending | |

## Combined closure (P19-M8)

| ID | Check | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| C-01 | P16 matrix all pass | pending | |
| C-02 | P17 matrix all pass | pending | |
| C-03 | P18 matrix all pass | pending | |
| C-04 | P19 matrix all pass | pending | |
| C-05 | Birkly + Marketplace tests green | pending | |
| C-06 | Roadmap § P16–P19 complete | pending | |
| C-07 | Human notified once | pending | digest + closure report |
