# P28 — Beta launch gap register

**Date:** 2026-07-13 · **Human Part 1 excluded** (DNS, Stripe live, legal)

## Part 2 — Proof sites (automate)

| ID | Gap | Current | P28 target |
|----|-----|---------|------------|
| G2-01 | No executable user journeys | `user-stories.md` docs only | `journey-runner.php` + 12 journeys |
| G2-02 | kekaba on baked HTML | `birkly-baked` meta on all pages | `migrate-kekaba-live-path.sh` |
| G2-03 | Staging smoke = health only | `production-smoke.sh` | Journeys against `BIRKLY_JOURNEY_BASE_URL` |
| G2-04 | Reference projects not CI-proven | Install script exists | J-BLOG through J-INFLUENCER in CI |
| G2-05 | Birkly-Website not smoke-tested | Manual | `deploy-smoke.sh` in Website repo |
| G2-06 | Agent proof = human deploy | kekaba deploy checklist human | Agents run journeys in cloud with staging URL |

## Part 4 — Ops (optimize)

| ID | Gap | Current | P28 target |
|----|-----|---------|------------|
| G4-01 | Smoke failures silent | Log file only | `health_alerts.php` on fail |
| G4-02 | No journey in cron | `scheduled-production-smoke.sh` | Append J-HEALTH-* |
| G4-03 | No admin ops visibility | — | Settings → Operations tab |
| G4-04 | Cron docs scattered | P16 playbook | `PRODUCTION_CRON.md` single doc |
| G4-05 | Digest doesn't include journey status | Agent digest | Include last journey-report.json |

## Part 5 — Product gaps (fix)

| ID | Gap | Current | P28 target |
|----|-----|---------|------------|
| G5-01 | Update system E2E | Documented only | `update_system_e2e_test.php` |
| G5-02 | Marketplace install path | Unit tests | J-MARKETPLACE journey |
| G5-03 | Fresh install → plugins | Onboarding hint only | J-INSTALL + J-MARKETPLACE |
| G5-04 | `studio_renderer_test` flake | Intermittent fail | Fix home slug / cache path |
| G5-05 | README START_HERE naming | `0_START-HERE.html` in README | Align to P19 `START_HERE.html` |
| G5-06 | UX flow matrix stale | Last updated P4 | Regenerate from P28 journeys |
| G5-07 | kekaba embed drift | Manual `sync-client-embed.sh` | CI job on kekaba PRs |
| G5-08 | Marketplace catalog audit | P9 seed | Script verifies official offers installable |
| G5-09 | Invite flow untested E2E | API exists | J-INVITE journey |
| G5-10 | Commerce/events on real site | kekaba bake hides client bugs | Live path exposes real stack |

## Out of scope

- Studio P26
- Human DNS / Stripe / legal execution
- Full Playwright browser grid
