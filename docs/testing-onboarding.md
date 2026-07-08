# Testing the Onboarding System (P19)

This guide covers first-run flow: **START_HERE.html** → install path → admin wizard v2 → optional AI → dashboard checklist.

## Quick start (agents / CI)

```bash
# From Birkly repo root
php scripts/create-default-user.php    # optional dev user
php scripts/reset-onboarding.php --keep-entries

# Automated suites
php tests/onboarding_install_path_test.php
php tests/onboarding_ai_step_test.php
php tests/onboarding_checklist_test.php
php tests/onboarding_e2e_test.php
php tests/release_manifest_test.php

# Full harness
php tests/run.php

# Release zip must include START_HERE.html only (no duplicate entry files)
./scripts/build-cms-release.sh && unzip -l /tmp/birkly-release.zip | grep START_HERE
```

## First-run (START_HERE)

1. Extract the release zip so `START_HERE.html` sits next to `admin/`, `api/`, and `core/`.
2. Open **START_HERE.html** in a browser (not from inside the zip).
3. Confirm the folder checker shows **right folder**.
4. Pick **local** or **cloud** path.
5. Click **Start setup** — URL should include `?page=onboarding&install=local` or `install=cloud`.
6. Local: run `./start-server.sh` and use `http://localhost:8080/START_HERE.html` for PHP probe checks.

### Wrong-folder cases to verify manually

| Case | Expected message |
|------|------------------|
| Opened only `admin/` folder | Move up one folder |
| `Birkly/Birkly/` double nest | Open inner folder |
| Random wrong directory | Re-extract zip |

## Wizard v2 step order

| Step | ID | Notes |
|------|-----|-------|
| 0 | first-user | Only when no users exist |
| 1 | welcome | Skip entire wizard available |
| 2 | install-context | Shows local vs cloud summary from `install` param |
| 3 | site-info | Project name / description |
| 4 | use-case + demo | blog, business, shop, docs, developer; keep/delete demo |
| 5 | marketplace | Hint + link to Plugins → Marketplace |
| 6 | connect-ai | **Optional** BYOAI mini-wizard; skip always visible |

Resume: partial progress saved via `api/onboarding.php?action=update_progress` (`last_step`).

## Optional AI step (P19-D4 / P5.6-D)

- [ ] Provider → API key → test → role → require approval (default on)
- [ ] **Skip for now** and **Set up later** both complete onboarding without AI
- [ ] Ollama path shows local hint
- [ ] **AI must NOT appear on dashboard checklist** unless user skipped step 6 (`ai_skipped` in progress)

## Dashboard checklist v2 (essential only)

| Item | When shown |
|------|------------|
| Create first collection | Always (until done) |
| Add first entry | Always (until done) |
| Install marketplace plugin | Only when no marketplace plugin installed |
| Connect AI | **Only if** user skipped wizard AI step and has no AI connection |

Removed (P5.6): upload media, create template, configure settings, setup complete.

- [ ] Dismiss (×) per item persists
- [ ] **Hide checklist** removes panel for user
- [ ] Local install path shows localhost hint on collection item

## API smoke tests

```bash
# Install path (persists to settings/onboarding.json)
curl -s "http://localhost:8080/api/onboarding.php?action=status&install=local"

# Progress / AI skip flag
curl -X POST "http://localhost:8080/api/onboarding.php?action=update_progress" \
  -H "Content-Type: application/json" -H "Cookie: PHPSESSID=..." \
  -d '{"step":6,"ai_skipped":true}'

# Setup probe (read-only, no auth)
curl -s "http://localhost:8080/api/setup_probe.php"
```

## Reset for retesting

```bash
php scripts/reset-onboarding.php --keep-entries
rm -f settings/onboarding.json   # clears install_path
```

## Common issues

**Onboarding doesn't appear after demo bootstrap** — Run `php scripts/reset-onboarding.php --keep-entries`; check `users/*_onboarding.json` for `setup_wizard_completed: false`.

**START_HERE not in zip** — Run `./scripts/build-cms-release.sh`; confirm `scripts/cms-release-manifest.json` lists `START_HERE.html`.

**AI on dashboard when it shouldn't** — Checklist `connect_ai` only when `progress.ai_skipped` is true; completing AI in wizard sets `ai_connected`.
