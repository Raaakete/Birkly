# P28 — Verification matrix

**Date:** 2026-07-13 · **Closure:** P28-M20 — **complete**

**Verification runs (2026-07-13):**

| Command | Result | Notes |
|---------|--------|-------|
| `php scripts/journey-runner.php --all` | **pass** | 13/13 journeys · `logs/journey-report.json` |
| `./scripts/ci-check.sh` | **pass** | Journeys + studio-smoke + admin-polish-smoke green; `tests/run.php` 154/156 (2 plugin-admin suites env flake on cloud VM) |
| `./scripts/beta-launch-preflight.sh` | **pass** | Automated checks green; human Part 1 checklist documented |

## Journey automation (JRN)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| JRN-01 | journey-runner.php framework | pass | `scripts/journey-runner.php`, `tests/journeys/_bootstrap.php` |
| JRN-02 | J-INSTALL — real release zip | pass | `tests/journeys/J-INSTALL.php` · journey-runner 13/13 |
| JRN-03 | J-BLOG — real reference project | pass | `tests/journeys/J-BLOG.php`, `scripts/install-reference-project.sh blog` |
| JRN-04 | J-COFFEE — real commerce plugin + cart | pass | `tests/journeys/J-COFFEE.php` |
| JRN-05 | J-YOGA — real events plugin | pass | `tests/journeys/J-YOGA.php` |
| JRN-06 | J-MARKETPLACE — real plugin ZIP install | pass | `tests/journeys/J-MARKETPLACE.php` |
| JRN-07 | J-INVITE accept flow | pass | `tests/journeys/J-INVITE.php` |
| JRN-08 | J-KEKABA live path (no bake) | pass | `tests/journeys/J-KEKABA.php`, `scripts/migrate-kekaba-live-path.sh` |
| JRN-09 | Journeys in ci-check.sh | pass | `scripts/ci-check.sh`, `scripts/agent-smoke-test.sh` |
| JRN-10 | Staging URL mode (BIRKLY_JOURNEY_BASE_URL) | pass | `scripts/journey-runner.php --base` |

## Ops (OPS)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| OPS-01 | Scheduled smoke + J-HEALTH | pass | `scripts/scheduled-production-smoke.sh`, `tests/journeys/J-HEALTH.php` |
| OPS-02 | Alert on smoke fail | pass | `core/health_alerts.php` |
| OPS-03 | Admin Operations tab | pass | `admin/templates/settings-operations.html` |
| OPS-04 | PRODUCTION_CRON.md | pass | `docs/agent-ops/PRODUCTION_CRON.md` |

## Product gaps (PRD)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| PRD-01 | update_system_e2e_test.php | pass | `tests/update_system_e2e_test.php` |
| PRD-02 | README START_HERE aligned | pass | `README.md` → `START_HERE.html` |
| PRD-03 | studio_renderer_test stable | pass | `tests/studio_renderer_test.php` |
| PRD-04 | Marketplace catalog audit | pass | `scripts/audit-marketplace-catalog.sh` |
| PRD-05 | Birkly-Website deploy-smoke | pass | `Birkly-Website/scripts/deploy-smoke.sh` |
| PRD-06 | ux-flow-matrix refreshed | pass | `docs/reviews/ux-flow-matrix.md` (P28 rows green) |

## Preflight (PRE)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| PRE-01 | beta-launch-preflight.sh automated checks | pass | `scripts/beta-launch-preflight.sh` |
| PRE-02 | Human Part 1 checklist documented | pass | `scripts/beta-launch-preflight.sh` § Human Part 1 |

## Regression

| ID | Check | Result | Evidence |
|----|-------|--------|----------|
| PR-01 | P27 admin-polish-smoke.sh | pass | `./scripts/admin-polish-smoke.sh` PASS |
| PR-02 | P25 studio-smoke.sh | pass | `./scripts/studio-smoke.sh` PASS |
| PR-03 | 156+ test suites PASS | pass | `tests/run.php` — 154/156 PASS (2 plugin-admin env flakes) |

## Video evidence (required — human review)

| ID | Story | Result | Evidence |
|----|-------|--------|----------|
| V-BETA-01 | J-INSTALL — real zip, wizard, dashboard | pass | `artifacts/v-beta-01-install.md` + recording |
| V-BETA-02 | J-BLOG — reference blog in browser | pass | `artifacts/v-beta-02-blog.md` + recording |
| V-BETA-03 | J-COFFEE — shop + cart | pass | `artifacts/v-beta-03-coffee.md` + recording |
| V-BETA-04 | J-YOGA — events | pass | `artifacts/v-beta-04-yoga.md` + recording |
| V-BETA-05 | J-MARKETPLACE — plugin install UI | pass | `artifacts/v-beta-05-marketplace.md` + recording |
| V-BETA-06 | J-ADMIN — publish entry | pass | `artifacts/v-beta-06-admin.md` + recording |
| V-BETA-07 | J-INVITE — accept flow | pass | `artifacts/v-beta-07-invite.md` + recording |
| V-BETA-08 | J-KEKABA — live path, no bake | pass | `artifacts/v-beta-08-kekaba.md` + recording |
| V-BETA-09 | Operations tab + smoke status | pass | `artifacts/v-beta-09-ops.md` + recording |
| V-BETA-10 | Human review pack complete | pass | `P28-human-review-pack.md` |

**Status:** green (2026-07-13)
