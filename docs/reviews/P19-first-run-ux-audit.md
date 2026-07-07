# P19 — First-run UX audit

**Date:** 2026-07-07 · **Scope:** P19-M0

## Current state

- `0_START-HERE.html` exists at repo root but **not** in `cms-release-manifest.json`
- README lacks prominent "open START_HERE first" pointer
- Onboarding wizard: `admin/templates/onboarding.html` + `admin/js/onboarding.js`
- No install=local|cloud deep link
- No wrong-folder detection on START_HERE
- AI setup only in Settings — not in onboarding (P19-M5 adds optional step)

## Flows to validate (P19-M7)

1. Zip extract → open START_HERE → local path → start-server → admin onboarding
2. Zip extract → cloud path → FTP checklist → admin onboarding?install=cloud
3. Wrong folder (inside admin/) → corrective message
4. Skip AI in wizard → dashboard checklist has optional AI item only
5. Complete AI in wizard → dashboard checklist excludes AI from required

## Mobile

- START_HERE responsive layout
- Onboarding wizard usable at 768px
