# Tier Design Proposal - Simplified & Flexible

## Current Issues

1. **Price** - Doesn't belong here. Should be handled by:
   - A dedicated payment/sales plugin
   - Or custom fields in a collection entry
   - Not core to user management

2. **Max Storage** - Questionable value:
   - Only relevant if there's a storage/media system
   - Even then, should be handled by that system, not user tiers
   - Too specific for a universal plugin

3. **Max Entries** - Could be useful, but:
   - Should be **per-collection**, not global
   - Should be **optional** (not all sites need this)
   - Should be **flexible** (different limits per collection)

4. **Hardcoded Features** - Too rigid:
   - `unlimited_entries`, `custom_domain`, `priority_support` are too specific
   - Should be **flexible** - admin defines feature names
   - Other plugins can extend with their own features

## Proposed Structure

```json
{
  "id": "premium",
  "name": "Premium",
  "description": "Premium tier with enhanced features",
  "permissions": {
    "collections": {
      "posts": ["create", "edit", "delete"],
      "pages": ["read"]
    },
    "features": ["api_access", "export_data"]
  },
  "limits": {
    "collections": {
      "posts": 100,
      "comments": 500
    }
  },
  "features": ["advanced_search", "custom_templates"],
  "custom": {}
}
```

## What to Keep

### ✅ Core (Required)
- **`id`** - Unique identifier
- **`name`** - Display name
- **`description`** - Optional description
- **`permissions`** - Core access control
  - `collections` - Which collections and what actions
  - `features` - Feature flags (flexible, not hardcoded)

### ✅ Optional but Useful
- **`limits`** - Optional limits
  - `collections` - Per-collection entry limits (e.g., `{"posts": 100}`)
  - Flexible - admin defines what to limit
- **`features`** - Array of feature names (flexible strings)
  - Admin-defined, not hardcoded
  - Other plugins can check for their features
- **`custom`** - Extensibility object
  - Other plugins can add their own metadata here
  - Example: `{"payment_plan_id": "plan_123"}`

## What to Remove

### ❌ Remove
- **`price`** - Belongs in payment plugin
- **`max_storage_mb`** - Too specific, belongs in storage plugin
- **Hardcoded feature checkboxes** - Replace with flexible text input

## Implementation Plan

1. **Update Tier Structure**
   - Remove `price` field
   - Remove `max_storage_mb` from default structure
   - Make `max_entries` per-collection in `limits.collections`
   - Make `features` flexible (text input, not checkboxes)

2. **Update UI**
   - Remove price input
   - Remove max storage input
   - Change max entries to per-collection limits (optional)
   - Change features to flexible text input (add/remove feature names)

3. **Update Backend**
   - Update `UserTiers::initializeDefaultTiers()` to new structure
   - Update API to handle new structure
   - Update permission checks to use new structure

4. **Migration**
   - Convert old `max_entries` to `limits.collections.*` if needed
   - Remove `price` and `max_storage_mb` from existing tiers
   - Convert hardcoded features to flexible array

## Benefits

- ✅ **Universal** - Works for any use case
- ✅ **Flexible** - Admin defines what matters
- ✅ **Extensible** - Other plugins can extend via `custom`
- ✅ **Clean** - No assumptions about payment/storage
- ✅ **Simple** - Less fields, clearer purpose
