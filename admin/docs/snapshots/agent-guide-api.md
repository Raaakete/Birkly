# Agent Guide — public API

## Summary

Read-only HTTP endpoints expose the **published** Agent Guide on your **public site domain**. Draft content is never served. Use these endpoints for headless sites, custom chatbots, server-side agents, and verification tools.

**Related:** [MCP & agents](agent-guide-mcp-and-agents.md) · [Data model](../02-content/agent-guide-data-model.md)

## Base URL

All URLs are on your **public site** (Settings → Site delivery), not the admin host:

```
https://YOUR-PUBLIC-SITE/
```

## Discovery channels

| Channel | URL | Format |
|---------|-----|--------|
| Well-known file (default) | `GET /.well-known/agent-guide.json` | Raw JSON export |
| Well-known file (legacy) | `GET /.well-known/brand-context.json` | Same snapshot (legacy path) |
| Public API | `GET /api/public/ai_presence.php` | JSON envelope `{ status, data }` |

Both well-known paths and the API serve the same canonical export for `action=brand` (full view) unless a `view` filter is applied on the API.

## Endpoints

| Action | URL | Returns |
|--------|-----|---------|
| Full guide | `?action=brand` or `?action=brand&view=full` | Complete enriched export |
| Summary | `?action=brand&view=summary` | Identity, positioning, supported claims, usage_hints |
| Products | `?action=brand&view=products` | `products_services[]` only |
| Claims | `?action=brand&view=claims` | Supported claims with sources |
| Single product | `?action=product&id={id}` | One product (id or name match) |
| Product graph | `?action=product&id={id}&graph=1` | Product + sources + linked claims |
| Single claim | `?action=claim&id={id}` | One supported claim |
| Claim graph | `?action=claim&id={id}&graph=1` | Claim + evidence + sources |
| Source lookup | `?action=source&url={encoded_url}` | Entities referencing URL |

### Parameters

- **id** — Product or claim `id` field, or product `name` (product action only)
- **graph** — `1` or truthy to expand related objects
- **url** — Full URL for source reverse lookup (must match published evidence/source URL exactly)

## Response envelope (API)

Success:

```json
{
  "status": "ok",
  "data": { … }
}
```

Error:

```json
{
  "status": "error",
  "message": "Product not found"
}
```

HTTP status: **404** when publishing disabled or entity not found; **400** for unknown action or missing params.

## Export metadata (all full/summary views)

| Field | Description |
|-------|-------------|
| schema_version | `agent_guide.v1` (legacy exports may show `ai_presence.v1`) |
| published_version | Integer, increments on each publish |
| generated_at | ISO 8601 publish timestamp |
| guide_meta | Product marker, summary_layer flag |
| sources_of_truth | Resolved registry of canonical sources |
| usage_hints | AI consumer guidance (full export) |
| json_ld | Optional schema.org graph when enabled in settings |

## Example — summary view

```http
GET /api/public/ai_presence.php?action=brand&view=summary
```

```json
{
  "status": "ok",
  "data": {
    "schema_version": "agent_guide.v1",
    "published_version": 3,
    "generated_at": "2026-07-27T10:00:00+00:00",
    "view": "summary",
    "guide_meta": {
      "product": "Agent Guide",
      "summary_layer": true
    },
    "sources_of_truth": [
      {
        "id": "truth_about",
        "type": "cms_entry",
        "role": "narrative",
        "label": "About",
        "url": "https://example.com/pages/about"
      }
    ],
    "identity": {
      "official_name": "Acme Co",
      "short_description": "Project management for remote teams",
      "website": "https://example.com",
      "derived_from": {
        "short_description": {
          "collection": "pages",
          "entry_id": "about",
          "slug": "about",
          "label": "About",
          "url": "https://example.com/pages/about"
        }
      }
    },
    "positioning": {
      "best_known_for": ["Async collaboration", "Simple pricing"],
      "differentiators": ["No per-seat tax"]
    },
    "supported_claims": [
      {
        "id": "claim_soc2",
        "statement": "SOC 2 Type II certified",
        "sources": []
      }
    ],
    "usage_hints": {
      "summary_layer": true,
      "follow_sources_of_truth": true,
      "claims_require_evidence": true,
      "follow_links_for": ["product specs", "pricing", "reviews"]
    }
  }
}
```

## Example — product with sources

```http
GET /api/public/ai_presence.php?action=product&id=prod_widget&graph=1
```

```json
{
  "status": "ok",
  "data": {
    "product": {
      "id": "prod_widget",
      "name": "Widget Pro",
      "type": "product",
      "short_description": "Flagship widget for teams",
      "sources": [
        {
          "role": "canonical_detail",
          "type": "cms_entry",
          "label": "Widget Pro",
          "url": "https://example.com/products/widget-pro",
          "ref": {
            "collection": "products",
            "entry_id": "e1",
            "slug": "widget-pro"
          }
        },
        {
          "role": "marketplace",
          "type": "marketplace_listing",
          "label": "Amazon US",
          "url": "https://www.amazon.com/dp/B0XXXXXX"
        }
      ]
    },
    "sources": [ "… same as product.sources …" ],
    "linked_claims": [],
    "usage_hints": {
      "follow_sources_for": "full specs, pricing, purchase links, and proof"
    }
  }
}
```

## Example — claim graph

```http
GET /api/public/ai_presence.php?action=claim&id=claim_soc2&graph=1
```

Includes `claim`, `evidence[]` (resolved items), `sources[]`, and `usage_hints.repeat_only_with_evidence`.

## Example — well-known file

```http
GET /.well-known/agent-guide.json
```

Returns raw JSON body (no `{ status, data }` wrapper). Same content as full API export including `json_ld` when enabled.

Legacy path:

```http
GET /.well-known/brand-context.json
```

Pretty-printed; suitable for crawlers and static mirrors.

## JSON-LD (optional)

When `publication_settings.include_json_ld` is true, export includes:

```json
{
  "json_ld": {
    "@context": "https://schema.org",
    "@graph": [
      { "@type": "Organization", "name": "…", "url": "…", "sameAs": ["…"] },
      { "@type": "Product", "name": "…", "url": "…", "sameAs": ["…"] },
      { "@type": "Claim", "name": "…", "isBasedOn": […] }
    ]
  }
}
```

Validate with [Google Rich Results Test](https://search.google.com/test/rich-results) if using for SEO.

## Caching

| Header | Value |
|--------|-------|
| Cache-Control | `public, max-age=300` (5 minutes) |
| X-Agent-Guide-Version | Published version integer |

Clients should re-fetch after known republish or when version header changes.

## CORS

Same origin whitelist as other Birkly public APIs: registered domains + same-host dev origins. Browser fetch from allowed origins only.

## Disabled / unpublished

Returns **404** JSON when:

- `publication_settings.enabled` is false
- No published snapshot exists

Message: `Agent Guide not published or disabled`

## Headless / separate public host

Configure **public site URL** under Settings → Site delivery. The well-known file is served on that domain — not the CMS admin host.

For `public_site_mode=project`, publish also writes:

```
project/.well-known/agent-guide.json
```

(Legacy filename `brand-context.json` when that path is configured.)

## Security

| Topic | Behavior |
|-------|----------|
| Authentication | None required (public read) |
| Draft exposure | Never — separate admin API |
| Admin API | `/api/ai_presence.php` — session auth + CSRF |
| URL fetch (admin) | SSRF-safe validation for evidence metadata |
| Rate limiting | Follows public API limits where configured |

## Admin API (not public)

Authenticated endpoints for draft edit, publish, AI suggest, link validate — see [Data model](../02-content/agent-guide-data-model.md#admin-api-authenticated).

Do not expose admin API to the public internet without normal Birkly auth.

## Integration examples

### curl smoke test

```bash
BASE="https://your-public-site.com"
curl -s "$BASE/.well-known/agent-guide.json" | jq .published_version
curl -s "$BASE/api/public/ai_presence.php?action=brand&view=summary" | jq .data.identity.official_name
```

### JavaScript (allowed origin)

```javascript
const res = await fetch('/api/public/ai_presence.php?action=brand&view=products');
const { data } = await res.json();
const products = data.products_services ?? [];
```

### Server-side agent (Node)

Fetch summary on session start; cache 5 min keyed by `X-Agent-Guide-Version`. Read `sources_of_truth` first; for product questions, call graph endpoint.

## Related docs

- [Agent Guide overview](../02-content/agent-guide.md)
- [Getting started](../02-content/agent-guide-getting-started.md)
- [MCP & agents](agent-guide-mcp-and-agents.md)
- [Website integration](website-integration.md)

## Further reading

- [Website tools (WebMCP)](webmcp-tools.md)
- [AI on localhost](ai-on-localhost.md)
- [Choose your stack](choose-your-stack.md)
- [Web components](web-components.md)
