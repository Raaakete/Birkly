# Contributing to Birkly

Thank you for your interest in Birkly. This repository is the **public open-source CMS** — product runtime only.

## Documentation

- **User & developer docs:** [Birkly-Docs](https://github.com/Raaakete/Birkly-Docs)
- **Quick start:** open `0_START-HERE.html` after extracting a release zip

## Reporting issues

Open a GitHub issue with steps to reproduce, expected behavior, and your PHP version.

## Development setup

1. Clone this repo and run the local server:

   ```bash
   chmod +x ./start-server.sh
   ./start-server.sh
   ```

2. Visit [http://localhost:8080/admin](http://localhost:8080/admin).

Official plugins for local development install from the marketplace registry:

```bash
./scripts/install-dev-plugins.sh
```

## Running tests (maintainers)

The full regression suite lives in the **private** `Raaakete/Birkly-Marketplace` repo under `internal/cms/tests/`. It runs against a sibling checkout of this CMS:

```bash
# Requires access to Birkly-Marketplace (private)
export BIRKLY_ROOT=/path/to/Birkly
cd /path/to/Birkly-Marketplace
./internal/cms/scripts/ci-check.sh
```

Contact the Birkly team if you need maintainer access.

## Pull requests

- Keep changes focused on the CMS product (`admin/`, `api/`, `core/`, `fieldtypes/`).
- Do not add planning docs, agent configs, or internal roadmaps to this public repo.
- Ensure `php -l` passes on files you change.

## Code of conduct

Be respectful and constructive. Security issues: report privately to the maintainers.
