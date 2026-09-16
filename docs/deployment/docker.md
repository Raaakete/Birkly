# Docker deployment

Production images ship from the root `Dockerfile` (nginx + PHP-FPM, port **80**).

## Single data directory (recommended)

Set one **Volume Mount** at **`/app/data`** (Dokploy). Avoid bind mounts to host paths on Docker Swarm. The image sets `BIRKLY_DATA=/app/data` by default.

Everything that must survive container rebuilds lives under that path:

| Under `/app/data` | Purpose |
|------------------|---------|
| `content/` | Collections, entries, schemas |
| `settings/` | Site and admin configuration |
| `users/` | Admin accounts |
| `media/` | Uploads |
| `cache/`, `logs/`, `temp/` | Runtime files |
| `plugins/`, `themes/` | Installed extensions and themes |
| `ai_conversations/`, `webhooks/`, … | API runtime stores |

The entrypoint symlinks `/app/content`, `/app/settings`, `/app/users`, `/app/media`, and related folders into `/app/data/*` so the CMS code keeps using the same paths. Public site files remain at **`/app/project`** (symlink to `content/project/`).

### docker compose

```bash
docker compose up --build
```

Open [http://localhost:8080/admin](http://localhost:8080/admin).

### Dokploy

1. Create one application from this repository (build from `Dockerfile`).
2. **Volumes:** **Volume Mount** (named volume) → **`/app/data`** — not Bind Mount (see below).
3. **Environment:** `BIRKLY_DATA=/app/data` (image default).
4. Expose port **80**; terminate HTTPS in Dokploy.
5. **Verify:** `/api/deploy_revision.php` returns minimal JSON (`status`, `service`, `build_revision`); Logs shows **Containers (1)**.
6. **Diagnostics (optional):** set `BIRKLY_DEPLOY_DIAG_SECRET` in Dokploy and call diagnostic actions with header `X-BIRKLY-DEPLOY-SECRET` — never expose this value in logs or git.

#### Volume type (important)

Dokploy uses **Docker Swarm**. A **Bind Mount** to `/opt/birkly-data` often prevents startup (**Containers (0)**, 502). Use **Volume Mount** with a name like `birkly-cms-data` → `/app/data`.

#### First install order (avoid creating admin twice)

| Step | Action |
|------|--------|
| 1 | Create Dokploy app (Birkly, Dockerfile, port 80). |
| 2 | Add **Volume Mount** at `/app/data` **before** first login. |
| 3 | **Deploy** → Logs shows **Containers (1)**. |
| 4 | Open **`/admin`** → create admin account **once**. |

If you created an admin **without** a volume, then added a **new empty** volume and redeployed: you must **create the admin again** (once). The first account lived in the temporary container, not on the volume. After that, the same login survives redeploys.

| Situation | Create admin again? |
|-----------|---------------------|
| Volume at `/app/data` from the start | No |
| No volume first, then new empty volume | **Yes** (once) |
| Every redeploy with volume attached | No |

Site-owner guide: [Birkly-Docs — Dokploy hosting](https://github.com/Raaakete/Birkly-Docs/blob/main/07-operations/dokploy-hosting.md).

## Plugin dev git sync (test/staging)

For **git-cloned** official plugins under `/app/data/plugins/` (e.g. from **Birkly-Other-Things**), the container keeps them on GitHub `main` automatically (default every **60s**). Config lives on the **data volume** and also accepts **Dokploy environment variables** (no terminal required).

### Recommended setup (Dokploy UI only)

1. Clone plugin repos once into `/app/data/plugins/{id}` (folder name = plugin id: `commerce`, `events`, `automation`, `user_management`, `studio`).
2. In Dokploy → your test CMS app → **Environment**, set:

   | Variable | Value |
   |----------|--------|
   | `GITHUB_TOKEN` | fine-grained PAT (`github_pat_…`) or classic (`ghp_…`) |
   | `PLUGIN_DEV_SYNC_ENABLED` | `1` (optional if `GITHUB_TOKEN` is set — bootstrap enables sync) |

3. **Redeploy** the app. Entrypoint writes/updates `/app/data/settings/plugin_dev_sync.env` from those vars and starts the pull loop.
4. Confirm in logs: `/app/data/logs/plugin-dev-sync.log` should show `updated … oldsha -> newsha` or `already up to date` with the **current** GitHub tip — not only `pulled` with no SHA.

### PAT requirements

Create a **fine-grained** GitHub PAT with:

- **Resource owner:** `Birkly-Other-Things` (not your personal account)
- **Repository access:** all repos (or the five plugin repos)
- **Permissions:** Contents **Read-only** (Metadata is automatic)
- If the org requires approval: approve the token under org → Settings → Personal access tokens

Classic `ghp_` tokens with `repo` scope also work.

If logs show `Invalid username or token`, the token is wrong, not approved for the org, or a **stale token is still stored on the volume**. Set a fresh `GITHUB_TOKEN` in Dokploy env and **Redeploy** (process env wins over the volume file).

### Optional terminal enable (legacy)

```bash
GITHUB_TOKEN=github_pat_your_read_token ./scripts/plugin-dev-sync-enable.sh
```

### Files

| File on volume | Purpose |
|----------------|---------|
| `settings/plugin_dev_sync.env` | `PLUGIN_DEV_SYNC_ENABLED=1`, org, token, interval, plugin ids |
| `logs/plugin-dev-sync.log` | Sync log (includes SHAs after each run) |

Disable: set `PLUGIN_DEV_SYNC_ENABLED=0` in Dokploy env or in that env file, then Redeploy.

Entrypoint also links `/app/data/core`, `fieldtypes`, and `api` to the image (required for git-cloned plugins) and **does not overwrite** a git-cloned `automation` folder with the bundled copy.

**Production:** use Marketplace installs, not dev git sync.


Configure URLs in **Settings → Hosting** (`settings/hosting.json`), not by hostname prefix or Dokploy Internal Path tricks.

| Setting | Example | Role |
|---------|---------|------|
| **CMS URL** | `https://cms.birkly.cloud` | Admin, API, `birkly-client.js` — `/` redirects to admin |
| **Public site URL** | `https://birkly.cloud` | Marketing site when mode = **In Birkly (`project/`)** |
| **Public site mode** | `project` | Serves `project/` files at clean root URLs (no `/project/` in the browser bar) |

Dokploy: add **both** domains to the same app. For each domain set **Path** `/` and **Internal Path** `/` (never `/project`). Mount **`/app/data`** as a named volume.

| Host | After P43 routing |
|------|-------------------|
| CMS host | `/` → admin · `/admin` · `/api/*` |
| Public host | `/` → `project/index.html` · `/about` → `project/about.html` · `/admin` → redirect to CMS URL |

Marketing pages must set `data-cms-url` to the **CMS URL** (copy from Settings → Hosting). Saving the public site URL auto-registers the host in **Settings → Domains**.

Site-owner guide: [Birkly-Docs — Dokploy hosting](https://github.com/Raaakete/Birkly-Docs/blob/main/07-operations/dokploy-hosting.md).

### Test vs production (separate Dokploy apps)

Each environment needs its **own named volume** (e.g. `birkly-cms-data` for prod, `birkly-cms-test-data` for test). If test reuses prod’s volume — or a copy of prod’s `/app/data` — `settings/hosting.json` will still point at `https://cms.birkly.cloud`, and visiting `https://test.birkly.cloud` redirects to production.

| Check | Production | Test |
|-------|------------|------|
| Volume name | `birkly-cms-data` | `birkly-cms-test-data` (unique) |
| Domain | `cms.birkly.cloud` | `test.birkly.cloud` |
| `settings/hosting.json` `cms_base_url` | `https://cms.birkly.cloud` | `https://test.birkly.cloud` |

**Quick fix on test** (pick one):

1. **Environment variables** on the test Dokploy app (no volume edit):

   | Variable | Value |
   |----------|--------|
   | `BIRKLY_CMS_BASE_URL` | `https://test.birkly.cloud` |
   | `BIRKLY_TRUST_REQUEST_HOST` | `true` |
   | `BIRKLY_PUBLIC_SITE_MODE` | `none` (optional) |

   Redeploy after saving env. `BIRKLY_TRUST_REQUEST_HOST` stops unknown hosts from redirecting to the CMS URL stored in the volume.

2. **Repair script** inside the test container (writes the volume):

   ```bash
   php /app/scripts/repair-hosting-domain.php https://test.birkly.cloud --public-mode=none
   ```

3. **Manual edit** on the test volume: `settings/hosting.json` → set `cms_base_url` to `https://test.birkly.cloud`; sync `settings/admin.json` → `cms_url` to the same value.

**Diagnose:** with `BIRKLY_DEPLOY_DIAG_SECRET` configured, request `GET /api/deploy_revision.php?action=diagnose` with header `X-BIRKLY-DEPLOY-SECRET: <your-secret>`. Check `hosting.cms_base_url`, `hosting.request_host`, and `hosting.request_host_role`. If `request_host` is `test.birkly.cloud` but `request_host_role` is `unknown` and `cms_base_url` is `cms.birkly.cloud`, you have a test/prod hosting mismatch. The public endpoint does not return hosting or installation metadata.

## Legacy multi-folder layout

Omit `BIRKLY_DATA` and mount folders separately (`/app/content`, `/app/settings`, `/app/users`, `/app/media`, …). Supported for older installs; new deployments should use the single data root.

## Health check

`GET /api/health.php` — used by the image `HEALTHCHECK` and external monitoring.

## Dokploy troubleshooting (deploy not updating)

If you merged fixes on GitHub but the live site still shows the same PHP warnings, the **container image was not rebuilt** from latest `main`. Auto-deploy and “clean cache” do not always trigger a new Docker build.

### General tab (required)

| Setting | Must be |
|---------|---------|
| Repository | **`Raaakete/Birkly`** (CMS core — not `Birkly-Website`) |
| Branch | **`main`** |
| Build type | **Dockerfile** (not Nixpacks, not Static) |
| Dockerfile | `Dockerfile` (repo root) |
| Port | **80** |

### Force a real rebuild

1. **General** → confirm settings above → **Save**
2. **Stop** the application (if Dokploy offers Stop)
3. Enable **Clean cache** (if available)
4. Click **Rebuild** (not only “Restart” / lightweight Redeploy)
5. Open **Deployments** → latest entry → read the **build log**

Build log must include:

```text
FROM php:8.3-fpm-bookworm
```

and after start, **Logs** must include:

```text
[entrypoint] Birkly CMS docker image — document root /app
[entrypoint] Build revision: ...
```

If the log mentions `/usr/share/nginx/html` or “Welcome to nginx”, you are on the wrong image type.

### Verify live revision (after rebuild)

Open:

**https://cms.birkly.cloud/api/deploy_revision.php** (public — no secret)

| Field | Expected |
|-------|----------|
| `status` | `ok` |
| `service` | `birkly-cms` |
| `build_revision` | commit SHA or `unknown` |

For `plugin_registry_globals` and hosting fields, use `?action=diagnose` with `X-BIRKLY-DEPLOY-SECRET` (see P88 deploy diagnostics).

Also: `/api/health.php` on a fixed build returns **JSON only** (no `Warning:` lines) and includes `"build_revision"`.

### Autodeploy

GitHub → repo **Webhooks** or Dokploy **Autodeploy** must fire on push to `main`. If unsure, use **Rebuild** manually after every merge you need live.

### Wrong repo symptom

App name “Birkly Website” in Dokploy is fine, but the **Git repository** must still be `Raaakete/Birkly`. `Birkly-Website` is a separate static/marketing repo and will not pick up CMS Dockerfile fixes.

