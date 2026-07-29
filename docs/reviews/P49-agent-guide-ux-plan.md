# P49 — Agent Guide UX excellence (guided setup, polish, hardening)

**Status:** Approved (2026-07-29, Keven) — **next active phase**  
**Repos:** Birkly (primary) · Birkly-Docs (user guides)  
**Handoff:** `docs/reviews/P49-lead-engineer-handoff.md`  
**Matrix:** `docs/reviews/P49-verification-matrix.md`  
**Gap register:** `docs/reviews/P49-gap-register.md`

**Depends on:** P46/P47 Agent Guide feature complete on `main` · P44 admin feedback patterns · production publish verified (`birkly.cloud`)

---

## Problem

Agent Guide **works technically** (publish, well-known JSON, public API — production v1 live) but **fails the non-developer product test**:

1. **Three competing UX paths** — AI Assist sidebar, 7 manual tabs, intro text — no single clear journey.
2. **Manual editing feels like a developer form** — JSON fields, dense tabs, duplicate actions.
3. **Publish feedback is invisible** — small bottom-right toast; sticky bar unchanged; Overview does not scream **Live**.
4. **No guided first-time path** — setup wizard was merged into intro; insufficient for non-technical users.
5. **Draft vs published state** unclear after first publish; version history underdeveloped.

**Goal of P49:** One execution wave — **fully functional, hardened, polished** Agent Guide for non-developers. **No “later” milestones** outside this phase.

---

## Vision

**One default journey** for new users; **power tools** for return visits — not three parallel apps.

```
First visit / empty guide
        │
        ▼
┌─────────────────────────────┐
│  Guided setup (modal steps) │  ← default; explains why + outcome each step
│  AI suggest inside steps    │  ← optional; never auto-save
└─────────────┬───────────────┘
              ▼
┌─────────────────────────────┐
│  Essentials view (simple)   │  ← name, sources, claims, publish
│  Advanced tabs (toggle)       │  ← products, cross-links, JSON terms
└─────────────┬───────────────┘
              ▼
         Publish
              ▼
┌─────────────────────────────┐
│  Success panel (unmissable) │  ← live URL, version, timestamp, open JSON
│  Overview: Live badge       │  ← persistent state + unpublished changes
│  History timeline           │
└─────────────────────────────┘
```

**AI Assist** becomes **contextual help inside steps**, not a permanent competing sidebar (sidebar collapsible / advanced only).

---

## Locked decisions (P49-D1–D16)

| ID | Decision |
|----|----------|
| **P49-D1** | **Single phase, full delivery** — no deferred “UX v2”; all milestones M1–M14 in one lead-engineer run |
| **P49-D2** | **Guided setup wizard** — modal or full-screen steps; skippable; re-openable from Overview (“Set up guide”) |
| **P49-D3** | Wizard steps (minimum): Welcome → Identity → Sources → Claims (optional skip) → Publish → **Success** |
| **P49-D4** | Each wizard step shows: **what**, **why**, **what happens after publish** (plain language) |
| **P49-D5** | **Essentials mode** default for manual editing; **Advanced** reveals full tab set (current power UI) |
| **P49-D6** | Publish success: **primary feedback = banner/modal + Overview live state** — toast is secondary only |
| **P49-D7** | Sticky save bar: after publish show **Live vN** or **Publish updates** when draft ≠ published; use `main-actions-message` |
| **P49-D8** | Overview always shows: **Live / Draft-only / Unpublished changes** badge + last published time + public URL |
| **P49-D9** | Version **history timeline** on Publish tab with view JSON + timestamp; optional one-line change summary per version |
| **P49-D10** | AI suggestions **never** auto-apply in wizard; Accept/Reject per item; bulk accept with confirm |
| **P49-D11** | Remove or hide **duplicate** AI entry points (field Generate + sidebar + wizard) — one clear pattern per context |
| **P49-D12** | No new data model version required unless necessary — UX layer on `agent_guide.v1` |
| **P49-D13** | **design-reviewer** sign-off at M14 before closure |
| **P49-D14** | **qa-verifier** runs V-AG matrix + `scripts/smoke-agent-guide.sh` on staging/local |
| **P49-D15** | Birkly-Docs: update Agent Guide user flows (wizard, essentials, publish) — no stale P46-only screenshots text |
| **P49-D16** | Notify human **once** at P49-M14 closure |

---

## Wizard step spec (target)

| Step | User action | System | Skip allowed |
|------|-------------|--------|--------------|
| **1 Welcome** | Understand purpose; choose Guided vs Advanced | Set `setup_started_at` | Advanced → essentials view |
| **2 Identity** | Official name, short description, website (prefill from settings) | Save draft partial | No (minimum) |
| **3 Sources** | Pick 1 narrative source (CMS entry or URL) + optional collections | `sources_of_truth` | Yes with warning |
| **4 Enrich** | Optional AI “suggest from sources” OR manual positioning bullets | Copilot scoped to step | Yes |
| **5 Claims** | Add 1–3 claims + link URL evidence; explain Supported status | Claims + evidence | Yes |
| **6 Publish** | Enable public publishing; confirm public URL preview | `publish` API | No |
| **7 Success** | Copy well-known URL; open JSON; try preview question | Show version + links | — |

Re-run wizard from Overview without wiping published data (edit mode).

---

## Essentials view (simple manual mode)

Single scroll or 2-column page (not 7 tabs):

| Section | Fields |
|---------|--------|
| **Status strip** | Live badge, completeness %, last published, public URL |
| **Who we are** | Name, short description, website |
| **Sources** | Top 3 sources + “Manage all” → Sources tab |
| **Key messages** | Best known for (tags), one differentiator line |
| **Claims** | Top 3 claims status + “Manage all” |
| **Publish** | Enable toggle + Publish button + history link |

**Advanced mode** = current tabbed UI (Sources, Context, Products, Claims, Media, Publish) with IA cleanup.

---

## Publish & feedback spec

### On successful publish

1. **Success modal** (first publish) or **top banner** (subsequent) — mint border, check icon  
   - “Agent Guide is live”  
   - Version, timestamp  
   - Button: **Open public JSON** · **Copy URL** · **Go to Overview**  
2. **Overview** updates immediately — green **Live · vN** chip  
3. **Sticky bar** — `Published at HH:MM` or disabled Publish until draft dirty  
4. **Toast** — optional short confirm (not sole feedback)  
5. **Publish tab** — timeline entry for new version  

### Draft vs published clarity

| State | UI |
|-------|-----|
| Never published | Amber **Not live** — CTA wizard |
| Published, draft matches | Green **Live · vN** — “No unpublished changes” |
| Published, draft differs | Green **Live · vN** + amber **Unpublished changes** — primary **Publish updates** |
| Publishing disabled | Grey **Publishing off** — public 404 expected |

---

## Hardening scope (M11 — mandatory, not optional)

- All API errors → `BirklyFeedback.toast` + inline field errors  
- Loading overlays on wizard steps, publish, AI suggest (no double-submit)  
- Keyboard: wizard focus trap, Esc to close (with dirty confirm)  
- Mobile: wizard + essentials readable at 375px  
- Empty states per tab with single CTA  
- `aria-live` on publish status and overview badge  
- Confirm before bulk accept AI suggestions  
- Regression: existing MCP tools + public API unchanged  

---

## Milestones (all in one phase)

| ID | Focus | Owner | Status |
|----|--------|-------|--------|
| **P49-M0** | Plan, gap register, matrix, handoff | product-planner | **this PR** |
| **P49-M1** | Publish success UX + sticky bar + overview live badge | frontend-developer | **done** |
| **P49-M2** | Overview redesign (status strip, next steps, re-run wizard CTA) | frontend-developer | **done** |
| **P49-M3** | Guided setup wizard (modal, 7 steps, skip/advanced) | frontend-developer | **done** |
| **P49-M4** | Essentials simple view + Advanced toggle | frontend-developer | **done** |
| **P49-M5** | AI Assist contextual (wizard steps + collapsible sidebar) | frontend-developer | **done** |
| **P49-M6** | Sources tab simplification + narrative source emphasis | frontend-developer | **done** |
| **P49-M7** | Claims & evidence guided flow (add claim wizard inline) | frontend-developer | **done** |
| **P49-M8** | Products & Media tab polish + empty states | frontend-developer | **done** |
| **P49-M9** | Publish tab: history timeline, change hint, API URL block | frontend-developer | **done** |
| **P49-M10** | Context preview polish + sample questions | cms-developer | **done** |
| **P49-M11** | Hardening: a11y, mobile, errors, loading, double-submit | frontend-developer + qa-verifier | **done** |
| **P49-M12** | i18n en + de for all new strings | cms-developer | **done** |
| **P49-M13** | Birkly-Docs user flows + admin help snippets | docs-writer | **done** |
| **P49-M14** | Tests, V-AG matrix, design review, smoke script, closure | lead-engineer | **done** |

**Execution order:** M1→M2 (feedback/state) before M3 wizard; M4–M9 can overlap after M3 shell; M11 continuous; M14 last.

---

## Files (primary touch)

| Area | Paths |
|------|--------|
| Admin template | `admin/templates/ai-presence.html` |
| Admin JS | `admin/js/ai-presence.js` |
| Admin CSS | `admin/css/pages/ai-presence.css` |
| Wizard (new) | `admin/js/agent-guide-wizard.js`, `admin/css/components/agent-guide-wizard.css` |
| API | `api/ai_presence.php` — `setup_*` meta, optional `version_summary` |
| Core | `core/ai_presence.php` — draft dirty detection vs published |
| Tests | `tests/ai_presence_*`, new `tests/agent_guide_ux_test.js` or PHP API tests |
| Docs | Birkly-Docs `02-content/agent-guide*.md` |

Reuse: `admin/js/ui-feedback.js`, `admin/js/hosting-wizard.js` (pattern), `admin/css/components/hosting-wizard.css`

---

## Out of scope (P49)

- Vector DB / semantic search  
- New public API fields beyond version metadata for UI  
- Replacing `agent_guide.v1` schema  
- Chatbot builder on public site  
- Marketplace or Content editor changes  

---

## Success criteria (closure)

1. Non-developer can complete **first publish** via wizard alone in &lt;15 minutes without opening Advanced tabs.  
2. After publish, user **cannot miss** success state (modal/banner + Overview Live badge).  
3. Draft vs unpublished changes always visible when editing after live.  
4. Version history shows at least version number + timestamp + open JSON.  
5. AI Assist not required; works without AI connection in wizard.  
6. V-AG matrix green; `scripts/smoke-agent-guide.sh` passes after publish in test env.  
7. design-reviewer approved; Birkly-Docs reflects new flows.

---

## Branch template

`cursor/p49-m[N]-<slug>-030a`
