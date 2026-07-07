# P11 + P12 — Combined phase closure checklist

**Target:** P12-M8 — single human notification  
**Date started:** 2026-07-07

---

## Verification matrices

- [ ] `docs/reviews/P11-verification-matrix.md` — all rows **pass**
- [ ] `docs/reviews/P12-verification-matrix.md` — all rows **pass**

## Test suites

- [ ] `php tests/run.php` — Birkly green
- [ ] `php tests/run.php` — Birkly-Marketplace green
- [ ] `./scripts/ci-check.sh` — Birkly green

## Phase 11 deliverables

- [ ] P11-M0 gap register + matrix
- [ ] P11-M1 subscription upgrades
- [ ] P11-M2 dunning
- [ ] P11-M3 gift cards
- [ ] P11-M4 B2B quotes
- [ ] P11-M5 analytics
- [ ] P11-M6 migration wizard
- [ ] P11-M6b official CI publish on tag
- [ ] P11-M7 commerce docs (finalized in P12-M7)

## Phase 12 deliverables

- [ ] P12-M0 threat model + gap register
- [ ] P12-M1 GitHub App → QC
- [ ] P12-M2 provider webhooks, payouts, fee tiers
- [ ] P12-M3 Shopify + Woo live import
- [ ] P12-M4 denylist + membership wiring
- [ ] P12-M5 deploy smoke + kekaba checklist
- [ ] P12-M6 Birkly-Website production refresh
- [ ] P12-M7 Birkly-Docs full sync
- [ ] P12-M8 this checklist complete + human notified **once**

## Roadmap

- [ ] `docs/roadmap/ROADMAP.md` § Phase 11 marked **complete**
- [ ] `docs/roadmap/ROADMAP.md` § Phase 12 marked **complete**

## Human follow-up (agent does NOT run live production)

- [ ] DNS / TLS for marketplace.birkly.io
- [ ] Stripe live keys + Connect
- [ ] Legal review of marketplace terms
- [ ] kekaba production deploy (human executes checklist)

---

**Closure notification:** `php scripts/send-agent-digest.php` + `docs/agent-ops/daily-reports/YYYY-MM-DD-p11-p12-closure.md`
