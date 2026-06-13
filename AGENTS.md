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

## Roadmap workflow

1. **Product agent** updates `docs/roadmap/ROADMAP.md` via PR
2. **Lead agent** executes milestones, delegates to specialist subagents
3. Cross-repo tasks must list dependency order in the roadmap (CMS → sites → docs)
