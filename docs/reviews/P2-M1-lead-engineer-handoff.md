# P2-M1 handoff — lead-engineer (review gate + videos)

**Human approved:** 2026-06-18  
**Phase 2 plan:** Approved — execute P2-M1 before Phase 2 roadmap PR.  
**CMS first.** Autonomous merge: docs + review artifacts only during P2-M1 (no feature fixes unless P0 blocker).

## Mission

Run the **system review & validation gate** (P2-M1). Deliver tests, UX flow matrix, issue/opportunity register, and **walkthrough videos** for human review at 07:00 CEST digest.

## Constraints

- Follow `docs/PROJECT_VALUES.md` and `docs/agent-ops/AUTONOMOUS_EXECUTION.md`
- Do **not** merge CMS feature work in P2-M1
- Do **not** open Phase 2 roadmap PR until register + videos are done (product-planner will PR after human sees deliverables)

## Phase 2 context (for register tagging)

Upcoming work (not in scope to implement now):

- **P2-M3 ContentEditor** — all code in `fieldtypes/ContentEditor/`; components = HTML+CSS+JS+Birkly templating from day one
- P2-M2 UX flow audit program, P2-M4 collection groups/DnD, P2-M5 onboarding, P2-M6+ hardening/sync

## Tasks

### P2-M1-T1 Automated baseline

```bash
cd /agent/repos/Birkly
./scripts/ci-check.sh | tee docs/reviews/2026-06-18-automated.log
php tests/run.php | tee -a docs/reviews/2026-06-18-automated.log
```

### P2-M1-T2 UX flow matrix (CMS-first)

Create `docs/reviews/2026-06-18-system-review.md` with verdict per row:

| Flow | Area |
|------|------|
| Admin login | Auth |
| Onboarding wizard (fresh user) | Onboarding |
| Create collection + fields | Collections |
| Create/edit/publish entry | Entries |
| **ContentEditor** visual ↔ HTML save/reload | ContentEditor |
| Media upload + pick in field | Media |
| Collection groups + drag/drop | Groups/DnD |
| Settings (domains, plugins) | Settings |
| Public API list collections | API |
| Demo website / client render | Client |
| page_bundle / page_snapshot | M4 APIs |

Verdict: pass | partial | fail. Tag Phase 2 milestone if applicable.

### P2-M1-T3 Videos (2–5 min each)

Record with screen tool; save under artifacts with descriptive names:

1. **V1-admin-workflow** — login → collection → entry → save
2. **V2-security-boundary** — public read works; protected endpoints blocked
3. **V3-content-editor** — visual/HTML tabs, known breakage shown honestly
4. **V4-client-performance** — network tab: bundle/snapshot/hydrate vs serial
5. **V5-collection-groups-dnd** — groups reorder, cross-group move (show bugs)
6. **V6-onboarding** — first-run path or document skip reason

### P2-M1-T4 Issue/opportunity register

Same file or `docs/reviews/2026-06-18-issue-register.md`:

| ID | Type | Area | Finding | Severity | Phase 2 |
|----|------|------|---------|----------|---------|

Types: bug | regression | perf | ux | security | opportunity

Minimum 10 entries. Include ContentEditor, groups/DnD, onboarding findings.

### P2-M1-T5 Deliver

- Commit review docs on branch `cursor/p2-m1-review-gate-24b5`
- PR: documentation only
- Daily digest paragraph + `docs/agent-ops/daily-reports/YYYY-MM-DD.md`
- Tell human: videos ready for review; Phase 2 roadmap PR follows after their OK

## Bootstrap

```bash
cd /agent/repos/Birkly
php scripts/create-default-user.php   # if no users/
php scripts/create-demo-content.php   # optional, for meaningful demos
php -S localhost:8080 -t . server-router.php
```

Admin: http://localhost:8080/admin (admin@localhost / password)

## Sign-off

- qa-verifier: matrix complete
- lead-engineer: videos + register delivered
