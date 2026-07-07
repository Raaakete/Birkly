# P16 — Production secrets threat model

**Date:** 2026-07-07 · **Scope:** Beta go-live secrets handling

## Secrets inventory

| Secret | Location | Agent commits? |
|--------|----------|----------------|
| Stripe live keys | Marketplace `.env` | Never |
| Stripe webhook secret | Marketplace `.env` | Never |
| `BIRKLY_CLOUD_OAUTH_*` | CMS env / settings | Never |
| `RESEND_API_KEY` | Cloud env | Never |
| Marketplace registry token | CI secrets | Never |

## Mitigations

- `.env.example` only in repo; document every var in P16-stripe-live-config.md
- Health/smoke scripts never log secrets
- Deploy playbooks mark human vs agent steps explicitly
