# Phase 36 — Lead-engineer handoff (CMS productization & platform depth)

**Status:** Proposed — execute after human approves P36 plan  
**Human rule:** Execute **P36-M0→M24** autonomously. Notify human **once** at **P36-M24**.

**Master plan:** `docs/reviews/P36-cms-productization-platform-plan.md`  
**Gap register:** `docs/reviews/P36-gap-register.md`  
**Matrix:** `docs/reviews/P36-verification-matrix.md`

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 36 — CMS productization & platform depth.

Execute P36-M0→M24 autonomously after human approves plan. Notify human ONCE at P36-M24.

BASELINE: P35 complete — Ops/Provider Birkly admin shell, SQLite, permissions, offer embed,
official plugin catalog seeds on marketplace.birkly.cloud.

PAUSED until P36-M18: Phase 28 (needs clean CMS zip + Ops starter install).

LOCKED DECISIONS (when approved):
- CMS zip: runtime only — NO demo-content, plugins source, AGENTS.md, agent scripts
- Official plugin source → packages/official-plugins/ (dev only)
- Starters hosted on Ops; CMS downloads on onboarding
- Paths: content/collections/ + project/ (not demo-website/)
- Ops sidebar groups: Marketplace · Platform · Marketing · System
- Birkly-Docs: new 06-plugin-economy/ section (8 pages)

READ FIRST:
1. docs/reviews/P36-cms-productization-platform-plan.md
2. scripts/cms-release-manifest.json
3. core/demo_starter_templates.php
4. Birkly-Marketplace/core/shell_nav.php
5. Birkly-Marketplace/views/shared/admin-shell-start.php

═══ WAVE 1 — CLEAN CMS (M1–M4) ═══

Release manifest v2. Move plugins to packages/official-plugins/.
Empty content/ + project/ skeleton. Migrate demo-website → project.

═══ WAVE 2 — OPS STARTERS (M5–M8) ═══

Ops starter pack admin + API. CMS onboarding fetches from Ops.
Port blog/business/docs demos to Ops artifacts; delete demo-content from zip.

═══ WAVE 3 — OPS DEPTH (M9–M13) ═══

Grouped sidebar. UI polish. Starter manager, featured offers, settings.
Installation detail, feedback workflow, revenue/webhook dashboard.

═══ WAVE 4 — PROVIDER DEPTH (M14–M16) ═══

Grouped nav, tabbed offer editor, analytics, payouts, drafts, media library.

═══ WAVE 5 — DOCS & WEBSITE (M17–M19) ═══

Birkly-Docs plugin economy section. Website featured catalog. URL refresh.

═══ WAVE 6 — CLOSURE (M20–M24) ═══

Template pack install path. Release CI gates. Human review pack.

DELEGATION:
- cms-developer: M1–M4, M7–M8, M20
- frontend-developer: M9–M10, M14, M18
- docs-writer: M17, M19
- qa-verifier: M21–M24

BRANCHES: cursor/p36-m[N]-<slug>-030a
```

---

## Human tasks (Keven)

| When | Task |
|------|--------|
| Before M1 | Approve P36 plan PR |
| M8 | Upload initial starter packs to Ops (or agent seeds from demo-content) |
| M24 | Unzip test CMS release — confirm clean product |
| M24 | Sign-off review pack |
