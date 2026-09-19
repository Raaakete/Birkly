# Prebuilt Birkly Launch binaries

Ops and other environments without Go use these artifacts when running `scripts/build-birkly-launch.sh`.

Refresh after launcher source changes:

**On a Mac (universal binary + ad-hoc sign):** run the GitHub Action **Refresh launchers** (`refresh-launchers.yml`), or locally:

```bash
./scripts/build-birkly-launch.sh
./scripts/sync-release-launchers.sh
git add release/launchers && git commit -m "chore: refresh vendored launchers"
```

**On Linux (Ops / CI):** copies from this folder; `.app` is arm64 Mach-O until a Mac refresh lands.

Do not edit binaries manually.
