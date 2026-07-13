# 05 — Business Model

## Philosophy

Birkly aligns revenue with **ecosystem growth**, not rent on core content storage:

- **Core CMS:** Planned **free and open source** (per Birkly-Website positioning — confirm license and timing).
- **Monetization:** Marketplace, professional services, and optional hosted/partner channels — not mandatory SaaS on content reads.

## Revenue streams

### 1. Birkly Marketplace (primary)

| Element | Model |
|---------|--------|
| Catalog | Paid and free plugins, themes, templates |
| Payments | Stripe Connect — providers onboard as merchants |
| Platform take | **[TBD: %]** on each sale (industry norm 15–30% for app stores) |
| Licenses | Per-site or per-install license keys via registry API |
| QC | Review pipeline before publish (P9-M5) |

**Status:** Marketplace app built (P9–P10); **Stripe live mode** and legal go-live are human blockers (P28).

### 2. Professional services & implementation

**[TBD]** — Agency certifications, premium support, migration packages. Not productized yet.

### 3. Hosting & cloud partners

**[TBD]** — Revenue share with hosts offering one-click Birkly. No proprietary cloud requirement today (upload-to-PHP-hosting model).

### 4. Enterprise / OEM

**[TBD]** — Multi-site, SLA, custom fieldtypes. Future option once beta stabilizes.

## Unit economics (structure — numbers TBD)

| Metric | Notes |
|--------|-------|
| **CAC** | Community, content, agency referrals — low paid spend assumed early |
| **ARPU** | Driven by marketplace purchases per install, not core subscription |
| **Gross margin** | High on software licenses; payment processing fees via Stripe |
| **Churn** | Measured at install + marketplace re-license; flat files reduce data lock-in — retention via ecosystem |

**[TBD: human input]** — target take rate, average revenue per active install, support cost assumptions.

## Pricing posture (market-facing)

| Offering | Indicative positioning |
|----------|------------------------|
| Birkly core | Free / open source (confirm) |
| Marketplace plugins | Set by providers; platform fee on paid offers |
| Commerce plugin | May include payment provider fees (Stripe, etc.) |

Avoid claiming specific prices until marketplace catalog is public and Stripe is live.

## Flywheel

```
More installs → more providers → richer catalog → more reasons to choose Birkly → more installs
```

Studio and Commerce as **anchor paid/free plugins** accelerate attach rate once marketplace is live.
