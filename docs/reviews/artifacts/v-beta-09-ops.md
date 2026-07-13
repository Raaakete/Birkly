# V-BETA-09 — Admin Settings Operations tab

**Date:** 2026-07-13 · **Phase:** P28-M17  
**Related Journey:** J-ADMIN (admin UI access)

Settings → Operations panel for cache, logs, system health.

## Prerequisites

- Birkly dev server running
- Admin account created and logged in
- Navigate to `Admin → Settings → Operations`

## Operations panel features

### 1. Cache management
- **Clear all caches** button
  - Deletes `/cache/collections/*`
  - Deletes `/cache/templates/*`
  - Deletes `/cache/api/*`
  - Success toast: "Caches cleared"
  
- **Cache statistics**
  - Collection cache size
  - Template cache size
  - API response cache size
  - Last cleared timestamp

### 2. Log viewer
- **Recent logs** table
  - Log level (info, warning, error)
  - Timestamp (ISO 8601)
  - Message preview
  - Source file/function
  
- **Filter controls**
  - Level filter (all, errors only, warnings only)
  - Date range picker
  - Search by message content
  
- **Download logs** button
  - Exports `logs/birkly-{date}.log`
  - Format: plain text or JSON

### 3. System health checks
- **PHP version** and configuration
  - Current version
  - Required extensions (curl, json, mbstring)
  - Memory limit, max execution time
  
- **File permissions**
  - `users/` writable ✓
  - `cache/` writable ✓
  - `logs/` writable ✓
  - `media/` writable ✓
  - Warning icons if not writable
  
- **Disk space**
  - Available disk space
  - CMS directory size
  - Media directory size

### 4. Maintenance mode
- **Enable maintenance mode** toggle
  - Creates `/settings/maintenance.json`
  - Public site shows maintenance page
  - Admin and API remain accessible
  
- **Maintenance message** editor
  - Custom message displayed to visitors
  - Estimated downtime field

### 5. Database operations (if using SQLite plugin)
- **Vacuum database** button
  - Optimizes SQLite file
  - Reclaims space from deleted records
  
- **Export backup** button
  - Downloads database backup
  - Format: `.sqlite` or `.sql`

## Automated verification

Operations tab is part of admin settings UI. Functional tests verify:

```bash
# Cache clear
php tests/cache_test.php

# Log rotation
php tests/logging_test.php

# File permissions check (part of install journey)
./scripts/journey-runner.php --journey J-INSTALL
```

Admin UI integration tested via:

```bash
# Admin dashboard and settings access
./scripts/journey-runner.php --journey J-ADMIN
```

## Manual verification steps

1. **Access Operations tab**
   - Login to admin
   - Navigate to `Settings → Operations`
   - Tab content loads (no errors)

2. **Clear caches**
   - Click "Clear All Caches"
   - Success message displayed
   - Cache statistics reset to 0

3. **View logs**
   - Logs table populated
   - Filter by error level
   - Download logs file

4. **Check system health**
   - All health checks show green ✓
   - No permission warnings
   - PHP requirements met

## Status

✅ Operations tab functional (verified via admin journey)  
✅ Cache and log operations tested in unit tests
