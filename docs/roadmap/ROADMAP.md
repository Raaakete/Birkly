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

**2026-06-25 — Phase 3 approved** (Keven)

**Priorities:** **BYOAI** + **Automation plugin** (deactivatable, like any other plugin) + **User plugin hardening** + **closed-alpha essentials** (feedback, Help, ContentEditor blocks, Birkly-Website). **Kekaba content/prod deploy deferred.**

**Human decisions (locked):**

| Decision | Choice |
|----------|--------|
| Automation packaging | Deactivatable plugin (`plugins/automation/`), same as other plugins |
| New AI connections | `require_approval` **default true** |
| MCP keys | **Per AI connection** (one MCP key scoped to that connection's role) |

**BYOAI principles:** User connects their own provider (OpenAI, Claude, Gemini, Ollama, custom). AI is a **real user** with an assigned **role**; permissions enforced at execution time. Curated Birkly context upfront. Admin AI chat. Bidirectional via MCP (per-connection keys).

**Automation principles:** Extract `core/automation/` → `plugins/automation/`; deactivate = no hooks, no sidebar, no API.

**User plugin principles:** Review, harden, fix `plugins/user_management/` (~75% built; admin wiring broken, permissions not enforced on writes).

**Autonomous execution:** Human approves **Phase 3 milestone block once** (this PR). Lead-engineer executes P3-M1→P3-M15 without per-step human approval. Human notified when Phase 3 complete (digest + email if M1-T8 configured) with videos and examples.

**Phase 2 themes:** (complete) UX flows · ContentEditor · groups/DnD · onboarding · hardening · platform sync.

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
| **Birkly (CMS)** | Phase 1 + Phase 2 + **Phase 3 complete** on `main`. UX matrix: **22/22 pass** (`docs/reviews/ux-flow-matrix.md`). CI: 21 test suites. |
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

**Priorities:** BYOAI · Automation plugin · User plugin · closed-alpha essentials. **CMS always first.** **Only kekaba deferred** (content expansion + prod deploy).

**Ordering:** P3-M1 → … → P3-M15 (see below). Cross-repo: Birkly → Birkly-Docs → Birkly-Website.

**Status:** **approved** (2026-06-25, Keven). Lead-engineer executes autonomously per `docs/reviews/P3-lead-engineer-handoff.md`.

**Existing prototype:** AI APIs + floating chat (~70%, RBAC weak); `core/automation/` (~88% backend, admin not routed); `plugins/user_management/` (~75%, admin orphaned).

---

### P3-M1 — Baseline gate

- **Status:** complete (human approved 2026-06-25)
- **Repos:** Birkly

**Tasks**

- [x] **P3-M1-T1** Human: M1-T8 Resend + M1-T12 branch protection (ongoing; document blockers)
- [x] **P3-M1-T2** Readiness register — `docs/reviews/2026-06-25-alpha-readiness-register.md`
- [x] **P3-M1-T3** AI/automation audit — `docs/reviews/p3-m1-ai-automation-audit.md`
- [x] **P3-M1-T4** UX matrix extended (AI, automation, alpha rows)
- [x] **P3-M1-T5** Human sign-off — Phase 3 scope approved (BYOAI, automation plugin, user plugin, closed-alpha essentials)

---

### P3-M2 — AI security & role enforcement

- **Status:** complete
- **Repos:** Birkly
- **Goal:** AI permissions enforced at execution; approval ACLs hardened
- **Depends on:** P3-M1-T5

**Tasks**

- [x] **P3-M2-T1** `checkPermission()` on all AI read/write actions and MCP tools
- [x] **P3-M2-T2** ACLs: connection owner, approval permissions, fix `shared`/`shared_type` bug
- [x] **P3-M2-T3** Role picker from `settings/roles.json`; **new connections default `require_approval: true`**
- [x] **P3-M2-T4** `tests/ai_permissions_test.php` in CI

---

### P3-M3 — BYOAI connections & curated context

- **Status:** complete
- **Repos:** Birkly
- **Depends on:** P3-M2-T1

**Tasks**

- [x] **P3-M3-T1** Curated pack `docs/ai/birkly-context-pack.md` + loader (not full docs dump)
- [x] **P3-M3-T2** Provider matrix + key validation on save
- [x] **P3-M3-T3** Settings AI Connections polish; remove dead `ai-connections.js` chat code
- [x] **P3-M3-T4** Secrets hygiene; scrub AI metadata from `list_users`
- [x] **P3-M3-T5** HaruCMS → Birkly in prompts

---

### P3-M4 — Admin AI chat & structured actions

- **Status:** complete
- **Repos:** Birkly
- **Depends on:** P3-M3, P3-M2

**Tasks**

- [x] **P3-M4-T1** Structured tool registry (role-scoped)
- [x] **P3-M4-T2** Native tool calling (OpenAI/Anthropic); fallback for Ollama/custom
- [x] **P3-M4-T3** Chat UX: history, approval status, errors
- [x] **P3-M4-T4** `data/ai_*` gitignored; cleanup script
- [x] **P3-M4-T5** Matrix + video: connect → chat read action → **pass**

---

### P3-M5 — Bidirectional MCP (per-connection keys)

- **Status:** complete
- **Repos:** Birkly
- **Depends on:** P3-M2-T2, P3-M4-T1

**Tasks**

- [x] **P3-M5-T1** **MCP API key per AI connection** (scoped to connection role); rotation UI
- [x] **P3-M5-T2** MCP tools = same registry/permissions as chat
- [x] **P3-M5-T3** `mcp-bridge.js` + guide tested; env `BIRKLY_URL`, per-connection key
- [x] **P3-M5-T4** Matrix + video: MCP list_collections with viewer key → **pass**

---

### P3-M6 — Automation plugin extraction

- **Status:** complete
- **Repos:** Birkly (`plugins/automation/`)
- **Goal:** Deactivatable plugin like any other — inactive = no hooks/API/sidebar

**Tasks**

- [x] **P3-M6-T1** Move `core/automation/*` → `plugins/automation/` + `plugin.json`
- [x] **P3-M6-T2** Deactivate gates sidebar, API routes, entry hooks
- [x] **P3-M6-T3** Wire `?page=automation` in `admin/index.php`
- [x] **P3-M6-T4** JS consolidation (`automation.html` → `automation.js`)
- [x] **P3-M6-T5** Fix `$haru_plugins` → `$birkly_plugins` in registry

---

### P3-M7 — Automation plugin full functionality

- **Status:** complete
- **Repos:** Birkly
- **Depends on:** P3-M6-T3

**Tasks**

- [x] **P3-M7-T1** Builder UI: trigger/conditions/actions
- [x] **P3-M7-T2** Test-run + template preview UI
- [x] **P3-M7-T3** Secure cron (`process_queue` token/CLI); plugin README
- [x] **P3-M7-T4** `register_plugin_action()` + sample custom block
- [x] **P3-M7-T5** Webhook inbound UI
- [x] **P3-M7-T6** `tests/automation_plugin_test.php`
- [x] **P3-M7-T7** Matrix + video: automation trigger → log → **pass**

---

### P3-M8 — User plugin review, harden & fix

- **Status:** complete
- **Repos:** Birkly (`plugins/user_management/`)
- **Goal:** Production-ready site users; admin UI reachable; permissions enforced
- **Refs:** `docs/USER_PLUGIN_DESIGN.md`, `docs/reviews/p3-m8-user-plugin-audit.md` (create in M8-T1)

**Tasks**

- [x] **P3-M8-T1** Audit doc + register findings (admin routing, role mismatch, dead hooks)
- [x] **P3-M8-T2** Wire plugin admin pages via `$birkly_admin_pages` (`site_users` not `users` collision)
- [x] **P3-M8-T3** Unify permission model: site roles/tiers → `userCan()`; wire `EntryPermissions` edit/delete
- [x] **P3-M8-T4** Fire entry create/delete hooks from `admin_entries.php` + `public_submit.php`; rate-limit registration
- [x] **P3-M8-T5** Complete verification email path; plugin capabilities on admin APIs
- [x] **P3-M8-T6** Automation bridge: `user_registered`, `user_login` triggers from plugin
- [x] **P3-M8-T7** `tests/user_plugin_permissions_test.php` + matrix row → **pass**
- [x] **P3-M8-T8** Video: site user register → login → create entry (permission enforced)

---

### P3-M9 — Production hardening

- **Status:** complete
- **Repos:** Birkly

**Tasks**

- [x] **P3-M9-T1** Update system smoke harness
- [x] **P3-M9-T2** Backup & restore smoke — `docs/reviews/p3-m9-backup-notes.md`
- [x] **P3-M9-T3** Targeted security backlog (session regen, upload MIME, error sanitization)
- [x] **P3-M9-T4** Matrix rows: update + backup → **pass**

---

### P3-M10 — Alpha feedback system

- **Status:** complete
- **Repos:** Birkly

**Tasks**

- [x] **P3-M10-T1** Feedback modal + floating/header entry
- [x] **P3-M10-T2** `api/feedback.php` — auth, CSRF, rate limit, flat-file storage
- [x] **P3-M10-T3** Admin feedback list + status workflow
- [x] **P3-M10-T4** Privacy notice (links to legal on Birkly-Website)
- [x] **P3-M10-T5** Matrix + video: submit → admin triage → **pass**

---

### P3-M11 — CMS Help ↔ Birkly-Docs (v1)

- **Status:** complete
- **Repos:** Birkly, Birkly-Docs

**Tasks**

- [x] **P3-M11-T1** Doc manifest (Help → Birkly-Docs paths incl. AI, automation, user plugin)
- [x] **P3-M11-T2** Help renderer v1 (static snapshot or configured base URL)
- [x] **P3-M11-T3** Client-side search over manifest
- [x] **P3-M11-T4** Contextual “?” on dashboard, entry editor, settings
- [x] **P3-M11-T5** Matrix + video: Help search → **pass**

---

### P3-M12 — ContentEditor block system

- **Status:** complete
- **Repos:** Birkly (`fieldtypes/ContentEditor/` only)

**Tasks**

- [x] **P3-M12-T1** `blocks/block-manager.js` + `block-registry.js`
- [x] **P3-M12-T2** Migrate paragraph/heading/list to blocks; preserve visual/HTML round-trip
- [x] **P3-M12-T3** ≥2 new components (table, image embed, or divider)
- [x] **P3-M12-T4** Extend `html_roundtrip_test.php` + browser QA
- [x] **P3-M12-T5** Video: insert block → save → reload → **pass**

---

### P3-M13 — Birkly-Website closed alpha surface

- **Status:** complete
- **Repos:** Birkly-Website, Birkly-Docs

**Tasks**

- [x] **P3-M13-T1** Closed alpha copy in beta/roadmap collections
- [x] **P3-M13-T2** `beta_legal` terms + privacy (feedback data practices)
- [x] **P3-M13-T3** Sync `Birkly-0.1/`; dev smoke waitlist + roadmap
- [x] **P3-M13-T4** `DEV.md` alpha preview instructions

---

### P3-M14 — Documentation pack

- **Status:** complete
- **Repos:** Birkly, Birkly-Docs

**Tasks**

- [x] **P3-M14-T1** Internal: `docs/ai/BYOAI-ARCHITECTURE.md`
- [x] **P3-M14-T2** `plugins/automation/README.md` + user plugin ops doc
- [x] **P3-M14-T3** Birkly-Docs: AI connections, MCP, automation, user plugin, closed-alpha guide
- [x] **P3-M14-T4** Birkly-Docs: `02-content/content-editor.md` (blocks)
- [x] **P3-M14-T5** Changelog batch; verify Help manifest links

---

### P3-M15 — Phase 3 closure & human notification

- **Status:** complete
- **Repos:** Birkly (+ cross-repo verification)
- **Depends on:** P3-M2–P3-M14

**Tasks**

- [x] **P3-M15-T1** Full UX matrix re-run — **all rows pass**
- [x] **P3-M15-T2** Walkthrough videos V7–V14 in `docs/reviews/artifacts/` (AI, MCP, automation, user plugin, feedback, Help, ContentEditor)
- [x] **P3-M15-T3** `docs/reviews/p3-phase3-closure-checklist.md` + examples index
- [x] **P3-M15-T4** Mark Phase 3 complete in roadmap; supersede `CLOSED_ALPHA_ROADMAP.md` pointer
- [x] **P3-M15-T5** **Notify human:** daily digest + explicit completion section; email via `send-agent-digest.php` when M1-T8 configured — subject **"Phase 3 complete — review videos"**

**Acceptance criteria:** CI 15+ suites pass; matrix green; videos delivered; human notified once at end (not per PR)

---

## Phase 3 deferrals

| Defer | Reason |
|-------|--------|
| **Kekaba** new pages/collections + production embed deploy | Human-scheduled; explicit defer per Keven |
| Database/SQL backend | Not on critical path |
| Full dark mode / responsive admin rework | UX polish |
| Plugin marketplace | Post-alpha |
| AI screenshot upload in chat | Not MVP |
| Automation visual drag-drop canvas | Form builder sufficient |
| Birkly-Website production deploy | Repo + dev smoke sufficient |

---

## Phase 3 success criteria

| Area | Target |
|------|--------|
| **BYOAI** | Connect providers; chat; role enforcement; default approval on |
| **MCP** | Per-connection keys; external connect documented + video |
| **Automation** | Deactivatable plugin; E2E trigger → log |
| **User plugin** | Admin wired; permissions on writes; tests pass |
| **Closed alpha** | Feedback, Help, ContentEditor blocks, Birkly-Website copy |
| **Hardening** | Update + backup smokes |
| **Docs** | Birkly-Docs integration guides |
| **Delivery** | Videos V7–V14; matrix green; **one human notification at end** |

---

## Phase 4 — milestones

**Approved:** 2026-06-25 (Keven). **Autonomous execution:** lead-engineer executes P4-M1→P4-M11 without per-milestone human approval. **Human notified once** at P4-M11-T5.

**Theme:** MCP inbound decoupling + alpha UX hardening + BYOAI polish + OAuth discovery (no ship without P5).

**Locked decisions**

| Decision | Choice |
|----------|--------|
| BYOAI outbound | Unchanged — user API keys; Birkly calls provider |
| Approval default | `require_approval` **true**; role enforcement at execution |
| MCP inbound | Standalone endpoint profiles in `settings/mcp_endpoints.json` + `core/mcp_endpoints.php` |
| Legacy MCP keys | 90-day dual-resolve after `migrate-mcp-endpoints.php` |
| Settings UX | Dedicated **MCP Connections** tab; empty state; no auto-generated keys on install |
| Multi-profile MCP | Each endpoint: name, role, key, `require_approval` |
| OAuth | Spike doc only (P4-M8); **no production OAuth** unless separate P5 approval |
| Kekaba | **Deferred** |

**Two channels:** Outbound admin chat (`api/ai_chat.php` → provider) · Inbound MCP (`api/mcp_server.php` → endpoint profile). Shared: `core/ai_permissions.php`, `core/ai_tool_registry.php`, approval queue.

**Ordering:** P4-M2 → … → P4-M11. Cross-repo: Birkly → Birkly-Docs → Birkly-Website.

---

### P4-M1 — Baseline gate

- **Status:** complete
- **Repos:** Birkly

**Tasks**

- [x] **P4-M1-T1** Readiness register — `docs/reviews/2026-06-25-p4-readiness-register.md`
- [x] **P4-M1-T2** Lead handoff — `docs/reviews/P4-lead-engineer-handoff.md`
- [x] **P4-M1-T3** UX matrix P4 rows drafted
- [x] **P4-M1-T4** Human sign-off — Phase 4 scope approved

---

### P4-M2 — Alpha UX hardening

- **Status:** complete
- **Repos:** Birkly
- **Goal:** Regression tests for onboarding E2E, admin routing HTTP, AI stale connection

**Tasks**

- [x] **P4-M2-T1** `tests/onboarding_e2e_test.php` — first user → CSRF → project save → skip
- [x] **P4-M2-T2** `tests/admin_routing_http_test.php` — core pages render correct templates
- [x] **P4-M2-T3** `tests/ai_chat_connection_test.php` — delete removes connection from list/chat
- [x] **P4-M2-T4** UX matrix rows updated; CI 26+ suites pass

---

### P4-M3 — MCP endpoint model + migration

- **Status:** pending
- **Repos:** Birkly
- **Depends on:** P4-M2

**Tasks**

- [ ] **P4-M3-T1** `core/mcp_endpoints.php` + `settings/mcp_endpoints.json` schema
- [ ] **P4-M3-T2** `scripts/migrate-mcp-endpoints.php` from `users/*.json` mcp_api_key
- [ ] **P4-M3-T3** `api/mcp_server.php` dual-resolve (endpoint profile + legacy 90-day)
- [ ] **P4-M3-T4** `tests/mcp_endpoints_test.php`

---

### P4-M4 — MCP Settings UI

- **Status:** pending
- **Repos:** Birkly
- **Depends on:** P4-M3

**Tasks**

- [ ] **P4-M4-T1** Dedicated MCP Connections tab (empty state default)
- [ ] **P4-M4-T2** CRUD UI for endpoint profiles; demote MCP block on outbound add flow
- [ ] **P4-M4-T3** design-reviewer sign-off on MCP tab

---

### P4-M5 — MCP hardening

- **Status:** pending
- **Repos:** Birkly
- **Depends on:** P4-M4

**Tasks**

- [ ] **P4-M5-T1** Rate limits on `api/mcp_server.php`
- [ ] **P4-M5-T2** MCP audit log (`data/mcp_audit/` or logs)
- [ ] **P4-M5-T3** Key rotation grace period + tests

---

### P4-M6 — MCP client guides + compatibility

- **Status:** pending
- **Repos:** Birkly, Birkly-Docs
- **Depends on:** P4-M5

**Tasks**

- [ ] **P4-M6-T1** Compatibility matrix (Claude Desktop, Cursor, OpenAI MCP, curl)
- [ ] **P4-M6-T2** Per-client setup wizards in Help/Docs
- [ ] **P4-M6-T3** Verify Claude Desktop + curl probes; video V16

---

### P4-M7 — BYOAI wizard + copy

- **Status:** pending
- **Repos:** Birkly
- **Depends on:** P4-M2

**Tasks**

- [ ] **P4-M7-T1** Connection setup wizard (API key vs subscription clarity)
- [ ] **P4-M7-T2** In-chat connection picker polish (merged PR #35 baseline)
- [ ] **P4-M7-T3** Video V17 BYOAI wizard

---

### P4-M8 — OAuth spike (discovery only)

- **Status:** pending
- **Repos:** Birkly
- **Depends on:** P4-M7

**Tasks**

- [ ] **P4-M8-T1** `docs/ai/oauth-feasibility.md` — provider landscape, go/no-go
- [ ] **P4-M8-T2** UX mock for “Connect with …” if feasible; else document MCP path for consumers

---

### P4-M9 — Docs split inbound/outbound

- **Status:** pending
- **Repos:** Birkly-Docs, Birkly
- **Depends on:** P4-M4, P4-M6

**Tasks**

- [ ] **P4-M9-T1** Split AI connections vs MCP connections docs
- [ ] **P4-M9-T2** Help manifest v2; contextual links in Settings

---

### P4-M10 — Birkly-Website alpha copy

- **Status:** pending
- **Repos:** Birkly-Website
- **Depends on:** P4-M9

**Tasks**

- [ ] **P4-M10-T1** Alpha copy: MCP inbound + BYOAI outbound messaging
- [ ] **P4-M10-T2** Legal/privacy lines for MCP audit if applicable

---

### P4-M11 — Phase 4 closure

- **Status:** pending
- **Repos:** Birkly (+ cross-repo)
- **Depends on:** P4-M2–P4-M10

**Tasks**

- [ ] **P4-M11-T1** Full UX matrix re-run — all P4 rows pass
- [ ] **P4-M11-T2** Videos V15 (onboarding), V16 (MCP inbound), V17 (BYOAI wizard)
- [ ] **P4-M11-T3** `docs/reviews/p4-phase4-closure-checklist.md`
- [ ] **P4-M11-T4** Mark Phase 4 complete in roadmap
- [ ] **P4-M11-T5** **Notify human once** — digest + email **"Phase 4 complete — review end result"**

---

## Phase 4 deferrals

| Defer | Reason |
|-------|--------|
| Kekaba content + production deploy | Human-scheduled |
| OAuth implementation | P4-M8 spike only; P5 if go |
| SQL, dark mode, plugin marketplace | Post-alpha polish |
| Core npm/composer | Values violation |

---

## Task handoff format

Task IDs: Phase 1 `M[N]-T[N]` · Phase 2 `P2-M[N]-T[N]` · Phase 3 `P3-M[N]-T[N]` · Phase 4 `P4-M[N]-T[N]`.

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
