# P13 + P14 + P15 — Combined phase closure checklist

**Target:** P15-M8 — single human notification  
**Date started:** 2026-07-07

---

## Verification matrices

- [ ] `docs/reviews/P13-verification-matrix.md` — all rows **pass**
- [ ] `docs/reviews/P14-verification-matrix.md` — all rows **pass**
- [ ] `docs/reviews/P15-verification-matrix.md` — all rows **pass**

## Test suites

- [ ] `php tests/run.php` — Birkly green
- [ ] `php tests/run.php` — Birkly-Marketplace green
- [ ] `./scripts/ci-check.sh` — Birkly green
- [ ] `./scripts/production-smoke.sh` — exits 0

## Phase 13 deliverables

- [ ] P13-M0 gap register + SSO threat model
- [ ] P13-M1 cloud SSO optional link
- [ ] P13-M2 paid official tiers
- [ ] P13-M3 subscription marketplace licenses
- [ ] P13-M4 health alerts + smoke v2
- [ ] P13-M5 critical code hygiene
- [ ] P13-M6 kekaba staging smoke
- [ ] P13-M7 docs (finalized in P15-M7)

## Phase 14 deliverables

- [ ] P14-M0 commerce scale gap + webhook threat model
- [ ] P14-M1 multi-currency
- [ ] P14-M2 inventory webhooks
- [ ] P14-M3 warehouse stock
- [ ] P14-M4 Etsy + Square live import
- [ ] P14-M5 denylist spaces
- [ ] P14-M6 docs
- [ ] P14-M7 matrix green

## Phase 15 deliverables

- [ ] P15-M0 UX audit
- [ ] P15-M1 dark mode + responsive
- [ ] P15-M2 kekaba content
- [ ] P15-M3 Help → Birkly-Docs
- [ ] P15-M4 legal + feedback
- [ ] P15-M5 scaffold-plugin.php
- [ ] P15-M6 Birkly-Website production
- [ ] P15-M7 full docs sync
- [ ] P15-M8 closure + human notified **once**

## Roadmap

- [ ] `docs/roadmap/ROADMAP.md` § Phase 13, 14, 15 marked **complete**

## Human follow-up

- [ ] DNS / TLS marketplace.birkly.io
- [ ] Stripe live keys + subscription price IDs
- [ ] Legal review alpha terms
- [ ] kekaba live production deploy

---

**Closure:** `php scripts/send-agent-digest.php` + `docs/agent-ops/daily-reports/YYYY-MM-DD-p13-p15-closure.md`
