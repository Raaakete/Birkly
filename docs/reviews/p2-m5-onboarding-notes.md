# P2-M5 onboarding notes

**Date:** 2026-06-18  
**Milestone:** P2-M5 — Onboarding revisit

## Issues fixed

| Issue | Fix |
|-------|-----|
| Wizard skipped when demo entries pre-installed | Redirect uses `shouldRedirectToOnboarding()` / `setup_wizard_completed` instead of `isFirstTimeSetup()` alone |
| Dashboard welcome checklist never shown | `admin/index.php` merges API checklist with session status instead of overwriting |
| Skip hid welcome dashboard | Skip sets `setup_wizard_completed` without `completed`; dashboard shows checklist until user finishes tasks |
| First-user onboarding file key mismatch | `normalizeOnboardingUserKey()`; `create_first_user` passes email to status lookup |
| Complete did not mark wizard done | `complete` sets `setup_wizard_completed` + `demo_content_installed` when keeping demo |

## Test path

```bash
php scripts/reset-onboarding.php --keep-entries
php -S localhost:8080 -t . server-router.php
# Login → onboarding wizard → skip or complete → dashboard checklist/modal
```

## Video (T4)

Walkthrough validated via browser smoke test (2026-06-18): login → onboarding wizard with pre-existing demo content → skip → dashboard checklist.

To capture a shareable recording locally:

```bash
php scripts/reset-onboarding.php --keep-entries
php -S localhost:8080 -t . server-router.php
# Record screen while visiting /admin/login.php and completing or skipping the wizard
```
