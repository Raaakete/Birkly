# AI Capabilities List for HaruCMS

This document lists all system functions and what the AI should be able to do based on permissions.

## Currently Implemented AI Actions

### ✅ Content Management (READ)
- **`read_entry`** - Read a specific entry by slug or ID
  - Permission: `read` or `*`
  - Executes immediately (no approval needed)
  
- **`list_entries`** - List entries in a collection
  - Permission: `read` or `*`
  - Executes immediately (no approval needed)

### ✅ Content Management (WRITE - Requires Approval)
- **`create_collection`** - Create a new collection
  - Permission: `manage_content` or `*`
  - Requires approval if `require_approval` is enabled
  
- **`create_entry`** - Create a new entry in a collection
  - Permission: `write`, `manage_content`, or `*`
  - Requires approval if `require_approval` is enabled
  
- **`update_entry`** - Update an existing entry
  - Permission: `write`, `manage_content`, or `*`
  - Requires approval if `require_approval` is enabled
  
- **`delete_entry`** - Delete an entry
  - Permission: `delete`, `manage_content`, or `*`
  - Requires approval if `require_approval` is enabled

## Available System Functions (Not Yet Implemented for AI)

### 📋 Collections Management
- **`update_collection`** - Update collection settings (name, slug, fields, etc.)
  - Permission: `manage_content` or `*`
  - Status: ❌ Not implemented
  
- **`delete_collection`** - Delete a collection
  - Permission: `manage_content` or `*`
  - Status: ❌ Not implemented
  
- **`list_collections`** - List all collections
  - Permission: `read` or `*`
  - Status: ❌ Not implemented (but could be useful)

### 📄 Media Management
- **`list_media`** - List media files
  - Permission: `read`, `manage_media`, or `*`
  - Status: ❌ Not implemented
  
- **`upload_media`** - Upload a media file
  - Permission: `manage_media`, `upload`, or `*`
  - Status: ❌ Not implemented
  - Note: Requires file upload handling
  
- **`delete_media`** - Delete a media file
  - Permission: `manage_media` or `*`
  - Status: ❌ Not implemented
  
- **`update_media`** - Update media metadata (name, alt text, etc.)
  - Permission: `manage_media` or `*`
  - Status: ❌ Not implemented
  
- **`create_media_folder`** - Create a media folder
  - Permission: `manage_media` or `*`
  - Status: ❌ Not implemented
  
- **`delete_media_folder`** - Delete a media folder
  - Permission: `manage_media` or `*`
  - Status: ❌ Not implemented
  
- **`move_media`** - Move media to a folder
  - Permission: `manage_media` or `*`
  - Status: ❌ Not implemented

### ⚙️ Settings Management
- **`read_settings`** - Read system settings
  - Permission: `read`, `manage_settings`, or `*`
  - Status: ❌ Not implemented
  
- **`update_settings`** - Update system settings
  - Permission: `manage_settings` or `*`
  - Status: ❌ Not implemented
  - Note: Should be very careful with this - might want to exclude sensitive settings

### 👥 User Management
- **`list_users`** - List all users
  - Permission: `read`, `manage_users`, or `*`
  - Status: ❌ Not implemented
  
- **`read_user`** - Read user details
  - Permission: `read`, `manage_users`, or `*`
  - Status: ❌ Not implemented
  
- **`update_user`** - Update user details (email, role, etc.)
  - Permission: `manage_users` or `*`
  - Status: ❌ Not implemented
  
- **`delete_user`** - Delete a user
  - Permission: `manage_users` or `*`
  - Status: ❌ Not implemented
  
- **`create_user`** - Create a new user
  - Permission: `manage_users` or `*`
  - Status: ❌ Not implemented (by design - too sensitive)
  - Note: User explicitly said AI shouldn't create users

### 🔐 Role Management
- **`list_roles`** - List all roles
  - Permission: `read`, `manage_users`, or `*`
  - Status: ❌ Not implemented
  
- **`read_role`** - Read role details
  - Permission: `read`, `manage_users`, or `*`
  - Status: ❌ Not implemented
  
- **`create_role`** - Create a new role
  - Permission: `manage_users` or `*`
  - Status: ❌ Not implemented
  
- **`update_role`** - Update role permissions
  - Permission: `manage_users` or `*`
  - Status: ❌ Not implemented
  
- **`delete_role`** - Delete a role
  - Permission: `manage_users` or `*`
  - Status: ❌ Not implemented

### 🔌 Plugin Management
- **`list_plugins`** - List all plugins
  - Permission: `read`, `manage_plugins`, or `*`
  - Status: ❌ Not implemented
  
- **`activate_plugin`** - Activate a plugin
  - Permission: `manage_plugins` or `*`
  - Status: ❌ Not implemented
  
- **`deactivate_plugin`** - Deactivate a plugin
  - Permission: `manage_plugins` or `*`
  - Status: ❌ Not implemented
  
- **`install_plugin`** - Install a plugin
  - Permission: `manage_plugins` or `*`
  - Status: ❌ Not implemented
  
- **`uninstall_plugin`** - Uninstall a plugin
  - Permission: `manage_plugins` or `*`
  - Status: ❌ Not implemented

### 📝 Pages Management
- **`list_pages`** - List all pages
  - Permission: `read` or `*`
  - Status: ❌ Not implemented
  
- **`read_page`** - Read a page
  - Permission: `read` or `*`
  - Status: ❌ Not implemented
  
- **`create_page`** - Create a new page
  - Permission: `write`, `manage_content`, or `*`
  - Status: ❌ Not implemented
  
- **`update_page`** - Update a page
  - Permission: `write`, `manage_content`, or `*`
  - Status: ❌ Not implemented
  
- **`delete_page`** - Delete a page
  - Permission: `delete`, `manage_content`, or `*`
  - Status: ❌ Not implemented

### 📊 Activity & Logs
- **`list_activity`** - List activity logs
  - Permission: `read` or `*`
  - Status: ❌ Not implemented
  
- **`read_activity`** - Read specific activity entry
  - Permission: `read` or `*`
  - Status: ❌ Not implemented

### 🔄 Backup & Restore
- **`create_backup`** - Create a system backup
  - Permission: `manage_settings` or `*`
  - Status: ❌ Not implemented
  
- **`list_backups`** - List available backups
  - Permission: `read` or `*`
  - Status: ❌ Not implemented
  
- **`restore_backup`** - Restore from backup
  - Permission: `manage_settings` or `*`
  - Status: ❌ Not implemented
  - Note: Very sensitive - might want to exclude this

### 📧 Invitations
- **`list_invitations`** - List invitations
  - Permission: `read`, `manage_users`, or `*`
  - Status: ❌ Not implemented
  
- **`create_invitation`** - Create an invitation
  - Permission: `manage_users` or `*`
  - Status: ❌ Not implemented
  
- **`delete_invitation`** - Delete an invitation
  - Permission: `manage_users` or `*`
  - Status: ❌ Not implemented

## Recommended Priority for Implementation

### High Priority (Most Useful)
1. **`list_collections`** - Essential for AI to know what collections exist
2. **`update_collection`** - Allow AI to modify collection settings
3. **`list_media`** - Allow AI to see available media
4. **`upload_media`** - Allow AI to upload images/files (with URL or base64)
5. **`read_settings`** - Allow AI to read system settings
6. **`list_users`** - Allow AI to see users (for context)

### Medium Priority
7. **`update_entry`** - Already implemented ✅
8. **`delete_collection`** - Allow AI to remove collections
9. **`update_media`** - Allow AI to update media metadata
10. **`list_pages`** - Allow AI to see pages
11. **`create_page`** - Allow AI to create pages
12. **`update_page`** - Allow AI to update pages

### Low Priority (Sensitive or Less Common)
13. **`update_settings`** - Very sensitive, should be limited
14. **`update_user`** - Sensitive, should require explicit approval
15. **`delete_user`** - Very sensitive
16. **Role management** - Sensitive, might want to exclude
17. **Plugin management** - Could be risky
18. **Backup/restore** - Very sensitive, probably exclude

## Actions That Should NEVER Be Available to AI

- **`create_user`** - User explicitly said AI shouldn't do this
- **`restore_backup`** - Too dangerous
- **`delete_all_entries`** or bulk destructive operations
- **System file modifications**
- **Database operations** (if any exist)
- **License management**
- **2FA management**

## Implementation Notes

1. All write actions should respect the `require_approval` setting
2. All actions should check permissions based on the AI user's role
3. File uploads (media) will need special handling (URL or base64)
4. Sensitive actions should have extra logging
5. Some actions might need rate limiting

