# Birkly plugins directory

Official plugin packages are **not** included in the CMS release download. Install them from the [Birkly Marketplace](https://marketplace.birkly.cloud) (Admin → Plugins → Marketplace).

## Fresh installs

New CMS installs ship with an empty `plugins/` directory (this README and `.gitkeep` only). Use the marketplace to install and update official extensions.

| Plugin | Marketplace slug |
|--------|-------------------|
| commerce | [birkly-commerce](https://marketplace.birkly.cloud/offers/birkly-commerce) |
| events | [birkly-events](https://marketplace.birkly.cloud/offers/birkly-events) |
| automation | [birkly-automation](https://marketplace.birkly.cloud/offers/birkly-automation) |
| user_management | [birkly-user-management](https://marketplace.birkly.cloud/offers/birkly-user-management) |
| studio | [birkly-studio](https://marketplace.birkly.cloud/offers/birkly-studio) |

## Test install: git clones + auto-sync (Dokploy)

For rapid plugin development against a **test** CMS (e.g. `test.birkly.cloud`):

1. Clone each plugin repo into the **data volume** as `/app/data/plugins/{plugin_id}` (ids: `commerce`, `events`, `automation`, `user_management`, `studio`).
2. Enable auto-sync once (config stays on the volume):

```bash
GITHUB_TOKEN=ghp_... ./scripts/plugin-dev-sync-enable.sh
```

3. Push/merge on GitHub → within ~60s the test install pulls (no manual scripts). **CMS redeploys** restart the sync loop automatically.

See `docs/deployment/docker.md` § Plugin dev git sync.

Marketplace zip installs remain the path for production sites.

## Local development (zip / Ops workspace)

`sample-plugin` is dev/examples only and is not published to the marketplace.

## Release publishing

Official plugin zips are built from the Ops internal packages and published to the Marketplace registry. See `scripts/README.md` in the Ops workspace and `scripts/build-plugin-zip.sh` in this repo.
