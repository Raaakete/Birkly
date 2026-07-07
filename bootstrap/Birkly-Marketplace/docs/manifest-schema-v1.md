# Marketplace offer manifest schema v1

**Status:** Draft for P9-M0 — documents listing fields for plugins, themes, and template packs.  
**Implementation:** P9-M2 (offer CRUD + registry API) validates and persists manifests.

## Overview

Each marketplace **offer** (listing) has a manifest describing metadata, pricing, compatibility, and versioned **releases**. Manifests are stored as JSON (flat-file in `data/offers/` in M2) and exposed via the registry API.

Offer types:

| `type` value | Description |
|--------------|-------------|
| `plugin` | Birkly plugin `.zip` with `plugin.json` |
| `theme` | Theme template bundle |
| `template_pack` | Exportable template/content pack |

## Top-level fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `slug` | string | yes | URL-safe unique identifier (e.g. `commerce`, `minimal-blog`) |
| `title` | string | yes | Display name |
| `summary` | string | yes | Short blurb for cards and search results |
| `description` | string | yes | Full description (Markdown allowed in M3+) |
| `type` | enum | yes | `plugin` \| `theme` \| `template_pack` |
| `category` | string | yes | Primary category slug (e.g. `commerce`, `blog`) |
| `tags` | string[] | no | Search/filter tags |
| `pricing` | enum | yes | `one_time` \| `subscription` |
| `price_cents` | integer | yes | Price in smallest currency unit (e.g. 1999 = $19.99) |
| `currency` | string | yes | ISO 4217 code (e.g. `USD`, `EUR`) |
| `license_seats` | enum | yes | Seat tier — see below |
| `requires_birkly` | string | yes | Semver range Birkly CMS must satisfy (e.g. `>=0.1.0`) |
| `requires_php` | string | yes | PHP version range (e.g. `>=8.1`) |
| `capabilities` | string[] | no | Declared plugin capabilities (e.g. `payments`, `webhooks`) |
| `screenshots` | string[] | no | URLs or paths to screenshot images |
| `provider_id` | string | yes | Marketplace provider account ID |
| `releases` | object[] | yes | Version history — see below |

### `license_seats`

| Value | Meaning |
|-------|---------|
| `single` | One CMS `installation_id` |
| `multi_3` | Up to 3 installations |
| `multi_10` | Up to 10 installations |
| `unlimited` | No seat cap (policy-defined) |

License activation uses `license_key` + `installation_id` (P9-D4); seat limits enforced at activate time (M4).

## `releases[]` entries

Each published version is one release object:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `version` | string | yes | Semver (e.g. `1.2.0`) |
| `date` | string | yes | ISO 8601 publish date |
| `sha256` | string | yes | Hex SHA-256 of artifact zip |
| `changelog` | string | yes | Release notes (Markdown in M3+) |
| `min_birkly` | string | yes | Minimum Birkly version for this release |
| `artifact_path` | string | yes | Server path or storage key for the `.zip` |

Releases are ordered newest-first in API responses. CMS update checks compare local semver against `releases[].version`.

## Example manifest

```json
{
  "slug": "example-plugin",
  "title": "Example Plugin",
  "summary": "Demonstrates marketplace manifest fields.",
  "description": "Full description for the offer detail page.",
  "type": "plugin",
  "category": "utilities",
  "tags": ["demo", "starter"],
  "pricing": "one_time",
  "price_cents": 0,
  "currency": "USD",
  "license_seats": "single",
  "requires_birkly": ">=0.1.0",
  "requires_php": ">=8.1",
  "capabilities": ["admin_page"],
  "screenshots": ["/public/screenshots/example-1.png"],
  "provider_id": "prov_abc123",
  "releases": [
    {
      "version": "1.0.0",
      "date": "2026-07-07T00:00:00Z",
      "sha256": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
      "changelog": "Initial release.",
      "min_birkly": "0.1.0",
      "artifact_path": "artifacts/example-plugin/1.0.0.zip"
    }
  ]
}
```

## Validation (M2+)

Planned checks before publish:

- `slug` unique, lowercase, `[a-z0-9-]` only
- `type`, `pricing`, `license_seats` enum values
- `price_cents` ≥ 0; `currency` valid ISO 4217
- Each `releases[].version` valid semver; `sha256` matches uploaded artifact
- Plugin zips pass automated preflight (P9-M5 QC)

## Related docs

- `registry-api-v1.md` — HTTP API for catalog and release download
- `Birkly/docs/reviews/P9-marketplace-plan.md` — Phase 9 product spec
