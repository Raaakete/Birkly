# Phase 49 — Lead-engineer handoff (Agent Guide UX excellence)

**Status:** Approved (2026-07-29, Keven) — **execute P49-M1→M14 in one run**  
**Human rule:** Execute autonomously. Notify human **once** at **P49-M14** closure.

**Master plan:** `docs/reviews/P49-agent-guide-ux-plan.md`  
**Gap register:** `docs/reviews/P49-gap-register.md`  
**Matrix:** `docs/reviews/P49-verification-matrix.md`

**Baseline:** P46/P47 complete · production publish live at `birkly.cloud` v1 · smoke script on `main`

**Product mandate:** Fully functional, hardened, polished Agent Guide for **non-developers**. **No shortcuts, no “later” phase** — all scope in P49.

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 49 — Agent Guide UX excellence.

Human approved P49 (2026-07-29, Keven). Execute P49-M1→M14 autonomously in ONE continuous run.
Notify human ONCE at P49-M14 with V-AG matrix green + design-reviewer sign-off.

GOAL: Non-developers can publish Agent Guide via guided wizard; manual mode is simple
(Essentials); publish feedback is unmissable; draft vs live always clear; version history
visible; AI Assist is contextual not competing; hardened a11y/mobile/errors.

DO NOT defer milestones to a future phase.
DO NOT add vector DB or new public schema version unless strictly required for UX.
DO NOT auto-apply AI suggestions — Accept/Reject always.
DO NOT break public API, well-known JSON, or MCP agent guide tools.

LOCKED (P49-D1–D16):
- Modal/full-screen guided wizard (7 steps): Welcome → Identity → Sources → Enrich →
  Claims → Publish → Success (skip/advanced paths)
- Essentials default manual view; Advanced toggle for full tabs
- Publish success: modal/banner + Overview Live badge + sticky bar state (not toast-only)
- Version history timeline on Publish tab
- AI Assist: contextual in wizard; sidebar collapsible/hidden in Essentials
- design-reviewer at M14; smoke-agent-guide.sh in CI path
- Birkly-Docs updated for new flows

READ FIRST:
1. docs/reviews/P49-agent-guide-ux-plan.md
2. docs/reviews/P49-gap-register.md
3. docs/reviews/P49-verification-matrix.md
4. admin/templates/ai-presence.html
5. admin/js/ai-presence.js (publish(), toast(), renderPublishStatus)
6. admin/js/ui-feedback.js (toast, confirm)
7. admin/js/hosting-wizard.js + hosting-wizard.css (wizard pattern reference)
8. docs/admin-design-system.md
9. docs/deployment/agent-guide-production-smoke.md

═══ WAVE 1 — TRUST & STATE (M1, M2) — DO FIRST ═══

P49-M1 Publish feedback:
- Success modal on first publish; banner on republish
- Overview: Live · vN chip, last published, public URL copy
- Sticky bar: Published state / Publish updates when draft dirty
- Wire ai-presence-message in sticky bar
- Matrix: V-AG-01–06

P49-M2 Overview redesign:
- Status strip (Not live / Live / Unpublished changes)
- Simplified next steps with deep links
- CTA: "Set up guide" / "Publish updates"
- Matrix: V-AG-03–05

═══ WAVE 2 — GUIDED JOURNEY (M3–M5) ═══

P49-M3 Wizard:
- New agent-guide-wizard.js + CSS (hosting-wizard pattern)
- 7 steps with why/outcome copy (i18n keys)
- setup_started_at / setup_completed_at in draft meta via API
- Matrix: V-AG-07–12

P49-M4 Essentials view:
- Default for new/returning non-advanced users
- Advanced toggle → existing tabs
- Matrix: V-AG-13–15

P49-M5 AI Assist integration:
- Suggest inside wizard Enrich step
- Collapse sidebar in Essentials; expand in Advanced
- Deduplicate field Generate vs sidebar where redundant
- Matrix: V-AG-16–19

═══ WAVE 3 — TAB POLISH (M6–M10) ═══

P49-M6 Sources simplification + narrative emphasis
P49-M7 Claims guided flow (claim + evidence inline)
P49-M8 Products & Media empty states + polish
P49-M9 Publish tab history timeline + API URL block
P49-M10 Context preview polish + preset questions

═══ WAVE 4 — HARDEN (M11–M12) ═══

P49-M11: loading, errors, mobile 375px, focus trap, double-submit guards
P49-M12: lang/en.json + de.json all new strings

═══ WAVE 5 — DOCS & CLOSURE (M13–M14) ═══

P49-M13: Birkly-Docs — wizard, essentials, publish feedback flows
P49-M14:
- tests (agent_guide_ux or extend ai_presence tests)
- ./scripts/ci-check.sh
- scripts/smoke-agent-guide.sh
- design-reviewer sign-off
- V-AG-28–34
- Human notify once

DELEGATION:
- frontend-developer: M1–M11 primary (template, JS, CSS, wizard)
- cms-developer: M10 API tweaks, M12 i18n, draft dirty API if needed
- docs-writer: M13
- design-reviewer: M14
- qa-verifier: V-AG matrix throughout M14

ACCEPTANCE: Non-dev completes first publish via wizard <15 min without Advanced tabs.
After publish, success state impossible to miss. V-AG matrix green.
```

---

## Milestone checklist

| M | Done when |
|---|-----------|
| M1 | Publish modal/banner + live badge + sticky state |
| M2 | Overview status strip + CTAs |
| M3 | 7-step wizard skippable + re-run |
| M4 | Essentials + Advanced toggle |
| M5 | AI contextual; sidebar not default noise |
| M6 | Sources UX + narrative emphasis |
| M7 | Guided claims/evidence |
| M8 | Products/Media polish |
| M9 | Version history timeline |
| M10 | Preview polish |
| M11 | Hardening matrix V-AG-24–27 |
| M12 | i18n complete |
| M13 | Birkly-Docs updated |
| M14 | Tests + design review + closure |

---

## Risk notes

| Risk | Mitigation |
|------|------------|
| Large JS refactor breaks ai-presence.js | Incremental commits per milestone; keep Advanced tabs as fallback |
| Wizard blocks power users | Always offer Skip / Advanced on step 1 |
| Modal fatigue | Success step only once; republish uses banner |
