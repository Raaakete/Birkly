# Phase 3 — lead-engineer execution handoff

**Start after:** This document and Phase 3 roadmap merged to `main` (PR #31).

**Human rule:** Birkly CMS always first. **Kekaba content/prod deploy is deferred** — do not block Phase 3 on it.

**Autonomy:** Human approved **entire Phase 3 once** (2026-06-25). Execute P3-M2→P3-M15 **without per-milestone human approval**. Merge when CI + qa-verifier pass. **Notify human only when Phase 3 is fully complete** (P3-M15).

---

## Copy-paste prompt (new Cloud Agent session)

```
You are lead-engineer for Birkly. Execute Phase 3 per docs/roadmap/ROADMAP.md (P3-M1 complete; start P3-M2).

READ FIRST:
- docs/roadmap/ROADMAP.md (Phase 3 section — approved 2026-06-25)
- docs/reviews/P3-lead-engineer-handoff.md (this file)
- docs/reviews/p3-m1-ai-automation-audit.md
- docs/reviews/2026-06-25-alpha-readiness-register.md
- docs/PROJECT_VALUES.md
- docs/agent-ops/AUTONOMOUS_EXECUTION.md

HUMAN DECISIONS (locked — do not re-ask):
- Automation = deactivatable plugin in plugins/automation/ (like any other plugin)
- New AI connections: require_approval defaults TRUE
- MCP API key: one per AI connection (scoped to that connection's role)

EXECUTION ORDER (P3-M2 → P3-M15):
1. P3-M2 — AI RBAC enforcement (checkPermission on all AI/MCP actions; approval ACLs)
2. P3-M3 — BYOAI connections + curated context pack
3. P3-M4 — Admin AI chat + structured tools + video
4. P3-M5 — MCP per-connection keys + bridge guide + video
5. P3-M6 — Automation plugin extraction (deactivate = no hooks/API/sidebar)
6. P3-M7 — Automation full E2E + tests + video
7. P3-M8 — User plugin: wire admin, fix permissions, tests + video
8. P3-M9 — Production hardening (update + backup smokes)
9. P3-M10 — Alpha feedback system + video
10. P3-M11 — Help ↔ Birkly-Docs v1 + video
11. P3-M12 — ContentEditor block system + video
12. P3-M13 — Birkly-Website closed alpha surface
13. P3-M14 — Documentation pack (Birkly + Birkly-Docs)
14. P3-M15 — Closure: full matrix green, videos V7–V14, notify human

DEFERRED (do not implement):
- Kekaba new pages/collections
- Kekaba production embed deploy (human later)

PER TASK:
- Branch: cursor/p3-m[N]-<slug>-24b5
- PR title: [P3-M[N]-T[X]] Description
- Run ./scripts/ci-check.sh and php tests/run.php before merge
- qa-verifier required; design-reviewer for UI; computerUse for browser flows
- Record walkthrough videos to docs/reviews/artifacts/ (V7+)
- Update roadmap checkboxes via small status commits on main or planner PR
- Daily digest: docs/agent-ops/daily-reports/YYYY-MM-DD.md (ongoing, not completion signal)

COMPLETION (P3-M15 only):
- All UX matrix rows pass
- Videos V7–V14 in docs/reviews/artifacts/
- docs/reviews/p3-phase3-closure-checklist.md complete
- php tests/run.php includes ai_permissions, automation_plugin, user_plugin suites
- Run: php scripts/send-agent-digest.php with Phase 3 COMPLETE section
- Email subject if M1-T8 configured: "Phase 3 complete — review videos"
- Do NOT ask human to review individual PRs. Escalate P0 only.

Start with P3-M2-T1: enforce checkPermission() in api/ai_chat.php executeReadAction/executeAiAction.
```

---

## Milestone map

| Milestone | Focus | Key paths |
|-----------|-------|-----------|
| P3-M2 | AI security | `api/ai_chat.php`, `api/ai_approvals.php`, `api/mcp_server.php` |
| P3-M3 | BYOAI setup | `api/ai_connections.php`, `api/ai_helpers.php`, `docs/ai/` |
| P3-M4 | AI chat | `admin/js/ai-chat-button.js` |
| P3-M5 | MCP | `api/mcp_server.php`, `mcp-bridge.js` |
| P3-M6–M7 | Automation plugin | `core/automation/` → `plugins/automation/` |
| P3-M8 | User plugin | `plugins/user_management/`, `admin/index.php` |
| P3-M9 | Hardening | `api/updates.php`, backup APIs |
| P3-M10 | Feedback | new `api/feedback.php` |
| P3-M11 | Help | `admin/templates/help.html`, Birkly-Docs |
| P3-M12 | ContentEditor | `fieldtypes/ContentEditor/` only |
| P3-M13 | Birkly-Website | beta collections, legal |
| P3-M14 | Docs | Birkly-Docs `06-integrations/` |
| P3-M15 | Closure | matrix, videos, human notify |

---

## Delegation

| Work | Agent |
|------|-------|
| CMS API, AI, automation, user plugin PHP | cms-developer |
| Admin UI, chat, automation builder | cms-developer + design-reviewer |
| Browser QA + videos | qa-verifier + computerUse |
| Birkly-Website / Birkly-Docs | frontend-developer + docs-writer |
| Roadmap status | product-planner (small edits) |

---

## Video register (P3-M15)

| ID | Topic | Milestone |
|----|-------|-----------|
| V7 | AI connect + role + default approval | P3-M3/M4 |
| V8 | AI chat read + write approval | P3-M4 |
| V9 | MCP external connect (per-connection key) | P3-M5 |
| V10 | Automation create → trigger → log | P3-M7 |
| V11 | User plugin register → entry | P3-M8 |
| V12 | Feedback submit → admin | P3-M10 |
| V13 | Help search + contextual link | P3-M11 |
| V14 | ContentEditor block insert round-trip | P3-M12 |

Store as `docs/reviews/artifacts/V7.mp4` … `V14.mp4`.

---

## Escalation to human

Only P0 blockers (security regression, data loss, cannot merge CI). Everything else: fix and merge autonomously.
