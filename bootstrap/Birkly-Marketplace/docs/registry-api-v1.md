# Registry API v1 (stub contract)

**Status:** P9-M0 stub — endpoints return minimal payloads. **P9-M2** implements full offer CRUD, artifact storage, and authenticated provider routes.

**Base URL:** `{marketplace_url}/api/v1` (default `https://marketplace.birkly.com/api/v1`)

**Format:** JSON, UTF-8. Errors use `{ "error": "code", ... }` with appropriate HTTP status.

**Auth:** Public read endpoints require no auth in v1. Provider write endpoints use session auth (M1) or API tokens (future). License and checkout endpoints added in M4.

---

## Public endpoints

### `GET /health`

Service health check for deploy verification and monitoring.

**Response 200:**

```json
{
  "status": "ok",
  "service": "birkly-marketplace",
  "version": "0.1.0-m0",
  "php": "8.3.x",
  "timestamp": "2026-07-07T10:00:00+00:00"
}
```

**M0 implementation:** `api/v1/health.php` — live.

---

### `GET /catalog`

List published offers for public catalog and CMS marketplace client.

**Query parameters (M3):**

| Param | Type | Description |
|-------|------|-------------|
| `q` | string | Search query |
| `type` | string | Filter: `plugin`, `theme`, `template_pack` |
| `category` | string | Category slug |
| `page` | int | Page number (default 1) |
| `per_page` | int | Page size (default 20, max 100) |

**Response 200 (M0 stub):**

```json
{
  "offers": [],
  "meta": {
    "count": 0,
    "page": 1,
    "per_page": 20
  }
}
```

**Response 200 (M2+ target):**

```json
{
  "offers": [
    {
      "slug": "example-plugin",
      "title": "Example Plugin",
      "summary": "Short summary.",
      "type": "plugin",
      "category": "utilities",
      "tags": ["demo"],
      "pricing": "one_time",
      "price_cents": 1999,
      "currency": "USD",
      "license_seats": "single",
      "requires_birkly": ">=0.1.0",
      "requires_php": ">=8.1",
      "screenshots": [],
      "provider_id": "prov_abc123",
      "latest_version": "1.0.0",
      "rating_avg": null,
      "review_count": 0
    }
  ],
  "meta": {
    "count": 1,
    "page": 1,
    "per_page": 20
  }
}
```

**M0 implementation:** `api/v1/catalog.php` — returns empty `offers`.

---

### `GET /offers/{slug}`

Single offer detail including full `releases[]` per manifest schema.

**Response 200:** Full offer manifest (see `manifest-schema-v1.md`).

**Response 404:** `{ "error": "offer_not_found" }`

**M0:** Not implemented (404). **M2:** Implement.

---

### `GET /offers/{slug}/releases/{version}/download`

Download artifact zip for a licensed or public offer version.

**Headers (M4+):** Optional `Authorization: Bearer {license_key}` or query `license_key` for paid offers.

**Response 200:** `application/zip` stream.

**Response 403:** `{ "error": "license_required" }`

**M0:** Not implemented. **M2/M4:** Implement with checksum verification.

---

## Provider endpoints (M1–M2)

Authenticated provider session required.

| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/provider/offers` | Create offer draft |
| `PUT` | `/provider/offers/{slug}` | Update offer metadata |
| `POST` | `/provider/offers/{slug}/releases` | Upload new version + artifact |
| `POST` | `/provider/offers/{slug}/submit` | Submit for QC review |
| `GET` | `/provider/offers` | List provider's offers |

Request/response shapes follow `manifest-schema-v1.md`. Validation errors return `422` with `{ "error": "validation_failed", "fields": { ... } }`.

---

## License endpoints (M4)

| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/licenses/activate` | `license_key` + `installation_id` → seat check |
| `GET` | `/licenses/validate` | Check license status for CMS install |
| `POST` | `/checkout/guest` | Email checkout → license key email |

---

## CMS integration (M6)

Birkly CMS marketplace client calls:

1. `GET /catalog` — browse tab
2. `GET /offers/{slug}` — detail / update check
3. `GET /offers/{slug}/releases/{version}/download` — install/upgrade
4. `POST /licenses/activate` — bind license to `installation_id`

CMS setting: `marketplace_url` (default `https://marketplace.birkly.com`).

---

## Versioning

- URL path includes `/v1/` for breaking changes.
- Additive fields may appear without version bump.
- Deprecation announced in changelog before `/v2/` cutover.

---

## Related docs

- `manifest-schema-v1.md` — offer and release field definitions
- `Birkly/docs/reviews/P9-marketplace-plan.md` — payments, QC, reviews
