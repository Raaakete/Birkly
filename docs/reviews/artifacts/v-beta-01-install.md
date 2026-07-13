# V-BETA-01 — Fresh install wizard flow

**Date:** 2026-07-13 · **Phase:** P28-M17  
**Journey:** J-INSTALL

Fresh CMS owner extracts release ZIP and completes onboarding.

## Prerequisites

- Birkly release ZIP (built via `./scripts/build-release.sh`)
- No existing CMS installation

## Steps

1. **Extract release ZIP**
   - Unzip `birkly-0.1-beta.zip` to fresh directory
   - `START_HERE.html` present in root
   - Contains setup instructions and first-run guidance

2. **Start PHP dev server**
   - `cd` into extracted directory
   - Run `php -S localhost:8080 -t . server-router.php`
   - Navigate to `http://localhost:8080/admin/login.php`

3. **Create first admin account**
   - Login page shows "Create First User" form
   - Fill in email, password, display name
   - Submit → redirects to onboarding wizard

4. **Onboarding wizard**
   - Wizard presents setup options (skip or configure)
   - Skip button available for quick start
   - Dashboard redirect after wizard completion

5. **Verify setup completion**
   - Dashboard accessible at `/admin/index.php?page=dashboard`
   - Onboarding status API returns `setup_wizard_completed: true`
   - Admin navigation shows Collections, Settings, Users

## Automated verification

```bash
./scripts/journey-runner.php --journey J-INSTALL
```

Journey builds release ZIP, extracts to temp dir, creates admin, completes onboarding, verifies dashboard access. All assertions pass.

## Status

✅ Automated journey passes (J-INSTALL)
