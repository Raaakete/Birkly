# Agent Guide — production smoke test (Dokploy)

Use this after deploying Birkly with Agent Guide support. No SSH required for the **publish** steps — only the Dokploy UI and your browser.

## What you are checking

After publish, these URLs should return **JSON** (not HTML 404):

| URL | Purpose |
|-----|---------|
| `https://birkly.cloud/.well-known/agent-guide.json` | Canonical guide on **public** domain |
| `https://cms.birkly.cloud/api/public/ai_presence.php?action=brand` | Read API on CMS host |

Legacy alias: `/.well-known/brand-context.json` (same content when published).

**Before first publish:** `404` with `{"status":"error","message":"Not found"}` is **expected**.

---

## Part 1 — Dokploy (one-time checks)

1. Open your Birkly app in **Dokploy**.
2. Confirm both domains point to the same app:
   - `cms.birkly.cloud`
   - `birkly.cloud`
3. For each domain: **Path** = `/`, **Internal Path** = `/`, port **80** (or your HTTP port).
4. **Volume:** data mounted at `/app/data` (BIRKLY_DATA).
5. **Redeploy** after pulling the latest image so Agent Guide code and nginx routes are live.

---

## Part 2 — Admin: publish a minimal guide (~10 minutes)

1. Open **https://cms.birkly.cloud/admin** and log in.
2. Sidebar → **Agent Guide**.
3. **Sources** tab  
   - Add one **narrative** source (pick an About CMS entry, or your website URL `https://birkly.cloud`).
4. **Context** tab  
   - **Official name:** `Birkly`  
   - **Short description:** one sentence about what Birkly is.  
   - **Save draft** (button on the sticky bar).
5. **Publish** tab  
   - Turn on **Enable public publishing for AI systems**.  
   - Confirm public site URL shows `https://birkly.cloud` (from Settings → Site delivery).  
   - Click **Publish**.
6. On Overview you should see:
   - Completeness bar  
   - **Last published** timestamp  
   - Public URL for `/.well-known/agent-guide.json`

Optional: **Publish** tab → Context preview → ask *“What does this organization do?”* — answer should list sources.

---

## Part 3 — Verify in the browser

Open:

```
https://birkly.cloud/.well-known/agent-guide.json
```

You should see pretty-printed JSON with `schema_version`, `identity`, `guide_meta`, etc.

Also try:

```
https://cms.birkly.cloud/api/public/ai_presence.php?action=brand
```

Should return `{"status":"ok","data":{...}}`.

---

## Part 4 — Script (optional, from a machine with curl)

From the Birkly repo:

```bash
chmod +x ./scripts/smoke-agent-guide.sh
./scripts/smoke-agent-guide.sh https://birkly.cloud https://cms.birkly.cloud
```

All lines should show `OK`.

---

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| `Not found` JSON on both URLs | Publish not done or publishing disabled — repeat Part 2 |
| Works on `cms.` but not `birkly.cloud` | Settings → Site delivery: public site mode **project**, URL `https://birkly.cloud` |
| Admin has no Agent Guide nav | Redeploy latest image; clear browser cache |
| 502 / connection error | Dokploy app not running; check container logs |
| Old nginx only has `brand-context` route | Redeploy image with `agent-guide.json` nginx location |

---

## What success means

- Public JSON is on **your brand domain** (`birkly.cloud`), not only the CMS host.  
- Draft edits do **not** change the public JSON until you **Publish** again.  
- This does **not** guarantee ChatGPT ingests it automatically — it proves **your** authoritative file is live and reachable.
