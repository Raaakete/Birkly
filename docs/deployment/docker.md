# Docker deployment

Production images ship from the root `Dockerfile` (nginx + PHP-FPM, port **80**).

## Single data directory (recommended)

Set one volume or bind mount at **`/app/data`**. The image sets `BIRKLY_DATA=/app/data` by default.

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
2. **Volumes:** add a single mount — host path or named volume → **`/app/data`**.
3. **Environment:** `BIRKLY_DATA=/app/data` (already the image default; set explicitly in Dokploy if needed).
4. Expose port **80**; terminate HTTPS in Dokploy.
5. **Verify deploy:** open `/api/health.php` and check `build_revision` (Git commit baked into the image). After a merge to your tracked branch, trigger **Redeploy** if auto-deploy did not run — a green DNS badge does not mean a new image was built.

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
