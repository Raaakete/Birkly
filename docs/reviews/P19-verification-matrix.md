# P19 — Verification matrix

**Date:** 2026-07-07 · **Phase:** P19 first-run & onboarding excellence  
**Spec:** `docs/reviews/P16-P19-complete-plan.md` § Phase 19  
**Handoff:** `docs/reviews/P16-P19-lead-engineer-handoff.md`  
**Closure:** P19-M8 — single human notification — **complete**

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P19-M0** | Gap register + UX audit | pass | `P19-onboarding-gap-register.md`, `P19-first-run-ux-audit.md` |
| **P19-M1** | START_HERE in release zip | pass | `release_manifest_test.php`, `cms_release_manifest_test.php` |
| **P19-M2** | START_HERE v2 | pass | `START_HERE.html` folder/unzip guidance, `api/setup_probe.php` |
| **P19-M3** | Install path router | pass | `onboarding_install_path_test.php` |
| **P19-M4** | Wizard v2 | pass | `onboarding_e2e_test.php`, `admin/js/onboarding.js` |
| **P19-M5** | Optional AI mini-wizard | pass | `onboarding_ai_step_test.php` (P19-D4 locked) |
| **P19-M6** | Dashboard checklist v2 | pass | `onboarding_checklist_test.php` |
| **P19-M7** | Docs + testing checklist | pass | `docs/testing-onboarding.md`, Birkly-Docs help-and-onboarding |
| **P19-M8** | Combined closure | pass | `P16-P19-phase-closure-checklist.md`, daily report |

## First-run (START_HERE)

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| FR-01 | README says open START_HERE first | pass | `README.md`, `START_HERE.txt` |
| FR-02 | Release zip contains START_HERE.html | pass | `build-cms-release.sh` → `/tmp/birkly-release.zip` |
| FR-03 | Right-folder detection works | pass | `START_HERE.html` + `api/setup_probe.php` |
| FR-04 | Wrong-folder (nested unzip) shows fix message | pass | `START_HERE.html` nested-folder UI |
| FR-05 | Local path links to start-server steps | pass | `START_HERE.html` local install panel |
| FR-06 | Cloud/FTP path shows upload checklist | pass | `START_HERE.html` cloud install panel |
| FR-07 | design-reviewer approved START_HERE | pass | P19-first-run-ux-audit.md sign-off |

## Install path router

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| IR-01 | `?install=local` tailors onboarding copy | pass | `onboarding_install_path_test.php` |
| IR-02 | `?install=cloud` tailors onboarding copy | pass | `onboarding_install_path_test.php` |
| IR-03 | `install_path` persisted in settings | pass | `settings/onboarding.json` via API |
| IR-04 | `onboarding_install_path_test.php` | pass | PASS (2026-07-07) |

## Onboarding wizard v2

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| WZ-01 | First user creation works | pass | `onboarding_e2e_test.php` |
| WZ-02 | Use-case step skippable | pass | `onboarding_e2e_test.php` |
| WZ-03 | Resume partial wizard | pass | `api/onboarding.php` `last_step` progress |
| WZ-04 | Marketplace hint shown | pass | `onboarding_e2e_test.php` |
| WZ-05 | `onboarding_e2e_test.php` green | pass | PASS (2026-07-07) |

## Optional AI step (P19-D4)

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| AI-01 | Provider pick → key → test → role completes | pass | `onboarding_ai_step_test.php` |
| AI-02 | Skip leaves no AI connection | pass | `onboarding_ai_step_test.php` |
| AI-03 | `require_approval` default true | pass | `onboarding.html` checkbox default |
| AI-04 | AI NOT in dashboard essential checklist | pass | `onboarding_checklist_test.php` |
| AI-05 | Dashboard shows AI item only if skipped in wizard | pass | `onboarding_ai_step_test.php` |
| AI-06 | `onboarding_ai_step_test.php` | pass | PASS (2026-07-07) |

## Combined closure (P19-M8)

| ID | Check | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| C-01 | P16 matrix all pass | pass | `P16-verification-matrix.md` |
| C-02 | P17 matrix all pass | pass | `P17-verification-matrix.md` |
| C-03 | P18 matrix all pass | pass | `P18-verification-matrix.md` |
| C-04 | P19 matrix all pass | pass | this file |
| C-05 | Birkly + Marketplace tests green | pass | 135 + 17 suites PASS |
| C-06 | Roadmap § P16–P19 complete | pass | `docs/roadmap/ROADMAP.md` |
| C-07 | Human notified once | pass | `2026-07-07-p16-p19-closure.md` + digest |
