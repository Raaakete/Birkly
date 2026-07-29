# P49 — Gap register (Agent Guide UX)

**Plan:** `docs/reviews/P49-agent-guide-ux-plan.md`  
**Updated:** 2026-07-29

---

## G1 — First-time / non-developer journey

| ID | Gap | Severity | Target |
|----|-----|----------|--------|
| G1-01 | No step-by-step wizard with why/outcome | **High** | P49-M3 |
| G1-02 | 7 tabs overwhelming for first publish | **High** | P49-M4 Essentials |
| G1-03 | Intro card is passive; setup wizard removed (P46) | **High** | P49-M3 |
| G1-04 | Unclear minimum path to “live” | **Med** | P49-M2 + wizard |

---

## G2 — Publish feedback & state

| ID | Gap | Severity | Target |
|----|-----|----------|--------|
| G2-01 | Publish only shows small bottom-right toast | **High** | P49-M1 |
| G2-02 | Sticky bar still says Publish after success | **High** | P49-M1 |
| G2-03 | `ai-presence-message` never used on publish | **Med** | P49-M1 |
| G2-04 | Overview lacks prominent Live / Unpublished changes badge | **High** | P49-M1, M2 |
| G2-05 | No success screen with public URL copy | **High** | P49-M1, M3 step 7 |
| G2-06 | Version history list weak / easy to miss | **Med** | P49-M9 |

---

## G3 — Manual editing complexity

| ID | Gap | Severity | Target |
|----|-----|----------|--------|
| G3-01 | JSON fields for preferred/avoided terms | **Med** | P49-M4 Essentials uses simple UI |
| G3-02 | Duplicate AI actions (sidebar, field Generate, copilot buttons) | **High** | P49-M5, D11 |
| G3-03 | AI Assist sidebar always visible — competes with forms | **High** | P49-M5 collapsible |
| G3-04 | Sources vs Context overlap confuses users | **Med** | P49-M6 |
| G3-05 | Claims + evidence flow not guided | **Med** | P49-M7 |

---

## G4 — Hardening & polish

| ID | Gap | Severity | Target |
|----|-----|----------|--------|
| G4-01 | Mobile layout for assist + tabs untested | **Med** | P49-M11 |
| G4-02 | Some errors only in console | **Med** | P49-M11 |
| G4-03 | i18n gaps for new assist strings | **Low** | P49-M12 |
| G4-04 | Empty states inconsistent across tabs | **Med** | P49-M8, M11 |

---

## G5 — Docs

| ID | Gap | Severity | Target |
|----|-----|----------|--------|
| G5-01 | Birkly-Docs describes tabs-only workflow | **Med** | P49-M13 |
| G5-02 | No “first publish in 15 min” guide | **Med** | P49-M13 |

---

## Production baseline (2026-07-29)

| Check | Status |
|-------|--------|
| `birkly.cloud/.well-known/agent-guide.json` | **Live v1** |
| Smoke script | Pass when published |
| UX gaps above | **Open — P49 scope** |
