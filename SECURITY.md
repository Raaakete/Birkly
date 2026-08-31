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
| `scripts/reset-admin-password.php` | CLI emergency recovery; must be run on the server with shell access |
| `eval()` in `fieldtypes/DynamicItems/fieldtype.js` | Loads trusted fieldtype JavaScript from admin-authored definitions; refactor planned |
| `extract()` in admin template rendering | Used for view data binding; refactor planned |
| Docker entrypoint runs as root | Required for nginx on port 80 and volume migration; worker processes run as `www-data` |

## Secure self-hosting

- Change the default admin password immediately after install
- Keep `users/`, `settings/`, and `data/` outside web roots or protected by server config
- Do not commit `config.php`, `.env`, or user account JSON to version control
- Use HTTPS in production
