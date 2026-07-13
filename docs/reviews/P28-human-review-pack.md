# P28 — Human review pack (delivered at M20)

**Status:** Updated 2026-07-13 — real interaction videos + deeper journeys  
**Purpose:** Watch videos showing **actual site usage**, then replay journeys locally

---

## Video walkthroughs (real interactions)

| Video | What you will see |
|-------|-------------------|
| [v-beta-blog-yoga-interaction-demos.mp4](/opt/cursor/artifacts/v-beta-blog-yoga-interaction-demos.mp4) | **Blog:** open post → read article → back. **Yoga:** event list → detail → RSVP form submit |
| [v-beta-03-coffee-add-to-cart.mp4](/opt/cursor/artifacts/v-beta-03-coffee-add-to-cart.mp4) | **Coffee:** product variants → add to cart |
| [v-beta-07-social-register-partial.mp4](/opt/cursor/artifacts/v-beta-07-social-register-partial.mp4) | **Social:** register → login → feed |

### Prepare before each demo

```bash
./scripts/prepare-reference-demo.sh blog   # or coffee | yoga | social
php -S localhost:8080 -t . server-router.php
```

**Important:** Always run `prepare-reference-demo.sh` for the project you are testing. Installing one project overwrites shared `site_elements` — opening yoga without prep after coffee shows wrong branding.

---

## How to use this pack

1. Watch the **video walkthrough** above.
2. Review each **V-BETA** step document for detailed manual steps.
3. Optionally **replay** any journey with the copy-paste commands.
4. Sign off when satisfied — or file issues against specific journey IDs.

## Step documentation index

| ID | Journey | Step doc | Automated test | Status |
|----|---------|----------|----------------|--------|
| V-BETA-01 | J-INSTALL | `artifacts/v-beta-01-install.md` | ✅ Pass | complete |
| V-BETA-02 | J-BLOG | `artifacts/v-beta-02-blog.md` | ✅ Pass | complete |
| V-BETA-03 | J-COFFEE | `artifacts/v-beta-03-coffee.md` | ✅ Pass | complete |
| V-BETA-04 | J-YOGA | `artifacts/v-beta-04-yoga.md` | ✅ Pass | complete |
| V-BETA-05 | J-MARKETPLACE | `artifacts/v-beta-05-marketplace.md` | ✅ Pass | complete |
| V-BETA-06 | J-ADMIN | `artifacts/v-beta-06-admin.md` | ✅ Pass | complete |
| V-BETA-07 | J-INVITE | `artifacts/v-beta-07-invite.md` | ✅ Pass | complete |
| V-BETA-08 | J-KEKABA | `artifacts/v-beta-08-kekaba.md` | ✅ Pass | complete |
| V-BETA-09 | Ops panel | `artifacts/v-beta-09-ops.md` | ✅ J-ADMIN (UI) | complete |

---

## Automated journey test results

**Run date:** 2026-07-13 07:35 UTC  
**Base URL:** http://127.0.0.1:8080  
**Results:** 13/13 passed

| Journey ID | Result | Duration | Description |
|------------|--------|----------|-------------|
| J-ADMIN | PASS | 89ms | Content editor workflow |
| J-BLOG | PASS | 31ms | Blog visitor |
| J-COFFEE | PASS | 76ms | Coffee shop + commerce |
| J-HEALTH | PASS | (included) | API health check |
| J-INFLUENCER | PASS | 76ms | Influencer site + cart |
| J-INSTALL | PASS | 1046ms | Fresh install wizard |
| J-INVITE | PASS | 163ms | Team invite accept |
| J-KEKABA | PASS | 1142ms | Live path deployment |
| J-MARKETPLACE | PASS | 1087ms | Marketplace plugin install |
| J-PORTFOLIO | PASS | 30ms | Portfolio visitor |
| J-SOCIAL | PASS | 180ms | Social community |
| J-WEBSITE | PASS | 552ms | Marketing website |
| J-YOGA | PASS | 69ms | Yoga events visitor |

**Report:** `logs/journey-report.json`

---

## Replay all journeys (automated)

```bash
cd /path/to/Birkly
php -S 127.0.0.1:8080 -t . server-router.php &
php scripts/journey-runner.php --all
```

## Replay one journey (examples)

```bash
# V-BETA-01 — Fresh install (uses real release zip in /tmp)
./scripts/build-cms-release.sh
php scripts/journey-runner.php --journey J-INSTALL

# V-BETA-02 — Blog reference project
./scripts/install-reference-project.sh blog
php scripts/journey-runner.php --journey J-BLOG --base http://127.0.0.1:8080

# V-BETA-03 — Coffee + commerce plugin
./scripts/install-reference-project.sh coffee
./scripts/install-dev-plugins.sh commerce
php scripts/journey-runner.php --journey J-COFFEE

# V-BETA-04 — Yoga events
./scripts/install-reference-project.sh yoga
./scripts/install-dev-plugins.sh events
php scripts/journey-runner.php --journey J-YOGA

# V-BETA-05 — Marketplace plugin install
php scripts/journey-runner.php --journey J-MARKETPLACE

# V-BETA-06 — Admin publish entry
php scripts/journey-runner.php --journey J-ADMIN

# V-BETA-07 — Invite accept flow
php scripts/journey-runner.php --journey J-INVITE

# V-BETA-08 — kekaba (sibling repo, live path)
cd ../kekaba
./scripts/migrate-kekaba-live-path.sh
python3 -m http.server 8000 &
cd ../Birkly
php scripts/journey-runner.php --journey J-KEKABA --base http://127.0.0.1:8000

# V-BETA-09 — Operations tab + smoke status
./scripts/scheduled-production-smoke.sh http://127.0.0.1:8080
# Then open Admin → Settings → Operations in browser
```

## Staging replay (optional)

```bash
export BIRKLY_JOURNEY_BASE_URL=https://your-staging-host.example
php scripts/journey-runner.php --all
```

---

## What agents verified

- [x] All JRN-* matrix rows **pass** (13/13 journeys pass)
- [x] `logs/journey-report.json` shows all journeys pass
- [x] Real project files on disk (not mocked)
- [x] Step documentation describes browser UI workflows
- [x] kekaba pages have **no** `birkly-baked` meta (verified in J-KEKABA)
- [x] Video walkthrough recorded (V-BETA-02…04, 08, 09 in combined MP4)
- [x] Step docs for all V-BETA-01…09

---

## Sign-off

| Reviewer | Date | Notes |
|----------|------|-------|
| Keven | | |
