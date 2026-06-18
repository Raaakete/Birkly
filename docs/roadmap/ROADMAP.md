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

**2026-06-16** — Initial roadmap approved by human (Keven).

**Priorities (next 4–8 weeks):** **A** agent infrastructure + **B** CMS stability (security, templating consolidation, client rendering performance). Client sites and docs are **maintenance-only**, gated on CMS milestones.

**Governance artifacts:** `docs/PROJECT_VALUES.md`, `docs/agent-ops/REPORTING.md`, `docs/agent-ops/AUTONOMOUS_EXECUTION.md` (daily digest 07:00 CEST; agents merge in-scope work without per-PR human review).

**Autonomous execution approved:** **2026-06-18** — human approves milestones; agent team tests, reviews, and merges within task IDs.

**Analysis basis:** Code inspection and smoke tests (June 2026) — not legacy internal planning docs alone.

---

## Vision

Build a reliable, **dependency-free** flat-file CMS (Birkly) that is simple, secure, flexible, and robust — with UI decoupled from functionality so design can evolve without breaking features. Maintain two reference client sites (Birkly-Website, kekaba) and synced documentation (Birkly-Docs), driven by a cloud agent team with clear milestones, values alignment, and daily reporting.

**Values:** See `docs/PROJECT_VALUES.md`.

**Reporting:** See `docs/agent-ops/REPORTING.md`.

**Autonomous execution:** See `docs/agent-ops/AUTONOMOUS_EXECUTION.md`.

---

## Current state (verified June 2026)

| Repo | Status |
|------|--------|
| **Birkly (CMS)** | Feature-rich; **not production-ready** as a security boundary. Critical gaps: open `api/content.php` GET, legacy `api/media.php`, three templating stacks, ~5.5k-line client renderer with N+1 API calls, no CI/PHPUnit. |
| **Birkly-Website** | Beta marketing site on GitHub; embedded `Birkly-0.1/`; docs vendored under `docs/`; `beta_*` CMS content not in git; 4 broken homepage doc links. |
| **kekaba** | Full site in repo (14 HTML pages, bake/publish pipeline); **most demanding Birkly client**; production CMS path issues documented; `Birkly-0.1/content/` not in git. |
| **Birkly-Docs** | 38 topics, working build; last changelog Feb 2025; not wired into CMS Help. |
| **Agent infra** | Roles + cloud bootstrap ~50% done; multi-repo cloud env working in current VM; smoke test + digest scripts added in M1 PR. |

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

- **Status:** in progress
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

- **Status:** not started
- **Repos:** Birkly
- **Goal:** Regressions caught automatically
- **Values:** Robust, inspectable
- **Depends on:** M1-T9 (runs in parallel from week 2)

**Tasks**

- [ ] **M5-T1** PHPUnit (or equivalent) for auth boundaries
- [ ] **M5-T2** Template parser directive tests
- [ ] **M5-T3** Security regression fixtures in `tests/fixtures/`
- [ ] **M5-T4** Update `qa-verifier` agent with runnable harness reference

**Acceptance criteria**

- CI fails on auth regression
- qa-verifier can run documented test command

---

### Milestone 6: Site hygiene (minimal, gated)

- **Status:** not started
- **Repos:** Birkly-Website, kekaba, Birkly-Docs
- **Goal:** Sites do not block CMS work; no new features
- **Depends on:** M4-T4 (kekaba deploy alignment after CMS snapshots stable)

**Tasks**

- [ ] **M6-T1** Birkly-Website: fix 4 broken homepage doc links; add `DEV.md`
- [ ] **M6-T2** Document CMS vendoring policy (`Birkly-0.1/` sync from releases)
- [ ] **M6-T3** kekaba: align loader CMS URL + root `.htaccess` (one folder name)
- [ ] **M6-T4** Birkly-Docs: on-change update rule documented in README

**Acceptance criteria**

- Birkly-Website fresh-clone dev setup documented
- kekaba CMS URL reachable on host (minimal deploy pass)
- No site feature work beyond hygiene

---

## Success at 4–8 weeks

| Repo | Target |
|------|--------|
| **Birkly** | Security boundary closed; one template language; client perf improved; CI green |
| **Birkly-Website** | Dev setup documented; broken links fixed |
| **kekaba** | CMS URL reachable; loader/htaccess aligned — no new features |
| **Birkly-Docs** | On-change updates when CMS milestones ship |
| **Agent ops** | Daily 07:00 CEST email digest + values gate on all PRs |

---

## Task handoff format

Task IDs: `M[N]-T[N]` (e.g. `M2-T3`).

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
