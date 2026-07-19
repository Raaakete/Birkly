# Phase 28 — Lead-engineer handoff (Beta launch automation)

**Status:** Approved (2026-07-13) — **execution approved** (2026-07-19, Keven)  
**Human rule:** Execute **P28-M0→M20** autonomously. Notify human **once** at **P28-M20** with **video review pack links**.

**Master plan:** `docs/reviews/P28-beta-launch-automation-plan.md`  
**Real projects + videos:** `docs/reviews/P28-real-projects-and-video-policy.md`  
**Journeys:** `docs/reviews/P28-user-journey-catalog.md`  
**Human review pack:** `docs/reviews/P28-human-review-pack.md`  
**Matrix:** `docs/reviews/P28-verification-matrix.md`

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 28 — Beta launch automation & product gaps.

Human approved P28. Execute P28-M0→M20 autonomously in one wave.
Notify human ONCE at P28-M20 with video review pack (V-BETA-01…09 links).

Human handles Part 1 manually (DNS, Stripe live, legal). YOU automate Part 2, 4, 5.

READ FIRST:
1. docs/reviews/P28-real-projects-and-video-policy.md  ← REAL projects, not mocks
2. docs/reviews/P28-beta-launch-automation-plan.md
3. docs/reviews/P28-user-journey-catalog.md
4. docs/reviews/P28-human-review-pack.md
5. docs/reference-projects/user-stories.md
6. scripts/install-reference-project.sh, build-cms-release.sh

═══ REAL TEST PROJECTS (mandatory) ═══

Journeys MUST use real disk state:
- J-INSTALL: extract build-cms-release.sh zip to temp dir
- J-BLOG…INFLUENCER: install-reference-project.sh (real collections + website/)
- J-COFFEE/YOGA: real plugin install (marketplace ZIP or install-dev-plugins.sh)
- J-MARKETPLACE: real plugin files in plugins/ — NOT mocked API responses
- J-KEKABA: real kekaba repo after migrate-kekaba-live-path.sh

HTTP assertions in journey-runner prove CI; videos prove UI for human.

═══ PART 2 — JOURNEYS (M1–M12) ═══

scripts/journey-runner.php + tests/journeys/J-*.php
Wire into ci-check.sh + agent-smoke-test.sh
Optional BIRKLY_JOURNEY_BASE_URL for staging

═══ PART 4 — OPS (M13–M14) ═══

Scheduled smoke + J-HEALTH, alerts, Operations admin tab

═══ PART 5 — PRODUCT (M15–M16) ═══

Update E2E, README, flakes, website smoke, catalog audit

═══ VIDEO REVIEWS (M17–M18) — human must SEE solutions ═══

After all journeys PASS in CI:
- qa-verifier + computerUse record V-BETA-01…09 (browser walkthroughs)
- Write docs/reviews/artifacts/v-beta-*.md step docs (like V-STU-04)
- Populate docs/reviews/P28-human-review-pack.md with video URLs + replay commands

Videos must show:
  V-BETA-01 install/wizard
  V-BETA-02 blog site in browser
  V-BETA-03 coffee shop + cart
  V-BETA-04 yoga events
  V-BETA-05 marketplace plugin install
  V-BETA-06 admin publish
  V-BETA-07 invite accept
  V-BETA-08 kekaba live path (no bake)
  V-BETA-09 ops panel

M19: beta-launch-preflight.sh
M20: closure + digest WITH video links

DO NOT start Studio P26.

DELEGATION:
cms-developer:      M1–M12, M15, M19
frontend-developer: M14
qa-verifier:        M17 videos + M12 staging + M20 matrix
computerUse:        V-BETA-01…09 recordings (mandatory)
docs-writer:        artifacts/v-beta-*.md, human review pack
kekaba agent:       M9–M10 kekaba repo PRs

CLOSURE:
[ ] Real projects installed (not mocked)
[ ] 12+ journeys green in CI + journey-report.json
[ ] kekaba live-path (no birkly-baked)
[ ] V-BETA-01…09 videos + step docs
[ ] P28-human-review-pack.md complete
[ ] Single digest with video index for human review
```

---

## Human approval checklist

- [x] P28-D1–D19 accepted (2026-07-13)
- [x] Real test projects + video reviews required (2026-07-13)
- [x] P26 Studio paused until P28-M20 (2026-07-13)
- [x] M0–M20 single-wave execution approved (2026-07-19)
