# Birkly Marketplace

Standalone PHP marketplace for Birkly CMS plugins, themes, and template packs. Part of **Phase 9** — provider accounts, buyer checkout, registry API, and CMS thin client.

**Status:** P9-M0 scaffold (stubs only). M1–M7 implement commerce, catalog UI, licenses, reviews, and CMS integration.

## Quick start

**Requirements:** PHP 8.1+, no Composer or npm.

```bash
cp config.example.php config.php   # optional — defaults work for local dev
chmod +x ./start-server.sh
./start-server.sh
```

Open:

- Landing: http://localhost:8090/
- Health: http://localhost:8090/api/v1/health
- Catalog stub: http://localhost:8090/api/v1/catalog

Or run directly:

```bash
php -S localhost:8090 -t . server-router.php
```

Run tests:

```bash
php tests/run.php
```

## Environment variables

Optional `.env` is not loaded automatically in M0; use `config.php` (copy from `config.example.php`).

| Setting | Purpose | Default |
|---------|---------|---------|
| `base_url` | Public URL for links and callbacks | `http://localhost:8090` |
| `data_dir` | Flat-file JSON storage | `./data` |
| `cache_dir` | Cache files | `./cache` |
| `logs_dir` | Server and app logs | `./logs` |
| `artifacts_dir` | Published offer zip files (M2+) | `./artifacts` |
| `debug` | Verbose errors (M1+) | `false` |
| `timezone` | PHP timezone | `UTC` |

Future milestones will add Stripe Connect keys and email provider settings via `config.php`.

## Architecture overview

Dependency-free PHP with UI decoupled from logic:

```
Birkly-Marketplace/
├── index.php              # Landing page (template-driven)
├── server-router.php      # PHP built-in server router
├── core/                  # Bootstrap, router, storage, responses
├── api/v1/                # JSON API endpoints
├── templates/             # HTML presentation
├── public/                # Static CSS/JS/assets
├── data/                  # Flat-file JSON (runtime, gitignored)
├── artifacts/             # Versioned offer zips (runtime, gitignored)
└── docs/                  # Manifest schema, registry API contract
```

| Layer | Responsibility |
|-------|----------------|
| `core/` | Config, routing, flat-file storage, response helpers |
| `api/v1/` | Registry and service endpoints (JSON) |
| `templates/` | Public HTML (no business logic) |
| `data/` | Offers, providers, licenses, orders (JSON files) |

**Related repos:**

- **Birkly** — CMS core; M6 adds marketplace client tab
- **Birkly-Docs** — Provider and buyer documentation
- **Birkly-Website** — Marketing links to featured offers

See `docs/manifest-schema-v1.md` for offer listing fields and `docs/registry-api-v1.md` for the registry API contract (M2 implements).

## Do not commit

`data/`, `cache/`, `logs/`, `temp/`, `artifacts/`, `config.php`, `.env`
