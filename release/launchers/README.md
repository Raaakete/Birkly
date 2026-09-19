# Prebuilt Birkly Launch binaries

Ops and other environments without Go use these artifacts when running `scripts/build-birkly-launch.sh`.

Refresh after launcher source changes:

**On a Mac (universal binary + ad-hoc sign):** run the GitHub Action **Refresh launchers** (`refresh-launchers.yml`), or locally:

```bash
./scripts/build-birkly-launch.sh
./scripts/sync-release-launchers.sh
git add release/launchers && git commit -m "chore: refresh vendored launchers"
```

**Customer zip:** Windows `.exe` and Linux binary only — macOS uses `start-server.sh` (see `0_START-HERE.html`). Dev builds can still produce `Birkly Launch.app` when `BIRKLY_LAUNCH_SKIP_MAC` is unset.

Do not edit binaries manually.
