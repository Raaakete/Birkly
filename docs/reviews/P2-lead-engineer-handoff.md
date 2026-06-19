# Phase 2 — lead-engineer execution handoff

**Start after:** Phase 2 roadmap PR merged + P2-M1 review PR #14 merged (register on `main`).

**Human rule:** Birkly CMS always first unless Keven overrides.

**Autonomy:** `docs/agent-ops/AUTONOMOUS_EXECUTION.md` — merge when CI + qa-verifier pass; daily digest by 06:45 CEST.

---

## Copy-paste prompt (new Cloud Agent session)

```
You are lead-engineer for Birkly. Execute Phase 2 per docs/roadmap/ROADMAP.md (Phase 2 milestones).

READ FIRST:
- docs/roadmap/ROADMAP.md (Phase 2 section)
- docs/reviews/2026-06-18-issue-register.md (P2-R01–P2-R15)
- docs/PROJECT_VALUES.md
- docs/agent-ops/AUTONOMOUS_EXECUTION.md

CURRENT MILESTONE: P2-M2 — UX flow audit & fix program (P2-M1 complete).

EXECUTION ORDER:
1. P2-M2 — Fix P1 bugs: P2-R03 (Select options), P2-R11 (slug regen), P2-R12 (create_collection schema), P2-R15 if quick
2. Re-run UX matrix; qa-verifier sign-off; merge autonomously
3. P2-M3a — ContentEditor stabilize (P2-R01, P2-R02): alias editor→content-editor, fix demo schemas — ALL code in fieldtypes/ContentEditor/
4. P2-M3b — Visual ↔ HTML tabs (P2-R05)
5. P2-M4 — Collection groups DnD (P2-R08–P2-R09)
6. P2-M3c–e — CSS preview + components (HTML+CSS+JS+templating, self-contained in fieldtypes/ContentEditor/)
7. P2-M5 — Onboarding (P2-R06, P2-R07)
8. P2-M6–M7 — Hardening + site sync + kekaba proof
9. P2-M8 — docs/changelog + Birkly-Docs when milestones accept

CONTENTEDITOR RULES (mandatory):
- Everything in fieldtypes/ContentEditor/ only
- Components = HTML + CSS + JavaScript + Birkly templating from day one
- Stored value = pure HTML

PER TASK:
- Branch: cursor/p2-m2-<slug>-24b5 (or m2, m3, etc.)
- PR title: [P2-M2-T1] Description
- Run ./scripts/ci-check.sh and php tests/run.php before merge
- qa-verifier pass required; design-reviewer for UI
- Update roadmap checkboxes via product-planner PR or small status PR
- Write docs/changelog/CHANGELOG-PLATFORM.md entry per merge batch
- Daily digest: docs/agent-ops/daily-reports/YYYY-MM-DD.md

DO NOT ask human to review routine PRs. Escalate P0/P1 only.

Start with P2-M2-T2: fix blog status Select error (P2-R03) and demo fieldtype registration (P2-R01/R02).
```

---

## P2-M2 first tasks (immediate)

| Task | Register | Files (likely) |
|------|----------|----------------|
| P2-M2-T2a | P2-R03 | `fieldtypes/Select/`, demo blog schema/entries |
| P2-M2-T2b | P2-R01, P2-R02 | Demo `create-demo-content.php`, collection schemas, ContentEditor alias |
| P2-M2-T2c | P2-R11 | `api/admin_entries.php` or slug logic |
| P2-M2-T2d | P2-R12 | `api/admin_entries.php` create_collection validation |

**Verification:** Edit demo blog entry without PHP error; body field renders; slug stable on title-only edit.

---

## Delegation

| Work | Agent |
|------|-------|
| CMS API, fieldtypes, admin | cms-developer |
| UX flow browser testing | qa-verifier + computerUse |
| ContentEditor UI | cms-developer + design-reviewer |
| Docs / changelog | docs-writer (P2-M8, after acceptance) |
| Roadmap status | product-planner |

---

## Videos

Record short clips when fixing P2-M3, P2-M4, P2-M5 for human review (optional per milestone, required at P2-M7 kekaba prod).

## Internal specs (P2-M8-T3)

See **`docs/reviews/internal-specs-index.md`** for milestone → spec path mapping. Platform changelog: **`docs/changelog/CHANGELOG-PLATFORM.md`**.
