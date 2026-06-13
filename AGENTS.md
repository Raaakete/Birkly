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

## Cursor Cloud specific instructions

- **PHP is required but not in the base image.** It is provisioned by the `install` step in `.cursor/environment.json` (and the cloud update script): `php-cli` + common extensions (PHP 8.3). Node 22 and Python 3 are already present. There is no database — everything is flat-file.
- **Seed runtime data on a fresh VM.** `users/`, `content/`, `settings/`, etc. are gitignored, so a fresh clone has no admin user. Before testing the admin UI, run (in `Birkly/`): `php scripts/create-default-user.php` (creates `admin@localhost` / `password`) and optionally `php scripts/create-demo-content.php` (seeds Blog/Services/Contact/About). Each client site embeds its own CMS under `Birkly-0.1/` with its own empty `users/`/`content/`.
- **Services / ports** (start each in its own terminal; see repo README/`*.sh` for the canonical commands):
  | Service | Repo | Command | Port |
  |---|---|---|---|
  | Core CMS (admin + API) | `Birkly` | `php -S localhost:8080 -t . server-router.php` | 8080 |
  | Marketing site (static) | `Birkly-Website` | `./serve.sh` | 3000 |
  | Marketing site CMS (optional) | `Birkly-Website` | `./serve-cms.sh` | 8090 |
  | kekaba site (static) | `kekaba` | `./serve.sh` | 3001 |
  | kekaba CMS (blog/API) | `kekaba` | `./serve-cms.sh` | 8099 |
  | Docs (build then serve) | `Birkly-Docs` | `node build-static.js` then serve static | 3000 by default |
- **Docs port collides with the website.** `Birkly-Docs/serve-docs.sh` defaults to port 3000, same as `Birkly-Website/serve.sh`. Serve docs on a different port if running both (e.g. `python3 -m http.server 3002`). Static pages and docs must be served over HTTP (not `file://`) because they use `fetch()`.
- **Harmless admin warning:** the seed script `create-demo-content.php` defines fields with type slugs `editor`/`media`, which don't match the installed fieldtype directories (`ContentEditor`, `File`, …), so the entry editor shows "Fieldtype 'editor'/'media' not found". This is pre-existing demo-data, not an environment problem — entries still save and persist correctly.
