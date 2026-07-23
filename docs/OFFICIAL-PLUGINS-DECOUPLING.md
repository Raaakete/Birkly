# Official plugins — decoupling handoff

Official Birkly extensions are being moved from the **Birkly CMS** repository to a **standalone GitHub organization** that publishes on the marketplace as an official provider.

This document is the handoff checklist for that move.

## Current state (pre-move)

| Plugin ID | Marketplace slug | Source in Birkly |
|-----------|------------------|------------------|
| `commerce` | `birkly-commerce` | `plugins/commerce/` |
| `events` | `birkly-events` | `plugins/events/` |
| `automation` | `birkly-automation` | `plugins/automation/` |
| `user_management` | `birkly-user-management` | `plugins/user_management/` |
| `studio` | `birkly-studio` | `plugins/studio/` |

Each plugin now includes **marketplace manifest v2** fields in `plugin.json`:

- `marketplace_slug`
- `requires_birkly`, `requires_php`
- `permissions`, `hooks`, `data_access`
- `dependencies` (where applicable)

Cross-plugin integration uses **installed paths** (`birkly_plugin_install_path`, `get_plugin_service`) — not hardcoded sibling folders under a monorepo.

## New organization repository layout

Create a repo (e.g. `Birkly-Official-Plugins`) with:

```
Birkly-Official-Plugins/
├── README.md
├── manifest.json              # copy from scripts/official-plugins-manifest.json
├── packages/
│   ├── commerce/
│   ├── events/
│   ├── automation/
│   ├── user_management/
│   └── studio/
├── scripts/
│   ├── build-plugin-zip.sh    # copy from Birkly; PLUGIN_DIR=packages/$id
│   ├── build-all.sh
│   └── publish-to-marketplace.sh
└── .github/workflows/publish.yml
```

### Move steps

1. Create the new GitHub org and empty repo.
2. Copy each `plugins/{id}/` → `packages/{id}/` (preserve git history with `git filter-repo` or `git subtree split` if desired).
3. Copy `scripts/build-plugin-zip.sh` and adapt `PLUGIN_DIR` to `packages/$PLUGIN_ID`.
4. Register **provider account** on marketplace for the new org.
5. Create marketplace offers matching existing slugs (`birkly-commerce`, etc.).
6. Build and upload artifacts:

   ```bash
   ./scripts/build-all.sh
   # Upload each dist/plugins/*.zip in Provider Studio
   ```

7. Verify install on a test CMS (`test.birkly.cloud`): consent modal, activate, admin pages.

## CMS repo after move

In **Birkly** (this repo):

1. Remove `plugins/commerce`, `plugins/events`, … (keep `plugins/README.md`, `sample-plugin` optional).
2. Update `scripts/cms-release-manifest.json` — `official_plugin_ids` becomes registry-only references (or remove; install via marketplace only).
3. Dev/CI continues to use `scripts/install-dev-plugins.sh` pulling from marketplace registry.
4. `scripts/publish-official-plugins.sh` moves to the new org repo (or uses provider upload API).

## Dependency graph

```
user_management  ←── (optional) commerce tier grants
commerce         ←── (optional) events paid tickets via payment_shelf
commerce, events ←── (optional) studio reference blocks
automation       ←── hooks from commerce/events (no hard dependency)
```

Install order for full stack: **user_management** → **commerce** → **events** / **automation** / **studio** (as needed).

## Marketplace preflight

Plugins with JSON API endpoints use `php://input` (commerce webhooks, etc.). The marketplace security scan allowlists `php://input` under `api/*.php` paths (see Birkly-Marketplace `core/artifact.php`).

## Provider account setup

1. Sign up on `providers.birkly.cloud` with the new org email.
2. Complete provider onboarding and agreement.
3. Create offers for each slug (or migrate existing `prov_official_birkly` offers to the new provider).
4. Upload release artifacts built from `packages/*/`.

## Verification checklist

- [ ] Each zip: `plugin.json` at archive root, `plugin.php` present
- [ ] Preflight passes on Provider Studio upload
- [ ] CMS install shows permission consent with correct manifest
- [ ] Plugin activates; admin nav appears
- [ ] Cross-plugin: events + commerce paid ticket smoke test
- [ ] Commerce + user_management tier mapping smoke test

## Related files

| File | Purpose |
|------|---------|
| `scripts/official-plugins-manifest.json` | Export manifest for new repo |
| `scripts/build-plugin-zip.sh` | Zip builder (reads `marketplace_slug` from plugin.json) |
| `scripts/publish-official-plugins.sh` | Registry publish (until provider upload) |
| `plugins/README.md` | CMS plugins directory pointer |
| `Birkly-Marketplace/docs/PLUGIN_PACKAGING.md` | Zip + manifest requirements |
