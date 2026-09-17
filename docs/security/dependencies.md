# Dependency and supply-chain visibility (Birkly CMS)

Birkly’s **runtime** is intentionally **dependency-free** in the Composer sense: the CMS ships as PHP, HTML, CSS, and JavaScript in this repository. There is no `vendor/` tree and no `composer.lock` on the release path.

## What “dependencies” means for scanners (e.g. Aikido)

Many security tools assume a **Node or Composer application** and look for:

- `package-lock.json` / `yarn.lock` → npm CVE matching  
- `composer.lock` → Packagist CVE matching  

Birkly has a minimal `composer.json` only for **project metadata** and optional PSR-4 autoload paths (`"require": { "php": ">=7.4" }`). That is **not** “install 50 packages on deploy.” A missing lockfile warning is **expected** and does **not** mean the CMS is missing pinned libraries.

## What Birkly actually depends on

| Layer | What | Who pins it |
|--------|------|-------------|
| **Language** | PHP 8.x (7.4+ declared in composer.json) | Host / Docker image (`php:8.3-fpm-bookworm` in `Dockerfile`) |
| **PHP extensions** | `curl`, `zip` (Dockerfile `docker-php-ext-install`) | Image build |
| **Web server** | nginx + php-fpm | Image + `docker/nginx-site.conf` |
| **System tools** | `git` (project sync), `curl` (healthcheck) | Image `apt-get` |
| **Storage** | SQLite or flat files (per install) | Core code, not Composer |
| **Plugins** | Optional zip packages from Hub | Separate releases (`plugin.json` per plugin) |
| **Client assets** | `birkly-client.js`, template engine (built in Docker build via repo scripts) | Git revision / `BIRKLY_BUILD_REVISION` |

Security review should focus on:

1. **This repo** — SAST, secret scan (`scripts/secret-scan.sh`), auth boundaries.  
2. **Docker base image** — rebuild when Debian/PHP security updates ship.  
3. **Plugins** — treat as separate products with their own versions and changelogs.  
4. **Host OS** — Dokploy/VM patches (outside the CMS repo).

## Plugins are not Composer dependencies

Installed plugins live under `plugins/` on the instance. They are **not** merged into `composer.json`. Hub/catalog versioning is the source of truth for plugin security updates (Hub → Update).

## When a lockfile would make sense

Only if Birkly later adds **runtime** Composer packages (e.g. a shared SDK). Then we would add `composer.lock` and CI CVE checks. Until then, documenting this model (this file) is the correct “dependency visibility” outcome—not an empty lockfile for tooling’s sake.
