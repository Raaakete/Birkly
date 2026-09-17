# Self-host install (release zip)

## Summary

Install Birkly on **your own server or local PHP** using the **official CMS release zip** from GitHub (not Composer). Official plugins are **not** bundled — install them from **Plugins → Hub** after onboarding. Support for blockers: **hello@birkly.io**.

## Beginner

### What you need

- **PHP** 8.1+ (match the version noted on the GitHub release)
- A **web server** (Apache/Nginx) or the included `./start-server.sh` for local trials
- Writable folders: `content/`, `data/`, `media/`, `settings/`, `logs/`

### Steps

1. **Download** the release zip for the tagged version you are using (GitHub → Releases, or a secure link from your team).
2. **Extract** so `admin/`, `api/`, `core/`, and **`0_START-HERE.html`** sit in the **same folder** (not nested twice).
3. Open **`0_START-HERE.html`** in a browser. Confirm the folder check passes.
4. Choose **local** or **server** path, then **Start setup** → admin onboarding.
5. Pick **admin UI language** during onboarding (separate from site language — see [Admin language vs site language](../01-admin/admin-language-vs-site-language.md)).
6. In **Settings → Hosting**, set **CMS URL** (and public site URL if you use `project/`).
7. Configure **Connections** (SMTP) and send a test email before User signup flows.
8. Install official plugins from **Plugins → Hub** (User, Commerce, Automation, Events, Studio as needed).

### Plugins

The zip ships an empty `plugins/` folder. Do not copy unofficial plugin trees into the CMS repo — use Hub install/upgrade. See [Official plugins](../06-plugin-economy/official-plugins.md).

## Advanced

- **Docker:** [Hosting on Dokploy](dokploy-hosting.md) and root `docker-compose.yml` in the release.
- **Updates:** [Updates](../06-extensibility/updates.md) — self-host instances do not use removed legacy `api/update.php`; use Hub + documented upgrade path.
- **Backup:** [Backup and restore](backup-and-restore.md) before upgrades.

## Related

- [Getting started](../00-overview/getting-started.md)
- [Deployment](deployment.md)
- [Troubleshooting](troubleshooting.md)
- [Known issues (Wave 1 engineering)](../00-overview/known-issues-wave1.md)
