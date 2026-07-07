# P12 — Import & ecosystem threat model

**Date:** 2026-07-07 · **Phase:** P12 ecosystem closure  
**Scope:** Live Shopify/Woo import, GitHub App submission, plugin denylist, production deploy

---

## Assets

| Asset | Location | Sensitivity |
|-------|----------|-------------|
| Shopify/Woo API credentials | `settings/commerce_import.json` (encrypted) | High |
| Product catalog / PII | `content/`, order stores | Medium |
| Marketplace GitHub installation tokens | `data/providers/{id}/github.json` | High |
| Plugin denylist | `settings/plugin_denylist.json` | Medium |
| Registry artifacts | Marketplace `data/artifacts/` | High |

---

## Threats & mitigations

### T1 — Stolen import credentials exfiltrate merchant catalog

- **Mitigation:** Encrypt at rest (same pattern as payment credentials); admin-only API; CSRF; audit log on credential save
- **Mitigation:** Import runs server-side only; no credential echo in API responses

### T2 — Malicious GitHub repo → supply-chain plugin

- **Mitigation:** P12-D1 — webhook builds zip → **QC queue**; no auto-publish without admin approve
- **Mitigation:** Reuse `marketplace_preflight_zip` + security scan before enqueue

### T3 — Denylist bypass via plugin API

- **Mitigation:** P12-D6 — `get_plugin_denylist()` checked in `has_plugin_capability()` before grant (denylist-first)
- **Mitigation:** Settings UI for global denylist; tests for blocked collection access

### T4 — Quote/import token guessing

- **Mitigation:** 32+ byte random tokens; expiry; single-use accept where applicable

### T5 — Live import DoS (large catalog pull)

- **Mitigation:** Pagination; batch size limits; manual + scheduled sync (no realtime webhooks v1)
- **Mitigation:** Timeout + resumable cursor in import job state

### T6 — Production deploy misconfiguration

- **Mitigation:** P12-M5 smoke scripts check health, writable dirs, default passwords
- **Mitigation:** Human runs DNS/Stripe/legal (P12-D4) — agent delivers checklist only

---

## Out of scope (P13+)

- CMS cloud SSO (P12-D8)
- Shopify/Woo inbound webhooks for inventory sync
- External APM / SIEM integration
