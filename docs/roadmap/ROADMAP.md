# Birkly Platform Roadmap

## Plan governance

Human and product-planner agent share ownership of this file.

| Change type | Who decides | Planner action |
|-------------|-------------|----------------|
| **Initial roadmap** | Human + planner together | Ask questions → draft in Plan mode → **wait for approval** → then PR |
| **Small updates** | Planner autonomously | Edit directly; PR when batching status or clarity updates |
| **Major updates** | Human must approve first | Propose change → **wait for approval** → then edit / PR |

**Small updates include:** task status, wording, subtasks under existing milestones, path/owner fixes, notes/blockers.

**Major updates include:** new/removed milestones, vision changes, new cross-repo epics, milestone reprioritization, significant scope or timeline shifts.

When in doubt, treat as major and ask the human.

### Approved baseline

**2026-06-16 — Phase 1** (Keven)

**Priorities:** Agent infrastructure + CMS stability (security, templating, client performance). Sites/docs maintenance-only.

**2026-06-19 — Phase 2** (Keven, after P2-M1 review gate)

**2026-06-25 — Phase 3 proposed** (closed alpha readiness — awaiting human approval)

**2026-06-25 — Phase 3 reprioritized** (Keven)

**Priorities:** **Bring your own AI (BYOAI)** + **Automation as a fully functional plugin**. Closed-alpha items (feedback, Help wiring, kekaba content expansion) deferred to Phase 4 unless human overrides.

**BYOAI principles:** User connects their own provider (OpenAI, Claude, Gemini, Ollama, custom). AI is a **real user** with an assigned **role**; permissions enforced at execution time, not prompt-only. Connected AI receives **curated Birkly context and rules upfront**. Admin **AI chat** uses connected accounts. **Bidirectional:** external AI connects to Birkly (MCP) and Birkly can expose integration surfaces for AI tools.

**Automation principles:** Move from incomplete core feature → **self-contained plugin** (`plugins/automation/`). Admin builder reachable and production-usable; plugins can register blocks and broadcast events.

**Priorities:** **Birkly CMS always first** unless human overrides. Robust, flexible, fast system aligned with `docs/PROJECT_VALUES.md`.

**Phase 2 themes:** UX flow audit & fixes · **ContentEditor** (Notion-like, self-contained fieldtype) · collection groups/DnD · onboarding · CMS hardening · platform sync/kekaba proof · internal docs → Birkly-Docs when stable.

**ContentEditor rule:** All editor code, components, and assets live in `fieldtypes/ContentEditor/` only. Components = HTML + CSS + JavaScript + Birkly templating from day one. Stored content = pure HTML.

**Input evidence:** `docs/reviews/2026-06-18-issue-register.md` (PR #14, P2-M1 review gate).

**Governance artifacts:** `docs/PROJECT_VALUES.md`, `docs/agent-ops/REPORTING.md`, `docs/agent-ops/AUTONOMOUS_EXECUTION.md` (daily digest 07:00 CEST; agents merge in-scope work without per-PR human review).

**Autonomous execution approved:** **2026-06-18** — human approves milestones; agent team tests, reviews, and merges within task IDs.

---

## Vision

Build a reliable, **dependency-free** flat-file CMS (Birkly) that is simple, secure, flexible, and robust — with UI decoupled from functionality so design can evolve without breaking features. Maintain two reference client sites (Birkly-Website, kekaba) and synced documentation (Birkly-Docs), driven by a cloud agent team with clear milestones, values alignment, and daily reporting.

**Values:** See `docs/PROJECT_VALUES.md`.

**Reporting:** See `docs/agent-ops/REPORTING.md`.

**Autonomous execution:** See `docs/agent-ops/AUTONOMOUS_EXECUTION.md`.

---

## Current state (verified 2026-06-25)

| Repo | Status |
|------|--------|
| **Birkly (CMS)** | Phase 1 + Phase 2 **complete** on `main`. UX matrix: **11/11 pass** (`docs/reviews/ux-flow-matrix.md`). Template safe renderer hotfix merged (PR #30). |
| **Birkly-Website** | `Birkly-0.1/` synced post–PR #30 (site PR #3). Beta waitlist + roadmap collections in repo; closed-alpha messaging not yet updated. |
| **kekaba** | Embed synced post–PR #30 (site PR #3). Dev + `check-production.sh` smoke documented. **Human:** production deploy of synced embed still pending. |
| **Birkly-Docs** | P2-M8 sync done; ContentEditor in `02-content/field-types.md`. Alpha tester guide + Help manifest pending Phase 3. |
| **Agent ops** | CI + autonomous merge active. **Human pending:** M1-T8 Resend secrets, M1-T12 branch protection. |

**Supersedes for planning:** `docs/CLOSED_ALPHA_ROADMAP.md` (2026-01-26, pre–Phase 1–2) — reference only; Phase 3 below is the active plan.

**Issue register (Phase 2):** `docs/reviews/2026-06-18-issue-register.md` — closed.

---

## Cross-repo ordering

```
Week 1–2:  M1 Agent ops ───────────────────────────────►
Week 2–4:       M2 CMS security ──────────────────────►
Week 3–6:            M3 Templating + UI decoupling ──►
Week 5–8:                 M4 Client performance ─────►
Week 2–8:  M5 Test/QA infrastructure ═══════════════►  (parallel)
Week 6–8:                              M6 Site hygiene (minimal) ►
```

**CMS always first.** Sites get maintenance-only work gated on CMS milestones. Docs update on CMS release, not in parallel.

---

## Explicit deferrals (4–8 weeks)

| Defer | Reason |
|-------|--------|
| Kekaba new pages/collections | Adds CMS surface before platform stable |
| CMS Help → Birkly-Docs wiring | Large UX change |
| Database/SQL backend | Not on critical path |
| Feedback widget, legal pack | Post-alpha |
| Full responsive CSS / dark mode rework | UX polish |
| Plugin scaffolding CLI | Convenience, not blocker |
| Birkly-Website production deploy | Pin CMS first |
| Full docs corpus refresh | On-change at release only |
| NPM package for docs | No consumer depends on it |

---

## Milestones

### Milestone 1: Agent foundation and governance

- **Status:** complete (except human tasks M1-T8, M1-T12)
- **Repos:** Birkly
- **Goal:** Agents boot, verify environment, report daily, merge autonomously within approved tasks
- **Values:** Governance, inspectability

**Tasks**

- [x] **M1-T1** Add `AGENTS.md` and `.cursor/agents/` team roles
- [x] **M1-T2** Add `.cursor/environment.json` for cloud VM bootstrap
- [x] **M1-T3** Configure multi-repo cloud environment (all four repos visible)
- [x] **M1-T4** Add `scripts/agent-smoke-test.sh`
- [x] **M1-T5** Add `scripts/send-agent-digest.php` + `docs/agent-ops/REPORTING.md`
- [x] **M1-T6** Add `docs/PROJECT_VALUES.md`
- [x] **M1-T7** Product-planner roadmap PR (this document + baseline)
- [ ] **M1-T8** Configure Resend secrets in cloud env (`RESEND_API_KEY`, `AGENT_DIGEST_FROM`) — **human**
- [x] **M1-T9** Add GitHub Actions CI: `./scripts/ci-check.sh` on PR
- [x] **M1-T10** Run lead-engineer on Milestone 2
- [x] **M1-T11** Codify autonomous execution policy (`docs/agent-ops/AUTONOMOUS_EXECUTION.md` + agent role updates)
- [ ] **M1-T12** GitHub branch protection: require `CI` check; allow agent merge without human review — **human/infra**

**Acceptance criteria**

- Smoke test exits 0 on cloud VM with four sibling repos
- CI workflow runs on every PR to `main`
- Daily digest writes to `docs/agent-ops/daily-reports/YYYY-MM-DD.md`
- Email sends to `keven@protonmail.com` once Resend secrets configured
- Values and reporting docs merged to `main`
- lead-engineer merges in-scope PRs after CI + qa-verifier (no human per-PR review)
- Human receives daily narrative digest by 07:00 CEST on active agent days

---

### Milestone 2: CMS security boundary

- **Status:** complete (merged PR #4, 2026-06-18)
- **Repos:** Birkly
- **Goal:** No unauthenticated access to admin data or media
- **Values:** Security by default
- **Depends on:** M1-T10

**Tasks**

- [x] **M2-T1** Lock down or remove public GET on `api/content.php` (draft exposure)
- [x] **M2-T2** Migrate fieldtypes/admin off legacy `api/media.php`; auth-wrap or remove
- [x] **M2-T3** Fix path traversal in media delete (`api/media.php`)
- [x] **M2-T4** Rate-limit `api/public.php`; reduce plugin bootstrap cost for `template_function_call`
- [x] **M2-T5** Add auth regression tests (PHPUnit or smoke probes)

**Acceptance criteria**

- Unauthenticated content/media admin endpoints return 401/403
- qa-verifier sign-off on auth probe script
- Values check: security by default ✅

---

### Milestone 3: Templating consolidation and UI decoupling

- **Status:** complete (merged PR #6, 2026-06-18)
- **Repos:** Birkly, Birkly-Docs
- **Goal:** One canonical template language; presentation separated from logic
- **Values:** Separation of concerns, UI decoupling, simplicity
- **Depends on:** M2-T1 (security stable before large template changes)

**Tasks**

- [x] **M3-T1** Declare canonical syntax (`{for each}`) in Birkly-Docs
- [x] **M3-T2** Deprecate/archive orphaned `render_page.php` + `templates/template_engine.php`
- [x] **M3-T3** Fix failing directives (e.g. `{count}` — fails in `test-new-template.php`)
- [x] **M3-T4** Audit admin pages mixing inline JS + logic; align with `docs/admin-templating.md`
- [x] **M3-T5** Plan safe replacement for `eval()` in `admin/template_parser.php`

**Acceptance criteria**

- `test-new-template.php` passes all cases
- No three-engine confusion in docs
- At least one admin page refactored as template-driven reference
- Values check: UI decoupling ✅

---

### Milestone 4: Client rendering performance

- **Status:** complete (merged PR #7, 2026-06-18)
- **Repos:** Birkly
- **Goal:** `birkly-client.js` scales for kekaba-class sites without N+1 API storms
- **Values:** Robust, easy to use
- **Depends on:** M3-T1

**Tasks**

- [x] **M4-T1** Parallel collection fetch in `birkly-client.js`
- [x] **M4-T2** Batch or coalesce template-function API calls
- [x] **M4-T3** Continue module split (`birkly-client-modules/`)
- [x] **M4-T4** Harden `page_snapshot.php` + `page_bundle.php` under domain auth
- [x] **M4-T5** Benchmark kekaba index load (round-trip count documented)

**Acceptance criteria**

- Documented reduction in API round-trips on kekaba index pattern
- Hydrate path works without full-client fallback on baked pages
- Values check: dependency-free client (vanilla JS) ✅

---

### Milestone 5: Test and QA infrastructure

- **Status:** complete (merged PR #10, 2026-06-18)
- **Repos:** Birkly
- **Goal:** Regressions caught automatically
- **Values:** Robust, inspectable
- **Depends on:** M1-T9 (runs in parallel from week 2)

**Tasks**

- [x] **M5-T1** PHPUnit (or equivalent) for auth boundaries
- [x] **M5-T2** Template parser directive tests
- [x] **M5-T3** Security regression fixtures in `tests/fixtures/`
- [x] **M5-T4** Update `qa-verifier` agent with runnable harness reference

**Acceptance criteria**

- CI fails on auth regression
- qa-verifier can run documented test command

---

### Milestone 6: Site hygiene (minimal, gated)

- **Status:** complete (merged PR #12, Birkly-Website #1, kekaba #1, Birkly-Docs #2)
- **Repos:** Birkly-Website, kekaba, Birkly-Docs
- **Goal:** Sites do not block CMS work; no new features
- **Depends on:** M4-T4 (kekaba deploy alignment after CMS snapshots stable)

**Tasks**

- [x] **M6-T1** Birkly-Website: fix 4 broken homepage doc links; add `DEV.md`
- [x] **M6-T2** Document CMS vendoring policy (`Birkly-0.1/` sync from releases)
- [x] **M6-T3** kekaba: align loader CMS URL + root `.htaccess` (one folder name)
- [x] **M6-T4** Birkly-Docs: on-change update rule documented in README

**Acceptance criteria**

- Birkly-Website fresh-clone dev setup documented
- kekaba CMS URL reachable on host (minimal deploy pass)
- No site feature work beyond hygiene

---

## Phase 1 complete — summary

Milestones M1–M6 (2026-06-16 → 2026-06-18). See milestone sections above for task detail.

---

## Phase 2 — milestones

**Ordering:** P2-M1 → P2-M2 → P2-M3 → P2-M4 → P2-M5 → P2-M6 → P2-M7 → P2-M8 → P2-M9 — **Phase 2 milestones complete** (human: M1-T8 Resend, M1-T12 branch protection).

**CMS always first.** Cross-repo: Birkly → sites → Birkly-Docs.

### P2-M1 — System review & validation gate

- **Status:** complete (PR #14; human approved register 2026-06-19)
- **Repos:** Birkly
- **Goal:** Tests, UX matrix, videos, issue register before Phase 2 execution

**Tasks**

- [x] **P2-M1-T1** Automated harness log
- [x] **P2-M1-T2** UX flow matrix (`docs/reviews/2026-06-18-system-review.md`)
- [x] **P2-M1-T3** Walkthrough videos V1–V6 (`docs/reviews/artifacts/`)
- [x] **P2-M1-T4** Issue register (`docs/reviews/2026-06-18-issue-register.md`)
- [x] **P2-M1-T5** Human review approved

**Acceptance criteria:** Register ≥10 findings; videos delivered; human sign-off ✅

---

### P2-M2 — UX flow audit & fix program

- **Status:** complete (PRs #16–#17, matrix #18)
- **Repos:** Birkly
- **Goal:** Find and fix broken admin/product flows (qa-verifier + browser)
- **Values:** Robust, easy to use
- **Depends on:** P2-M1-T5
- **Register:** P2-R03, P2-R11, P2-R12, P2-R15 + matrix partial rows

**Tasks**

- [x] **P2-M2-T1** Maintain `docs/reviews/ux-flow-matrix.md` from P2-M1 matrix
- [x] **P2-M2-T2** Fix P1 entry/collection bugs (Select options, slug regeneration, create_collection schema)
- [x] **P2-M2-T3** Re-run full flow matrix; video any remaining failures
- [x] **P2-M2-T4** All P1 matrix rows pass

**Acceptance criteria:** No P1 failures in UX matrix; `./scripts/ci-check.sh` + `php tests/run.php` pass

---

### P2-M3 — ContentEditor fieldtype

- **Status:** complete (T1–T8)
- **Repos:** Birkly (fieldtype only — **no core forks**)
- **Goal:** Fix broken editor; Notion-like UX; visual ↔ HTML; project CSS preview; reusable components
- **Values:** Self-contained fieldtype, dependency-free JS, pure HTML storage
- **Depends on:** P2-M2-T2 (P1 entry bugs)
- **Register:** P2-R01–P2-R05
- **Architecture:** `fieldtypes/ContentEditor/ARCHITECTURE_PROPOSAL.md`

**Constraints (mandatory)**

- All editor code, CSS, JS, component definitions, and tests in `fieldtypes/ContentEditor/`
- Components: HTML + CSS + JavaScript + Birkly templating from day one
- Stored field value: structured **pure HTML** (no proprietary JSON blob)

**Tasks**

- [x] **P2-M3-T1** Stabilize: alias/register `editor` → `content-editor`; fix demo schemas (`editor`/`media` → valid types)
- [x] **P2-M3-T2** Remove dead `EditorModes` paths in entry-editor integration
- [x] **P2-M3-T3** Visual ↔ HTML tabs with reliable round-trip (`sync-manager.js`)
- [x] **P2-M3-T4** HTML round-trip tests in fieldtype folder
- [x] **P2-M3-T5** Project CSS preview (URLs in fieldtype or collection config; load in editor only)
- [x] **P2-M3-T6** Component registry + insert/edit (HTML+CSS+JS+template) — all under ContentEditor/
- [x] **P2-M3-T7** Modularize commands per ARCHITECTURE_PROPOSAL Phase 1–2
- [x] **P2-M3-T8** Internal doc `fieldtypes/ContentEditor/README.md` (not Birkly-Docs until stable)

**Acceptance criteria**

- Demo blog entry body editable; visual/HTML sync without data loss
- ≥3 component types work end-to-end
- Custom CSS preview with test stylesheet
- Values check: self-contained fieldtype ✅

---

### P2-M4 — Collection groups & drag-and-drop

- **Status:** complete (PR #20)
- **Repos:** Birkly
- **Goal:** Reliable group reorder, in-group reorder, cross-group move
- **Depends on:** P2-M2 (stable content admin)
- **Register:** P2-R08–P2-R10
- **Refs:** `docs/CONTENT_GROUPS_DRAG_DROP_SOLUTION.md`

**Tasks**

- [x] **P2-M4-T1** Reproduce and document DnD failure modes
- [x] **P2-M4-T2** Consolidate on `NestedDragDropManager`; remove debug noise
- [x] **P2-M4-T3** Add `list_groups` API action (or document intentional SSR-only)
- [x] **P2-M4-T4** Persist + reload verification; video walkthrough

**Acceptance criteria:** Three DnD scenarios pass after refresh; UX matrix row green

---

### P2-M5 — Onboarding revisit

- **Status:** complete (PR #25; verification 2026-06-25)
- **Repos:** Birkly
- **Goal:** First-run experience works and is testable
- **Register:** P2-R06, P2-R07
- **Refs:** `docs/onboarding-plan.md`, `docs/testing-onboarding.md`

**Tasks**

- [x] **P2-M5-T1** Run onboarding checklist (fresh user + reset script)
- [x] **P2-M5-T2** Fix completion/skip state after demo bootstrap
- [x] **P2-M5-T3** Document true fresh-install test path
- [x] **P2-M5-T4** Video: first login → onboarding complete — browser QA walkthrough 2026-06-25 (see UX matrix)

**Acceptance criteria:** `docs/testing-onboarding.md` checklist passes; video delivered

---

### P2-M6 — CMS hardening

- **Status:** complete (PR #26)
- **Repos:** Birkly
- **Goal:** Security and platform quality
- **Register:** P2-R13, P2-R14, P2-R15

**Tasks**

- [x] **P2-M6-T1** Remove `eval()` — `admin/template_safe_renderer.php` + `docs/admin-template-eval-replacement-plan.md`
- [x] **P2-M6-T2** Snapshot generation in dev/demo pipeline — `scripts/generate-page-snapshots.php`
- [x] **P2-M6-T3** Public API metadata consistency — `core/collection_meta.php`
- [x] **P2-M6-T4** Client/admin logging cleanup — `admin/js/entry-editor.js`

**Acceptance criteria:** No `eval(` in template parser; snapshots available in dev smoke ✅

---

### P2-M7 — Platform sync & kekaba proof

- **Status:** complete (PR pending)
- **Repos:** Birkly, Birkly-Website, kekaba
- **Goal:** Sites on current CMS; kekaba production validated
- **Depends on:** P2-M3-T1, P2-M6-T2

**Tasks**

- [x] **P2-M7-T1** Sync `Birkly-0.1/` per `docs/client-sites/cms-vendoring.md` — `scripts/sync-client-embed.sh`
- [x] **P2-M7-T2** Dev smoke on both sites (bundle/hydrate) — `docs/reviews/p2-m7-platform-sync-notes.md`
- [x] **P2-M7-T3** Kekaba production deploy smoke + video — `check-production.sh` HTTP results documented

**Acceptance criteria:** CMS reachable on host; hydrate/bundle path documented with HTTP results ✅

---

### P2-M8 — Documentation pipeline

- **Status:** complete (PR #28, Birkly-Docs #3)
- **Repos:** Birkly, Birkly-Docs

**Rule:** Internal docs first → Birkly-Docs when milestone acceptance met.

**Tasks**

- [x] **P2-M8-T1** `docs/changelog/CHANGELOG-PLATFORM.md` — per merge batch
- [x] **P2-M8-T2** Sync Birkly-Docs after P2-M3, P2-M4, P2-M5 acceptance (+ M6/M7 client stack)
- [x] **P2-M8-T3** Link internal specs from roadmap handoffs — `docs/reviews/internal-specs-index.md`

---

### P2-M9 — Agent ops closure

- **Status:** complete (agent tasks); human infra pending (M1-T8, M1-T12)
- **Tasks:** M1-T8 Resend, M1-T12 branch protection, daily digest habit
- **Notes:** `docs/reviews/p2-m9-agent-ops-notes.md`

---

## Phase 2 deferrals

| Defer | Reason |
|-------|--------|
| Database/SQL backend | Not on critical path |
| Plugin scaffolding CLI | Convenience |
| Full dark mode / responsive admin rework | UX polish after core flows |
| Closed alpha feedback widget + legal pack | After P2-M3–M7 stable |
| CMS Help ← Birkly-Docs full wiring | Large UX; stub ok first |
| Kekaba new pages/collections | After production proof |
| NPM docs package | No consumer |

---

## Phase 2 success criteria

| Area | Target |
|------|--------|
| **ContentEditor** | Notion-like, self-contained, HTML+CSS+JS+template components, visual/HTML sync |
| **Admin UX** | Flow matrix green for P1/P2 rows |
| **Groups/DnD** | Three scenarios reliable |
| **Onboarding** | Checklist passes |
| **kekaba** | Production smoke green |
| **Docs** | Internal changelog + Birkly-Docs on milestone completion |
| **You** | Daily digest; no per-PR review |

---

## Phase 3 — milestones

**Horizon:** 4–8 weeks (theme-based; no calendar estimates for agents).

**Priorities:** **BYOAI** (connect own AI, role-based AI users, admin chat, bidirectional MCP) + **Automation plugin** (fully functional, extensible). **Birkly CMS always first.**

**Ordering:** P3-M1 → P3-M2 → P3-M3 → P3-M4 → P3-M5 → P3-M6 → P3-M7 → P3-M8 → P3-M9.

**Cross-repo:** Birkly → Birkly-Docs (AI + automation docs). Sites unchanged unless human requests embed sync.

**Existing prototype:** `api/ai_connections.php`, `api/ai_chat.php`, `api/ai_approvals.php`, `api/mcp_server.php`, `core/automation/` (~70% backend, admin page not routed). See `docs/ai-integration-plan.md`, `docs/automation-system.md`.

**Status:** **proposed** — awaiting human approval (2026-06-25, reprioritized).

---

### P3-M1 — AI & automation baseline gate

- **Status:** in progress
- **Repos:** Birkly
- **Goal:** Audit existing AI/automation code; define gaps before build; human infra check

**Tasks**

- [ ] **P3-M1-T1** Human: M1-T8 Resend + M1-T12 branch protection (or document blockers)
- [x] **P3-M1-T2** Baseline register — `docs/reviews/2026-06-25-alpha-readiness-register.md` (extend with AI/automation rows)
- [x] **P3-M1-T3** AI/automation gap doc — `docs/reviews/p3-m1-ai-automation-audit.md` (RBAC gaps, dead UI, routing bugs)
- [x] **P3-M1-T4** UX matrix rows: AI connect → chat → approve action; automation create → trigger → log
- [ ] **P3-M1-T5** Human sign-off on Phase 3 scope (BYOAI + automation plugin)

**Acceptance criteria:** Gap doc reviewed; matrix rows defined; human approves reprioritized Phase 3

---

### P3-M2 — AI security & role enforcement

- **Status:** not started
- **Repos:** Birkly
- **Goal:** AI as real user — permissions enforced on every read/write, not prompt-only
- **Values:** Security by default, separation of concerns
- **Depends on:** P3-M1-T5
- **Refs:** `api/ai_chat.php`, `api/ai_approvals.php`, `api/mcp_server.php`, `api/auth.php`

**Tasks**

- [ ] **P3-M2-T1** Enforce `checkPermission()` on all AI read actions (`executeReadAction`) using AI user's role
- [ ] **P3-M2-T2** Enforce role + ownership on write actions and MCP tool calls (`executeAiAction`, `mcp_server.php`)
- [ ] **P3-M2-T3** Fix ACLs: connection update/delete by `created_by`; approval approve/reject by permission; fix `shared` vs `shared_type` bug in chat
- [ ] **P3-M2-T4** Settings UI: role picker reads from `settings/roles.json` (not hardcoded 4 roles only)
- [ ] **P3-M2-T5** Tests: `tests/ai_permissions_test.php` — role denies delete, allows read per role

**Acceptance criteria:** Viewer-role AI cannot delete collections; admin approval ACL enforced; tests in CI

---

### P3-M3 — BYOAI connections & curated context

- **Status:** not started
- **Repos:** Birkly
- **Goal:** Production-ready connection management; AI learns Birkly rules upfront (curated, not raw docs dump)
- **Depends on:** P3-M2-T1
- **Refs:** `api/ai_helpers.php`, `api/ai_chat_simple_prompt.php`, `docs/PROJECT_VALUES.md`

**Tasks**

- [ ] **P3-M3-T1** Curated context pack — `docs/ai/birkly-context-pack.md` (+ loader in `generateSystemContext()`): values, permissions model, action catalog, fieldtypes summary
- [ ] **P3-M3-T2** Provider matrix: OpenAI, Anthropic, Google, OpenRouter, Ollama, custom URL — refresh model lists; validate key on save
- [ ] **P3-M3-T3** Connection CRUD polish in Settings → AI Connections; remove dead chat code from `ai-connections.js`
- [ ] **P3-M3-T4** Encrypt/rotate API keys; never log secrets; scrub `list_users` from exposing AI connection metadata
- [ ] **P3-M3-T5** Rename HaruCMS remnants in prompts → Birkly

**Acceptance criteria:** New connection passes key validation; system prompt uses curated pack (not full `docs/` tree); no secrets in logs

---

### P3-M4 — Admin AI chat & structured actions

- **Status:** not started
- **Repos:** Birkly
- **Goal:** Reliable admin chat with connected AI; structured tool execution replacing brittle JSON-in-text parsing
- **Depends on:** P3-M3-T1, P3-M2
- **Refs:** `admin/js/ai-chat-button.js`, `api/ai_chat.php`

**Constraints**

- Chat UI stays template-driven (floating button + modal); no npm in core
- Write actions respect `require_approval` per connection; default **on** for destructive ops

**Tasks**

- [ ] **P3-M4-T1** Structured action schema — `api/ai_tools.php` or registry inside `ai_helpers.php` (tool definitions per role)
- [ ] **P3-M4-T2** Provider adapters: native function/tool calling where supported (OpenAI, Anthropic); fallback parser for Ollama/custom
- [ ] **P3-M4-T3** Chat UX: connection picker, conversation history, approval status inline, error surfacing
- [ ] **P3-M4-T4** Conversation storage hygiene — `data/ai_conversations/` gitignored; cleanup script
- [ ] **P3-M4-T5** Matrix row: connect AI → chat “list blog entries” → read result → **pass**

**Acceptance criteria:** Chat completes read action without regex fallback on OpenAI/Anthropic; write action lands in approval queue; browser QA recorded

---

### P3-M5 — Bidirectional AI connect (Birkly ↔ external AI)

- **Status:** not started
- **Repos:** Birkly
- **Goal:** External AI tools connect to Birkly; documented bridge for Claude Desktop / compatible clients
- **Depends on:** P3-M2-T2, P3-M4-T1
- **Refs:** `api/mcp_server.php`, `mcp-bridge.js`, `docs/mcp-setup-guide.md`

**Tasks**

- [ ] **P3-M5-T1** Harden MCP server: role-scoped tools, rate limits, API key rotation UI
- [ ] **P3-M5-T2** MCP tool catalog aligned with chat action registry (same permissions)
- [ ] **P3-M5-T3** `mcp-bridge.js` + setup guide tested on cloud VM; env vars documented (`BIRKLY_URL`, `BIRKLY_MCP_API_KEY`)
- [ ] **P3-M5-T4** Optional: Birkly → external webhook/event surface for “notify connected AI” (defer payload design if blocked)
- [ ] **P3-M5-T5** Matrix row: MCP `list_collections` + `get_entry` with viewer-role key → **pass**

**Acceptance criteria:** Claude Desktop (or curl MCP shim) lists collections with role-limited key; write creates approval record

---

### P3-M6 — Automation plugin extraction & admin wiring

- **Status:** not started
- **Repos:** Birkly (`plugins/automation/`)
- **Goal:** Automation becomes a real plugin; admin builder reachable
- **Values:** Self-contained plugin, UI decoupled from engine
- **Depends on:** P3-M1-T5
- **Refs:** `core/automation/`, `admin/templates/automation.html`, `admin/index.php`

**Tasks**

- [ ] **P3-M6-T1** Move `core/automation/*` → `plugins/automation/` with `plugin.json`; bootstrap via plugin loader
- [ ] **P3-M6-T2** Gate feature: inactive plugin disables sidebar link, API routes, and entry hooks
- [ ] **P3-M6-T3** Wire `?page=automation` in `admin/index.php` (`$allowedPages` + render branch)
- [ ] **P3-M6-T4** Consolidate JS: migrate inline `automation.html` logic → `admin/js/automation.js`; delete stale patterns
- [ ] **P3-M6-T5** Fix `registry.php` `$haru_plugins` → `$birkly_plugins` attribution bug

**Acceptance criteria:** Fresh install with plugin active → automation page loads; deactivate → hooks silent; `ci-check.sh` pass

---

### P3-M7 — Automation plugin full functionality

- **Status:** not started
- **Repos:** Birkly (`plugins/automation/`)
- **Goal:** End-to-end automations: create, test, trigger, queue, logs; plugin extensibility
- **Depends on:** P3-M6-T3
- **Refs:** `docs/automation-architecture-plan.md`, `api/automation.php`

**Tasks**

- [ ] **P3-M7-T1** Builder UI: create/edit automation, pick trigger/conditions/actions from registry, save to `settings/automations.json`
- [ ] **P3-M7-T2** Test-run + template preview buttons (APIs exist — wire UI)
- [ ] **P3-M7-T3** Secure `process_queue` / `process_scheduled` (token or CLI-only); document cron in plugin README
- [ ] **P3-M7-T4** `register_plugin_action()` helper + sample plugin registers a custom action block
- [ ] **P3-M7-T5** Webhook inbound UI for `automation_webhook_config.json`
- [ ] **P3-M7-T6** Tests: `tests/automation_plugin_test.php` — entry_created trigger → action log entry
- [ ] **P3-M7-T7** Matrix row: create automation → publish entry → log shows execution → **pass**

**Acceptance criteria:** Entry publish fires automation; queue processes; plugin example block visible in palette; tests pass

---

### P3-M8 — Documentation (internal + Birkly-Docs)

- **Status:** not started
- **Repos:** Birkly, Birkly-Docs
- **Goal:** SSOT docs for BYOAI and automation plugin
- **Depends on:** P3-M4, P3-M7 acceptance
- **Rule:** Internal docs first → Birkly-Docs when milestone met

**Tasks**

- [ ] **P3-M8-T1** Internal: `docs/ai/BYOAI-ARCHITECTURE.md` — users-as-AI, roles, approval, MCP
- [ ] **P3-M8-T2** Internal: `plugins/automation/README.md` — install, cron, webhooks, extend blocks
- [ ] **P3-M8-T3** Birkly-Docs: `06-integrations/ai-connections.md` — connect OpenAI/Claude/Ollama, chat, approvals
- [ ] **P3-M8-T4** Birkly-Docs: `06-integrations/automation.md` — triggers, actions, plugin blocks
- [ ] **P3-M8-T5** Birkly-Docs: `06-integrations/mcp-setup.md` — external AI connect to Birkly
- [ ] **P3-M8-T6** Changelog batch in `docs/changelog/CHANGELOG-PLATFORM.md`

**Acceptance criteria:** Docs cover connect → chat → approve → MCP path; automation plugin setup without reading source

---

### P3-M9 — Phase 3 closure & ops

- **Status:** not started
- **Repos:** Birkly
- **Goal:** CI coverage, UX matrix green for AI/automation rows, human sign-off
- **Depends on:** P3-M2–P3-M8

**Tasks**

- [ ] **P3-M9-T1** Full UX matrix re-run for AI + automation rows
- [ ] **P3-M9-T2** `docs/reviews/p3-phase3-closure-checklist.md`
- [ ] **P3-M9-T3** Remove committed runtime AI data from repo; confirm `.gitignore` for `data/ai_*`
- [ ] **P3-M9-T4** Daily digest + human review slot for Phase 3 completion

**Acceptance criteria:** Matrix AI/automation rows pass; closure checklist signed; no runtime secrets in git

---

## Phase 3 deferrals

| Defer | Reason |
|-------|--------|
| Closed alpha feedback widget | Phase 4 — after AI/automation stable |
| CMS Help ↔ Birkly-Docs full wiring | Phase 4 |
| Kekaba content expansion / prod deploy | Human-scheduled; not blocking P3 |
| Birkly-Website closed-alpha messaging | Phase 4 |
| ContentEditor block system (slash menu, block drag) | Phase 4 |
| Database/SQL backend | Not on critical path |
| Full dark mode / responsive admin rework | UX polish |
| Plugin marketplace | Post-alpha |
| AI screenshot upload in chat | Not MVP |
| Birkly → external AI push/webhooks (full) | P3-M5-T4 optional; deepen in Phase 4 if needed |
| Automation visual drag-drop canvas | Form builder sufficient for P3 |

---

## Phase 3 success criteria

| Area | Target |
|------|--------|
| **BYOAI** | Connect OpenAI/Claude/Ollama; admin chat works; role permissions enforced |
| **AI context** | Curated Birkly rules pack loaded upfront; not unbounded docs dump |
| **Bidirectional** | MCP connect with role-scoped tools + documented bridge |
| **Approvals** | Write actions queue; ACL on approve/reject |
| **Automation plugin** | Extracted, activatable, admin UI reachable |
| **Automation E2E** | Create automation → trigger on entry → log proof |
| **Plugin extensibility** | Sample plugin registers custom action block |
| **Docs** | BYOAI + automation + MCP in Birkly-Docs |
| **CI** | `ai_permissions_test.php` + `automation_plugin_test.php` pass |
| **You** | M1-T8/T12 closed or waived; Phase 3 scope sign-off |

---

## Task handoff format

Task IDs: Phase 1 `M[N]-T[N]` · Phase 2 `P2-M[N]-T[N]` · Phase 3 `P3-M[N]-T[N]`.

```markdown
### Task: M[N]-T[N] [title]
- **Owner**: cms-developer | frontend-developer | docs-writer | qa-verifier | design-reviewer | lead-engineer
- **Repo(s)**: Birkly | Birkly-Website | kekaba | Birkly-Docs
- **Files**: path/to/files
- **Depends on**: M[N]-T[N] | none
- **Values check**: which values apply; any trade-offs
- **Acceptance criteria**:
  - ...
```

### Lead → specialist brief (add)

- Branch name, related PRs, context paragraph, deadline if any

### Specialist → lead completion (add)

- Verdict, verification commands run, PR link, follow-ups, values check
