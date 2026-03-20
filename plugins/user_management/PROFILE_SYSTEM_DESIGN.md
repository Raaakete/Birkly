# Profile System Design - Hybrid Approach

## Core Principles

1. **Basic Info Always in Plugin**: username, email, password, user_id always stored in plugin
2. **Optional Collection Link**: User can connect to a collection entry for extended profile
3. **No Auto-Detection**: Must explicitly enable collection profiles
4. **Redundancy**: Basic info saved in both places when linked (for resilience)
5. **Backward Compatible**: Works with or without collection profiles

## Data Structure

### Plugin-Managed (Always Present)
```json
{
  "id": "user_123",
  "username": "johndoe",
  "email": "john@example.com",
  "password_hash": "...",
  "role": "member",
  "status": "active",
  "created_at": "2024-01-01T00:00:00Z",
  "profile_entry_id": null  // or "profile_entry_123" if linked
}
```

### Collection Entry (Optional)
```json
{
  "id": "profile_entry_123",
  "user_id": "user_123",  // Link back to plugin user
  "username": "johndoe",  // Redundant but ensures data if collection deleted
  "email": "john@example.com",  // Redundant
  "bio": "...",
  "avatar": "...",
  "location": "...",
  "friends": ["user_456", "user_789"]  // User fieldtype
}
```

## Transition Approach

### Option 1: Admin Setting + Auto-Create (Recommended)

**How it works:**
1. Admin enables "Use Collection Profiles" in plugin settings
2. Admin selects which collection to use (e.g., "profiles")
3. When enabled:
   - New users automatically get profile entries created
   - Existing users can be migrated (bulk action)
4. When disabled:
   - Profile entries remain but aren't used
   - Plugin falls back to plugin-managed only

**Pros:**
- ✅ Simple for admin
- ✅ Automatic for new users
- ✅ Can migrate existing users
- ✅ Easy to enable/disable

**Cons:**
- ⚠️ Need to handle migration of existing users

### Option 2: Per-User Manual Link

**How it works:**
1. Each user has optional `profile_entry_id` field
2. Admin manually links users to entries
3. No automatic creation

**Pros:**
- ✅ Full control
- ✅ Can link to existing entries
- ✅ No migration needed

**Cons:**
- ❌ Manual work for each user
- ❌ Not scalable

### Option 3: Hybrid (Best of Both)

**How it works:**
1. Admin setting: "Use Collection Profiles" (on/off)
2. Admin selects collection
3. **When enabled:**
   - New users: Auto-create profile entries
   - Existing users: Optional migration (bulk action)
4. **Per-user override:**
   - Can manually set `profile_entry_id` for specific users
   - Can unlink users (set to null)

**Pros:**
- ✅ Automatic for new users
- ✅ Can migrate existing users
- ✅ Manual control when needed
- ✅ Flexible

**Cons:**
- ⚠️ Slightly more complex

## Recommended: Option 3 (Hybrid)

### Implementation Plan

1. **Plugin Settings**:
   ```json
   {
     "profile_collection_enabled": false,
     "profile_collection_slug": "profiles",
     "auto_create_profiles": true
   }
   ```

2. **User Creation Flow**:
   ```php
   // When creating user
   $user = createUser($data);
   
   // If collection profiles enabled
   if (get_setting('profile_collection_enabled')) {
       $profileEntry = createProfileEntry($user);
       $user['profile_entry_id'] = $profileEntry['id'];
       updateUser($user['id'], $user);
   }
   ```

3. **Profile Entry Creation**:
   ```php
   function createProfileEntry($user) {
       // Create entry in selected collection
       $entry = [
           'user_id' => $user['id'],
           'username' => $user['username'],
           'email' => $user['email'],  // Redundant but safe
           // ... other fields from collection schema
       ];
       
       return saveEntry('profiles', $entry);
   }
   ```

4. **Template Functions**:
   ```php
   {user_profile.bio}  // Gets from entry if linked, or plugin if not
   {user_profile.username}  // Always from plugin (source of truth)
   {user_profile.email}  // Always from plugin
   ```

5. **Migration**:
   ```php
   // Bulk migrate existing users
   function migrateUsersToCollectionProfiles() {
       $users = listUsers();
       foreach ($users as $user) {
           if (empty($user['profile_entry_id'])) {
               $profileEntry = createProfileEntry($user);
               $user['profile_entry_id'] = $profileEntry['id'];
               updateUser($user['id'], $user);
           }
       }
   }
   ```

## Benefits

1. **Resilience**: If collection deleted, user account still works
2. **Flexibility**: Can use collection features when needed
3. **Simplicity**: Works out of the box (plugin-managed)
4. **Scalability**: Can migrate to collection profiles when ready
5. **Backward Compatible**: Existing users work without changes

## Migration Strategy

### When Enabling Collection Profiles

1. **New Users**: Automatically get profile entries
2. **Existing Users**: 
   - Option A: Migrate all at once (bulk action)
   - Option B: Migrate on first login/update
   - Option C: Manual migration per user

### When Disabling Collection Profiles

1. Profile entries remain in collection
2. Plugin stops using them
3. Users continue to work with plugin data only
4. Can re-enable later without data loss

## Questions to Consider

1. **What happens to profile entry when user is deleted?**
   - Option A: Delete entry (cascade)
   - Option B: Keep entry but mark as orphaned
   - Option C: Admin choice

2. **What if profile entry is deleted manually?**
   - Option A: Auto-recreate on next access
   - Option B: Unlink user (set profile_entry_id to null)
   - Option C: Admin choice

3. **Should profile entry be required once enabled?**
   - Option A: Yes, always create for new users
   - Option B: Optional, can create later
   - Recommendation: Yes (auto-create)

4. **How to handle username/email changes?**
   - Update both plugin and entry
   - Plugin is source of truth
   - Entry is synced

## Recommended Answers

1. **User deletion**: Delete entry (cascade) - keeps data clean
2. **Entry deletion**: Auto-recreate on next access - resilient
3. **Required**: Yes, auto-create when enabled
4. **Sync**: Plugin → Entry (one-way sync, plugin is source of truth)
