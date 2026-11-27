# Birkly CMS — releases and versioning

Open-source self-host downloads are published as **GitHub Releases** on `Raaakete/Birkly`. Each release ships a **runtime-only** zip built with `./scripts/build-cms-release.sh` (see `scripts/cms-release-manifest.json`).

## Version scheme

| Stage | Version range | GitHub Release |
|-------|----------------|----------------|
| **Alpha** | `0.0.x` | Mark **Pre-release** |
| **Beta** | `0.1.x` | Mark **Pre-release** until you promote a beta to stable |
| **Official 1.0** | `1.0.0` | First stable public **1.x** — not pre-release |
| **Stable after 1.0** | `1.0.x`, `1.1.x`, … | Normal semver; **Latest** tracks newest stable |

Patch (`0.0.1` → `0.0.2`) = fixes on the same stage. Minor bumps change stage (`0.0.x` → `0.1.0` = beta).

The **`VERSION`** file at repo root is the installed CMS version. It must match the release tag (without the leading `v`).

## Tags and assets

- **One tag per release:** `v{VERSION}` (example: `v0.0.1`).
- **Release asset:** `birkly-release.zip` (runtime zip from `build-cms-release.sh`).
- Do not publish phase-scoped tag names (`v1.0.1-p86.2`, demo dates, etc.) on the public line.
- **GitHub “Latest”** (UI badge and API `GET /repos/.../releases/latest`) resolves to the newest **non–pre-release** only. While every 0.0.x / 0.1.x build stays marked **Pre-release**, `/releases/latest/download/...` returns **404** — use explicit tag URLs in docs and on birkly.io (`.../releases/download/v0.0.3/birkly-release.zip`). Unflagging a 0.0.x release as “latest” just to fix that URL blurs alpha vs stable; prefer tag pins until **1.0.0**.

## Maintainer checklist (new release)

1. `main` passes `./scripts/secret-scan.sh` and `./scripts/oss-history-audit.sh`.
2. Set **`VERSION`** to the new semver (e.g. `0.0.2`).
3. Run `./scripts/ci-check.sh` and `OUT=/tmp/birkly-release.zip ./scripts/build-cms-release.sh`.
4. Commit and push `main`.
5. `git tag v0.0.2 && git push origin v0.0.2`
6. Create GitHub Release: attach `birkly-release.zip`, notes, **Pre-release** if `0.0.x` or `0.1.x`.
7. Hub/Docs: announce when pins or install docs need updating (Marketplace stays private).
8. **Marketing site:** update hard-coded download fallback URLs on [birkly.io](https://birkly.io) (hero/CTA buttons) so they point at the new tag, e.g. `https://github.com/Raaakete/Birkly/releases/download/v{VERSION}/birkly-release.zip` — not only GitHub **Latest** (see below).

## Hub and update checks

CMS **Settings → Updates** uses the GitHub Releases API (`core/version.php`). Private installs need a read token until the repo is public. After **1.0.0**, prefer stable releases for the default “latest” channel.
