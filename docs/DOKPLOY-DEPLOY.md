# Dokploy deployment — Birkly CMS

One Dokploy **Application** per CMS installation (e.g. `test.birkly.cloud`).

Unlike **Birkly-Marketplace**, the CMS repo must run **nginx + PHP-FPM**. If you see **“Welcome to nginx!”** or PHP files download instead of running, the app was built without this Dockerfile.

---

## 1. General tab

| Setting | Value |
|---------|--------|
| Repository | `Raaakete/Birkly` |
| Branch | `main` |
| Build type | **Dockerfile** |
| Dockerfile path | `Dockerfile` |
| Port (Domains tab) | **80** |

Enable **Autodeploy** on push.

**Do not** use Nixpacks or a plain nginx static image for Birkly.

---

## 2. Domain

| Field | Value |
|-------|--------|
| Host | `test.birkly.cloud` (your subdomain) |
| Path | `/` |
| Port | `80` |
| HTTPS | On (Let’s Encrypt) |

---

## 3. Volumes (Advanced → Volumes)

Use **Volume Mount** (not Bind Mount) so data survives redeploys:

| Volume name | Container path |
|-------------|----------------|
| `birkly-cms-test-content` | `/app/content` |
| `birkly-cms-test-media` | `/app/media` |
| `birkly-cms-test-settings` | `/app/settings` |
| `birkly-cms-test-users` | `/app/users` |
| `birkly-cms-test-data` | `/app/data` |
| `birkly-cms-test-cache` | `/app/cache` |
| `birkly-cms-test-logs` | `/app/logs` |

After adding volumes: **Rebuild**.

---

## 4. Environment (optional)

```env
BIRKLY_MARKETPLACE_URL=https://marketplace.birkly.cloud
```

Save → Rebuild.

---

## 5. First-time setup (onboarding)

Birkly does **not** show onboarding on `/` (root may 404 — that is normal).

Open:

**https://test.birkly.cloud/admin**

or

**https://test.birkly.cloud/admin/login.php**

With no users yet, you are redirected to the **onboarding wizard** to create the first admin account.

---

## 6. Smoke tests

```bash
curl -s https://test.birkly.cloud/api/health.php | head -c 200
```

Expected: JSON with `"status":"ok"` (or `"degraded"` on a fresh install).

```bash
curl -sI https://test.birkly.cloud/admin/login.php | head -3
```

Expected: `HTTP/2 200` and `content-type: text/html` (not `application/octet-stream`).

---

## 7. Troubleshooting

| Symptom | Cause | Fix |
|---------|--------|-----|
| **Welcome to nginx!** on `/` | Default nginx image, no Birkly PHP stack | Build type → **Dockerfile**, Rebuild with clean cache |
| `/admin/login.php` downloads as a file | PHP not executed | Same — need Dockerfile image |
| 502 Bad Gateway | Container crashed | Check Dokploy **Logs**; verify port **80** |
| Onboarding never appears | Wrong URL | Use `/admin`, not `/` only |
| Marketplace plugins fail | CMS can’t reach marketplace | Set `BIRKLY_MARKETPLACE_URL` |

### Verify the rebuild actually ran

After **Rebuild**, container should be **Up 1 minute** (not hours). Build log should show:

`FROM php:8.3-fpm-bookworm`

If you still see the nginx welcome page, enable **Clean Cache** → **Rebuild**.

---

## 8. Relation to Marketplace

| App | Repo | Domains |
|-----|------|---------|
| **Platform** | `Birkly-Marketplace` | `ops` / `providers` / `marketplace` `.birkly.cloud` |
| **CMS install** | `Birkly` | e.g. `test.birkly.cloud` (one app per site) |

See `Birkly-Marketplace/docs/DOKPLOY-DEPLOY.md` for the Platform app.
