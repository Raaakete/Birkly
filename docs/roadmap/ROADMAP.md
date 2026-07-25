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
| **Docker / Dokploy** | `BIRKLY_DATA=/app/data` single volume (#282–#288) |
| **Production** | `cms.birkly.cloud` + `birkly.cloud` on one Dokploy app |
| **P43 Hosting** | Settings-driven CMS vs public URLs on `main` — complete |
| **Admin UX gap** | Save semantics, settings IA, tokens, a11y, i18n — see **Phase 44** |

**Active plugins (dev tree):** automation, commerce, events, user_management, studio, sample-plugin

---

## Completed phase

### Phase 43 — Configurable hosting URLs (CMS vs public site)

**Status:** **Complete** (2026-07-25)

| Milestone | Focus | Status |
|-----------|--------|--------|
| P43-M0–M9 | Settings URLs, routing, Hosting API/UI, nginx, tests, docs | complete |

**Artifacts:** `docs/reviews/P43-*` · `core/hosting_urls.php` · `api/hosting.php`

---

## Next active phase

### Phase 44 — Admin UI/UX excellence (full design remediation)

**Status:** **Complete** (2026-07-25) — **M1→M12 done**

**Goal:** Fix all issues from the 2026-07-25 admin design review: trustworthy save model, grouped settings IA, hosting wizard UX, design token compliance, accessibility bugs, toast feedback (no alerts), i18n, simplified Project tab, ContentEditor mobile polish, skeleton loaders, design system doc.

| Milestone | Focus | Status |
|-----------|--------|--------|
| **P44-M0** | Gap register + plan + handoff | complete |
| **P44-M1** | Critical: a11y + global save scope | complete |
| **P44-M2** | Token compliance (sidebar, focus, links, fonts) | complete |
| **P44-M3** | Settings IA — 4 grouped areas | complete |
| **P44-M4** | Dirty state + per-area save bars | complete |
| **P44-M5** | Hosting wizard + onboarding reuse | complete |
| **P44-M6** | Project tab simple vs advanced IDE | complete |
| **P44-M7** | Toast + confirm modal (remove alerts) | complete |
| **P44-M8** | i18n audit (settings, hosting, onboarding) | complete |
| **P44-M9** | ContentEditor responsive + dark + mint | complete |
| **P44-M10** | Mobile entries, skeletons, empty states | complete |
| **P44-M11** | `docs/admin-design-system.md` | complete |
| **P44-M12** | Birkly-Docs + V-UX matrix + closure | complete |

**Spec:** `docs/reviews/P44-admin-ux-excellence-plan.md`  
**Handoff:** `docs/reviews/P44-lead-engineer-handoff.md`  
**Matrix:** `docs/reviews/P44-verification-matrix.md`  
**Gaps:** `docs/reviews/P44-gap-register.md`

**Human decisions (locked):** P44-D1–D12 · design-reviewer at M12 · notify once at M12

**Depends on:** P43 complete

**Paused / not in scope:** Studio P26 · P42 marketing template pack

---

## Task handoff format

```markdown
### Task: P44-M[N]-T[N] [title]
- **Owner**: cms-developer | frontend-developer | docs-writer | qa-verifier | design-reviewer | lead-engineer
- **Repo(s)**: Birkly | Birkly-Docs
- **Files**: path/to/files
- **Depends on**: P44-M[N]-T[N] | none
- **Acceptance criteria**:
  - ...
```

Branch template: `cursor/p44-m[N]-<slug>-030a`
