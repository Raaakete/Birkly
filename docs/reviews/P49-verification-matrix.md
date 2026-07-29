# P49 — Verification matrix (Agent Guide UX)

**Plan:** `docs/reviews/P49-agent-guide-ux-plan.md`  
**Status:** Green (2026-07-29, P49 execution)

---

## Publish feedback (M1)

| ID | Scenario | Steps | Expected | Milestone | Status |
|----|----------|-------|----------|-----------|--------|
| V-AG-01 | First publish | Complete wizard → Publish | Success modal or banner visible ≥3s | M1 | **pass** |
| V-AG-02 | Public URL in success | Click copy/open from success UI | Correct `/.well-known/agent-guide.json` | M1 | **pass** |
| V-AG-03 | Overview after publish | Return to Overview | Green **Live · vN** badge | M1 | **pass** |
| V-AG-04 | Sticky bar state | After publish without edits | Publish disabled or shows Published | M1 | **pass** |
| V-AG-05 | Edit after publish | Change draft field | **Unpublished changes** + Publish updates enabled | M1 | **pass** |
| V-AG-06 | Toast secondary | Publish | Banner/modal primary; toast optional | M1 | **pass** |

---

## Guided wizard (M3)

| ID | Scenario | Steps | Expected | Milestone | Status |
|----|----------|-------|----------|-----------|--------|
| V-AG-07 | Empty guide first visit | Open Agent Guide empty | Wizard offered (not blocking Advanced) | M3 | **pass** |
| V-AG-08 | Skip to Advanced | Choose Advanced on welcome | Essentials or tab view; no forced wizard | M3 | **pass** |
| V-AG-09 | Step why text | Each step | Why + outcome copy visible | M3 | **pass** |
| V-AG-10 | Minimum publish via wizard | Complete wizard only | Public JSON 200 | M3 | **pass** |
| V-AG-11 | Re-run wizard | Overview → Set up guide | Wizard opens; published data preserved | M3 | **pass** |
| V-AG-12 | Esc dirty draft | Close wizard mid-edit | Confirm if unsaved | M3, M11 | **pass** |

---

## Essentials vs Advanced (M4)

| ID | Scenario | Steps | Expected | Milestone | Status |
|----|----------|-------|----------|-----------|--------|
| V-AG-13 | Default view | New user skips wizard once | Essentials visible | M4 | **pass** |
| V-AG-14 | Advanced toggle | Show advanced | Full tabs appear | M4 | **pass** |
| V-AG-15 | Essentials edit | Change name + save | Draft saved; completeness updates | M4 | **pass** |

---

## AI Assist integration (M5)

| ID | Scenario | Steps | Expected | Milestone | Status |
|----|----------|-------|----------|-----------|--------|
| V-AG-16 | Wizard AI step | Suggest from sources | Suggestions; draft unchanged until Accept | M5 | **pass** |
| V-AG-17 | No AI connection | Wizard enrich step rules-only | Still completes without error | M5 | **pass** |
| V-AG-18 | Sidebar default | Fresh load | Sidebar collapsed or hidden in Essentials | M5 | **pass** |
| V-AG-19 | Bulk accept | Accept all | Confirm modal | M5, M11 | **pass** |

---

## Claims & sources (M6–M7)

| ID | Scenario | Steps | Expected | Milestone | Status |
|----|----------|-------|----------|-----------|--------|
| V-AG-20 | Narrative source | Wizard sources step | At least one narrative role source | M6 | **pass** |
| V-AG-21 | Guided claim | Add claim + URL evidence | Status can become Supported | M7 | **pass** |

---

## History & preview (M9–M10)

| ID | Scenario | Steps | Expected | Milestone | Status |
|----|----------|-------|----------|-----------|--------|
| V-AG-22 | Version timeline | Publish twice | Two entries with timestamps | M9 | **pass** |
| V-AG-23 | Preview | Ask preset question | Answer + sources list | M10 | **pass** |

---

## Hardening (M11)

| ID | Scenario | Expected | Milestone | Status |
|----|----------|----------|-----------|--------|
| V-AG-24 | Mobile 375px wizard | Usable layout | M11 | **pass** |
| V-AG-25 | Double publish click | Single publish only | M11 | **pass** |
| V-AG-26 | API error on publish | User-visible error, no silent fail | M11 | **pass** |
| V-AG-27 | Keyboard wizard | Focus trap + tab order | M11 | **pass** |

---

## Regression (M14)

| ID | Scenario | Expected | Milestone | Status |
|----|----------|----------|-----------|--------|
| V-AG-28 | `php tests/ai_presence_test.php` | PASS | M14 | **pass** |
| V-AG-29 | `scripts/smoke-agent-guide.sh` | PASS after publish | M14 | **pass** |
| V-AG-30 | Public API unchanged | `action=brand` still works | M14 | **pass** |
| V-AG-31 | MCP agent guide tools | Still readable | M14 | **pass** |

---

## Docs & closure (M13–M14)

| ID | Scenario | Expected | Milestone | Status |
|----|----------|----------|-----------|--------|
| V-AG-32 | Birkly-Docs wizard flow | Documented | M13 | **pass** |
| V-AG-33 | design-reviewer sign-off | Approved (post-fix) | M14 | **pass** |
| V-AG-34 | Human notify once | Closure message | M14 | **pass** |
