# Profile as Entry - Optional Design

## The Question
**When is profile as entry needed? Should it be optional?**

## Answer: Yes, It Should Be Optional!

### When Profile as Entry is NOT Needed

**Simple use cases:**
- ✅ Basic membership site (just email, name, role)
- ✅ E-commerce (customer data in user account)
- ✅ Simple blog (author info in user account)
- ✅ Internal tools (minimal user info)

**User account has enough fields:**
- Email, name, phone (basic fields)
- Role, tier, status
- Created date, last login

**No need for complex profiles!**

### When Profile as Entry IS Needed

**Complex use cases:**
- ✅ Social media site (bio, avatar, cover photo, interests, location)
- ✅ Community forum (signature, badges, reputation, custom fields)
- ✅ Dating site (photos, preferences, detailed info)
- ✅ Professional network (resume, skills, portfolio)

**Profile needs:**
- Many custom fields (different per site)
- Media (photos, videos)
- Relationships (friends, followers)
- Dynamic fields (admin can add/remove)

## Design: Make It Optional

### Option 1: Plugin Setting
```json
{
  "plugin_id": "user_management",
  "settings": {
    "profile_as_entry": false,  // Default: false (simple)
    "profile_collection": "profiles"  // If enabled, which collection
  }
}
```

### Option 2: Auto-Detection
- If collection "profiles" exists → use it
- If not → use basic user account fields
- No configuration needed

### Implementation

#### Simple Mode (Default)
```php
// User account has basic fields
$user = [
  'id' => 'user123',
  'email' => 'user@example.com',
  'name' => 'John Doe',
  'phone' => '123-456-7890',
  'role' => 'member',
  'tier' => 'free'
];

// Template: {user_current.name}
```

#### Profile Entry Mode (Optional)
```php
// User account links to profile entry
$user = [
  'id' => 'user123',
  'email' => 'user@example.com',
  'profile_entry_id' => 'profile_user123'  // Link to entry
];

// Profile entry in "profiles" collection
$profile = [
  'entry_id' => 'profile_user123',
  'user_id' => 'user123',
  'bio' => '...',
  'avatar' => '...',
  'friends' => ['user456', 'user789'],  // User fieldtype
  'custom_field_1' => '...',
  'custom_field_2' => '...'
];

// Template: {user_profile.bio} or {user_current.profile.bio}
```

## Template Functions

### Simple Mode
```html
{user_current.name}
{user_current.email}
{user_current.role}
```

### Profile Entry Mode
```html
{user_current.name}  <!-- From user account -->
{user_profile.bio}   <!-- From profile entry -->
{user_profile.avatar}
{user_profile.friends}  <!-- User fieldtype -->
```

## Recommendation

**Make it optional with auto-detection:**
1. Default: Simple mode (user account fields only)
2. If "profiles" collection exists → automatically use profile entries
3. Template functions work in both modes
4. Admin can enable/disable in settings

**Benefits:**
- ✅ Simple projects don't need complexity
- ✅ Complex projects can use full profile system
- ✅ No breaking changes
- ✅ Flexible and future-proof
