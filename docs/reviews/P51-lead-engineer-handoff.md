# Phase 51 — Lead-engineer handoff (SSR & shared template engine)

**Status:** **Complete** (2026-07-31) — P51-M1→M6 executed; human notified at M6  
**Human rule:** Execute autonomously. Notify human **once** at P51-M6 with verification matrix green + design-reviewer sign-off on admin project settings UX.

**Master plan:** `docs/reviews/P51-ssr-shared-template-engine-plan.md`  
**Gap register:** `docs/reviews/P51-gap-register.md`  
**Matrix:** `docs/reviews/P51-verification-matrix.md`

---

## PR strategy

| Wave | Branch | Contents |
|------|--------|----------|
| 1 | `cursor/p51-ssr-shared-template-engine-afa4` | M1 + M2 (+ M3 if ready) |
| 2 | same or follow-up | M4 + M5 + M6 |

Base branch: `main`.

---

## Delegation

| Milestone | Agent |
|-----------|--------|
| M1, M2, M5 | cms-developer |
| M3 | frontend-developer + cms-developer |
| M4 | frontend-developer |
| M6 | lead-engineer + qa-verifier + design-reviewer |

---

## Hard constraints

- No third-party frontend frameworks.
- No build-time static export / zip download.
- Do not change template language syntax for authors.
- Do not modify `birkly-client.js` until M1 and M2 are stable and tested.
