# Birkly plugins directory

Official plugin packages are **not** included in the CMS release download. Install them from the [Birkly Marketplace](https://marketplace.birkly.cloud) (Admin → Plugins → Marketplace).

## Local development

Plugin **source** for building marketplace releases lives under `plugins/` in this repository (commerce, events, automation, user_management, studio). These folders are **not** shipped in CMS release zips and are **not** required for a fresh install — they exist for development and CI only.

| Plugin | Marketplace slug |
|--------|-------------------|
| commerce | [birkly-commerce](https://marketplace.birkly.cloud/offers/birkly-commerce) |
| events | [birkly-events](https://marketplace.birkly.cloud/offers/birkly-events) |
| automation | [birkly-automation](https://marketplace.birkly.cloud/offers/birkly-automation) |
| user_management | [birkly-user-management](https://marketplace.birkly.cloud/offers/birkly-user-management) |
| studio | [birkly-studio](https://marketplace.birkly.cloud/offers/birkly-studio) |

`sample-plugin` is dev/examples only and is not published to the marketplace.

If plugin folders are missing (e.g. after extracting a release zip), install from the marketplace registry:

```bash
./scripts/install-dev-plugins.sh
```

That script pulls official plugins from the production registry (`MARKETPLACE_URL`, default `https://marketplace.birkly.cloud`). Set `BIRKLY_INSTALL_FROM_REGISTRY=0` to use only local seed zips from sibling `Birkly-Marketplace/catalog/packages/`.

## Release publishing

Official plugin zips are built from this repo and published to the Marketplace registry when a `v*` tag is pushed. See `scripts/README.md` and `scripts/publish-official-plugins.sh`.

## Fresh installs

New CMS installs ship with an empty `plugins/` directory (this README and `.gitkeep` only). Use the marketplace to install and update official extensions.
