# Birkly

A flat-file, modular CMS — upload and run. No database, no build step.

## Quick start

1. Extract the release zip on your server (or run locally).
2. Open **`0_START-HERE.html`** for setup steps.
3. Start the built-in server for local testing:

```bash
chmod +x ./start-server.sh
./start-server.sh
```

Admin: [http://localhost:8080/admin](http://localhost:8080/admin)

Create the default admin user:

```bash
php scripts/create-default-user.php
```

Default login after that script: `admin@localhost` / `password` — change it immediately.

## Where your site lives

| Path | Purpose |
|------|---------|
| `content/collections/` | Entries and collection schemas (JSON) |
| `project/` | Public website files (HTML, CSS, assets) |
| `media/` | Uploaded files |
| `plugins/` | Installed extensions (empty on fresh install) |
| `settings/` | Site and plugin configuration |

Install plugins from the [Birkly Marketplace](https://marketplace.birkly.cloud) or upload a plugin zip in **Settings → Plugins**.

## Documentation

- **[Birkly-Docs](https://github.com/Raaakete/Birkly-Docs)** — guides, API reference, plugin development
- **[CONTRIBUTING.md](CONTRIBUTING.md)** — issues and pull requests for the open-source CMS

## Production

1. Upload the release files to your web host (Apache or Nginx).
2. Apache: use the included `.htaccess` when available.
3. Nginx: see `nginx.conf.example` in the full development repository.
4. Point your domain at the install; admin is at `/admin`.

**Docker / Dokploy:** one volume at `/app/data` — see [docs/deployment/docker.md](docs/deployment/docker.md).

## Local development (maintainers)

Official plugin sources and the full test suite live in the private **Birkly-Marketplace** ops repository. Clone it alongside this repo and run CI from there — see `CONTRIBUTING.md`.

```bash
./scripts/install-dev-plugins.sh   # optional: install official plugins for dev
```

## License

See [LICENSE](LICENSE).
