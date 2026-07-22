# P34 — Gap register (Birkly Ops & platform production)

**Status:** Open — seed at P34-M0  
**Plan:** `docs/reviews/P34-birkly-ops-platform-plan.md`

---

## Known gaps (planning seed)

| ID | Gap | Severity | Milestone |
|----|-----|----------|-----------|
| G34-01 | Entire app deployed on `ops.birkly.cloud` — no surface split | **blocker** | M1–M6 (code M1–M3 before nginx M4–M6) |
| G34-02 | No `POST /api/ops/v1/feedback` receiver | **blocker** | M15 |
| G34-03 | No install registry API or admin UI | high | M16 |
| G34-04 | No CMS release channel / updates endpoint on Ops | high | M17 |
| G34-05 | CMS default marketplace URL still `marketplace.birkly.io` | medium | M10 |
| G34-06 | Owner feedback triage still in CMS admin | medium | M19 |
| G34-07 | Provider and admin routes share one host today | **blocker** | M1–M6 |
| G34-08 | Stripe webhooks may point at wrong host after split | high | M8 |
| G34-09 | P28 J-MARKETPLACE blocked without live registry | **blocker** | M11 |
| G34-10 | Birkly-Docs marketplace install guide not production-aligned | low | M20 |

---

## Interim VPS state (Keven)

- Host: `ops.birkly.cloud`
- App: full `Birkly-Marketplace` repo
- Target: split to three vhosts on same VPS (P34-M3–M6)
