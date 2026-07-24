# Phase 36 — Lead-engineer handoff (CMS productization & platform depth)

**Status:** Approved — execute now (2026-07-24, Keven)  
**Human rule:** Execute **P36-M0→M30** autonomously. Notify human **once** at **P36-M30**.

**Master plan:** `docs/reviews/P36-cms-productization-platform-plan.md`  
**Gap register:** `docs/reviews/P36-gap-register.md`  
**Matrix:** `docs/reviews/P36-verification-matrix.md`

**Important:** After M1, planning docs live in **`Raaakete/Birkly-Internal`** (private). Clone/update paths in handoff below.

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 36 — CMS productization & platform depth.

Human approved P36 (2026-07-24). Execute P36-M0→M30 autonomously.
Notify human ONCE at P36-M30.

BASELINE: P35 complete. P34 deployed on *.birkly.cloud.

PAUSED until P36-M18: Phase 28 (needs clean public CMS + Ops starters).

LOCKED DECISIONS:
- Raaakete/Birkly = PUBLIC open source — product runtime ONLY
- Raaakete/Birkly-Internal = PRIVATE — tests, roadmap, reviews, agent-ops, AGENTS.md, .cursor/, CI scripts
- Product docs = Birkly-Docs (not CMS docs/ folder)
- CMS zip = runtime only (no demo-content, tests, agent files)
- Official plugin source in Internal packages/official-plugins/
- Starters on Ops; CMS downloads on onboarding
- Paths: content/collections/ + project/
- Ops sidebar: Marketplace · Platform · Marketing · System

READ FIRST (until M1 moves them — then read from Birkly-Internal):
1. Birkly-Internal/docs/reviews/P36-cms-productization-platform-plan.md (after M1)
2. scripts/cms-release-manifest.json
3. core/demo_starter_templates.php
4. Birkly-Marketplace/core/shell_nav.php

═══ WAVE 0 — PUBLIC / INTERNAL SPLIT (M0–M3) — DO FIRST ═══

P36-M0: Create private repo Raaakete/Birkly-Internal
  - README: multi-repo layout, BIRKLY_ROOT=../Birkly
  - AGENTS.md moved from Birkly (update repo table)
  - .cursor/ moved from Birkly
  - Human: add repo to Cursor Cloud environment (private)

P36-M1: Move from public Birkly → Birkly-Internal (git mv in Internal PR + delete from Birkly):
  - tests/ (entire suite)
  - docs/reviews/
  - docs/roadmap/
  - docs/agent-ops/
  - scripts/ci-check.sh, agent-smoke-test.sh, send-agent-digest.php
  - AGENTS.md, .cursor/

P36-M2: Remove from public Birkly:
  - docs/*.md legacy planning (63 files) — archive to Internal or merge to Birkly-Docs
  - docs/*.html old docs site
  - business/ if present
  - demo-content/ (after copying packs for Ops seed in M11)

P36-M3: Public Birkly polish:
  - README.md (self-hoster focused, links Birkly-Docs)
  - CONTRIBUTING.md (clone Internal for tests, or CI badge)
  - LICENSE (confirm)

P36-M8: Internal CI — tests/run.php uses getenv('BIRKLY_ROOT') default ../Birkly

═══ WAVE 1 — CMS PRODUCT (M4–M7) ═══

Release manifest v2. packages/official-plugins in Internal.
Empty content/ + project/. demo-website → project migration.

═══ WAVE 2 — OPS STARTERS (M9–M11) ═══

Ops starter admin + API. CMS onboarding from Ops. Delete demo-content.

═══ WAVE 3 — OPS & PROVIDER (M12–M15) ═══

Grouped sidebars. Feature depth per plan.

═══ WAVE 4 — DOCS & CLOSURE (M16–M30) ═══

Birkly-Docs plugin economy. Website catalog. Template install.
P36-M19: Human eyeball — public GitHub tree looks like a clean CMS product.
P36-M30: Human review pack.

DELEGATION:
- cms-developer: M2–M7, M10, M18 (Birkly public)
- lead-engineer: M0–M1, M8 (Birkly-Internal scaffold)
- frontend-developer: M12–M15, M17
- docs-writer: M16 + Birkly-Docs URL refresh
- qa-verifier: M20, M30

HUMAN TASKS:
- M0: Create private GitHub repo Birkly-Internal + add to Cursor Cloud
- M19: Browse public github.com/Raaakete/Birkly — confirm clean
- M30: Sign-off

BRANCHES:
- Birkly (public): cursor/p36-m[N]-<slug>-030a
- Birkly-Internal: cursor/p36-m[N]-<slug>-030a
- Marketplace: cursor/p36-m[N]-<slug>-030a

ORDER: M0→M3 before any public-facing polish. Never commit planning docs back to public Birkly.
```

---

## Specialist task map

| Milestone | Owner | Repo |
|-----------|-------|------|
| M0–M1, M8 | lead-engineer | Birkly-Internal (new) + Birkly |
| M2–M7, M10, M18 | cms-developer | Birkly |
| M5 | cms-developer | Internal |
| M9–M15 | lead-engineer + frontend-developer | Marketplace |
| M16 | docs-writer | Birkly-Docs |
| M17 | frontend-developer | Birkly-Website |
| M19–M30 | qa-verifier + lead-engineer | All |

---

## Human tasks (Keven)

| When | Task | Status |
|------|------|--------|
| M0 | Create private GitHub repo `Raaakete/Birkly-Internal` | pending |
| M0 | Add Birkly-Internal to Cursor Cloud environment | pending |
| M19 | Eyeball public Birkly repo on GitHub | pending |
| M30 | Human review pack sign-off | pending |
