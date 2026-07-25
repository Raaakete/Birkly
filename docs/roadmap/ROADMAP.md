# Birkly Platform Roadmap (active slice)

This file tracks **current state** and the **active execution phase**. Full historical roadmap may live in `Raaakete/Birkly-Internal` when the public repo is scrubbed for OSS.

## Plan governance

| Change type | Who decides |
|-------------|-------------|
| **New phase approval** | Human + product-planner |
| **Milestone execution** | Lead-engineer autonomously within approved phase |
| **Small status updates** | Planner or lead via PR |

---

## Current state (verified 2026-07-25)

| Area | Status |
|------|--------|
| **Docker / Dokploy** | `BIRKLY_DATA=/app/data` single volume on `main` (#282–#288) |
| **Production** | `cms.birkly.cloud` (CMS) + `birkly.cloud` (marketing) on one Dokploy app |
| **Routing (temporary)** | `cms.*` hostname → admin at `/`; other hosts → `/project/` if folder exists |
| **Gap** | No settings for CMS URL vs public site URL — see **Phase 43** |

**Active plugins (dev tree):** automation, commerce, events, user_management, studio, sample-plugin

---

## Next active phase

### Phase 43 — Configurable hosting URLs (CMS vs public site)

**Status:** **Approved** (2026-07-25) — **M0 complete**, **M1→M9 execution ready**

**Goal:** Replace `cms.` hostname magic and `/project/` root hijack with explicit **CMS URL** and **optional public site URL** in settings. Support external sites (headless), bundled `project/`, and single-domain installs.

| Milestone | Focus | Status |
|-----------|--------|--------|
| **P43-M0** | Gap register + plan + handoff | complete |
| **P43-M1** | `hosting.json` + `core/hosting_urls.php` + migration | complete |
| **P43-M2** | Settings-based routing (`index.php`) | complete |
| **P43-M3** | Settings → Hosting UI + API | complete |
| **P43-M4** | Onboarding (CMS URL, public site mode) | complete |
| **P43-M5** | Clean public URLs + docker nginx | complete |
| **P43-M6** | Canonical admin host redirects | complete |
| **P43-M7** | CORS, `site_domain`, public URL helpers | complete |
| **P43-M8** | Tests + CI | complete |
| **P43-M9** | Docs (Birkly + Birkly-Docs) + closure | complete |

**Spec:** `docs/reviews/P43-configurable-hosting-urls-plan.md`  
**Handoff:** `docs/reviews/P43-lead-engineer-handoff.md`  
**Matrix:** `docs/reviews/P43-verification-matrix.md`  
**Gaps:** `docs/reviews/P43-gap-register.md`

**Human decisions (locked):** P43-D1–D10 in plan · notify once at M9 · Dokploy Internal Path always `/`

**Depends on:** Docker single-volume deploy · live `*.birkly.cloud` reference

**Paused / not in scope:** P41 design system · P42 marketing template pack · Studio P26

---

## Task handoff format

```markdown
### Task: P43-M[N]-T[N] [title]
- **Owner**: cms-developer | docs-writer | qa-verifier | design-reviewer | lead-engineer
- **Repo(s)**: Birkly | Birkly-Docs
- **Files**: path/to/files
- **Depends on**: P43-M[N]-T[N] | none
- **Acceptance criteria**:
  - ...
```

Branch template: `cursor/p43-m[N]-<slug>-030a`
