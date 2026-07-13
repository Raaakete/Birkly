# V-BETA-05 — Marketplace plugin install

**Date:** 2026-07-13 · **Phase:** P28-M17  
**Journey:** J-MARKETPLACE

CMS owner installs Commerce plugin from Birkly Marketplace registry.

## Prerequisites

- Fresh Birkly installation (via release ZIP extract)
- Isolated test environment (journey builds release)
- Marketplace mock handler for local testing

## Steps

1. **Admin → Marketplace browser**
   - Navigate to `Admin → Marketplace` in CMS
   - Browse available plugins and themes
   - Search for "Commerce" plugin
   - View plugin details: description, version, pricing

2. **Free license claim**
   - Click "Install" on Commerce plugin (free tier)
   - API call to `/api/v1/checkout` (mock endpoint in test)
   - Returns license key: `BIRKLY-JOURNEY-TEST-KEY`
   - License stored in CMS settings

3. **Plugin download and install**
   - Marketplace registry API returns download URL
   - CMS downloads plugin ZIP from registry
   - Verifies SHA256 checksum: `9a7b3c...` (test fixture)
   - Extracts to `/plugins/birkly-commerce/`

4. **Installation verification**
   - Plugin files present on disk:
     - `plugins/birkly-commerce/manifest.json`
     - `plugins/birkly-commerce/routes/`
     - `plugins/birkly-commerce/hooks.php`
   - Manifest validates against schema
   - License activation API called successfully

5. **Admin UI verification**
   - Create first admin account
   - Skip onboarding wizard
   - Navigate to `Admin → Commerce`
   - Commerce admin route returns HTTP 200
   - Plugin activated and functional

## Automated verification

```bash
./scripts/journey-runner.php --journey J-MARKETPLACE
```

Journey builds release ZIP, extracts to temp, verifies empty `/plugins/` dir, mocks marketplace API, claims free license, installs commerce plugin, verifies files on disk, creates admin, confirms commerce admin route accessible.

## Status

✅ Automated journey passes (J-MARKETPLACE)
