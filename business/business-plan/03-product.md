# 03 — Product

*Facts sourced from `README.md`, `docs/PROJECT_VALUES.md`, `docs/roadmap/ROADMAP.md`, and marketplace docs.*

## Core CMS

| Capability | Detail |
|------------|--------|
| Runtime | PHP, HTML, CSS, JavaScript — **no npm/composer dependencies in core** |
| Storage | Flat files (JSON/Markdown) — no database required |
| Deployment | Upload to Apache/Nginx/shared hosting; `.htaccess` / sample nginx config included |
| Admin | Full browser UI at `/admin`; setup wizard for first run |
| Content model | Collections, entries, 22+ fieldtypes, nested structures, relations |
| Templating | Zero-dependency templating language (loops, conditionals, filters) |
| API | REST-style endpoints for content, media, users, settings |
| Security | Auth, permissions, CSRF; 2FA with admin-configurable grace (P27) |
| AI | Provider integration with human approval controls (roadmap P3–P5) |
| Agent/MCP | Hybrid agent tooling for admin automation (P5.1) |

## Active plugins (dev tree; excluded from core release zip)

Installed via marketplace in production model:

| Plugin | Purpose |
|--------|---------|
| **Studio** | Visual site builder, IDE, production canvas, template engine v2 |
| **Commerce** | Catalog, cart, checkout, discounts, fulfillment (P6) |
| **Events** | Event management |
| **Automation** | Workflow automation |
| **User management** | Extended user tooling |

## Birkly Marketplace (sibling repo)

Standalone PHP marketplace application:

- Provider signup, Stripe Connect, provider agreement
- Offer CRUD, artifact hosting, manifest schema validation
- Registry API, guest checkout, licenses, reviews, QC pipeline (P9–P10)
- CMS **marketplace client** for install from admin (P9-M6+)
- CMS local accounts unchanged — marketplace has separate buyer/provider accounts

Default dev port: **8090** (CMS: **8080**).

## Reference sites

| Site | Role |
|------|------|
| **Birkly-Website** | Beta marketing, waitlist, dual-audience copy |
| **kekaba** | Real client-style reference site |
| **Birkly-Docs** | Product documentation (synced at release per P27) |

## Quality & engineering maturity

- **~156 automated test suites** on CMS `main` (per P27/P28 era roadmap)
- Autonomous agent development workflow with CI (`ci-check.sh`), qa-verifier, design-reviewer gates
- Phase 28 (in execution): automated user-journey proof sites, beta launch automation

## Product principles (non-negotiable)

From `docs/PROJECT_VALUES.md`:

1. Dependency-free core
2. UI decoupled from functionality
3. Self-contained extensions
4. Security by default
5. Platform changes land in Birkly first — client sites embed `Birkly-0.1/`, do not fork core

## Roadmap snapshot (selected shipped phases)

| Phase | Delivered |
|-------|-----------|
| P9–P10 | Marketplace scaffold → go-live, plugin unbundling |
| P11–P12 | Marketplace hardening (single wave) |
| P16–P19 | Beta-ready platform, docs sync, reference projects |
| P20–P21 | Templating excellence, performance |
| P22–P25 | Studio plugin → production builder |
| P27 | Admin polish, 2FA, docs sync, help v2 |
| P28 | *In execution* — beta launch automation, journey videos |

**Paused:** P26 Studio creative suite (resumes after P28-M18 per roadmap).
