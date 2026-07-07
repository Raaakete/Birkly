# Phase 9 — Birkly Marketplace — lead-engineer handoff

**Status:** Approved (2026-07-07, Keven) — autonomous execution P9-M0→M7  
**Baseline:** P8 complete on `main` (**96 test suites**)  
**Human rule:** Execute **P9-M0 → P9-M7** autonomously. Notify human **once** at **P9-M7 closure**.

**Product spec:** `docs/reviews/P9-marketplace-plan.md`  
**Roadmap:** `docs/roadmap/ROADMAP.md` § Phase 9  
**Marketplace repo:** `Raaakete/Birkly-Marketplace` (must be attached to cloud environment)

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 9 — Marketplace (standalone app + CMS client).

Human approved full P9 autonomous execution (M0→M7). Notify human ONCE at P9-M7 closure only.

Read first:
- docs/reviews/P9-lead-engineer-handoff.md
- docs/reviews/P9-marketplace-plan.md
- docs/roadmap/ROADMAP.md § Phase 9
- docs/agent-ops/CLOUD_ENV_REPOS.md (if Marketplace repo missing — STOP and ask human)
- docs/plugin-system-architecture-plan.md § Supply Chain, Marketplace
- plugins/commerce/ (Stripe patterns — reference only; marketplace payments are separate)

## Context

P8 COMPLETE. CMS uses LOCAL accounts only — do NOT change onboarding or users/ auth.

Marketplace is a NEW repo: Birkly-Marketplace
- Provider accounts (sell, Stripe Connect payouts)
- Buyer = email checkout + optional marketplace login (NOT CMS SSO)
- Licenses: installation_id + seat limits
- Registry API + artifacts + QC + reviews
- CMS: thin client in Birkly (browse, license key, install, update)

## Locked decisions (do not re-ask human)

P9-D1: CMS local accounts unchanged
P9-D2: Provider account required to sell
P9-D3: Buyer email checkout; optional marketplace "My purchases" login
P9-D4: license_key + installation_id + seat tiers
P9-D5: Stripe Connect; provider is seller; Birkly application fee
P9-D6: Automated preflight + manual verified review
P9-D7: Verified-purchase reviews only
P9-D8: Repo Birkly-Marketplace
P9-D9: Platform intermediary ToS + Provider Agreement

## Deliverables

P9-M0: Marketplace repo scaffold, manifest schema, AGENTS.md, README, start-server
P9-M1: Provider signup, Stripe Connect, Provider Agreement flow
P9-M2: Offer CRUD, artifact storage, registry API v1
P9-M3: Public catalog UI + offer detail pages
P9-M4: Guest checkout + buyer login + license issuance API
P9-M5: Reviews + QC pipeline (preflight + moderation queue)
P9-M6: Birkly CMS marketplace client (Plugins tab, license, install, update)
P9-M7: Seed official plugins, Birkly-Website links, Birkly-Docs, verification matrix, closure

## Execution order

M0 → M1+M2 → M3+M4+M5 (parallel where safe) → M6 → M7

Branches: cursor/p9-m[N]-<slug>-24b5 (Marketplace and Birkly as needed)

## Delegate

- cms-developer: Marketplace backend + Birkly M6 client
- frontend-developer: Marketplace catalog UI + provider dashboard
- docs-writer: Provider guide, buyer license docs, verification matrix
- qa-verifier: Checkout, license activate, install/update, review gating
- design-reviewer: Catalog, provider dashboard, CMS marketplace tab

Run php tests/run.php on Birkly before each CMS PR merge. Merge PRs yourself.
```

---

## Milestone checklist

| ID | Deliverable | Acceptance |
|----|-------------|------------|
| **M0** | Marketplace repo scaffold | Repo cloneable; manifest schema documented |
| **M1** | Provider + Stripe Connect | Provider completes onboarding; test payout path |
| **M2** | Registry API + artifacts | Publish version; API returns release + sha256 |
| **M3** | Public catalog | Browse/search; offer detail page |
| **M4** | Checkout + licenses | Guest purchase → email license; seat activate API |
| **M5** | Reviews + QC | Unsafe zip blocked; verified purchase review only |
| **M6** | CMS client | License install + update check from registry |
| **M7** | Closure | Matrix green; seed catalog; human notified once |

---

## Branch naming

- **Birkly-Marketplace:** `cursor/p9-m[N]-<slug>-24b5`
- **Birkly:** `cursor/p9-m[N]-<slug>-24b5`
