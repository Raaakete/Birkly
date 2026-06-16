# Birkly Platform

Flat-file PHP CMS and its client sites. This repo (`Birkly`) is the **platform core**; everything else is a sub-project.

## Repositories

| Repo | Purpose |
|------|---------|
| `Raaakete/Birkly` | Core CMS — admin, API, fieldtypes, plugins, templating (this repo) |
| `Raaakete/Birkly-Website` | Birkly marketing / beta site |
| `Raaakete/kekaba` | Kekaba client site |
| `Raaakete/Birkly-Docs` | Product and API documentation |

## Cloud VM layout

In a multi-repo cloud environment, repos clone as sibling directories. Discover paths with `ls` at session start — do not assume fixed absolute paths.

## Architecture rules

- Platform changes land in **Birkly first**; client sites follow
- Client sites embed CMS as `Birkly-0.1/` — do not fork CMS logic into site repos
- Read `docs/roadmap/ROADMAP.md` before implementing any milestone task
- Do not commit runtime data: `users/`, `cache/`, `logs/`, `temp/`, `media/`, `settings/`, `.env`, `config.php`

## Development

```bash
chmod +x ./start-server.sh
./start-server.sh
# Admin: http://localhost:8080/admin
```

Or directly:

```bash
php -S localhost:8080 -t . server-router.php
```

## Testing

Run existing tests or manual smoke checks before opening PRs. Verify admin login, API endpoints, and affected templates when changing core CMS code.

## Cursor Cloud specific instructions

- Cloud VMs run Ubuntu. PHP is installed via `.cursor/environment.json` `install` script.
- **Multi-repo layout:** In a multi-repo environment, repos clone as siblings (e.g. `/workspace` = Birkly, siblings nearby). If only `/workspace` exists with one repo, the multi-repo environment is not attached — report FAIL and ask the human to fix the cloud environment in the dashboard.
- CMS dev server (Birkly repo root): `php -S localhost:8080 -t . server-router.php`
- Client sites are static HTML; serve with `python3 -m http.server 8000` when needed.
- Do not commit runtime data (see Architecture rules above).

## Roadmap workflow

1. **Initial plan:** Human and **product-planner** collaborate; planner waits for explicit approval before the first roadmap PR
2. **Later updates:** Planner may apply **small** roadmap edits alone (status, subtasks, clarifications); **major** changes require human approval first — see `docs/roadmap/ROADMAP.md` § Plan governance
3. **Lead agent** executes approved milestones; delegates to specialist subagents
4. Cross-repo tasks must list dependency order in the roadmap (CMS → sites → docs)
