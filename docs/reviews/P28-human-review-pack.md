# P28 — Human review pack (delivered at M20)

**Status:** Closed 2026-07-19 — P28-M0→M20 complete  
**Purpose:** Watch videos showing **actual site usage**, then replay journeys locally

---

## Video index (V-BETA-01…09)

Videos live in `docs/reviews/artifacts/` on branch `cursor/p28-m0-m20-beta-launch-030a`.

| ID | Video file | Journey | What you will see |
|----|------------|---------|-------------------|
| V-BETA-01 | [v-beta-01-install.mp4](artifacts/v-beta-01-install.mp4) | J-INSTALL | Fresh admin setup — create-first-user / onboarding |
| V-BETA-02 | [v-beta-02-blog-fixed.mp4](artifacts/v-beta-02-blog-fixed.mp4) | J-BLOG | Blog list → post detail → rendered content |
| V-BETA-03 | [v-beta-03-coffee-fixed.mp4](artifacts/v-beta-03-coffee-fixed.mp4) | J-COFFEE | Product variants → add to cart |
| V-BETA-04 | [v-beta-04-yoga-fixed.mp4](artifacts/v-beta-04-yoga-fixed.mp4) | J-YOGA | Upcoming events → event detail → RSVP form |
| V-BETA-05 | [v-beta-05-marketplace.mp4](artifacts/v-beta-05-marketplace.mp4) | J-MARKETPLACE | Marketplace plugin install in admin |
| V-BETA-06 | [v-beta-06-admin-publish.mp4](artifacts/v-beta-06-admin-publish.mp4) | J-ADMIN | Content editor publish workflow |
| V-BETA-07 | [v-beta-07-social-fixed.mp4](artifacts/v-beta-07-social-fixed.mp4) | J-INVITE / J-SOCIAL | Social register/login + community feed |
| V-BETA-08 | [v-beta-08-kekaba.mp4](artifacts/v-beta-08-kekaba.mp4) | J-KEKABA | kekaba live path — no `birkly-baked` meta, client hydrates |
| V-BETA-09 | [v-beta-09-ops.mp4](artifacts/v-beta-09-ops.mp4) | J-HEALTH / Ops | Admin Settings → Operations + smoke status |

**Combined walkthroughs (older, still useful):**

- [v-beta-02-04-blog-yoga-interaction.mp4](artifacts/v-beta-02-04-blog-yoga-interaction.mp4) — blog + yoga in one session
- [v-beta-03-coffee-add-to-cart.mp4](artifacts/v-beta-03-coffee-add-to-cart.mp4) — coffee cart interaction
- [v-beta-walkthrough-overview.mp4](artifacts/v-beta-walkthrough-overview.mp4) — multi-site overview

### Prepare before each reference demo

```bash
./scripts/prepare-reference-demo.sh blog   # or coffee | yoga | social | influencer
php -S localhost:8080 -t . server-router.php
```

**Important:** Always run `prepare-reference-demo.sh` for the project you are testing. Installing one project overwrites shared `site_elements`.

---

## Step documentation index

| ID | Journey | Step doc | Automated test |
|----|---------|----------|----------------|
| V-BETA-01 | J-INSTALL | [artifacts/v-beta-01-install.md](artifacts/v-beta-01-install.md) | ✅ |
| V-BETA-02 | J-BLOG | [artifacts/v-beta-02-blog.md](artifacts/v-beta-02-blog.md) | ✅ |
| V-BETA-03 | J-COFFEE | [artifacts/v-beta-03-coffee.md](artifacts/v-beta-03-coffee.md) | ✅ |
| V-BETA-04 | J-YOGA | [artifacts/v-beta-04-yoga.md](artifacts/v-beta-04-yoga.md) | ✅ |
| V-BETA-05 | J-MARKETPLACE | [artifacts/v-beta-05-marketplace.md](artifacts/v-beta-05-marketplace.md) | ✅ |
| V-BETA-06 | J-ADMIN | [artifacts/v-beta-06-admin.md](artifacts/v-beta-06-admin.md) | ✅ |
| V-BETA-07 | J-INVITE | [artifacts/v-beta-07-invite.md](artifacts/v-beta-07-invite.md) | ✅ |
| V-BETA-08 | J-KEKABA | [artifacts/v-beta-08-kekaba.md](artifacts/v-beta-08-kekaba.md) | ✅ |
| V-BETA-09 | Ops panel | [artifacts/v-beta-09-ops.md](artifacts/v-beta-09-ops.md) | ✅ J-HEALTH |
| — | J-RENDER | — | ✅ client template harness (blog/coffee/yoga/social/influencer) |

---

## Automated journey test results

**Run date:** 2026-07-19  
**Base URL:** http://127.0.0.1:8080  
**Results:** 14/14 passed

```bash
php scripts/journey-runner.php --all
# Report: logs/journey-report.json
```

| Journey | Description |
|---------|-------------|
| J-INSTALL | Fresh release zip → START_HERE → onboarding |
| J-BLOG | Reference blog visitor |
| J-PORTFOLIO | Portfolio visitor |
| J-COFFEE | Commerce cart (real plugin) |
| J-YOGA | Events RSVP (real plugin) |
| J-SOCIAL | Community register/post |
| J-INFLUENCER | Fan hub + merch cart |
| J-ADMIN | Publish entry |
| J-MARKETPLACE | Real plugin ZIP install |
| J-INVITE | Team invite accept |
| J-KEKABA | Live path, no bake |
| J-WEBSITE | Marketing site static smoke |
| J-HEALTH | health.php + production-smoke |
| J-RENDER | Reference demo client render |

---

## Replay commands

```bash
# All journeys
php -S 127.0.0.1:8080 -t . server-router.php &
php scripts/journey-runner.php --all

# Preflight (automated Part 2)
./scripts/beta-launch-preflight.sh

# kekaba live path
./scripts/migrate-kekaba-live-path.sh ../kekaba
cd ../kekaba && python3 -m http.server 8000 &
cd ../Birkly && php scripts/journey-runner.php --journey J-KEKABA --base http://127.0.0.1:8000

# Browser smoke (Playwright, optional)
./scripts/reference-browser-smoke.sh
```

## Staging replay

```bash
export BIRKLY_JOURNEY_BASE_URL=https://your-staging-host.example
php scripts/journey-runner.php --all
```

---

## Closure checklist

- [x] Real projects installed via `install-reference-project.sh` (not mocked)
- [x] 14 journeys green + `logs/journey-report.json`
- [x] kekaba live-path migration (`migrate-kekaba-live-path.sh`)
- [x] V-BETA-01…09 videos + step docs
- [x] `beta-launch-preflight.sh` automated gates
- [x] Wired into `ci-check.sh` + `agent-smoke-test.sh`

**Human Part 1 (manual):** DNS, Stripe live, legal — see `scripts/beta-launch-preflight.sh` § Part 1

---

## Sign-off

| Reviewer | Date | Notes |
|----------|------|-------|
| Keven | | |
