# P16 — Go-live gap register

**Date:** 2026-07-07 · **Phase:** P16 beta go-live & production operations  
**Threat model:** `docs/reviews/P16-prod-secrets-threat-model.md`  
**Spec:** `docs/reviews/P16-P19-complete-plan.md` § Phase 16

## Gap summary

| Area | P15 baseline | P16 target | Milestone |
|------|--------------|------------|-----------|
| Deploy playbooks | Checklists only | Per-repo master playbooks | M1 |
| Stripe live | Placeholder prices | Env templates + live guide | M2 |
| Cloud SSO prod | Mock OAuth | Production endpoint docs | M3 |
| Scheduled smoke | Manual script | Cron wrapper + digest | M4 |
| CSP | TODO in validate.php | Admin nonce pass | M5 |
| kekaba live | Staging smoke | Live checklist hardened | M6 |
| Open beta | Closed alpha copy | Invite/waitlist beta | M7 |

## Human-only (P16-D1)

DNS/TLS, Stripe live keys, legal review, kekaba live deploy — agent delivers playbooks only.
