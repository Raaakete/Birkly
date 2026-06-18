# CMS vendoring policy for client sites

Client sites (Birkly-Website, kekaba, etc.) embed the platform as **`Birkly-0.1/`** — a copy of the Birkly CMS at a pinned version. They do **not** fork or patch core CMS logic in the site repo.

## Rules

1. **Platform first** — security, API, and client rendering changes land in `Raaakete/Birkly` and merge to `main` before site embeds update.
2. **One folder name** — use `Birkly-0.1/` at the site root (or document a single alternate in that site’s `DEV.md`).
3. **Copy, don’t fork** — replace the embed folder from a release/tag; avoid editing `api/`, `core/`, or `admin/` in the site repo except during intentional upgrade PRs.
4. **Site-specific code stays outside** — loaders (`components/birkly-loader.js`), publish scripts, and `.htaccess` rewrites live in the site repo.
5. **Runtime data never in git** — `content/`, `users/`, `media/`, `cache/`, `logs/`, `settings/`, `.env`.

## Upgrade workflow

1. Note the Birkly commit or milestone PR merged on platform `main`.
2. Copy platform files into `site/Birkly-0.1/` (rsync or manual), preserving site-only paths if any:
   - `site/kekaba_publish.php` (kekaba)
   - Local `content/` (never from git)
3. Verify client assets synced: `birkly-client.js`, `birkly-hydrate.js`, `birkly-client-modules/`, `api/page_bundle.php`, `api/page_snapshot.php`.
4. Run site smoke: `./serve-cms.sh` + `./serve.sh`, load index and one template page.
5. Run platform `php tests/run.php` against the embed if PHP files changed.

## Production URL layout

Sites typically expose:

- **Static site** — document root (HTML, CSS, JS)
- **CMS** — `/Birkly-0.1/` (or root rewrites for `/admin`, `/api`, `/media` into that folder — see kekaba `.htaccess`)

Loaders must set `data-cms-url` / `KEKABA_BIRKLY.cmsUrl` to the **public CMS base** (e.g. `https://example.com/Birkly-0.1`).

## Related

- `AGENTS.md` — multi-repo architecture
- Milestone M6 — site hygiene tasks
- `docs/benchmarks/kekaba-index-roundtrips.md` — client performance expectations after M4
