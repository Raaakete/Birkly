# P13 — Cloud SSO link threat model

**Date:** 2026-07-07 · **Scope:** Optional CMS cloud account link (P13-M1)  
**Decision:** P13-D1 — local CMS accounts remain primary; cloud link is optional.

---

## Assets

- CMS admin sessions (`users/*.json`)
- Cloud link tokens (`settings/cloud_account.json`)
- Marketplace `installation_id` + optional owner email

## Threats & mitigations

| Threat | Mitigation |
|--------|------------|
| Cloud link replaces local login | **Blocked** — local password login always available |
| Token theft via XSS | HttpOnly session; CSRF on link/unlink; tokens stored server-side only |
| CSRF on link callback | State parameter + CSRF token on link initiation |
| Token replay | Short-lived access tokens; refresh rotation |
| Privilege escalation via link | Link does not grant admin role; syncs email metadata only |
| Unlink leaves stale marketplace owner | Unlink clears `installation_owner_email` sync |

## Storage

- `settings/cloud_account.json`: `linked`, `email`, `access_token` (encrypted at rest via file permissions), `refresh_token`, `expires_at`
- Never commit tokens; never log token values

## Test strategy

- Mock OAuth callback in `tests/cloud_account_link_test.php`
- Verify local login works when link absent or revoked
