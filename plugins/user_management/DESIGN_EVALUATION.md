# User Management Plugin - Design Evaluation

## Current Issues & Questions

### 1. Permissions Tab
**Question**: Why is there a permissions tab? No user will ever have system-wide permissions, and we handle it differently now.

**Current State**: 
- The "Permissions" tab actually shows **Collection Permissions** (which roles can create/edit/delete entries in each collection)
- This is NOT user-level permissions, but collection-level role permissions
- User-level permissions are handled via roles and collection permissions

**Recommendation**: 
- ✅ **Keep it, but rename to "Collection Permissions"** 
- This is actually useful - it lets admins configure which roles can do what in each collection
- It's different from user-level permissions (which we don't need)

### 2. Profile Schema Tab
**Question**: Why do we have profile schema and fields when we handle it via collections?

**Current State**:
- The "User Profile" tab allows defining custom profile fields
- But we now use **collection profiles** where profiles are entries in a collection
- This creates redundancy and confusion

**Recommendation**:
- ❌ **Remove the Profile Schema tab entirely**
- Profiles are now managed via collections (the "profiles" collection)
- Admins can define profile fields directly in the collection editor
- This is more flexible and consistent with the rest of Birkly

### 3. Tiers
**Question**: Tiers must be connected to user profiles, and we need a default tier setting.

**Current State**:
- Tiers exist but aren't connected to profiles
- No default tier setting
- New users don't automatically get assigned a tier

**Recommendation**:
- ✅ **Add default tier setting** in General tab
- ✅ **Auto-assign default tier** to new users
- ✅ **Show tier in user profile** (if using collection profiles, tier should be a field)
- Tiers are useful for subscription-based access control

### 4. Groups
**Question**: Do groups make sense anymore?

**Current State**:
- Groups allow grouping users together
- Groups can have permissions
- But we already have roles and collection permissions

**Analysis**:

**Groups ARE useful for**:
- Team collaboration (e.g., "Marketing Team", "Development Team")
- Shared permissions for a group of users (e.g., "Editors" group can edit specific collections)
- User-created groups (e.g., "Friends", "Project Team")
- Non-permission use cases (e.g., notifications, content sharing)

**Groups might be redundant if**:
- You only need role-based permissions
- You don't need team/organizational structure
- You don't need user-created groups

**Recommendation**:
- ⚠️ **Keep Groups, but make it optional/clearer**
- Groups serve a different purpose than roles:
  - **Roles** = What a user CAN do (permissions)
  - **Groups** = Who a user belongs to (organization/teams)
- Groups are useful for:
  - Team management
  - Shared content access
  - User-created communities
  - Group-based notifications
- Consider making it a setting: "Enable Groups" (on/off)

## Proposed Changes

1. ✅ Fix API error (undefined user_id)
2. ✅ Rename "Permissions" tab to "Collection Permissions"
3. ✅ Remove "User Profile" tab (profile schema)
4. ✅ Add "Default Tier" setting in General tab
5. ✅ Auto-assign default tier to new users
6. ⚠️ Keep Groups but add clarification/documentation
