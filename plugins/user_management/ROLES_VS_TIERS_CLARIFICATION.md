# Roles vs Tiers - Design Clarification

## Current Problem

We have **two overlapping concepts**:

1. **Roles** (hardcoded): `member`, `subscriber`, `premium`, `vip`
   - Defined in `plugin.json`
   - Hardcoded in `UserService.php`
   - Used for collection permissions

2. **Tiers** (admin-created): `free`, `basic`, `premium`, etc.
   - Created by admin in Tiers tab
   - Stored in `UserStorage`
   - Used for access levels/limits

## The Issue

These are **essentially the same thing** but implemented differently:
- Both control what users can access
- Both are assigned to users
- Both affect permissions
- Having both is confusing and redundant

## Solution: Consolidate to Tiers Only

**Recommendation**: Remove hardcoded roles, use only admin-created tiers.

### Why Tiers?
- ✅ Admin can create custom tiers (flexible)
- ✅ Tiers can have limits (max entries, storage, etc.)
- ✅ Tiers can be tied to payment plans
- ✅ More flexible than hardcoded roles

### Migration Path:
1. **Default Tiers**: Create default tiers that match current roles:
   - `member` (replaces "member" role)
   - `subscriber` (replaces "subscriber" role)
   - `premium` (replaces "premium" role)
   - `vip` (replaces "vip" role)

2. **Collection Permissions**: Use tier IDs instead of role names
   - Change from: `permissions[collection][role]`
   - Change to: `permissions[collection][tier_id]`

3. **User Assignment**: Users have a `tier` field (already exists)
   - Remove `role` field or make it a computed property from tier

4. **Backward Compatibility**: 
   - Map old roles to tiers during migration
   - Support both during transition period

## Alternative: Keep Both (But Clarify)

If we keep both:
- **Roles** = What user CAN do (permissions/capabilities)
- **Tiers** = What level of access/subscription (limits/features)

But this is still confusing because:
- Roles and tiers both affect permissions
- Users need to understand both concepts
- More complex to manage

## Recommendation

**Consolidate to Tiers Only** - simpler, more flexible, less confusing.
