# Marketplace bootstrap (P9-M0)

This directory holds the **Birkly-Marketplace** scaffold until `Raaakete/Birkly-Marketplace` exists on GitHub and is attached to the Cursor Cloud environment.

## Promote to standalone repo

When the GitHub repo exists:

```bash
# From a fresh clone of Raaakete/Birkly-Marketplace
cp -a /path/to/Birkly/bootstrap/Birkly-Marketplace/* .
cp -a /path/to/Birkly/bootstrap/Birkly-Marketplace/.gitignore .
git add -A && git commit -m "P9-M0: Marketplace repo scaffold"
git push origin main
```

Or run `./bootstrap/promote-marketplace.sh` from the Birkly repo root (after creating the remote repo).

## Cloud environment

Add `Raaakete/Birkly-Marketplace` to the multi-repo Cursor environment — see `docs/agent-ops/CLOUD_ENV_REPOS.md`. Start a **new** cloud agent session after saving.
