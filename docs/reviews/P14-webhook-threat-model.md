# P14 — Commerce webhook threat model

**Date:** 2026-07-07 · **Scope:** Shopify/Woo inventory webhooks (P14-M2)  
**Depends on:** P13-M4 health/ops baseline

---

## Endpoints

- `POST /api/commerce_webhooks.php?platform=shopify`
- `POST /api/commerce_webhooks.php?platform=woocommerce`

## Threats & mitigations

| Threat | Mitigation |
|--------|------------|
| Forged webhook payload | HMAC signature verification (Shopify `X-Shopify-Hmac-Sha256`, Woo signature header) |
| Replay attacks | Reject stale timestamps where platform provides; idempotent SKU updates |
| SSRF via webhook registration | Registration helper uses stored credentials only; no user-supplied fetch URLs |
| Stock manipulation | Map to known SKUs only; ignore unknown SKUs with audit log |
| DoS | Rate limit per platform secret; return 200 after validation failure to avoid retry storms (platform-specific) |

## Secrets

- Stored in `CommerceImportCredentialStore` alongside API credentials
- Admin UI: rotate webhook secret without re-import

## Test strategy

- `tests/commerce_inventory_webhook_test.php` with mock payloads and invalid signatures
