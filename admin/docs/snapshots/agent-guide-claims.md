# Agent Guide — claims, evidence & cross-links

## Summary

Claims, evidence, and cross-links are how Agent Guide stays **honest**: summaries tell agents the headline; links and the **sources of truth** registry tell them where to verify. This doc covers strategy, types, and worked examples — especially marketplace and review URLs.

## Four layers of truth

| Layer | What it is | Agent should… |
|-------|------------|---------------|
| **Sources of truth** | Declared registry (`sources_of_truth[]`) | Read these first for narrative and proof |
| **Summary** | Short description, positioning bullets | Paraphrase carefully; follow registry |
| **Claim** | Asserted fact with status | Repeat only if supported |
| **Evidence / source link** | URL or CMS ref with proof | Follow link to verify |

**Rule:** Don’t paste marketplace review text into Agent Guide. Store the **claim** (“Highly rated on Amazon”) + **link** to the listing + optional rating/count metadata.

## Sources of truth vs product cross-links

| Registry | Scope | When to use |
|----------|-------|-------------|
| **`sources_of_truth[]`** | Site-wide canonical reading list | About pages, primary website, key docs, proof archives |
| **Product `sources[]`** | Per-offering detail links | Product page, marketplace listing, docs, purchase URL |

Both export resolved URLs. The registry sets **global** reading order; product sources set **offering-specific** depth.

### Registry entry example

```json
{
  "id": "truth_about",
  "type": "cms_entry",
  "role": "narrative",
  "label": "About us",
  "url": "https://example.com/pages/about",
  "ref": {
    "collection": "pages",
    "entry_id": "about",
    "slug": "about",
    "label": "About us",
    "ref_type": "entry"
  },
  "priority": 80,
  "link_status": "ok"
}
```

## Claims

### Writing good claims

Good claims are:

- **Specific** — “ISO 27001 certified since 2023” not “very secure”
- **Checkable** — You can point to evidence or a proof source
- **Bounded** — Time, geography, audience where relevant

Bad claims:

- Pure opinion without qualification
- Superlatives you can’t prove (“best in the world”)
- Forward-looking guarantees without disclaimer

### Status workflow

```
pending_review → (evidence added, reviewed) → supported
                    ↓
              unsupported / expired / conflicting
```

Public export and `view=summary` only include **supported** claims.

### Claim sources vs evidence

| Mechanism | When to use |
|-----------|-------------|
| **evidence[]** + `evidence_ids` | Reusable proof documents shared across claims |
| **claim.sources[]** | Direct URLs tied only to this claim |

Example: Claim “Featured in TechCrunch 2025” → one evidence row with publication URL. Claim “4.8★ on Amazon” → evidence type `marketplace_listing` + URL; optional rating 4.8, review_count 1200.

Consider adding a **`proof`** role source in the registry when the same certification page supports multiple claims.

## Evidence

### Evidence types (reference)

| Type | Typical URL |
|------|-------------|
| `cms_content` | Resolved public CMS entry URL |
| `external_url` | Any allowed HTTPS page |
| `marketplace_listing` | `amazon.com/dp/…`, App Store, Etsy |
| `review_platform` | G2, Trustpilot, Capterra |
| `certification` | Issuer badge or certificate page |
| `publication` | News article, paper |
| `upload` | Internal — prefer public URL when possible |

### Verification

- **user_verified** — Editor confirmed link matches claim
- **unverified** — Default for new evidence

Use **Verify** in admin after checking the page live.

### Link health

**Check all links** (Overview) performs safe external HEAD requests:

| link_status | Meaning |
|-------------|---------|
| ok | 2xx/3xx response |
| redirect | Final URL differs — may update on check |
| broken | 4xx/5xx or network failure |
| unknown | Not checked yet |
| blocked | SSRF-safe filter rejected URL |

**Staleness:** Evidence URLs not verified in 180 days trigger publish warnings.

### SSRF safety

Admin URL fetch and link check block localhost, private IPs, non-HTTP schemes. Only public HTTPS/HTTP URLs.

## Cross-links (product sources)

### Source link shape

Each product source link has:

```json
{
  "id": "src_abc",
  "role": "canonical_detail",
  "type": "cms_entry",
  "label": "Widget Pro — product page",
  "url": "https://example.com/products/widget-pro",
  "ref": {
    "collection": "products",
    "entry_id": "entry_123",
    "slug": "widget-pro",
    "label": "Widget Pro"
  },
  "link_status": "ok",
  "last_verified_at": "2026-07-27T10:00:00+00:00"
}
```

CMS entries without stored URL get **resolved on publish** from collection + slug + public site base.

### Product source roles

| Role | Purpose |
|------|---------|
| canonical_detail | Authoritative full detail |
| reference | General background |
| marketplace | Where to buy / listing |
| review | Reviews and ratings |
| docs | Specs, API docs, manuals |
| proof | Supports a specific assertion |
| purchase | Checkout/pricing |
| social | Social profile |

## Worked example — Amazon reviews

**Goal:** Agents can say you’re well-reviewed without hallucinating review text.

1. **Registry:** Optional `marketplace` source with role `proof` for the listing.
2. **Claim:** “Consistently rated 4.5+ stars on Amazon US”
3. **Evidence:**
   - type: `marketplace_listing`
   - url: `https://www.amazon.com/dp/B0XXXXXX`
   - rating: 4.7
   - review_count: 842
   - verification: user_verified after manual check
4. **Do not** scrape or paste review bodies into Agent Guide
5. **Republish** when rating changes materially — set calendar reminder

## Worked example — B2B SaaS

1. **Sources:** CMS About entry (`narrative`), pricing page (`reference`), security whitepaper (`proof`)
2. **Products:** Plan names with canonical links to `/pricing` anchors
3. **Claims:** “SOC 2 Type II certified” → evidence type certification → auditor page
4. **Claims:** “Listed on G2 with 4.8 rating” → review_platform URL

## Completeness interaction

- Sources: need **narrative** source for full 15 pts
- Products: need **grounded source** for full 10 pts
- Evidence: need **URL** for full 15 pts
- Claims: need **supported + evidence_ids** for full 20 pts

Use completeness as quality gate, not checkbox filling.

## Public export & JSON-LD

Published exports include resolved `sources_of_truth[]` and product `sources[]`. Supported claims in graph views include `linked_evidence`. JSON-LD adds:

- Product `url`, `sameAs` from sources
- Claim `isBasedOn` from evidence URLs

See [Public API](../04-integration/agent-guide-public-api.md).

## Related docs

- [Workflows](agent-guide-workflows.md)
- [Best practices](agent-guide-best-practices.md)
- [Data model](agent-guide-data-model.md)

## Further reading

- [Agent Guide](agent-guide.md)
- [Agent Guide — getting started](agent-guide-getting-started.md)
- [Agent Guide — workflows](agent-guide-workflows.md)
- [Collection groups](collection-groups.md)
