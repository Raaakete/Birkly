# User Management Plugin - Features Guide

This guide explains how to use the advanced features of the User Management plugin.

## 1. Collection Permissions - User Entry Creation & Ownership

### Setting Up Collections for User Entry Creation

To allow users to create entries in a collection:

1. **Configure Collection Permissions** (via Admin UI or API):
   ```php
   // Set collection-level permissions
   $permissions = new UserPermissions();
   $permissions->setCollectionPermission('blog', 'member', [
       'create' => true,
       'edit_own' => true,
       'delete_own' => true,
       'publish' => false  // Requires admin approval
   ]);
   ```

2. **Collection Configuration** (in collection.json):
   ```json
   {
     "id": "blog",
     "name": "Blog Posts",
     "user_permissions": {
       "role:member": {
         "create": true,
         "edit_own": true,
         "delete_own": true
       }
     }
   }
   ```

3. **Automatic Ownership**:
   - When a user creates an entry, `author_id` is automatically set to their user ID
   - Users can only edit/delete their own entries (unless they have `edit_all` permission)
   - Admin can override ownership

### Template Functions

```html
<!-- Check if user can create entries in collection -->
{if user_can('create', 'blog')}
  <a href="/create-post">Create Post</a>
{/if}

<!-- Show edit button only if user owns entry -->
{if user_can('edit', 'blog', entry.id) && entry.author_id == user_current.id}
  <a href="/edit-post/{entry.id}">Edit</a>
{/if}
```

## 2. User Groups

### Admin-Managed Groups

Groups are managed in the admin panel under "Users" → "Groups".

**Creating a Group:**
1. Go to Users → Groups
2. Click "Create Group"
3. Set group name, description, and permissions
4. Add members to the group

**Group Permissions:**
- Groups can have collection-level permissions
- All group members inherit group permissions
- Group permissions are additive (added to user's individual permissions)

### User-Created Groups

Users can create their own groups (if enabled in settings):

1. User goes to "My Groups"
2. Clicks "Create Group"
3. Sets group name and privacy settings
4. Invites members or makes it public
5. Manages group permissions

**Group Types:**
- **Public**: Anyone can join
- **Private**: Invite only
- **Closed**: No new members

## 3. Tiers

### Creating Tiers

Tiers define access levels (often tied to payment plans):

1. Go to Users → Tiers
2. Click "Create Tier"
3. Configure:
   - Tier name (e.g., "Premium", "VIP")
   - Permissions (which collections/features)
   - Limits (max entries, storage, etc.)
   - Price (if payment plugin integrated)

**Default Tiers:**
- `free` - Basic access
- `member` - Standard member
- `premium` - Premium features
- `vip` - Highest tier

### Assigning Tiers to Users

- Admin can assign tiers manually
- Payment plugin can auto-assign tiers
- Tiers can expire (time-limited access)

## 4. User Verification

### Email Verification

1. **Enable in Settings**: "Require email verification"
2. **User Registration**: User receives verification email
3. **Verification Link**: User clicks link to verify
4. **Status Update**: User status changes from "pending" to "active"

### Admin Approval

1. **Enable in Settings**: "Require admin approval"
2. **User Registration**: User status is "pending"
3. **Admin Review**: Admin reviews user in admin panel
4. **Approval**: Admin approves/rejects user

### Manual Verification

Admin can verify users manually:
- Go to Users → Select User → Click "Verify"

## 5. Account Deletion

### Self-Service Deletion

Users can delete their own accounts:

1. User goes to "Account Settings"
2. Clicks "Delete Account"
3. Confirms deletion
4. Account is soft-deleted (can be restored by admin)

### Admin Deletion

Admin can delete user accounts:
- Go to Users → Select User → Click "Delete"
- Choose: Soft delete (can restore) or Hard delete (permanent)

### Data Retention

- Soft-deleted accounts: Data retained for 30 days (configurable)
- Hard-deleted accounts: Data permanently removed
- Associated entries: Can be transferred to another user or deleted

## 6. Mandatory User Information

### Configuring Mandatory Fields

In plugin settings, you can define mandatory fields:

```json
{
  "mandatory_fields": [
    {
      "name": "phone",
      "label": "Phone Number",
      "type": "text",
      "required": true,
      "validation": "phone"
    },
    {
      "name": "bio",
      "label": "Biography",
      "type": "textarea",
      "required": true,
      "min_length": 50
    }
  ]
}
```

### User Experience

1. **Registration**: User must fill mandatory fields
2. **Profile Completion**: User prompted to complete profile
3. **Access Restriction**: User can't access certain features until profile complete

### Field Types

- Text
- Email
- Phone
- Date
- Select (dropdown)
- Textarea
- File upload

---

## Quick Start Examples

### Example 1: Blog with User Authors

```php
// 1. Create collection "blog" with user permissions
$permissions->setCollectionPermission('blog', 'member', [
    'create' => true,
    'edit_own' => true,
    'delete_own' => true
]);

// 2. Users can now create blog posts
// 3. Each post automatically gets author_id set
// 4. Users can only edit their own posts
```

### Example 2: Premium Content Site

```php
// 1. Create tier "premium"
$tiers->createTier('premium', [
    'name' => 'Premium Member',
    'permissions' => [
        'collections' => [
            'premium_content' => ['read' => true, 'create' => true]
        ]
    ],
    'limits' => ['max_entries' => 100]
]);

// 2. Assign tier to user
$tiers->setUserTier($user_id, 'premium');

// 3. User now has access to premium_content collection
```

### Example 3: Team Workspace

```php
// 1. Create group "design_team"
$groups->createGroup('design_team', [
    'name' => 'Design Team',
    'permissions' => [
        'collections' => [
            'projects' => ['create' => true, 'edit_all' => true]
        ]
    ]
]);

// 2. Add members
$groups->addMember('design_team', $user_id);

// 3. All members can create/edit projects
```
