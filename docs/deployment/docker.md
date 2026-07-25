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
5. **Verify:** `/api/deploy_revision.php` returns JSON; Logs shows **Containers (1)**.

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

No per-folder volume list is required.

### Two domains (marketing + CMS)

Use one container and route by hostname in your reverse proxy (Dokploy domains):

| Host | Serves | Notes |
|------|--------|--------|
| `example.com` | `/project` (public site) | Marketing HTML in repo `project/` → persisted in `data/content/project/` |
| `cms.example.com` | `/admin`, `/api`, client JS | Register `https://example.com` in CMS **Settings → Domains** |

Marketing pages must set `data-cms-url` to the **CMS** host (not the marketing host).

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

**https://cms.birkly.cloud/api/deploy_revision.php**

| Field | Old broken build | Fixed build |
|-------|------------------|-------------|
| `plugin_registry_globals` | `false` | **`true`** |
| `build_revision` | missing or `unknown` | commit SHA or `unknown` (if no build arg) |
| PHP warnings above JSON | yes | **no** |

Also: `/api/health.php` on a fixed build returns **JSON only** (no `Warning:` lines) and includes `"build_revision"`.

### Autodeploy

GitHub → repo **Webhooks** or Dokploy **Autodeploy** must fire on push to `main`. If unsure, use **Rebuild** manually after every merge you need live.

### Wrong repo symptom

App name “Birkly Website” in Dokploy is fine, but the **Git repository** must still be `Raaakete/Birkly`. `Birkly-Website` is a separate static/marketing repo and will not pick up CMS Dockerfile fixes.

