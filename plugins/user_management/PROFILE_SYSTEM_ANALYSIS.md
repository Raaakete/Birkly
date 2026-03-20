# Profile System - Two Options Analysis

## Option A: Plugin-Managed Profiles

### Concept
Plugin has its own profile builder (similar to collection editor) that manages user profiles completely within the plugin.

### Implementation
```php
// Plugin manages profile schema
$profileSchema = [
    'fields' => [
        ['name' => 'bio', 'type' => 'textarea'],
        ['name' => 'avatar', 'type' => 'media'],
        ['name' => 'location', 'type' => 'text'],
        ['name' => 'friends', 'type' => 'user', 'multiple' => true]
    ]
];

// Profile data stored in plugin directory
data/plugins/user_management/profiles/user123.json
```

### Pros ✅
- **Self-contained**: Everything in plugin, easy to install/remove
- **Consistent**: Same profile system for all users
- **Easy to use**: Admin configures profile fields in plugin settings
- **No collection setup**: Works out of the box
- **Isolated**: Profile data safe from system updates

### Cons ❌
- **Less flexible**: Can't use collection features (sub-entries, relationships, etc.)
- **Limited fieldtypes**: Only plugin-supported fieldtypes
- **No collection benefits**: Can't query profiles like entries, can't use collection permissions
- **Separate system**: Profiles are separate from content system

### Use Cases
- ✅ Simple membership sites
- ✅ Basic user profiles
- ✅ When you want everything in plugin
- ❌ Complex social media (needs collection features)
- ❌ When you need to query profiles like entries

---

## Option B: Profile as Entry

### Concept
Plugin only handles authentication (username, email, password). Everything else is a profile entry in a "profiles" collection, tied to user ID.

### Implementation
```php
// User account (plugin)
$user = [
    'id' => 'user123',
    'email' => 'user@example.com',
    'password_hash' => '...',
    'profile_entry_id' => 'profile_user123'  // Link to entry
];

// Profile entry (collection)
// Collection: "profiles"
// Entry: profile_user123.json
{
    "entry_id": "profile_user123",
    "user_id": "user123",  // Link back to user
    "bio": "...",
    "avatar": "...",
    "friends": ["user456", "user789"],  // User fieldtype
    "custom_field": "..."
}
```

### Pros ✅
- **Full collection features**: Sub-entries, relationships, permissions, etc.
- **All fieldtypes**: Can use any fieldtype in profile
- **Queryable**: Can query profiles like any entry
- **Flexible**: Admin can customize profile collection like any collection
- **Integrated**: Profiles are part of content system
- **Sub-entries**: Can have comments, activity, etc. on profiles

### Cons ❌
- **Requires setup**: Need to create "profiles" collection
- **More complex**: Two systems (user account + profile entry)
- **Not self-contained**: Profile data in collections, not plugin
- **Link management**: Need to maintain user_id ↔ entry_id link

### Use Cases
- ✅ Social media sites (needs collection features)
- ✅ Community forums (needs sub-entries for activity)
- ✅ Complex profiles (many fields, relationships)
- ✅ When you need to query profiles
- ❌ Simple sites (overkill)

---

## Comparison Table

| Feature | Plugin-Managed | Profile as Entry |
|---------|---------------|------------------|
| **Self-contained** | ✅ Yes | ❌ No (needs collection) |
| **Easy setup** | ✅ Yes | ❌ Requires collection setup |
| **Collection features** | ❌ No | ✅ Yes (sub-entries, etc.) |
| **All fieldtypes** | ❌ Limited | ✅ Yes |
| **Queryable** | ❌ No | ✅ Yes |
| **Flexible** | ⚠️ Limited | ✅ Very flexible |
| **Isolated data** | ✅ Yes | ❌ No (in collections) |
| **Sub-entries** | ❌ No | ✅ Yes (comments, activity) |
| **Simple projects** | ✅ Perfect | ⚠️ Overkill |
| **Complex projects** | ❌ Limited | ✅ Perfect |

---

## Scenarios

### Scenario 1: Simple Membership Site
**Needs:** Basic profiles (name, email, role)
**Best:** Plugin-managed (Option A)
- Simple, self-contained
- No collection setup needed
- Easy to use

### Scenario 2: Social Media Site
**Needs:** Complex profiles, friends, posts, comments
**Best:** Profile as entry (Option B)
- Can use collection features
- Friends as user fieldtype
- Sub-entries for activity/comments
- Queryable profiles

### Scenario 3: E-commerce
**Needs:** Customer profiles, order history
**Best:** Profile as entry (Option B)
- Orders as sub-entries or separate collection
- Can link to user
- Queryable customer data

### Scenario 4: Community Forum
**Needs:** User profiles, posts, replies, reputation
**Best:** Profile as entry (Option B)
- Posts as entries
- Replies as sub-entries
- Activity/reputation as sub-entries on profile

### Scenario 5: Internal Tool
**Needs:** Minimal user info
**Best:** Plugin-managed (Option A)
- Just email, name, role
- No complex profiles needed

---

## Recommendation: Hybrid Approach

### Default: Plugin-Managed (Simple)
- Plugin handles basic user info (email, name, password)
- Optional profile fields in plugin settings
- Works out of the box

### Optional: Profile as Entry (Advanced)
- If "profiles" collection exists → use it
- Link user account to profile entry
- Full collection features available

### Implementation
```php
// Check if profiles collection exists
if (collection_exists('profiles')) {
    // Use profile as entry
    $profile = get_profile_entry($user_id);
} else {
    // Use plugin-managed profile
    $profile = get_plugin_profile($user_id);
}

// Template functions work for both
{user_profile.bio}  // Works in both modes
```

### Benefits
- ✅ Simple projects: Plugin-managed (default)
- ✅ Complex projects: Profile as entry (optional)
- ✅ Auto-detection: No configuration needed
- ✅ Flexible: Best of both worlds
