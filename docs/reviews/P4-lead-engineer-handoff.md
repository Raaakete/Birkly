# Phase 4 — lead-engineer execution handoff

**Start after:** Phase 4 roadmap merged to `main` (human approved 2026-06-25).

**Human rule:** Birkly CMS always first. **Kekaba deferred.** Human does **not** approve during P4 — they review the **end result only** after you finish internal QA.

**Autonomy:** Execute P4-M2→P4-M11 autonomously. Merge when CI + qa-verifier pass. **Do not email or flag the human for routine PRs, milestones, or mid-phase demos.** Notify human **once** at P4-M11 after: full matrix green, videos V15–V17, closure checklist, qa-verifier phase sign-off, design-reviewer on all UI milestones.

---

## Copy-paste prompt (new Cloud Agent session)

```
You are lead-engineer for Birkly. Own Phase 4 end-to-end per docs/roadmap/ROADMAP.md (P4-M1 complete; start P4-M2).

READ FIRST:
- docs/roadmap/ROADMAP.md (Phase 4 section — approved 2026-06-25)
- docs/reviews/P4-lead-engineer-handoff.md (this file)
- docs/reviews/2026-06-25-p4-readiness-register.md
- docs/PROJECT_VALUES.md
- docs/agent-ops/AUTONOMOUS_EXECUTION.md

HUMAN MODEL (critical):
- Keven approved Phase 4 ONCE. Do NOT ask for per-milestone or per-PR approval.
- Keven reviews ONLY the finished Phase 4 deliverable after YOU complete internal review.
- Daily digests are fine (background); they are NOT the handoff signal.
- Handoff signal = P4-M11-T5 only, after qa-verifier + design-reviewer sign off the whole phase.

LOCKED DECISIONS (do not re-ask):
- BYOAI outbound unchanged (user API keys; Birkly calls provider)
- require_approval default TRUE; role enforcement at execution — no regression
- MCP inbound: standalone endpoint profiles in settings/mcp_endpoints.json + core/mcp_endpoints.php
- Legacy users/*.json mcp_api_key: 90-day dual-resolve after migrate-mcp-endpoints.php
- Settings: dedicated MCP Connections tab; empty state; NO auto-generated keys on install
- Multi-profile MCP allowed (each endpoint: name, role, key, require_approval)
- OAuth: spike doc only (P4-M8); NO production OAuth code unless separate P5 approval
- Kekaba: DEFERRED

TWO CHANNELS (architecture):
- Outbound: admin chat → api/ai_chat.php → provider API (api_key_encrypted on AI connection rows)
- Inbound: external client → api/mcp_server.php → MCP endpoint profile (mcp_api_key only)
- Shared: core/ai_permissions.php, core/ai_tool_registry.php, approval queue

EXECUTION ORDER (P4-M2 → P4-M11):
1. P4-M2 — Alpha UX hardening (onboarding E2E, admin routing tests, AI stale connection regression)
2. P4-M3 — MCP endpoint model + migration + tests
3. P4-M4 — MCP Settings UI (first-class tab; demote MCP from outbound add flow)
4. P4-M5 — MCP hardening (rate limits, audit log, rotation grace, tests)
5. P4-M6 — MCP client guides + compatibility matrix + verify Claude/curl
6. P4-M7 — BYOAI wizard + API-key-vs-subscription copy + video V17
7. P4-M8 — OAuth spike go/no-go doc (no ship without P5)
8. P4-M9 — Docs split inbound/outbound + Help manifest v2
9. P4-M10 — Birkly-Website alpha copy/legal verification
10. P4-M11 — CLOSURE: matrix all green, videos V15–V17, checklist, notify human ONCE

INTERNAL REVIEW GATES (before P4-M11-T5):
- qa-verifier: full matrix browser re-run + all new PHPUnit suites pass
- design-reviewer: P4-M4 MCP tab, P4-M7 wizard copy
- computerUse: record V15 onboarding, V16 MCP inbound, V17 BYOAI wizard
- lead-engineer: p4-phase4-closure-checklist.md complete

PER TASK:
- Branch: cursor/p4-m[N]-<slug>-24b5
- PR title: [P4-M[N]-T[X]] Description
- Run ./scripts/ci-check.sh && php tests/run.php before every merge
- Merge autonomously when gates pass — do not wait for human
- Update roadmap checkboxes in batch commits

DEFERRED:
- Kekaba content + production deploy
- OAuth implementation (unless P4-M8 go → document as P5 only)
- SQL, dark mode, plugin marketplace, core npm/composer

COMPLETION (P4-M11 only — notify human):
- docs/reviews/p4-phase4-closure-checklist.md signed by qa-verifier
- docs/reviews/ux-flow-matrix.md all P4 rows pass
- docs/reviews/artifacts/V15.mp4, V16.mp4, V17.mp4
- php scripts/send-agent-digest.php with section "## Phase 4 COMPLETE — ready for human review"
- Email subject (if M1-T8): "Phase 4 complete — review end result"
- Include links: closure checklist, matrix, video paths, key PR range

Start with P4-M2-T1: onboarding E2E test or scripted browser QA.
```

---

## Milestone map

| Milestone | Focus | Key paths |
|-----------|-------|-----------|
| P4-M2 | Alpha UX | `docs/testing-onboarding.md`, `admin/index.php`, `admin/js/ai-chat-button.js` |
| P4-M3 | MCP model | `core/mcp_endpoints.php`, `api/mcp_endpoints.php`, `api/mcp_server.php` |
| P4-M4 | MCP UI | `admin/templates/settings.html`, `admin/js/mcp-connections.js` |
| P4-M5 | MCP hardening | `api/mcp_server.php`, audit log path |
| P4-M6 | MCP clients | `docs/mcp-setup-guide.md`, `mcp-bridge.js` |
| P4-M7 | BYOAI UX | `api/ai_connections.php`, `admin/js/ai-connections.js` |
| P4-M8 | OAuth spike | `docs/reviews/p4-oauth-spike.md` only |
| P4-M9 | Docs | `docs/ai/`, Birkly-Docs `01-admin/` |
| P4-M10 | Website | Birkly-Website beta collections |
| P4-M11 | Closure | matrix, videos, human notify |

---

## Delegation

| Work | Agent |
|------|-------|
| MCP API, migration, mcp_server | cms-developer |
| MCP + BYOAI Settings UI | cms-developer + design-reviewer |
| Browser QA + videos V15–V17 | qa-verifier + computerUse |
| Birkly-Docs + Help | docs-writer |
| Birkly-Website copy | frontend-developer |
| OAuth spike | cms-developer (research doc) + lead review |
| Phase closure | lead-engineer + qa-verifier |

---

## Video register

| ID | Topic | Milestone |
|----|-------|-----------|
| V15 | Onboarding full wizard E2E | P4-M2 |
| V16 | MCP inbound: create endpoint → external tools/list | P4-M4/M6 |
| V17 | BYOAI wizard: connect → chat → approval | P4-M7 |

---

## Escalation to human

**P0 only:** security regression, data loss, CI permanently red with no fix path. Everything else: fix and continue. **Never** escalate for scope confirmation — Phase 4 is fully approved.

---

## Execution status (lead-engineer)

| Milestone | Status | Notes |
|-----------|--------|-------|
| P4-M1 | complete | Roadmap + handoff merged |
| P4-M2 | complete | PR #37 — 26 CI suites; HTTP E2E onboarding/routing/AI delete |
| P4-M3 | next | MCP endpoint model |
