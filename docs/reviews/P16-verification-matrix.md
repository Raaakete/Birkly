# P16 — Verification matrix

**Date:** 2026-07-07 · **Phase:** P16 beta go-live & production operations  
**Spec:** `docs/reviews/P16-P19-complete-plan.md` § Phase 16  
**Handoff:** `docs/reviews/P16-P19-lead-engineer-handoff.md`  
**Closure:** Target P19-M8 (combined wave)

## Milestone acceptance

| Milestone | Focus | Result | Evidence |
|-----------|--------|--------|----------|
| **P16-M0** | Gap register, threat model | pending | `P16-go-live-gap-register.md` |
| **P16-M1** | Deploy playbooks | pending | `P16-production-deploy-playbook.md` |
| **P16-M2** | Stripe live config | pending | `P16-stripe-live-config.md` |
| **P16-M3** | Cloud SSO prod docs | pending | cloud account settings |
| **P16-M4** | Scheduled smoke | pending | `scheduled-production-smoke.sh` |
| **P16-M5** | CSP nonces | pending | `csp_nonce_test.php` |
| **P16-M6** | kekaba live assist | pending | deploy checklist |
| **P16-M7** | Open beta copy | pending | Birkly-Website |
| **P16-M8** | Docs | pending | Birkly-Docs |

## Deploy playbooks

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| GL-01 | CMS deploy playbook complete | pending | |
| GL-02 | Marketplace deploy playbook complete | pending | |
| GL-03 | kekaba deploy playbook cross-linked | pending | |
| GL-04 | Human vs agent matrix documented | pending | |

## Stripe live

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| ST-01 | Env template lists all live vars | pending | |
| ST-02 | Paid official price mapping documented | pending | |
| ST-03 | Subscription price ID steps documented | pending | |

## Ops

| ID | Flow | Target | Result | Evidence |
|----|------|--------|--------|----------|
| OPS-01 | Scheduled smoke exits 0 locally | pending | |
| OPS-02 | Digest includes smoke timestamp | pending | |
| OPS-03 | CSP nonce on admin layout | pending | |

## Test suites

| ID | Check | Target | Result | Evidence |
|----|-------|--------|--------|----------|
| T-01 | Birkly `php tests/run.php` | pending | |
| T-02 | Marketplace `php tests/run.php` | pending | |
| T-03 | `./scripts/ci-check.sh` | pending | |
