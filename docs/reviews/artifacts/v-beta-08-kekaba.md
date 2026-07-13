# V-BETA-08 — Kekaba live path deployment

**Date:** 2026-07-13 · **Phase:** P28-M17  
**Journey:** J-KEKABA

Production reference site using live path (dynamic API, no bake meta).

## Prerequisites

- Kekaba repo available as sibling: `/agent/repos/kekaba`
- Migration script: `./scripts/migrate-kekaba-live-path.sh`

## Steps

1. **Copy kekaba to test workspace**
   - Journey creates temp directory: `/tmp/birkly-journey-kekaba-{suffix}`
   - Copies entire kekaba site to temp workspace
   - Isolated test environment (no modification of source repo)

2. **Run live path migration**
   - Execute: `./scripts/migrate-kekaba-live-path.sh {workDir}`
   - Script actions:
     - Removes `<meta name="birkly-baked">` tags from HTML
     - Updates API paths to use embedded CMS: `/Birkly-0.1/api/`
     - Preserves existing `birkly-client.js` references
     - Configures live API endpoint in JavaScript
   - Migration completes successfully

3. **Start static file server**
   - Serve kekaba on temp port (e.g., 8000)
   - Python: `python3 -m http.server 8000`
   - Or PHP: `php -S localhost:8000`

4. **Verify homepage**
   - Navigate to `/index.html`
   - Page loads successfully (HTTP 200)
   - **No birkly-baked meta tag** in HTML source
   - `birkly-client.js` present and functional
   - Content dynamically loaded from embedded CMS API

5. **Verify other pages**
   - `/blog.html` — HTTP 200, dynamic blog posts
   - `/service.html` — HTTP 200, service pages loaded

6. **Verify embedded CMS health**
   - API health check: `/Birkly-0.1/api/health.php`
   - Returns HTTP 200
   - Response includes CMS version, API status

## Key difference from baked mode

| Aspect | Baked (legacy) | Live path (P28) |
|--------|----------------|-----------------|
| Meta tag | `<meta name="birkly-baked">` present | **Removed** |
| API calls | Pre-baked data in HTML | Dynamic API calls to `/Birkly-0.1/api/` |
| Content updates | Requires re-bake | **Instant** (API queries live data) |
| Deployment | Static HTML only | Static HTML + embedded CMS |

## Automated verification

```bash
cd ../kekaba  # sibling repo
./scripts/migrate-kekaba-live-path.sh .
python3 -m http.server 8000   # serve kekaba
cd ../Birkly
./scripts/journey-runner.php --journey J-KEKABA --base http://127.0.0.1:8000
```

Journey copies kekaba, migrates to live path, starts server, verifies no birkly-baked meta, confirms birkly-client.js present, tests multiple pages, validates CMS health endpoint.

## Status

✅ Automated journey passes (J-KEKABA)
