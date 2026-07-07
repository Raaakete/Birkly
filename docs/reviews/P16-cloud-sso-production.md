# P16 — Cloud SSO production configuration

**Date:** 2026-07-07 · **Milestone:** P16-M3  
**Threat model:** `docs/reviews/P13-sso-threat-model.md`

Optional Birkly Cloud account link for marketplace purchases. **Local CMS login remains primary.**

---

## Production endpoints

| Setting | Production value | Notes |
|---------|------------------|-------|
| `BIRKLY_CLOUD_OAUTH_URL` | `https://cloud.birkly.io` | OAuth issuer base (no trailing slash) |
| Authorization | `https://cloud.birkly.io/oauth/authorize` | Built from base URL |
| Token exchange | `https://cloud.birkly.io/oauth/token` | Server-side (when live) |
| CMS callback | `{cms-origin}/api/cloud_account.php?action=callback` | Register in cloud app |

### CMS environment

```bash
# Production .env (Birkly install or embed)
BIRKLY_CLOUD_OAUTH_URL=https://cloud.birkly.io
# Never set in production:
# BIRKLY_CLOUD_ACCOUNT_MOCK=1
```

When unset, CMS defaults to `https://cloud.birkly.io` (`core/cloud_account.php`).

---

## Settings UI behavior

| State | Admin → Account |
|-------|-----------------|
| Not linked | “Link Birkly cloud account” when OAuth endpoint reachable |
| Cloud unreachable | Graceful message; local login unaffected |
| Linked | Shows email; unlink available |
| Mock mode (dev only) | `BIRKLY_CLOUD_ACCOUNT_MOCK=1` bypasses live OAuth |

Unreachable detection: HEAD/GET probe to cloud base URL with 3s timeout. Mock mode skips probe.

---

## Human setup checklist

- [ ] Cloud app registered with client id `birkly-cms`
- [ ] Redirect URI allowlist includes each production CMS callback URL
- [ ] TLS valid on `cloud.birkly.io`
- [ ] `BIRKLY_CLOUD_OAUTH_URL` set on CMS host (or default accepted)
- [ ] Verify link flow on staging CMS before production cutover

---

## Security notes

- Tokens stored in `settings/cloud_account.json` (mode 0600)
- Status API never returns access tokens
- Unlink clears marketplace `installation_owner_email` sync

---

## Related

- `docs/reviews/P16-production-deploy-playbook.md`
- `api/cloud_account.php` · `core/cloud_account.php`
- `tests/cloud_account_link_test.php`
