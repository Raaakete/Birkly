# Birkly plugins directory

Official plugin packages are **not** included in the CMS release download. Install them from the [Birkly Marketplace](https://marketplace.birkly.com) (Admin → Plugins → Marketplace).

## Local development

This repository keeps official plugin sources under `plugins/` for day-to-day development:

- `commerce`
- `events`
- `automation`
- `user_management`
- `sample-plugin` (dev / examples only)

If plugin folders are missing (e.g. after extracting a release zip), run:

```bash
./scripts/install-dev-plugins.sh
```

That script installs official plugins from sibling `Birkly-Marketplace/catalog/packages/` zips when available, or skips directories that are already present.

## Fresh installs

New CMS installs ship with an empty `plugins/` directory (this README and `.gitkeep` only). Use the marketplace to install and update official extensions.
