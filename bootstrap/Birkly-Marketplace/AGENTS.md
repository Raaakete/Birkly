# Birkly Marketplace

Standalone PHP app for the Birkly plugin/theme marketplace — catalog, provider accounts, buyer checkout, Stripe Connect payouts, registry API, and artifact storage.

## Repositories

| Repo | Purpose |
|------|---------|
| `Raaakete/Birkly` | Core CMS — marketplace thin client (P9-M6) |
| `Raaakete/Birkly-Marketplace` | This repo — marketplace + registry API |
| `Raaakete/Birkly-Docs` | Provider publishing and buyer license docs |
| `Raaakete/Birkly-Website` | Marketing site with marketplace links |

## Cloud multi-repo layout

In Cursor Cloud and local multi-repo setups, repos clone as siblings. Typical paths:

- `/workspace` or `/agent/repos/Birkly` — CMS core
- `/Birkly-Marketplace` or `/agent/repos/Birkly-Marketplace` — this repo

Discover actual paths with `ls` at session start; do not assume fixed locations.

## Architecture rules

- **Dependency-free PHP** — no Composer/npm required to run
- **Flat-file storage** — JSON in `data/` (no database)
- **UI decoupled from logic** — templates in `templates/`, API in `api/`, core in `core/`
- **CMS accounts unchanged** — marketplace has its own provider/buyer accounts (P9-D1)
- Do not commit runtime data: `data/`, `cache/`, `logs/`, `temp/`, `artifacts/`, `config.php`, `.env`

## Development

```bash
chmod +x ./start-server.sh
./start-server.sh
# http://localhost:8090
```

Or:

```bash
php -S localhost:8090 -t . server-router.php
```

Copy `config.example.php` to `config.php` for local overrides.

## Testing

```bash
php tests/run.php
```

Exit 0 = all suites pass. Add smoke and integration tests per milestone before merging.

Manual checks for M0:

- `GET /api/v1/health` returns `status: ok`
- `GET /api/v1/catalog` returns empty `offers` array
- Landing page loads at `/`

## Roadmap

Phase 9 milestones (see `Birkly/docs/roadmap/ROADMAP.md`):

| ID | Focus |
|----|--------|
| P9-M0 | Repo scaffold + manifest schema (this milestone) |
| P9-M1 | Provider signup + Stripe Connect |
| P9-M2 | Offer CRUD + registry API + artifacts |
| P9-M3 | Public catalog UI |
| P9-M4 | Buyer checkout + licenses |
| P9-M5 | Reviews + QC pipeline |
| P9-M6 | CMS marketplace client (Birkly repo) |
| P9-M7 | Seed catalog, docs, closure |

Spec: `Birkly/docs/reviews/P9-marketplace-plan.md`

## Project values

Align with `Birkly/docs/PROJECT_VALUES.md`:

- Dependency-free core (PHP, HTML, CSS, JS only)
- Presentation separated from business logic
- Security by default (auth on write endpoints in M1+)
- Portable flat-file data
