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

**Autonomous execution:** Human approves **Phase 3 milestone block once** (PR #31). Lead-engineer executes P3-M1→P3-M15 without per-step human approval. Human notified when Phase 3 complete (digest + email if M1-T8 configured) with videos and examples.

**2026-06-25 — Phase 4 approved** (Keven)

**Priorities:** **Phase 4.1** AI/MCP integration hardening (OAuth, unified settings, live testing). Kekaba deferred.

**Human decisions (locked):**

| Decision | Choice |
|----------|--------|
| BYOAI outbound | Unchanged — user brings API credentials |
| Approval + role enforcement | No regression |
| MCP storage | Standalone endpoint profiles (`settings/mcp_endpoints.json` + `core/mcp_endpoints.php`) |
| Legacy MCP keys | 90-day dual-resolve via migration script |
| MCP Settings UI | Dedicated **MCP Connections** tab; visible empty state; no auto-generated keys |
| Multi-profile MCP | Yes — multiple endpoints, each with role + key |
| OAuth | Spike only in P4; implementation only in Phase 5+ if go/no-go approves |
| Human touchpoints | **End of Phase 4 only** — after full internal QA, videos, matrix green |

**Two-channel model:** Outbound = Birkly → provider API (`api/ai_chat.php`). Inbound = external client → `api/mcp_server.php` (no outbound API key on Birkly).

**Autonomous execution:** Human approves **Phase 4 once** (this approval). Lead executes P4-M1→P4-M11; **do not notify human until P4-M11** after qa-verifier, design-reviewer, and videos complete.

**Phase 2 themes:** (complete) UX flows · ContentEditor · groups/DnD · onboarding · hardening · platform sync.

**ContentEditor rule:** All editor code, components, and assets live in `fieldtypes/ContentEditor/` only. Components = HTML + CSS + JavaScript + Birkly templating from day one. Stored content = pure HTML.

**Input evidence:** `docs/reviews/2026-06-18-issue-register.md` (PR #14, P2-M1 review gate).

**Governance artifacts:** `docs/PROJECT_VALUES.md`, `docs/agent-ops/REPORTING.md`, `docs/agent-ops/AUTONOMOUS_EXECUTION.md` (daily digest 07:00 CEST; agents merge in-scope work without per-PR human review).

**Autonomous execution approved:** **2026-06-18** — human approves milestones; agent team tests, reviews, and merges within task IDs.

**2026-06-29 — Phase 5.1 approved** (Keven)

**Priorities:** **Hybrid AI agents** — personal (dynamic permissions) + service (fixed role, assignment). Replace rank-ceiling stopgap with real permission resolution for personal agents. **Kekaba deferred.**

**Human decisions (locked):**

| Decision | Choice |
|----------|--------|
| Agent types | `personal` (one per site) + `service` (admin-created) |
| Personal agent permissions | Authorizing user's CMS role + permissions |
| Service agent visibility | Admin sees all; others by `visible_to_roles` + `allowed_users` |
| Migration | Existing agents → service, admin-only until reassigned |

**Spec:** `docs/reviews/P5.1-hybrid-agents-plan.md` · **Handoff:** `docs/reviews/P5.1-lead-engineer-handoff.md`

**2026-06-29 — Phase 5.2 approved** (Keven)

**Priorities:** **Platform hardening** — plugin activation, roles/permissions UI, AI slug rules, entry list repair, updates API. **Kekaba deploy deferred.**

**Human decisions (locked):**

| Decision | Choice |
|----------|--------|
| Slugs | `[a-z0-9-]` only; AI normalizes `_` → `-` |
| Default roles | Seed admin, editor, author, viewer + permissions |
| Role pickers | Single `list_roles` source everywhere |
| Plugin deps | Fix `$birkly_plugins`; show activation errors |
| Updates API | `manage_settings` not hardcoded admin role |

**Spec:** `docs/reviews/P5.2-platform-hardening-plan.md` · **Handoff:** `docs/reviews/P5.2-lead-engineer-handoff.md`

**2026-07-03 — Phase 5.5 complete** (Keven approved; M5 docs + closure merged)

**Priorities delivered:** ContentEditor layout studio — visual table/grid editing (resize, gap, cell styling, grid span/flow) · toolbar reliability · layout modules in `fieldtypes/ContentEditor/layout/`.

**Human decisions (locked):** Fluid inline grid tracks (no `@media` in entry HTML) · cell merge deferred to Phase 6 · client embed sync (D5) post-closure · notify once at P5.5-M5-T5.

**Spec:** `docs/reviews/P5.5-contenteditor-layout-plan.md` · **Handoff:** `docs/reviews/P5.5-lead-engineer-handoff.md` · **Closure:** `docs/reviews/p5.5-phase-closure-checklist.md`

**2026-07-03 — Phase 5.6 approved** (Keven)

**Priorities:** Borderless topbar · content DnD · AI sub-collection admin knowledge · onboarding (optional AI in wizard only) · automation UX.

**Human decisions (locked):** Topbar fully borderless (no border) · AI optional in wizard only (never dashboard checklist) · dashboard checklist essential-only (remove media upload + template items) · notify once at P5.6-M4-T4.

**Spec:** `docs/reviews/P5.6-admin-ai-onboarding-automation-plan.md` · **Handoff:** `docs/reviews/P5.6-lead-engineer-handoff.md`

**2026-07-04 — Phase 5.7 approved** (Keven)

**Priorities:** Settings overhaul (all tabs) · global Save · security enforcement · storage DB backends (MySQL/PostgreSQL) · AI tab UX polish · global admin spacing/alignment audit.

**Human decisions (locked):** AI tab improved like other tabs (not regression-only) · implement DB storage backends now · security toggles fully enforce · global Save (not per-tab) · global admin UI audit + fixes · notify once at P5.7-M5-T6.

**Spec:** `docs/reviews/P5.7-settings-ui-ux-plan.md` · **Handoff:** `docs/reviews/P5.7-lead-engineer-handoff.md`

---

## Vision

Build a reliable, **dependency-free** flat-file CMS (Birkly) that is simple, secure, flexible, and robust — with UI decoupled from functionality so design can evolve without breaking features. Maintain two reference client sites (Birkly-Website, kekaba) and synced documentation (Birkly-Docs), driven by a cloud agent team with clear milestones, values alignment, and daily reporting.

**Values:** See `docs/PROJECT_VALUES.md`.

**Reporting:** See `docs/agent-ops/REPORTING.md`.

**Autonomous execution:** See `docs/agent-ops/AUTONOMOUS_EXECUTION.md`.

---

## Current state (verified 2026-06-29)

| Repo | Status |
|------|--------|
| **Birkly (CMS)** | Phase 5 + P5.1 + P5.2 **complete** on `main`. **P5.3 complete** — admin UX & workflows. |
| **Birkly-Website** | `Birkly-0.1/` sync deferred until CMS deploy verified. |
| **kekaba** | P5.3 fixes ready; embed sync **deferred** (deploy checklist). |
| **Birkly-Docs** | P5.2 roles/plugins; P5.3 workflow notes in deploy checklist. |

**Phase 5.3 handoff:** `docs/reviews/P5.3-lead-engineer-handoff.md`
| **Agent ops** | Autonomous merge active. |

**Phase 5.2 handoff:** `docs/reviews/P5.2-lead-engineer-handoff.md`

**Phase 5.1 handoff:** `docs/reviews/P5.1-lead-engineer-handoff.md` (complete)

**Phase 5 handoff:** `docs/reviews/P5-lead-engineer-handoff.md`

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

**Status:** **approved** (2026-06-25, Keven). Lead-engineer executes autonomously per `docs/reviews/P3-lead-engineer-handoff.md`. **Phase 3 complete on `main`** — see P3-M15 closure artifacts.

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

**Horizon:** theme-based; no calendar estimates for agents.

**Priorities:** MCP inbound decoupling · alpha UX hardening · BYOAI wizard · OAuth spike (docs only). **CMS always first.** **Kekaba deferred.**

**Ordering:** P4-M1 → … → P4-M11. Cross-repo: Birkly → Birkly-Docs → Birkly-Website.

**Status:** **approved** (2026-06-25, Keven). Execute per `docs/reviews/P4-lead-engineer-handoff.md`. **Human reviews end result only at P4-M11** (after internal QA + videos).

**Architecture:** Outbound BYOAI (`api/ai_chat.php`, `api_key_encrypted`) and inbound MCP (`api/mcp_server.php`, endpoint `mcp_api_key`) are **independent channels** sharing `core/ai_permissions.php` + `core/ai_tool_registry.php`.

---

### P4-M1 — Phase 4 validation gate

- **Status:** complete (human approved 2026-06-25)
- **Repos:** Birkly

**Tasks**

- [x] **P4-M1-T1** P4 readiness register — `docs/reviews/2026-06-25-p4-readiness-register.md`
- [x] **P4-M1-T2** Re-run UX matrix; add P4 rows
- [x] **P4-M1-T3** Browser QA checklist — `docs/reviews/p4-browser-qa-checklist.md`
- [x] **P4-M1-T4** Human sign-off — Phase 4 scope approved

---

### P4-M2 — Alpha UX hardening

- **Status:** complete
- **Repos:** Birkly
- **Goal:** Onboarding E2E, admin routing smoke, AI chat stale-connection regression
- **Depends on:** P4-M1-T4
- **Refs:** PR #33, #34, #35, #37; `tests/onboarding_e2e_test.php`

**Tasks**

- [x] **P4-M2-T1** Onboarding E2E test (`tests/onboarding_e2e_test.php`) — CSRF + project save
- [x] **P4-M2-T2** `tests/admin_routing_http_test.php` — HTTP routing for core admin pages
- [x] **P4-M2-T3** `tests/ai_chat_connection_test.php` — delete removes connection; chat rejects
- [x] **P4-M2-T4** Matrix rows: onboarding + admin routes + AI delete → **pass** (26 CI suites)

**Acceptance criteria:** HTTP E2E regressions in CI for onboarding, routing, AI stale connection

---

### P4-M3 — MCP endpoint model & migration

- **Status:** complete
- **Repos:** Birkly
- **Goal:** Standalone inbound MCP profiles; legacy key dual-resolve
- **Depends on:** P4-M1-T4
- **Values:** Security by default; credential separation

**Tasks**

- [x] **P4-M3-T1** `core/mcp_endpoints.php` + `settings/mcp_endpoints.json` CRUD
- [x] **P4-M3-T2** `api/mcp_endpoints.php` — create/list/rotate/revoke (auth + CSRF)
- [x] **P4-M3-T3** `api/mcp_server.php` resolves endpoint profiles; **90-day** dual-resolve legacy `users/*.json` `mcp_api_key`
- [x] **P4-M3-T4** `scripts/migrate-mcp-endpoints.php`
- [x] **P4-M3-T5** `tests/mcp_endpoints_test.php`

**Acceptance criteria:** Inbound works without outbound AI row; migration idempotent; tests in CI

---

### P4-M4 — MCP Settings UI (first-class)

- **Status:** complete
- **Repos:** Birkly
- **Goal:** Settings → **MCP Connections** tab; multi-profile; not buried in AI Connections
- **Depends on:** P4-M3-T2

**Tasks**

- [x] **P4-M4-T1** Settings tab + empty state (no auto-generated keys)
- [x] **P4-M4-T2** Create/rotate/revoke UI; role + `require_approval` per endpoint
- [x] **P4-M4-T3** Demote/remove MCP-as-outbound-provider from AI Connections add flow
- [x] **P4-M4-T4** Copy snippets: URL, key, `mcp-bridge.js` command
- [x] **P4-M4-T5** Matrix + internal video V16: MCP endpoint without outbound AI → **pass**

**Acceptance criteria:** design-reviewer pass; non-technical path documented in UI

---

### P4-M5 — MCP hardening

- **Status:** complete
- **Repos:** Birkly
- **Depends on:** P4-M3-T3, P4-M4-T2

**Tasks**

- [x] **P4-M5-T1** Per-endpoint rate limits
- [x] **P4-M5-T2** MCP audit log (tool, endpoint id, timestamp; no secrets)
- [x] **P4-M5-T3** Key rotation with 24h grace unless revoked
- [x] **P4-M5-T4** Failed-auth lockout + admin visibility
- [x] **P4-M5-T5** `tests/mcp_hardening_test.php`

**Acceptance criteria:** Audit trail on `tools/call`; writes still use approval queue

---

### P4-M6 — MCP client compatibility & guides

- **Status:** complete
- **Repos:** Birkly, Birkly-Docs
- **Depends on:** P4-M4-T4, P4-M5-T1

**Tasks**

- [x] **P4-M6-T1** Refresh `docs/mcp-setup-guide.md`; `BIRKLY_URL` naming
- [x] **P4-M6-T2** Cursor/Windsurf HTTP MCP section (experimental label if unverified)
- [x] **P4-M6-T3** Generic HTTP MCP examples
- [x] **P4-M6-T4** `docs/reviews/p4-mcp-client-matrix.md`
- [x] **P4-M6-T5** QA verify Claude Desktop or curl shim — video evidence in V16

**Acceptance criteria:** ≥1 client path verified on cloud VM

---

### P4-M7 — BYOAI outbound UX polish

- **Status:** complete
- **Repos:** Birkly
- **Depends on:** P4-M2-T3

**Tasks**

- [x] **P4-M7-T1** Connection wizard: provider → key → test call → role/approval
- [x] **P4-M7-T2** Clear validation errors (invalid key, model, rate limit)
- [x] **P4-M7-T3** Copy: API key vs consumer subscription (ChatGPT Plus / Claude Pro ≠ API)
- [x] **P4-M7-T4** Ollama unreachable detection + doc link
- [x] **P4-M7-T5** Matrix + internal video V17: outbound connect → chat → approval → **pass**

**Acceptance criteria:** Wizard completes without reading source code

---

### P4-M8 — OAuth / device-flow discovery spike

- **Status:** complete
- **Repos:** Birkly (docs only unless go approved for P5)
- **Depends on:** P4-M7-T3

**Tasks**

- [x] **P4-M8-T1** `docs/reviews/p4-oauth-spike.md` — providers, limits, security
- [x] **P4-M8-T2** Optional device-code evaluation (no core dependency)
- [x] **P4-M8-T3** Go/no-go table; if go → propose **P5** milestone (not P4 code)
- [x] **P4-M8-T4** UI remains API-key wizard on no-go

**Acceptance criteria:** Written go/no-go; **no OAuth production code in P4 without separate approval**

---

### P4-M9 — Documentation & Help manifest

- **Status:** complete
- **Repos:** Birkly, Birkly-Docs
- **Depends on:** P4-M6-T1, P4-M7-T3

**Tasks**

- [x] **P4-M9-T1** Update `docs/ai/BYOAI-ARCHITECTURE.md` (two-channel diagram)
- [x] **P4-M9-T2** Birkly-Docs: `01-admin/mcp-connections.md` (new) + `ai-connections.md` (outbound only)
- [x] **P4-M9-T3** Birkly-Docs: `ai-chat-and-actions.md` approval flow
- [x] **P4-M9-T4** Help manifest v2 + contextual links on MCP tab
- [x] **P4-M9-T5** `docs/changelog/CHANGELOG-PLATFORM.md` P4 batch

**Acceptance criteria:** Docs never imply subscription login replaces API key

---

### P4-M10 — Birkly-Website closed-alpha verification

- **Status:** complete
- **Repos:** Birkly-Website, Birkly-Docs
- **Depends on:** P4-M9-T2
- **Note:** Kekaba still deferred

**Tasks**

- [x] **P4-M10-T1** Audit beta/roadmap copy for AI/MCP accuracy
- [x] **P4-M10-T2** Legal/privacy: MCP audit logs + feedback practices
- [x] **P4-M10-T3** Dev smoke: waitlist, roadmap, legal; sync `Birkly-0.1/` if APIs changed

**Acceptance criteria:** `DEV.md` fresh-clone path works

---

### P4-M11 — Phase 4 closure & human handoff

- **Status:** complete
- **Repos:** Birkly (+ cross-repo)
- **Depends on:** P4-M2–P4-M10

**Tasks**

- [x] **P4-M11-T1** Full UX matrix — **all P4 rows pass**
- [x] **P4-M11-T2** Videos V15 (onboarding), V16 (MCP), V17 (BYOAI) in `docs/reviews/artifacts/`
- [x] **P4-M11-T3** `docs/reviews/p4-phase4-closure-checklist.md` + examples index
- [x] **P4-M11-T4** Mark Phase 4 complete in roadmap
- [x] **P4-M11-T5** **Notify human once:** digest + email **"Phase 4 complete — review end result"** — only after qa-verifier + design-reviewer sign-off on full phase

**Acceptance criteria:** CI includes mcp + onboarding + routing tests; matrix green; videos delivered; **single notification to human**

---

## Phase 4 deferrals

| Defer | Reason |
|-------|--------|
| **Kekaba** pages + production deploy | Human defer |
| OAuth **implementation** (if spike no-go) | Phase 5+ |
| SQL backend, dark mode, plugin marketplace, NPM in core | Unchanged |
| Birkly → external AI push/webhooks | Post-MCP hardening |
| Consumer OAuth without API scope | Speculative |

---

## Phase 4 success criteria

| Area | Target |
|------|--------|
| **MCP inbound** | Standalone endpoints; Settings tab; migration; hardening |
| **MCP clients** | Claude Desktop verified; client matrix |
| **BYOAI outbound** | Wizard + API-key vs subscription clarity |
| **Security** | Approval default + role checks unchanged |
| **Alpha UX** | Onboarding + routing matrix green |
| **OAuth** | Spike go/no-go doc only |
| **Docs** | Inbound/outbound split in Birkly-Docs + Help |
| **Delivery** | Videos V15–V17; **human notified once at P4-M11 after internal review** |

---

## Phase 4.1 — AI & MCP integration hardening

**Horizon:** theme-based; no calendar estimates for agents.

**Priorities:** Fix real-world MCP + BYOAI failures · unified Settings UX · MCP OAuth for Claude remote connectors · integration test matrix. **CMS always first.** **Kekaba deferred.**

**Ordering:** P4.1-M1 → … → P4.1-M6. Cross-repo: Birkly → Birkly-Docs.

**Status:** **complete** (2026-06-29). Human review ready at P4.1-M6-T5.

**Trigger:** User reported Claude remote connector OAuth failure (`ofid_bca0068514e0b124`) and outbound AI not working on live install despite P4 CI green.

---

### P4.1-M1 — Reproduce & document failure modes

- **Status:** complete
- **Repos:** Birkly
- **Goal:** Evidence before code; confirm root causes

**Tasks**

- [x] **P4.1-M1-T1** Live repro matrix: outbound connect+chat, MCP curl, MCP stdio, Claude remote connector
- [x] **P4.1-M1-T2** `docs/reviews/p4.1-integration-repro.md`
- [x] **P4.1-M1-T3** Install shape check (subdirectory, legacy MCP keys, migration dry-run)

**Acceptance criteria:** Written repro doc with pass/fail per path; root causes confirmed

---

### P4.1-M2 — Unified Settings UI

- **Status:** complete
- **Repos:** Birkly, Birkly-Docs
- **Goal:** One **AI & Integrations** tab with Outbound + Inbound sections
- **Depends on:** P4.1-M1-T2

**Tasks**

- [x] **P4.1-M2-T1** Tab `ai-integrations`; hash redirects from legacy tabs
- [x] **P4.1-M2-T2** Outbound (BYOAI) section
- [x] **P4.1-M2-T3** Inbound (MCP) section
- [x] **P4.1-M2-T4** Shared approval queue panel
- [x] **P4.1-M2-T5** Setup cards + remote-connector warning until OAuth ships
- [x] **P4.1-M2-T6** Birkly-Docs topic updates

**Acceptance criteria:** design-reviewer pass; deep links work

---

### P4.1-M3 — MCP OAuth for remote connectors

- **Status:** complete
- **Repos:** Birkly
- **Goal:** Claude remote connector completes OAuth; API key path unchanged
- **Depends on:** P4.1-M1-T2

**Tasks**

- [x] **P4.1-M3-T1** `docs/reviews/p4.1-mcp-oauth-threat-model.md`
- [x] **P4.1-M3-T2** `core/mcp_oauth.php` — metadata, PKCE, token endpoints
- [x] **P4.1-M3-T3** DCR or CIMD (research Claude broker compatibility)
- [x] **P4.1-M3-T4** `api/mcp_server.php` — `WWW-Authenticate` + resource metadata
- [x] **P4.1-M3-T5** Bearer token → endpoint profile + permissions
- [x] **P4.1-M3-T6** Admin auth_mode + optional OAuth client ID field
- [x] **P4.1-M3-T7** CORS for `claude.ai`; subdirectory-safe well-known URLs

**Acceptance criteria:** Remote connector OAuth E2E; `tests/mcp_oauth_test.php` in CI

---

### P4.1-M4 — Outbound BYOAI fixes

- **Status:** complete
- **Repos:** Birkly
- **Goal:** Admin chat works with valid provider keys
- **Depends on:** P4.1-M1-T2

**Tasks**

- [x] **P4.1-M4-T1** Fix issues from M1 repro
- [x] **P4.1-M4-T2** Encryption bootstrap on fresh installs
- [x] **P4.1-M4-T3** Wizard test-before-save + inline errors
- [x] **P4.1-M4-T4** Chat picker reliability after create
- [x] **P4.1-M4-T5** CI mock provider harness

**Acceptance criteria:** Ollama + ≥1 cloud provider manual QA pass

---

### P4.1-M5 — Bridge & docs polish

- **Status:** complete
- **Repos:** Birkly, Birkly-Docs
- **Depends on:** P4.1-M2-T3, P4.1-M3-T4

**Tasks**

- [x] **P4.1-M5-T1** `mcp-bridge.js` — `notifications/initialized`, `ping`
- [x] **P4.1-M5-T2** Rewrite `docs/mcp-setup-guide.md` (bridge vs remote connector)
- [x] **P4.1-M5-T3** Admin Claude Desktop config snippet generator
- [x] **P4.1-M5-T4** Update `docs/reviews/p4-mcp-client-matrix.md`

**Acceptance criteria:** Docs match two connection paths; stdio bridge verified

---

### P4.1-M6 — Integration tests & closure

- **Status:** complete
- **Repos:** Birkly (+ Birkly-Docs)
- **Depends on:** P4.1-M2–P4.1-M5

**Tasks**

- [x] **P4.1-M6-T1** `docs/reviews/p4.1-integration-test-matrix.md` — all rows green
- [x] **P4.1-M6-T2** CI: `tests/mcp_oauth_test.php` + extended suites
- [x] **P4.1-M6-T3** Videos V18 (unified settings), V19 (MCP OAuth), V20 (outbound chat)
- [x] **P4.1-M6-T4** `docs/reviews/p4.1-phase-closure-checklist.md` + roadmap update
- [x] **P4.1-M6-T5** **Notify human once** — only after qa-verifier + design-reviewer sign-off

**Acceptance criteria:** Integration matrix green; videos delivered; **single notification to human**

---

## Phase 4.1 deferrals

| Defer | Reason |
|-------|--------|
| **Kekaba** | Human defer |
| Outbound BYOAI OAuth | Providers lack CMS-friendly OAuth; API keys only |
| SQL, dark mode, plugin marketplace | Unchanged |

---

## Phase 4.1 success criteria

| Area | Target |
|------|--------|
| **MCP stdio** | Claude Desktop + bridge verified |
| **MCP remote** | Claude connector OAuth E2E |
| **MCP API key** | Unchanged backward compatibility |
| **Outbound BYOAI** | Connect + chat E2E |
| **Settings UX** | Single AI & Integrations tab |
| **Security** | OAuth threat model; approval + roles unchanged |
| **Tests** | Integration matrix + CI OAuth tests |
| **Delivery** | Videos V18–V20; human notified once at P4.1-M6 |

---

## Phase 5 — MCP transport, CIMD OAuth & AI agents

**Horizon:** theme-based; no calendar estimates for agents.

**Priorities:** Fix delete · `/mcp` Streamable HTTP · CIMD OAuth · AI agents with roles · connector E2E (empty OAuth fields in client UI). **CMS always first.** **Kekaba deferred.**

**Ordering:** P5-M0 → … → P5-M7. Cross-repo: Birkly → Birkly-Docs.

**Status:** **complete** (2026-06-29). Human review ready at P5-M7-T5.

**Handoff:** `docs/reviews/P5-lead-engineer-handoff.md`

---

### P5-M0 — Fix AI connection delete

- **Status:** complete
- **Repos:** Birkly

**Tasks**

- [x] **P5-M0-T1** `birkly_ai_user_can_manage_connection()` — owner + `manage_settings`
- [x] **P5-M0-T2** `checkPermission()` honors explicit `permissions` array
- [x] **P5-M0-T3** Admin JS: `birklyApiUrl()` / `Birkly.url()` + CSRF on delete
- [x] **P5-M0-T4** `tests/ai_permissions_test.php`

---

### P5-M1 — OAuth CIMD

- **Status:** complete
- **Repos:** Birkly

**Tasks**

- [x] **P5-M1-T1** CIMD fetch/cache in `core/mcp_oauth.php` (SSRF-safe)
- [x] **P5-M1-T2** `client_id_metadata_document_supported` in AS metadata
- [x] **P5-M1-T3** `tests/mcp_cimd_test.php`

---

### P5-M2 — `/mcp` Streamable HTTP

- **Status:** complete
- **Repos:** Birkly

**Tasks**

- [x] **P5-M2-T1** `api/mcp.php` + `core/mcp_protocol.php`
- [x] **P5-M2-T2** Router + `.htaccess` for `/mcp`
- [x] **P5-M2-T3** OAuth resource URL → `/mcp`; legacy `/api/mcp_server.php` retained
- [x] **P5-M2-T4** `tests/mcp_streamable_test.php`

---

### P5-M3 — AI agents with roles

- **Status:** complete
- **Repos:** Birkly

**Tasks**

- [x] **P5-M3-T1** `core/ai_agents.php` + `settings/ai_agents.json`
- [x] **P5-M3-T2** `api/ai_agents.php` CRUD
- [x] **P5-M3-T3** Agent create → OAuth MCP endpoint with role
- [x] **P5-M3-T4** `tests/ai_agents_test.php`

---

### P5-M4 — Settings → AI tab (agents UI)

- **Status:** complete
- **Repos:** Birkly

**Tasks**

- [x] **P5-M4-T1** Agents list in AI tab (primary section)
- [x] **P5-M4-T2** Create agent modal — name, role, description
- [x] **P5-M4-T3** Connector URL → `/mcp`; copy UX
- [x] **P5-M4-T4** Empty OAuth fields copy in setup steps

---

### P5-M5 — Connector E2E (Claude/ChatGPT)

- **Status:** complete
- **Repos:** Birkly

**Tasks**

- [x] **P5-M5-T1** DCR + CIMD + bearer on `/mcp` in CI
- [x] **P5-M5-T2** Admin copy: paste URL only; leave Client ID/Secret empty
- [x] **P5-M5-T3** HTTPS warning retained for Claude

---

### P5-M6 — Integration matrix & videos

- **Status:** complete
- **Repos:** Birkly, Birkly-Docs

**Tasks**

- [x] **P5-M6-T1** `docs/reviews/p5-integration-test-matrix.md`
- [x] **P5-M6-T2** Videos V21–V23
- [x] **P5-M6-T3** Birkly-Docs: mcp-connections.md

---

### P5-M7 — Phase 5 closure

- **Status:** complete
- **Repos:** Birkly (+ cross-repo)
- **Depends on:** P5-M6

**Tasks**

- [x] **P5-M7-T1** `docs/reviews/p5-phase-closure-checklist.md`
- [x] **P5-M7-T2** Mark Phase 5 complete in roadmap
- [x] **P5-M7-T3** qa-verifier + design-reviewer sign-off
- [x] **P5-M7-T4** Full integration matrix green
- [x] **P5-M7-T5** **Notify human once** — after internal sign-off

---

## Phase 5 deferrals

| Defer | Reason |
|-------|--------|
| **Kekaba** | Human defer |
| Outbound BYOAI OAuth | API keys only |
| SQL, dark mode, plugin marketplace | Unchanged |

---

## Phase 5 success criteria

| Area | Target |
|------|--------|
| **Delete** | Owner + manage_settings; subdirectory-safe |
| **MCP transport** | Canonical `POST /mcp` Streamable HTTP |
| **OAuth** | DCR + CIMD |
| **AI agents** | Named roles → OAuth endpoints |
| **Settings UX** | AI tab; empty OAuth fields in client apps |
| **Tests** | 38+ CI suites; integration matrix green |
| **Delivery** | Videos V21–V23; human notified once at P5-M7 |

---

## Phase 5.1 — Hybrid AI agents (personal + service)

**Horizon:** theme-based; no calendar estimates for agents.

**Priorities:** Personal agent (dynamic permissions) · service agent assignment · OAuth visibility enforcement · migration from P5 agents. **CMS always first.** **Kekaba deferred.**

**Ordering:** P5.1-M0 → … → P5.1-M6. Cross-repo: Birkly → Birkly-Docs.

**Status:** **complete** (2026-06-29). PR `cursor/p5.1-hybrid-agents-24b5`.

**Spec:** `docs/reviews/P5.1-hybrid-agents-plan.md`  
**Handoff:** `docs/reviews/P5.1-lead-engineer-handoff.md`  
**Closure:** `docs/reviews/p5.1-phase-closure-checklist.md`

---

### P5.1-M0 — Data model + personal agent backend

- **Status:** complete
- **Repos:** Birkly
- **Depends on:** Phase 5 complete

**Tasks**

- [x] **P5.1-M0-T1** Agent schema: `type`, `visible_to_roles`, `allowed_users` in `core/ai_agents.php`
- [x] **P5.1-M0-T2** `birkly_ai_agents_ensure_personal()` singleton + endpoint
- [x] **P5.1-M0-T3** `birkly_ai_agents_list_for_user()` visibility filter
- [x] **P5.1-M0-T4** Personal agent MCP actor from authorizer permissions (not fixed role)
- [x] **P5.1-M0-T5** Rewrite `birkly_ai_user_can_authorize_agent()` per hybrid plan
- [x] **P5.1-M0-T6** `tests/ai_agent_auth_test.php`, `tests/ai_agents_test.php`

---

### P5.1-M1 — Service visibility + OAuth enforcement

- **Status:** complete
- **Repos:** Birkly

**Tasks**

- [x] **P5.1-M1-T1** `api/ai_agents.php` — assignment fields; filtered list
- [x] **P5.1-M1-T2** OAuth authorize: `can_see` + `can_authorize`; consent copy by type
- [x] **P5.1-M1-T3** Token: `agent_type`, `authorized_by`; personal permission resolve
- [x] **P5.1-M1-T4** `tests/mcp_oauth_test.php` — escalation + personal scenarios

---

### P5.1-M2 — Settings UI (personal + service)

- **Status:** complete
- **Repos:** Birkly
- **Depends on:** P5.1-M0, P5.1-M1

**Tasks**

- [x] **P5.1-M2-T1** Personal agent card (primary) — one URL, team copy
- [x] **P5.1-M2-T2** Service agent list filtered; type badges
- [x] **P5.1-M2-T3** Create/edit modal — role + who can use (roles + users)
- [x] **P5.1-M2-T4** design-reviewer sign-off

---

### P5.1-M3 — Migration

- **Status:** complete
- **Repos:** Birkly

**Tasks**

- [x] **P5.1-M3-T1** `scripts/migrate-ai-agents-hybrid.php` (--dry-run)
- [x] **P5.1-M3-T2** Existing agents → `service`, `visible_to_roles: ["admin"]`
- [x] **P5.1-M3-T3** Personal agent on fresh install + post-migrate

---

### P5.1-M4 — Integration matrix

- **Status:** complete
- **Repos:** Birkly

**Tasks**

- [x] **P5.1-M4-T1** `docs/reviews/p5.1-integration-test-matrix.md`
- [x] **P5.1-M4-T2** qa-verifier: personal, service, escalation, list filter
- [x] **P5.1-M4-T3** `./scripts/ci-check.sh` green

---

### P5.1-M5 — Documentation

- **Status:** complete
- **Repos:** Birkly-Docs, Birkly

**Tasks**

- [x] **P5.1-M5-T1** Birkly-Docs: personal vs service, assignment, security
- [x] **P5.1-M5-T2** Admin help snapshot if needed

---

### P5.1-M6 — Phase 5.1 closure

- **Status:** complete
- **Depends on:** P5.1-M4, P5.1-M5

**Tasks**

- [x] **P5.1-M6-T1** `docs/reviews/p5.1-phase-closure-checklist.md`
- [x] **P5.1-M6-T2** Mark Phase 5.1 complete in roadmap
- [x] **P5.1-M6-T3** qa-verifier phase sign-off
- [x] **P5.1-M6-T4** **Notify human once**

---

## Phase 5.1 success criteria

| Area | Target |
|------|--------|
| **Personal agent** | One per site; tools = authorizing user's permissions |
| **Service agent** | Fixed role; visibility + assignment enforced |
| **Escalation** | Editor cannot use admin service agent without assignment |
| **OAuth** | Login + consent; deny unauthorized users |
| **Migration** | Existing agents safe; personal auto-created |
| **Tests** | Integration matrix green |
| **Docs** | Birkly-Docs updated |

---

## Phase 5.2 — Platform hardening (roles, plugins, admin reliability)

**Horizon:** theme-based; no calendar estimates for agents.

**Priorities:** Plugin activation fix · roles seed + permissions UI · AI slug normalization · entry list repair · updates API · user plugin smoke. **CMS always first.** **Kekaba deferred.**

**Ordering:** P5.2-M0 → … → P5.2-M6. Cross-repo: Birkly → Birkly-Docs.

**Status:** **complete** (2026-06-29). Human deploy verification pending for live kekaba rows.

**Spec:** `docs/reviews/P5.2-platform-hardening-plan.md`  
**Handoff:** `docs/reviews/P5.2-lead-engineer-handoff.md`

**Live repro:** `birkly.kekaba.com` — AI slugs, entry list, updates, empty roles, plugin activation.

---

### P5.2-M0 — Plugin system fix

- **Status:** complete
- **Repos:** Birkly
- **Priority:** **first** — unblocks user_management

**Tasks**

- [x] **P5.2-M0-T1** `$haru_plugins` → `$birkly_plugins` in `core/plugins/dependencies.php`
- [x] **P5.2-M0-T2** Audit plugin subsystem rename debt (`api/plugins.php`, capabilities, services)
- [x] **P5.2-M0-T3** Settings UI: show `errors[]` on activation failure
- [x] **P5.2-M0-T4** Verify `user_management` + `automation` activate; add/update test

---

### P5.2-M1 — Roles & permissions foundation

- **Status:** complete
- **Repos:** Birkly
- **Depends on:** none (parallel with M0 after M0 if preferred)

**Tasks**

- [x] **P5.2-M1-T1** `ensureDefaultRoles()` seed admin/editor/author/viewer + permissions
- [x] **P5.2-M1-T2** Fix Settings Users & Roles data binding (`$settings['roles']`)
- [x] **P5.2-M1-T3** Permissions editor UI per role
- [x] **P5.2-M1-T4** CMS user modals: dynamic role picker from `list_roles`
- [x] **P5.2-M1-T5** AI agents + all pickers accept custom roles from roles.json
- [x] **P5.2-M1-T6** Role-user matching by `role['id']`; tests

---

### P5.2-M2 — AI slug & collection hygiene

- **Status:** complete
- **Repos:** Birkly

**Tasks**

- [x] **P5.2-M2-T1** Unify collection normalization in `api/ai_approvals.php` (create + update)
- [x] **P5.2-M2-T2** AI create uses `birkly_resolve_entry_slug()`; `_` → `-` policy
- [x] **P5.2-M2-T3** AI context pack + chat prompt slug rules
- [x] **P5.2-M2-T4** Tool schema hints; tests (`blog_posts`, underscores)

---

### P5.2-M3 — Entry list & admin URL repair

- **Status:** complete
- **Repos:** Birkly

**Tasks**

- [x] **P5.2-M3-T1** `getEntriesData()` collection meta normalization
- [x] **P5.2-M3-T2** `entries.html` — `{admin_url}` / `birkly_url()` not hardcoded paths
- [x] **P5.2-M3-T3** Scan/fix other admin templates with hardcoded admin URLs
- [x] **P5.2-M3-T4** `admin_routing_http_test.php` — entries page smoke

---

### P5.2-M4 — Update system

- **Status:** complete
- **Repos:** Birkly

**Tasks**

- [x] **P5.2-M4-T1** `api/updates.php` → `requireAuth('manage_settings')`
- [x] **P5.2-M4-T2** Fix `get_birkly_version()` for plugin/update checks
- [x] **P5.2-M4-T3** Updates UI clearer error messages; `version_update_test.php`

---

### P5.2-M5 — User plugin readiness

- **Status:** complete
- **Repos:** Birkly
- **Depends on:** P5.2-M0

**Tasks**

- [x] **P5.2-M5-T1** Smoke activate user_management; admin pages load
- [x] **P5.2-M5-T2** Help copy: CMS users vs site users (plugin)
- [x] **P5.2-M5-T3** Minimal permission enforcement gaps if blocking smoke

---

### P5.2-M6 — Integration matrix, docs, closure

- **Status:** complete
- **Depends on:** P5.2-M1–M5

**Tasks**

- [x] **P5.2-M6-T1** `docs/reviews/p5.2-integration-test-matrix.md`
- [x] **P5.2-M6-T2** Birkly-Docs: roles, plugins, AI slugs
- [x] **P5.2-M6-T3** `docs/reviews/p5.2-phase-closure-checklist.md`
- [x] **P5.2-M6-T4** **Notify human once**

---

## Phase 5.2 success criteria

| Area | Target |
|------|--------|
| **Plugins** | user_management activates; real errors shown |
| **Roles** | Defaults visible; permissions editable; unified pickers |
| **AI slugs** | Underscores normalized; create succeeds |
| **Entry list** | Works on subdirectory install |
| **Updates** | manage_settings users can use update API |
| **Tests** | Integration matrix green |
| **Docs** | Birkly-Docs updated |

---

## Phase 5.3 — Admin UX & core workflows

**Status:** **complete** (2026-06-29). Live deploy verification pending.

**Spec:** `docs/reviews/P5.3-admin-ux-plan.md` · **Handoff:** `docs/reviews/P5.3-lead-engineer-handoff.md`

### P5.3-M0 — Entry drill-down URLs
- [x] smart-table, entry-detail, entry-editor, collection-editor URL sweep
- [x] entry-editor.js, content-groups.js `Birkly.adminUrl()`
- [x] admin_routing_http_test entry-detail smoke

### P5.3-M1 — Automation plugin
- [x] Remove duplicate `let automations`
- [x] `Birkly.url()` for automation API

### P5.3-M2 — Dashboard onboarding
- [x] Skip sets `completed: true`
- [x] Collapsible checklist; href fixes
- [x] Real checklist state in getDashboardData()

### P5.3-M3 — Alpha feedback page
- [x] `getPageData('feedback')` + `getFeedbackData()`

### P5.3-M4 — Content DnD
- [x] nested-drag-drop capture flag fix
- [x] ungrouped normalize in content-groups.js

### P5.3-M5 — Unified FAB
- [x] AI speed-dial + feedback in ai-chat-button.js
- [x] Hide standalone feedback FAB

### P5.3-M6 — UX audit
- [x] p5.3-ux-issue-register.md
- [x] dashboard.js, collection-editor URL fixes

### P5.3-M7 — Closure
- [x] Integration matrix + closure checklist + deploy checklist
- [x] **P5.3-M7-T4** Notify human once

---

## Phase 5.4 — Admin stability, platform feedback & AI knowledge

**Status:** Merged to main (#64) — pending human live deploy on `birkly.kekaba.com`

**Spec:** `docs/reviews/P5.4-platform-feedback-ai-plan.md` · **Handoff:** `docs/reviews/P5.4-lead-engineer-handoff.md` · **Live issues:** `docs/reviews/p5.4-live-issues-register.md`

### P5.4-M0 — Admin render hotfix
- [x] Partial #62: redeclare guards, layout, content bootstrap, get_plugin_state, Throwable catch
- [x] Fix Content `syntax error, unexpected token \"` — template `?>\"` audit, `{json content_bootstrap}`, `admin_content_helpers.php`
- [x] Template `?>\"` audit; content HTTP smoke on subdirectory (`admin_subdir_content_test.php`, `server-router.php`)

### P5.4-M1 — Entries & automation verification
- [x] Entry drill-down chain verified in CI (`admin_routing_http_test.php`)
- [x] `birkly_is_plugin_active()` unified; automation bootstrap + nav + page gate

### P5.4-M2 — Platform feedback model
- [x] Remove customer-side Alpha Feedback admin page (owner_mode only)
- [x] Floating submit + platform relay (`core/platform.php`, `feedback_relay_to_platform`)
- [ ] design-reviewer sign-off (post-PR)

### P5.4-M3 — AI knowledge system
- [x] Expand context pack; MCP topic resources (`birkly://docs/*`)
- [x] `read_birkly_docs` tool; Birkly-Docs sync (#9 merged)
- [x] `ai_knowledge_coverage_test.php`

### P5.4-M4 — Deploy & embed automation
- [x] kekaba + Birkly-Website embed sync (#64 + kekaba main)
- [x] `p5.4-deploy-checklist.md`

### P5.4-M5 — Closure
- [x] Integration matrix; **P5.4-M5-T4** notify human once

---

## Phase 5.5 — ContentEditor layout power features

**Status:** Complete (2026-07-03) — merged to main; pending human live deploy on `birkly.kekaba.com`

**Spec:** `docs/reviews/P5.5-contenteditor-layout-plan.md` · **Handoff:** `docs/reviews/P5.5-lead-engineer-handoff.md` · **Matrix:** `docs/reviews/p5.5-integration-test-matrix.md`

### P5.5-M0 — Toolbar & insert reliability
- [x] Admin dropdown clash fix (#68); caret insert via `insertNodesAtCaret` (#67, #68)
- [x] qa-verifier entry editor smoke (dropdowns + mid-document table insert)

### P5.5-M1 — Layout block architecture
- [x] `layout/layout-block.js`, `layout-inspector.js`, `resize-controller.js`, `layout-manager.js`
- [x] Save sanitization in `sync-manager.js` + `ContentEditor.php` `processValue`
- [x] `layout_roundtrip_test.php`

### P5.5-M2 — Table visual studio
- [x] `table.content-editor-table` + `<colgroup>` column widths
- [x] Column/row resize, cell + table inspector
- [x] `table_layout_test.php`, `caret_insert_test.php`

### P5.5-M3 — Grid visual studio
- [x] `div.content-editor-grid` with inline grid styles, span, gap, auto-flow presets
- [x] Fluid responsive tracks (no `@media` in saved HTML)
- [x] `grid_layout_test.php`

### P5.5-M4 — Commands cleanup
- [x] Layout modules in `layout/table-layout.js`, `layout/grid-layout.js`; wired in `ContentEditor.php`
- [x] ARCHITECTURE_PROPOSAL Phase 4 checklist updated

### P5.5-M5 — Docs, AI knowledge & closure
- [x] Birkly-Docs `02-content/content-editor.md`
- [x] AI context pack layout patterns; `ai_knowledge_coverage_test.php`
- [x] Integration matrix + phase closure checklist; **P5.5-M5-T5** notify human once

**Human decisions (locked):**

| Decision | Choice |
|----------|--------|
| Grid responsive export | Fluid inline tracks; no `@media` in entry HTML |
| Cell merge (tables) | Deferred to Phase 6 |
| Client embed sync (D5) | Deferred until post-closure |
| Execution | Autonomous M0→M5; notify human once at P5.5-M5-T5 |

---

## Phase 5.6 — Admin polish, AI knowledge, onboarding & automation

**Status:** Complete (2026-07-03) — merged to main; pending human live deploy on `birkly.kekaba.com`

**Spec:** `docs/reviews/P5.6-admin-ai-onboarding-automation-plan.md` · **Handoff:** `docs/reviews/P5.6-lead-engineer-handoff.md` · **Matrix:** `docs/reviews/p5.6-integration-test-matrix.md`

**Priorities delivered:** Borderless topbar + avatar fix · content DnD within/between groups · AI sub-collection/sub-entry admin knowledge + MCP tools · onboarding redesign (optional AI wizard step, slim checklist) · automation CSS + builder UX + sub-entry triggers + pickers + dry-run.

| Milestone | Focus | Status |
|-----------|--------|--------|
| **P5.6-M0** | Borderless topbar + avatar fix; content DnD within/between groups | complete |
| **P5.6-M1** | AI context pack + MCP tools for sub-collections/sub-entries | complete |
| **P5.6-M2** | Onboarding redesign; optional AI wizard step; essential-only checklist | complete |
| **P5.6-M3** | Automation CSS, builder UX, sub-entry triggers, pickers | complete |
| **P5.6-M4** | Birkly-Docs, integration matrix, closure | complete |

**Human decisions (locked):**

| Decision | Choice |
|----------|--------|
| Topbar | Fully borderless — no border-bottom |
| AI onboarding | Optional wizard step only; not on dashboard checklist |
| Dashboard checklist | Essential only (remove media upload, remove templates) |
| Execution | Autonomous M0→M4; notify once at P5.6-M4-T4 |
| kekaba embed sync | Deferred post-closure (D6); human deploys via `git pull` |

---

## Phase 5.7 — Settings UI/UX, enforcement & global admin polish

**Status:** Approved (2026-07-04) — autonomous execution P5.7-M0→M5

**Spec:** `docs/reviews/P5.7-settings-ui-ux-plan.md` · **Handoff:** `docs/reviews/P5.7-lead-engineer-handoff.md`

**Priorities:** Settings overhaul (all tabs) · **global Save** · **security enforcement** · **storage DB backends** (MySQL/PostgreSQL) · **AI tab UX polish** · **global admin spacing/alignment audit** · structured verification matrix for every setting.

**Depends on:** P5.6 complete on `main` (coordinate topbar tokens).

| Milestone | Focus | Status |
|-----------|--------|--------|
| **P5.7-M0** | Settings audit + verification matrix + global admin UI audit | pending |
| **P5.7-M1** | JS extraction, **global Save**, toast, `Birkly.url()` everywhere | pending |
| **P5.7-M2** | P0: General fixes, **security enforcement**, **Storage DB backends**, API honesty | pending |
| **P5.7-M3** | Settings UI redesign + **AI tab UX** (shared components) | pending |
| **P5.7-M4** | P1 tabs + global spacing/alignment fixes (sidebar, forms, etc.) | pending |
| **P5.7-M5** | Automated tests + matrix execution + docs + closure | pending |

**Human decisions (locked):**

| Decision | Choice |
|----------|--------|
| AI tab | Improve UX like other tabs — not regression-only |
| Storage | Implement MySQL + PostgreSQL backends now via `StorageFactory` |
| Security | All visible toggles enforce at runtime — no save-only controls |
| Save UX | Global Save — single primary action + toast |
| Global UI | Full admin audit (M0) + high-impact spacing fixes (M4) |
| Execution | Autonomous M0→M5; notify once at P5.7-M5-T6 |

**Design rule:** No new visual language — extend shared components only; new CSS only when necessary.

---

## Task handoff format

Task IDs: Phase 1 `M[N]-T[N]` · Phase 2 `P2-M[N]-T[N]` · Phase 3 `P3-M[N]-T[N]` · Phase 4 `P4-M[N]-T[N]` · Phase 4.1 `P4.1-M[N]-T[N]` · Phase 5 `P5-M[N]-T[N]` · Phase 5.1 `P5.1-M[N]-T[N]` · Phase 5.2 `P5.2-M[N]-T[N]` · Phase 5.3 `P5.3-M[N]-T[N]` · Phase 5.4 `P5.4-M[N]-T[N]` · Phase 5.5 `P5.5-M[N]-T[N]` · Phase 5.6 `P5.6-M[N]-T[N]` · Phase 5.7 `P5.7-M[N]-T[N]`.

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
