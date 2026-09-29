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

Create the first admin user when the browser setup has not done it yet:

```bash
BIRKLY_ADMIN_PASSWORD='YourPassword123!' php scripts/create-default-user.php
```

The default email is `admin@localhost`. Pass `--email=you@example.com` to use another. If an admin account already exists, the script leaves it unchanged.

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
- **[SECURITY.md](SECURITY.md)** — vulnerability reporting and secure self-hosting

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

Birkly is released under the GNU Affero General Public License v3.0 or any later version. See [LICENSE](LICENSE).

The Map field includes [Leaflet](https://leafletjs.com) 1.9.4 so the admin map works without loading it from another website. Leaflet stays under the BSD 2-Clause license in `fieldtypes/Map/leaflet/LICENSE`. That license applies only to those files.
