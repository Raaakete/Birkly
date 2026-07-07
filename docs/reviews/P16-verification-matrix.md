# P16 — Verification matrix

**Date:** 2026-07-07 · **Phase:** P16 beta go-live & production operations  
**Spec:** `docs/reviews/P16-P19-complete-plan.md` § Phase 16  
**Handoff:** `docs/reviews/P16-P19-lead-engineer-handoff.md`  
**Closure:** P19-M8 (combined wave) — **complete**

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P16-M0** | Gap register, threat model | pass | `P16-go-live-gap-register.md`, `P16-prod-secrets-threat-model.md` |
| **P16-M1** | Deploy playbooks | pass | `P16-production-deploy-playbook.md`, Marketplace runbook, Website DEV.md |
| **P16-M2** | Stripe live config | pass | `P16-stripe-live-config.md`, `.env.example` |
| **P16-M3** | Cloud SSO prod docs | pass | `P16-cloud-sso-production.md`, `core/cloud_account.php` graceful OAuth |
| **P16-M4** | Scheduled smoke | pass | `scripts/scheduled-production-smoke.sh`, digest smoke timestamp |
| **P16-M5** | CSP nonces | pass | `core/csp.php`, `tests/csp_nonce_test.php` |
| **P16-M6** | kekaba live assist | pass | `docs/client-sites/kekaba-deploy-checklist.md` live section |
| **P16-M7** | Open beta copy | pass | Birkly-Website open-beta/waitlist, admin legal footer |
| **P16-M8** | Docs | pass | Birkly-Docs `07-operations/production-deploy-checklist.md` |

## Deploy playbooks

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| GL-01 | CMS deploy playbook complete | pass | `docs/reviews/P16-production-deploy-playbook.md` |
| GL-02 | Marketplace deploy playbook complete | pass | Marketplace `docs/DEPLOY.md` |
| GL-03 | kekaba deploy playbook cross-linked | pass | `docs/client-sites/kekaba-deploy-checklist.md` |
| GL-04 | Human vs agent matrix documented | pass | P16-D1 in complete plan; playbooks § Human-only |

## Stripe live

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| ST-01 | Env template lists all live vars | pass | `.env.example`, `P16-stripe-live-config.md` |
| ST-02 | Paid official price mapping documented | pass | `P16-stripe-live-config.md` § price IDs |
| ST-03 | Subscription price ID steps documented | pass | `P16-stripe-live-config.md` § subscriptions |

## Ops

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| OPS-01 | Scheduled smoke exits 0 locally | pass | `./scripts/scheduled-production-smoke.sh` (Birkly + Marketplace) |
| OPS-02 | Digest includes smoke timestamp | pass | `scripts/send-agent-digest.php` smoke line |
| OPS-03 | CSP nonce on admin layout | pass | `csp_nonce_test.php` — login + admin shell |

## Test suites

| ID | Check | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| T-01 | Birkly `php tests/run.php` | pass | 135 suites PASS (2026-07-07) |
| T-02 | Marketplace `php tests/run.php` | pass | 17 suites PASS (2026-07-07) |
| T-03 | `./scripts/ci-check.sh` | pass | PASS (2026-07-07) |
