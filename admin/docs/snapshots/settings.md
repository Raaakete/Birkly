# Settings

## Summary

Settings is where you configure the Birkly installation itself: project name, branding (logo, favicon), users and roles, plugins, security, storage, and optionally webhooks, updates, or API options. Changes here apply to the whole CMS, not to a single collection or entry. Usually only administrators can access and change Settings.

**Phase 5.7:** Settings uses a **single global Save** bar at the top of the page. Edits on any tab are persisted together in one request when you click **Save settings**. Feedback appears as an inline toast (`#settings-message`), not browser alerts.

## Beginner

**Settings** (gear icon or sidebar link) is the control panel for *how* the CMS works and looks—not for editing blog posts or products, but for things like the project name, who can log in, and what they’re allowed to do.

### Global Save

1. Open **Settings** and switch between tabs (General, Security, Storage, and so on).
2. Change fields on one or more tabs.
3. Click **Save settings** once at the top of the page.
4. A success or error message appears in the bar above the button. You do **not** need to save each tab separately.

If something fails (for example invalid CSRF or missing permission), the message explains the problem. Fix the issue and click **Save settings** again.

### What you can change (typical sections)

- **General** — Project name, site URL, timezone, language, and media defaults (max upload size, image quality, thumbnail size, create thumbnails).

- **Branding** — Upload a **logo** and/or **favicon** so the admin panel shows your company or site branding.

- **Users and roles** — See who has an account, invite new users, and assign **roles**. Birkly seeds four default CMS roles: **Admin**, **Editor**, **Author**, and **Viewer**. The Settings tab shows a summary; use **Manage all users** for the full account page. Role lists in AI connections and user modals load from the same source.

- **Plugins** — Summary of installed and active plugins. Use **Manage all plugins** to open the full plugins page for activate/deactivate and per-plugin options.

- **Security** — Session timeout, login lockout, password rules, and CSRF protection. See [Security settings](#security-settings-enforcement) below for what each control does.

- **Storage** — Choose where Birkly stores configuration and content metadata. **Flat file** is the default (JSON under `settings/` and content on disk). **MySQL/MariaDB** and **PostgreSQL** are available for database-backed storage with test connection and migration tools. See [Storage backends](#storage-backends).

- **API & Domains** — Honest readout of public API behaviour; domain registration and rate limits where configured.

- **Activity & audit log** — Summary with a link to the full activity page.

- **AI & Integrations** — Outbound AI providers (in-admin chat) and inbound MCP endpoints. See [AI overview](ai-overview.md), [AI connections](ai-connections.md), and [MCP connections](mcp-connections.md).

- **Monitoring** — Health alert email and webhook URL when `/api/health.php` reports degraded or error. See [Monitoring and health alerts](../07-operations/monitoring-alerts.md).

- **Webhooks** — Link to the dedicated webhooks page (`?page=webhooks`) for outgoing webhook URLs and events.

- **Updates** (if available) — Check for new versions. Always back up before updating.

**Who can use Settings**

Usually only users with the **Administrator** role or **manage settings** permission. Editors and viewers often don’t see Settings, or see it read-only.

## Security settings (enforcement)

Security values are stored in `settings/security.json` and **enforced at runtime** when you save via global Save—not display-only toggles.

| Setting | What it does |
|---------|----------------|
| **Session timeout (minutes)** | Maximum idle time before the admin session expires. Checked on each authenticated request (`api/auth.php`). |
| **Max login attempts** | Failed logins allowed before lockout for that account/IP (`api/users.php`). Default range in UI: 3–20. |
| **Lockout time (minutes)** | How long login stays blocked after max attempts is exceeded. Stored in seconds internally. |
| **Password min length** | Minimum characters required when creating or resetting passwords (`api/validate.php`). |
| **Require strong password** | When enabled, passwords must meet complexity rules (upper, lower, number, symbol). When disabled, only min length applies. |
| **Enable CSRF protection** | When enabled, mutating API requests must include a valid CSRF token. When disabled, token checks are skipped (use only in controlled environments). |

**Plugin security denylist** — Paths, collections, or **admin spaces** blocked from all plugins regardless of granted capabilities. Managed in the Security tab; changes apply immediately. Supports collection slugs, entry ids, wildcard patterns (for example `payroll/*`), and space ids (for example `commerce`). See [Plugin security denylist](../06-extensibility/plugin-security-denylist.md).

**Two-factor authentication** — Per-user feature under **Account**, not global Settings.

## Storage backends

Birkly supports three storage modes in Settings → **Storage**:

### Flat file (default)

- **Driver:** `flatfile`
- **Config file:** `settings/storage.json` (key `driver`: `flatfile`)
- Content and settings remain JSON/files on disk under the project root. No database required. Best for small sites, local development, and alpha installs.

### MySQL / MariaDB

1. Create an empty database and a user with CREATE/INSERT/UPDATE/DELETE on that database.
2. In **Settings → Storage**, select **MySQL/MariaDB**.
3. Enter host, port (default 3306), database name, user, password, and optional table prefix (default `birkly_`).
4. Click **Test connection** — verifies credentials before migration.
5. Click **Save settings** (global Save) to persist credentials.
6. Use **Migrate data** to copy existing flat-file data into the database. Review the result message; verify collections and entries in admin after migration.

### PostgreSQL

Same workflow as MySQL with port default **5432**. The UI and API accept `postgresql` as the driver name (`postgres` is normalized automatically).

### SQLite

Shown in the UI as **coming soon** and is not selectable. Use flat file or MySQL/PostgreSQL for production.

### Test connection and migrate (API)

- **Test connection:** `POST /api/settings.php?action=test_database` with CSRF token and database fields. Available for MySQL and PostgreSQL only.
- **Migrate:** `POST /api/settings.php?action=migrate_storage` after a successful connection test. Migrates from the current flat-file layout to the configured database driver.
- **Save:** Storage fields are included in the global `save_all` action together with General and Security settings.

**Warning:** Changing the active backend affects where Birkly reads and writes data. Back up `settings/` and `content/` before migrating. Do not switch drivers without running migration or restoring from backup.

## Advanced Users

**Structure:** Settings are stored under `settings/` as JSON: `admin.json`, `site.json`, `media.json`, `security.json`, `branding.json`, `storage.json`, `plugins.json`, plus roles/users as applicable. Webhooks, domains, and API options may use additional files.

**Global Save implementation:**

- UI: `admin/templates/settings.html` — sticky `#global-save-settings-btn` and `#settings-message`.
- Client: `admin/js/settings-page.js` — collects all tab form fields, POSTs to `Birkly.url('/api/settings.php?action=save_all')` with CSRF.
- Server: `api/settings.php` — maps camelCase form keys to snake_case JSON, writes each category, persists storage via `StorageFactory::saveStorageConfig()`.

**Security:** Values in `security.json` are read by `api/auth.php` (session timeout), `api/users.php` (lockout), `api/validate.php` (password rules), and `core/csrf.php` (`enable_csrf` flag).

**Storage factory:** `core/storage/StorageFactory.php` — unified config path `settings/storage.json`, drivers `flatfile`, `mysql`, `postgresql`. `MySqlStorage` and `PostgresStorage` implement `StorageInterface`; flat file uses `FlatFileStorage`.

**Tab hash routing:** Deep links such as `#security`, `#storage`, `#ai-integrations`, and alias `#mcp-connections` activate the correct tab on load.

**Standalone pages (D7):** Full management for plugins, activity, webhooks, and users remains on dedicated admin pages; Settings tabs show summaries and deep links.

**Access control:** Settings UI and API require **manage settings** permission. CSRF applies to mutating actions when enabled.

## Related

- [Roles and permissions](roles-and-permissions.md)
- [Account](account.md)
- [AI overview](ai-overview.md)
- [AI connections](ai-connections.md)
- [MCP connections](mcp-connections.md)
- [Plugins](../06-extensibility/plugins.md)
- [Updates](../06-extensibility/updates.md)
- [Security](../07-operations/security.md)
- [Backup and restore](../07-operations/backup-and-restore.md)
