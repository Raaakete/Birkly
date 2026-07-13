# Phase 28 — Lead-engineer handoff (Beta launch automation)

**Status:** Proposed (2026-07-13) — awaiting human approval  
**Human rule:** Execute **P28-M0→M18** autonomously after approval. Notify human **once** at **P28-M18** only.

**Master plan:** `docs/reviews/P28-beta-launch-automation-plan.md`  
**Journeys:** `docs/reviews/P28-user-journey-catalog.md`  
**Gap register:** `docs/reviews/P28-beta-launch-gap-register.md`  
**Matrix:** `docs/reviews/P28-verification-matrix.md`

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 28 — Beta launch automation & product gaps.

Human approved P28. Execute P28-M0→M18 autonomously in one wave.
Notify human ONCE at P28-M18 closure only.

Human handles Part 1 manually (DNS, Stripe live, legal). YOU automate Part 2, 4, 5.

READ FIRST:
1. docs/reviews/P28-lead-engineer-handoff.md
2. docs/reviews/P28-beta-launch-automation-plan.md
3. docs/reviews/P28-user-journey-catalog.md
4. docs/reference-projects/user-stories.md
5. scripts/production-smoke.sh, staging-smoke-kekaba.sh, onboarding_e2e_test.php
6. kekaba repo — bake scripts, check-production.sh

═══ PART 2 — AUTOMATED USER JOURNEYS (M1–M12) ═══

Build scripts/journey-runner.php:
- Loads tests/journeys/J-*.php
- Runs against local php -S OR BIRKLY_JOURNEY_BASE_URL
- Outputs logs/journey-report.json; non-zero exit on fail

Implement journeys:
  J-INSTALL, J-BLOG, J-PORTFOLIO, J-COFFEE, J-YOGA
  J-SOCIAL, J-INFLUENCER, J-ADMIN, J-MARKETPLACE, J-INVITE
  J-KEKABA (after live-path migration), J-HEALTH, J-WEBSITE

kekaba (sibling repo):
- scripts/migrate-kekaba-live-path.sh — remove birkly-baked, canonical client tag
- CI: sync embed → migrate → J-KEKABA

Wire into ci-check.sh + agent-smoke-test.sh

═══ PART 4 — OPS (M13–M14) ═══

- scheduled-production-smoke.sh runs J-HEALTH after production-smoke
- On fail → health_alerts.php email/webhook
- Admin Settings → Operations tab (last smoke + last journey JSON)
- docs/agent-ops/PRODUCTION_CRON.md

═══ PART 5 — PRODUCT GAPS (M15–M16) ═══

- update_system_e2e_test.php
- README START_HERE fix
- studio_renderer_test flake fix
- Marketplace catalog audit script
- Birkly-Website deploy-smoke.sh
- Refresh docs/reviews/ux-flow-matrix.md from journey results

═══ PREFLIGHT (M17) ═══

scripts/beta-launch-preflight.sh — automated checks + human checklist for DNS/Stripe/legal

DO NOT start Studio P26.

Branches: cursor/p28-m[N]-<slug>-030a

DELEGATION:
cms-developer:      M1–M2, M6–M8, M11, M13–M15, M17
frontend-developer: M14 (ops UI)
kekaba/frontend:    M9–M10 (kekaba repo PRs)
docs-writer:        M16–M17, PRODUCTION_CRON.md
qa-verifier:        M12, M18 — run full journey suite against staging if URL set

CLOSURE:
[ ] 12+ journeys green in CI
[ ] kekaba live-path (no birkly-baked)
[ ] Ops smoke + alerts + admin panel
[ ] Update E2E + marketplace install journey
[ ] beta-launch-preflight.sh
[ ] Single digest notification
```

---

## Human approval checklist

- [ ] P28-D1–D16 accepted
- [ ] P26 Studio remains paused until P28-M18
- [ ] M0–M18 single-wave execution approved
