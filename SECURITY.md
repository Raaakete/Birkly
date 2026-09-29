# Security Policy

## Supported versions

Security fixes are applied to the latest release on the default branch of [Raaakete/Birkly](https://github.com/Raaakete/Birkly).

## Reporting a vulnerability

**Please do not open public GitHub issues for security vulnerabilities.**

Report security issues privately to the maintainers via GitHub Security Advisories on the Birkly repository, or contact the team through [birkly.cloud](https://birkly.cloud).

Include:

- Description of the issue and impact
- Steps to reproduce
- Affected version or commit
- Suggested fix (if any)

We aim to acknowledge reports within a few business days.

## Scope

This policy covers the **Birkly CMS** open-source repository only (`admin/`, `api/`, `core/`, `fieldtypes/`, bundled client assets).

Out of scope:

- Third-party plugins installed from the Marketplace
- Private Ops/Marketplace infrastructure
- Misconfigurations on self-hosted installs (weak passwords, exposed `.env`, missing HTTPS)

## Accepted design notes

The following are intentional for self-hosted CMS operation and are not treated as vulnerabilities on their own:

| Item | Rationale |
|------|-----------|
| `scripts/reset-admin-password.php` | CLI emergency recovery. It refuses to run over HTTP. The web server also refuses `/scripts/` except the Commerce import cron. |
| `eval()` in `fieldtypes/DynamicItems/fieldtype.js` | Loads trusted fieldtype JavaScript from admin-authored definitions; refactor planned |
| `extract()` in admin template rendering | Used for view data binding; refactor planned |
| Docker entrypoint runs as root | Required for nginx on port 80 and volume migration; worker processes run as `www-data` |

## Secure self-hosting

- Create the first admin in the browser, or with `BIRKLY_ADMIN_PASSWORD` and `scripts/create-default-user.php`. The script does not invent a shared password, and it does not add an account when one already exists.
- The shipped `.htaccess`, `nginx.conf.example`, and Docker nginx config refuse HTTP access to `core/`, `settings/`, `users/`, `content/`, `data/`, `cache/`, `logs/`, `scripts/` (except the token-gated Commerce import cron), and `tests/`. PHP can still write those directories.
- Do not commit `config.php`, `.env`, or user account JSON to version control
- Use HTTPS in production
