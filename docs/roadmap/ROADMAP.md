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

## Current state (verified 2026-06-19)

| Repo | Status |
|------|--------|
| **Birkly (CMS)** | Phase 1 complete on `main`: auth boundary, templating consolidation, client bundle/snapshot APIs, CI + `tests/run.php`. **Phase 2 gaps:** ContentEditor broken on demo entries (legacy `editor`/`media` types), no visual/HTML tabs, `eval()` in admin parser, collection groups DnD fragile, onboarding state inconsistent. See issue register. |
| **Birkly-Website** | M6 hygiene merged; embedded `Birkly-0.1/` **not synced** to platform `main`. |
| **kekaba** | M6 loader/htaccess aligned in repo; production not re-validated post-M4; embed not synced. |
| **Birkly-Docs** | On-change rule documented; ContentEditor not documented yet. |
| **Agent ops** | CI + autonomous merge active. Resend digest (M1-T8) and branch protection (M1-T12) pending human. P2-M1 review gate in PR #14. |

**Issue register:** `docs/reviews/2026-06-18-issue-register.md`

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

**Ordering:** P2-M1 (done) → P2-M2 → P2-M3a–b → P2-M4 → P2-M3c–e → P2-M5 → P2-M6 → P2-M7 → P2-M8 (ongoing) → P2-M9.

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

- **Status:** in progress (T1–T6 complete)
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
- [ ] **P2-M3-T7** Modularize commands per ARCHITECTURE_PROPOSAL Phase 1–2
- [ ] **P2-M3-T8** Internal doc `fieldtypes/ContentEditor/README.md` (not Birkly-Docs until stable)

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

- **Status:** not started
- **Repos:** Birkly
- **Goal:** First-run experience works and is testable
- **Register:** P2-R06, P2-R07
- **Refs:** `docs/onboarding-plan.md`, `docs/testing-onboarding.md`

**Tasks**

- [ ] **P2-M5-T1** Run onboarding checklist (fresh user + reset script)
- [ ] **P2-M5-T2** Fix completion/skip state after demo bootstrap
- [ ] **P2-M5-T3** Document true fresh-install test path
- [ ] **P2-M5-T4** Video: first login → onboarding complete

**Acceptance criteria:** `docs/testing-onboarding.md` checklist passes; video delivered

---

### P2-M6 — CMS hardening

- **Status:** not started
- **Repos:** Birkly
- **Goal:** Security and platform quality
- **Register:** P2-R13, P2-R14, P2-R15

**Tasks**

- [ ] **P2-M6-T1** Remove `eval()` — `docs/admin-template-eval-replacement-plan.md`
- [ ] **P2-M6-T2** Snapshot generation in dev/demo pipeline
- [ ] **P2-M6-T3** Public API metadata consistency
- [ ] **P2-M6-T4** Client/admin logging cleanup

**Acceptance criteria:** No `eval(` in template parser; snapshots available in dev smoke

---

### P2-M7 — Platform sync & kekaba proof

- **Status:** not started
- **Repos:** Birkly, Birkly-Website, kekaba
- **Goal:** Sites on current CMS; kekaba production validated
- **Depends on:** P2-M3-T1, P2-M6-T2

**Tasks**

- [ ] **P2-M7-T1** Sync `Birkly-0.1/` per `docs/client-sites/cms-vendoring.md`
- [ ] **P2-M7-T2** Dev smoke on both sites (bundle/hydrate)
- [ ] **P2-M7-T3** Kekaba production deploy smoke + video

**Acceptance criteria:** CMS reachable on host; hydrate/bundle path documented with HTTP results

---

### P2-M8 — Documentation pipeline

- **Status:** not started (ongoing across Phase 2)
- **Repos:** Birkly, Birkly-Docs

**Rule:** Internal docs first → Birkly-Docs when milestone acceptance met.

**Tasks**

- [ ] **P2-M8-T1** `docs/changelog/CHANGELOG-PLATFORM.md` — per merge batch
- [ ] **P2-M8-T2** Sync Birkly-Docs after P2-M3, P2-M4, P2-M5 acceptance
- [ ] **P2-M8-T3** Link internal specs from roadmap handoffs

---

### P2-M9 — Agent ops closure

- **Status:** not started
- **Tasks:** M1-T8 Resend, M1-T12 branch protection, daily digest habit

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

## Task handoff format

Task IDs: Phase 1 `M[N]-T[N]` · Phase 2 `P2-M[N]-T[N]`.

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
