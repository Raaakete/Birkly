# P28 — Human review pack (delivered at M20)

**Status:** Placeholder — video URLs pending from video agent · replay commands ready  
**Purpose:** Single index for Keven to **watch videos** and **replay journeys locally**

---

## How to use this pack

1. Watch each **V-BETA** video below (agents record after automated journeys pass).
2. Optionally **replay** any journey with the copy-paste commands.
3. Sign off when satisfied — or file issues against specific journey IDs.

---

## Video index

| Video | Journey | Recording | Step doc | Status |
|-------|---------|-----------|----------|--------|
| V-BETA-01 | J-INSTALL | _pending_ | `artifacts/v-beta-01-install.md` | pending |
| V-BETA-02 | J-BLOG | _pending_ | `artifacts/v-beta-02-blog.md` | pending |
| V-BETA-03 | J-COFFEE | _pending_ | `artifacts/v-beta-03-coffee.md` | pending |
| V-BETA-04 | J-YOGA | _pending_ | `artifacts/v-beta-04-yoga.md` | pending |
| V-BETA-05 | J-MARKETPLACE | _pending_ | `artifacts/v-beta-05-marketplace.md` | pending |
| V-BETA-06 | J-ADMIN | _pending_ | `artifacts/v-beta-06-admin.md` | pending |
| V-BETA-07 | J-INVITE | _pending_ | `artifacts/v-beta-07-invite.md` | pending |
| V-BETA-08 | J-KEKABA | _pending_ | `artifacts/v-beta-08-kekaba.md` | pending |
| V-BETA-09 | Ops panel | _pending_ | `artifacts/v-beta-09-ops.md` | pending |

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

## What agents verified before recording

- [x] All JRN-* matrix rows **pass**
- [x] `logs/journey-report.json` shows all journeys pass (13/13)
- [x] Real project files on disk (not mocked)
- [ ] Videos show **browser UI**, not terminal-only
- [ ] kekaba pages have **no** `birkly-baked` meta

---

## Sign-off

| Reviewer | Date | Notes |
|----------|------|-------|
| Keven | | |
