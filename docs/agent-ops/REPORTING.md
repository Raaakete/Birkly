# Agent reporting and autonomy

How the Birkly cloud agent team reports to the human owner while working autonomously. All agents read this file at session start.

**Values:** `docs/PROJECT_VALUES.md`  
**Merge policy (no human per-PR review):** `docs/agent-ops/AUTONOMOUS_EXECUTION.md`

## Principles

1. **Silence during work blocks = progress** — no hourly noise.
2. **Daily digest at 07:00 CEST** — narrative summary of what happened (especially **merges**, not review requests).
3. **P0/P1 escalations stop work immediately** — do not wait for the digest.
4. **PRs are the audit trail**; digests summarize outcomes in plain language.
5. **Approved baseline in `docs/roadmap/ROADMAP.md`** is the contract; scope changes need human approval.
6. **Agents merge in-scope work** after CI + qa-verifier (+ design-reviewer for UI) — see AUTONOMOUS_EXECUTION.md.

## Daily digest (email + file)

| Setting | Value |
|---------|--------|
| **Recipient** | `keven@protonmail.com` |
| **Delivery deadline** | **06:45 CEST** each active agent day |
| **Human review** | **07:00 CEST** |
| **Owner** | `lead-engineer` compiles; specialists contribute notes |
| **Quiet days** | Still send a short digest (“no changes yesterday”) |

### Format

**Subject:** `Birkly daily — YYYY-MM-DD`

**Body:** **1–2 paragraphs** in plain language — what happened, why it matters, what is next. Then a short appendix:

```markdown
---
PRs: [links or none]
Milestone: M[N] (X/Y tasks)
Values check: ✅ aligned | ⚠️ trade-off noted
Blocked / needs you: none | [P0/P1 item]
Today: [1–3 concrete next items]
```

### Delivery channels (in order)

1. **Email** — run `php scripts/send-agent-digest.php` (requires env secrets; see script header).
2. **File** — always write `docs/agent-ops/daily-reports/YYYY-MM-DD.md` (same content as email).
3. **Chat** — if email fails, post the digest in the cloud-agent session and note the failure.

Never skip a digest silently. If email is not configured, file + chat still run.

### Email configuration (cloud environment secrets)

Configure in the Cursor cloud environment dashboard (not committed to git):

| Variable | Purpose |
|----------|---------|
| `AGENT_DIGEST_TO` | Override recipient (default: `keven@protonmail.com`) |
| `RESEND_API_KEY` | Resend HTTP API key |
| `AGENT_DIGEST_FROM` | Verified sender address (required) |

Receiving at ProtonMail is fine. Sending uses **Resend** (plain PHP + curl — no npm). Add other providers later if needed.

## Report types

| Report | Owner | When | Human action? |
|--------|-------|------|---------------|
| **Session bootstrap** | First agent in session | Session start | Only if FAIL |
| **Task completion** | Specialist → lead-engineer | After each task chunk | No |
| **Daily digest** | lead-engineer → human | By 06:45 CEST | Read outcomes at 07:00 — not PR review |
| **Milestone status** | lead-engineer → human | When milestone completes | Optional ack |
| **Blocker / decision** | Any → human | Immediately | Yes (P0/P1) |
| **Roadmap delta** | product-planner → human | After merge or major proposal | Major only |

## Session bootstrap (≤10 lines)

```
Status: READY | FAIL
Repos: [paths found]
Services: CMS [up/down], sites [up/down/n/a]
Roadmap: M[N] in progress
Blockers: none | ...
```

Run `scripts/agent-smoke-test.sh` when available.

## Task completion (specialist → lead)

```
Task: M[N]-T[N] [title]
Repo: ...
Verdict: complete | partial | blocked
Changes: [files/areas]
Verification: [what was run]
PR: [link or not opened]
Values check: ✅ | ⚠️
Follow-ups: [dependencies]
```

## Milestone status (lead → human)

```
Milestone: M[N] — [name]
Progress: X/Y tasks done
PRs: [links by repo]
QA/Design: pass | pending | issues
Blocked: none | [item + options]
Needs decision: none | [question + recommendation]
Next: [concrete actions]
```

## Escalation matrix

| Situation | Agent action | Human required? |
|-----------|--------------|-----------------|
| Acceptance criteria ambiguous | Pause task | Yes |
| Scope exceeds roadmap task | Escalate P1; do not expand | Yes |
| Cross-repo dependency in milestone | Lead reorders | No |
| Test failure (first cycle) | Fix and re-run | No |
| Gate failure after 2 fix cycles | P1 escalation | Yes |
| In-scope PR, all gates pass | lead-engineer merges | No |
| Security / secrets / data leak | Stop, P0 | Yes |
| Major roadmap change | Planner proposes, waits | Yes |
| Cloud env FAIL (single repo) | Stop all, P0 | Yes (dashboard) |
| Values trade-off (npm in core, open API, UI/logic mix) | Stop, P1 | Yes |

**Default:** agents decide implementation detail, test fixes, doc wording. **Stop and ask** on scope, architecture, security, and plan changes.

## PR conventions

See **`docs/agent-ops/AUTONOMOUS_EXECUTION.md`** for merge gates and PR template.

- **Branch:** `cursor/<descriptive-name>-24b5` (cloud agents) or `m[N]-<slug>` (milestone work)
- **Title:** `[M[N]-T[N]] Short description`
- **Body:** task ID, acceptance checklist, `./scripts/ci-check.sh` evidence, qa-verifier + design-reviewer sign-off, **values check**
- **Merge:** lead-engineer when gates pass — **do not** request human review for routine work
- **One PR per repo** for cross-repo work; link siblings in description
- **product-planner** PRs are roadmap/docs only — not mixed with implementation

## ROADMAP sync

- **product-planner** updates task status after merged PRs or milestone reports
- **lead-engineer** reports status; does not change roadmap scope
- Checkbox updates in planner PRs or dedicated status-sync PRs — not drive-by in feature PRs

## Task IDs

Format: `M[N]-T[N]` (e.g. `M2-T3`). Used in handoffs, PR bodies, and digests.
