# P34 — Gap register (Birkly Ops & platform production)

**Status:** Open — seed at P34-M0  
**Plan:** `docs/reviews/P34-birkly-ops-platform-plan.md`

---

## Known gaps (planning seed)

| ID | Gap | Severity | Milestone |
|----|-----|----------|-----------|
| G34-01 | Entire app deployed on `ops.birkly.cloud` — no surface split | **blocker** | M1–M6 — **resolved** 2026-07-23 |
| G34-02 | No `POST /api/ops/v1/feedback` receiver | **blocker** | M15 — **resolved** |
| G34-03 | No install registry API or admin UI | high | M16 — **resolved** |
| G34-04 | No CMS release channel / updates endpoint on Ops | high | M17 — **resolved** |
| G34-05 | CMS default marketplace URL still `marketplace.birkly.io` | medium | M10 — **resolved** |
| G34-06 | Owner feedback triage still in CMS admin | medium | M19 — **resolved** (gated when relay configured) |
| G34-07 | Provider and admin routes share one host today | **blocker** | M1–M6 — **resolved** 2026-07-23 |
| G34-08 | Stripe webhooks may point at wrong host after split | high | M8 |
| G34-09 | P28 J-MARKETPLACE blocked without live registry | **blocker** | M11 |
| G34-10 | Birkly-Docs marketplace install guide not production-aligned | low | M20 |

---

## Interim VPS state (Keven)

- **Ops:** `ops.birkly.cloud` — staff admin + Ops APIs
- **Provider Studio:** `providers.birkly.cloud`
- **Public marketplace:** `marketplace.birkly.cloud`
- Single Dokploy app, three domains, Volume Mount data volumes
