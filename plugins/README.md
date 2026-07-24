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

## Local development

Official plugin **source** lives in the private Ops workspace at `Birkly-Marketplace/internal/cms/packages/official-plugins/`. It is not part of the public CMS repository.

To work on plugins locally:

1. Clone the Ops repo (or use a sibling checkout at `../Birkly-Marketplace`).
2. Build a plugin zip: `BIRKLY_OPS_ROOT=../Birkly-Marketplace ./scripts/build-plugin-zip.sh <plugin-id>`
3. Install the zip via Admin → Plugins, or pull from the registry with `./scripts/install-dev-plugins.sh`.

`sample-plugin` is dev/examples only and is not published to the marketplace.

## Release publishing

Official plugin zips are built from the Ops internal packages and published to the Marketplace registry. See `scripts/README.md` in the Ops workspace and `scripts/build-plugin-zip.sh` in this repo.
