# Universal User Management Plugin - Flexible Design

## Core Philosophy

**The user plugin should be a flexible foundation that works with Birkly's existing collection/entry/sub-entry system, not a rigid social media plugin.**

Everything should be built on top of collections and entries, using the plugin for:
- Authentication & authorization
- User relationships/connections
- Permission checks
- User references in content

---

## 1. **User Fieldtype** 🔴 Critical

### Concept
A fieldtype that can reference one or multiple users from the user management plugin. This enables:
- Friend lists (collection entry with user fieldtype containing friends)
- Post authors (entry with user fieldtype for author)
- Mentions (@user references)
- Group members
- Any user relationship

### Implementation
```php
// fieldtypes/User/User.php
class User extends BaseField {
    // Can select single user or multiple users
    // Can filter by role, status, etc.
    // Returns user IDs or full user objects
}
```

### Use Cases
- **Profile Collection**: Entry with `friends` field (user fieldtype, multiple)
- **Posts Collection**: Entry with `author` field (user fieldtype, single)
- **Groups Collection**: Entry with `members` field (user fieldtype, multiple)
- **Comments**: Sub-entry with `author` field (user fieldtype, single)

### Template Usage
```html
{entry.author.name} <!-- Display author name -->
{entry.friends} <!-- Loop through friends -->
{user_field entry.friends} <!-- Get user data for friend IDs -->
```

---

## 2. **Flexible Permission System** 🔴 Critical

### Concept
Permission system that works with collections and entries, checking:
- User roles
- Entry ownership
- User relationships (friends, connections)
- Custom rules

### Permission Types

#### Collection-Level Permissions
- Who can create entries in this collection?
- Who can view entries in this collection?
- Who can edit entries in this collection?
- Who can delete entries in this collection?
- Who can create sub-entries (comments)?

#### Entry-Level Permissions
- Who can view this specific entry?
- Who can edit this specific entry?
- Who can delete this specific entry?
- Who can create sub-entries (comments) on this entry?

### Permission Sources

1. **Role-Based**: User has role (member, premium, etc.)
2. **Ownership-Based**: User owns the entry (`author_id` matches)
3. **Relationship-Based**: User is connected (friend, follower, etc.)
4. **Field-Based**: Entry has user fieldtype that includes current user
5. **Custom Rules**: Plugin-defined logic

### Implementation

```php
// Check if user can perform action
function user_can($action, $context) {
    // $action: 'create', 'view', 'edit', 'delete', 'comment'
    // $context: collection slug, entry ID, or entry object
    
    // 1. Check role permissions
    // 2. Check ownership
    // 3. Check relationships
    // 4. Check entry visibility settings
    // 5. Check custom rules
}
```

### Entry Visibility Settings

Entries can have visibility metadata:
```json
{
  "entry_id": "post123",
  "author_id": "user123",
  "visibility": {
    "type": "public|friends|private|custom",
    "allowed_users": ["user456", "user789"], // if custom
    "allowed_roles": ["premium", "vip"], // if role-based
    "relationship_type": "friends|followers|both" // if friends
  }
}
```

---

## 3. **User Relationships System** 🟡 Important

### Concept
Flexible relationship system where relationships are stored as entries in a collection, not hardcoded.

### Implementation Options

#### Option A: Relationships Collection
Create a "relationships" collection with entries like:
```json
{
  "entry_id": "rel123",
  "user_a": "user123",
  "user_b": "user456",
  "relationship_type": "friend|follow|block",
  "status": "pending|accepted|rejected",
  "initiated_by": "user123"
}
```

#### Option B: User Fieldtype in Profile Entry
User's profile entry has relationship fields:
```json
{
  "entry_id": "profile_user123",
  "user_id": "user123",
  "friends": ["user456", "user789"], // user fieldtype
  "following": ["user111", "user222"], // user fieldtype
  "followers": ["user333"], // user fieldtype (computed)
  "blocked": ["user999"] // user fieldtype
}
```

#### Option C: Hybrid (Recommended)
- Profile entry stores relationships (user fieldtype)
- Separate "relationships" collection for relationship metadata (status, dates, etc.)

### Relationship Types
- `friend` - Two-way connection
- `follow` - One-way connection
- `block` - Blocked user
- `custom` - Plugin-defined types

### Template Functions
```php
{user_is_connected user_id="123" type="friend"} // Check if connected
{user_connections user_id="123" type="friend"} // Get connections
{user_can_view entry_id="post123"} // Check if user can view entry based on relationships
```

---

## 4. **Profile as Collection Entry** 🟡 Important

### Concept
User profiles are regular entries in a "profiles" collection, owned by the user.

### Implementation
```json
// Collection: "profiles"
{
  "entry_id": "profile_user123",
  "user_id": "user123", // Linked to user account
  "name": "John Doe",
  "avatar": "/media/avatars/user123.jpg",
  "bio": "Software developer...",
  "friends": ["user456", "user789"], // user fieldtype
  "custom_fields": {...} // Profile schema fields
}
```

### Benefits
- Profiles are just entries - can use all entry features
- Can have sub-entries (activity, posts, etc.)
- Can be queried like any collection
- Can have custom fields via profile schema
- Can be versioned, moderated, etc.

### Template Usage
```html
{entry user_id="123" collection="profiles"} <!-- Get user profile -->
{entry.author.profile.name} <!-- Get author's profile -->
```

---

## 5. **Access Control Based on Relationships** 🔴 Critical

### Concept
Entries can check user relationships to determine access.

### Visibility Rules

#### Public
- Anyone can view

#### Friends Only
- Check if viewer is in entry author's friends list
- Use user fieldtype to check relationship

#### Private
- Only author can view

#### Custom
- Check specific user IDs or roles
- Use entry metadata

#### Relationship-Based
- Check if viewer has specific relationship type with author
- Flexible: friends, followers, mutual friends, etc.

### Implementation

```php
function check_entry_visibility($entry, $viewer_id) {
    $visibility = $entry['visibility'] ?? 'public';
    
    switch ($visibility['type']) {
        case 'public':
            return true;
            
        case 'friends':
            // Check if viewer is in author's friends list
            $author_profile = get_profile_entry($entry['author_id']);
            $friends = $author_profile['friends'] ?? []; // user fieldtype
            return in_array($viewer_id, $friends);
            
        case 'private':
            return $entry['author_id'] === $viewer_id;
            
        case 'custom':
            $allowed = $visibility['allowed_users'] ?? [];
            return in_array($viewer_id, $allowed);
            
        case 'relationship':
            $rel_type = $visibility['relationship_type'] ?? 'friend';
            return user_has_relationship($entry['author_id'], $viewer_id, $rel_type);
    }
    
    return false;
}
```

---

## 6. **Sub-Entries as Comments** 🟢 Built-in

### Concept
Comments are sub-entries on entries. User plugin just needs to:
- Check if user can create sub-entry (comment permission)
- Link comment author to user account
- Apply visibility rules to comments

### Implementation
```json
// Main entry: post123
// Sub-entry: comment456 (in comments sub-collection)
{
  "entry_id": "comment456",
  "parent_entry_id": "post123",
  "author_id": "user789",
  "content": "Great post!",
  "created_at": "2024-01-01T00:00:00Z"
}
```

### Permissions
- Can user comment on this entry? (sub-entry create permission)
- Who can see comments? (sub-entry view permission)
- Can user edit/delete their own comments? (sub-entry edit permission)

---

## 7. **Template Functions for Flexibility** 🔴 Critical

### Core Functions

```php
// User info
{user_current} // Get current user
{user user_id="123"} // Get user by ID
{user_by_email email="user@example.com"} // Get user by email

// Permissions
{user_can action="create" collection="posts"} // Can create?
{user_can action="edit" entry_id="post123"} // Can edit?
{user_can action="comment" entry_id="post123"} // Can comment?
{user_can_view entry_id="post123"} // Can view?

// Relationships
{user_is_connected user_id="123" type="friend"} // Is connected?
{user_connections user_id="123" type="friend"} // Get connections
{user_relationship user_a="123" user_b="456"} // Get relationship

// Profile
{user_profile user_id="123"} // Get profile entry
{user_profile_field user_id="123" field="name"} // Get profile field

// User fieldtype helpers
{user_field user_ids="123,456"} // Get user data from IDs
{user_field entry.friends} // Get user data from entry field
```

---

## 8. **Admin UI for Permission Configuration** 🟡 Important

### Collection Permissions Tab
- Configure who can create/edit/delete entries per collection
- Configure who can create sub-entries (comments)
- Set default visibility rules

### Entry-Level Overrides
- Allow entries to override collection defaults
- UI for setting entry visibility
- Relationship-based visibility options

---

## 9. **Flexible Use Cases**

### Social Media Site
- Profiles collection with user fieldtype for friends
- Posts collection with visibility rules
- Comments as sub-entries
- Feed based on friend relationships

### Membership Site
- Profiles collection
- Content collections with role-based access
- Premium content for specific roles

### Community Forum
- Profiles collection
- Topics collection (posts)
- Replies as sub-entries
- Role-based moderation

### E-commerce
- Customer profiles
- Orders collection linked to users
- Product reviews as sub-entries
- Role-based pricing

### Blog Platform
- Author profiles
- Posts collection
- Comments as sub-entries
- Guest author permissions

---

## 10. **Implementation Priority**

### Phase 1: Foundation
1. ✅ User fieldtype (critical for everything)
2. ✅ Enhanced permission system (collection + entry level)
3. ✅ Relationship system (using user fieldtype)
4. ✅ Template functions for permissions

### Phase 2: Access Control
1. ✅ Entry visibility system
2. ✅ Relationship-based access checks
3. ✅ Admin UI for permission configuration
4. ✅ Profile as collection entry pattern

### Phase 3: Advanced Features
1. ✅ Notifications (optional)
2. ✅ Activity feed (optional)
3. ✅ User search (optional)

---

## 11. **Key Design Principles**

1. **Everything is an Entry**: Profiles, relationships, everything uses collections
2. **User Fieldtype is Key**: Enables all relationships and connections
3. **Permission System is Flexible**: Role, ownership, relationship, custom
4. **Template Functions are Primary**: Frontend uses template functions
5. **No Hardcoded Features**: Everything configurable via collections/permissions
6. **Works with Existing System**: Leverages collections, entries, sub-entries

---

## 12. **Example: Social Media Implementation**

### Collections Setup
1. **profiles** collection
   - Fields: name, avatar, bio, friends (user fieldtype, multiple)
   
2. **posts** collection
   - Fields: content, author (user fieldtype, single), visibility (select)
   - Permissions: members can create, friends can view if visibility="friends"
   
3. **comments** sub-collection on posts
   - Fields: content, author (user fieldtype, single)
   - Permissions: can comment if can view post

### Template
```html
<!-- Post with visibility check -->
{if {user_can_view entry_id="{entry.id}"}}
    <div class="post">
        <h3>{entry.content}</h3>
        <p>By {user_field entry.author}</p>
        
        <!-- Comments (sub-entries) -->
        {sub_entries entry_id="{entry.id}" collection="comments"}
            <div class="comment">
                {sub_entry.content} by {user_field sub_entry.author}
            </div>
        {/sub_entries}
        
        <!-- Can comment? -->
        {if {user_can action="comment" entry_id="{entry.id}"}}
            <form>...</form>
        {/if}
    </div>
{/if}
```

---

## Summary

The user plugin should be:
- **Universal**: Works for any use case
- **Flexible**: Configurable via collections and permissions
- **Integrated**: Works with existing Birkly systems
- **Extensible**: Can add features via collections, not code

**Key Components:**
1. User fieldtype (enables all relationships)
2. Flexible permission system (role + ownership + relationship)
3. Relationship system (using user fieldtype in entries)
4. Template functions (for frontend access)
5. Profile as entry (flexible, uses existing system)

This approach makes the plugin universal while enabling social media features when needed.
