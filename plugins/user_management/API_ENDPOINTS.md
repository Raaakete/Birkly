# User Management Plugin - API Endpoints

## Base URLs

All endpoints are accessed via the API router:
- `/api/index.php?route=user_admin&action=...`
- `/api/index.php?route=user_tiers&action=...`
- `/api/index.php?route=user_groups&action=...`
- `/api/index.php?route=user_auth&action=...`
- `/api/index.php?route=user_registration&action=...`

## Authentication

Most endpoints require authentication. Include CSRF token in requests:
```javascript
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
```

## User Admin API (`user_admin`)

### List Users
```
GET /api/index.php?route=user_admin&action=list&limit=50&offset=0&role=member&status=active
```

### Get User
```
GET /api/index.php?route=user_admin&action=get&id=user_123
```

### Create User
```
POST /api/index.php?route=user_admin
{
  "action": "create",
  "csrf_token": "...",
  "name": "John Doe",
  "email": "john@example.com",
  "password": "securepassword",
  "role": "member",
  "status": "active",
  "tier": "free",
  "verified": true,
  "approved": true
}
```

### Update User
```
POST /api/index.php?route=user_admin
{
  "action": "update",
  "csrf_token": "...",
  "id": "user_123",
  "name": "John Doe Updated",
  "role": "premium",
  "tier": "premium"
}
```

### Delete User (Admin)
```
POST /api/index.php?route=user_admin
{
  "action": "delete",
  "csrf_token": "...",
  "id": "user_123"
}
```

### Verify User
```
POST /api/index.php?route=user_admin
{
  "action": "verify",
  "csrf_token": "...",
  "id": "user_123"
}
```

### Delete Account (Self-Service)
```
POST /api/index.php?route=user_admin
{
  "action": "delete_account",
  "csrf_token": "...",
  "confirm": true
}
```

### Update Permissions
```
POST /api/index.php?route=user_admin
{
  "action": "update_permissions",
  "csrf_token": "...",
  "id": "user_123",
  "permissions": {
    "global": ["read", "create_entries"],
    "collections": {
      "blog": {
        "create": true,
        "edit_own": true
      }
    }
  }
}
```

### Get Stats
```
GET /api/index.php?route=user_admin&action=stats
```

## Tiers API (`user_tiers`)

### List Tiers
```
GET /api/index.php?route=user_tiers&action=list
```

### Get Tier
```
GET /api/index.php?route=user_tiers&action=get&tier_id=premium
```

### Create Tier
```
POST /api/index.php?route=user_tiers
{
  "action": "create",
  "csrf_token": "...",
  "id": "enterprise",
  "name": "Enterprise",
  "description": "Enterprise tier with all features",
  "permissions": {
    "collections": {
      "premium_content": {
        "read": true,
        "create": true
      }
    },
    "features": ["unlimited_entries", "custom_domain", "priority_support"]
  },
  "limits": {
    "max_entries": -1,
    "max_storage_mb": 5000
  },
  "price": 99.99
}
```

### Update Tier
```
POST /api/index.php?route=user_tiers
{
  "action": "update",
  "csrf_token": "...",
  "tier_id": "premium",
  "name": "Premium Plus",
  "price": 49.99
}
```

### Delete Tier
```
POST /api/index.php?route=user_tiers
{
  "action": "delete",
  "csrf_token": "...",
  "tier_id": "custom_tier"
}
```

## Groups API (`user_groups`)

### List Groups
```
GET /api/index.php?route=user_groups&action=list&owner_id=user_123&type=private
```

### Get Group
```
GET /api/index.php?route=user_groups&action=get&group_id=group_abc123
```

### Create Group
```
POST /api/index.php?route=user_groups
{
  "action": "create",
  "csrf_token": "...",
  "name": "Design Team",
  "description": "Design team group",
  "type": "private",
  "members": ["user_123", "user_456"],
  "permissions": {
    "collections": {
      "projects": {
        "create": true,
        "edit_all": true
      }
    }
  }
}
```

### Update Group
```
POST /api/index.php?route=user_groups
{
  "action": "update",
  "csrf_token": "...",
  "group_id": "group_abc123",
  "name": "Design Team Updated",
  "description": "Updated description"
}
```

### Delete Group
```
POST /api/index.php?route=user_groups
{
  "action": "delete",
  "csrf_token": "...",
  "group_id": "group_abc123"
}
```

### Add Member
```
POST /api/index.php?route=user_groups
{
  "action": "add_member",
  "csrf_token": "...",
  "group_id": "group_abc123",
  "user_id": "user_789"
}
```

### Remove Member
```
POST /api/index.php?route=user_groups
{
  "action": "remove_member",
  "csrf_token": "...",
  "group_id": "group_abc123",
  "user_id": "user_789"
}
```

## Authentication API (`user_auth`)

### Login
```
POST /api/index.php?route=user_auth
{
  "action": "login",
  "csrf_token": "...",
  "email": "user@example.com",
  "password": "password123"
}
```

### Logout
```
POST /api/index.php?route=user_auth
{
  "action": "logout",
  "csrf_token": "..."
}
```

## Registration API (`user_registration`)

### Register
```
POST /api/index.php?route=user_registration
{
  "action": "register",
  "csrf_token": "...",
  "name": "John Doe",
  "email": "john@example.com",
  "password": "securepassword"
}
```

### Verify Email
```
POST /api/index.php?route=user_registration
{
  "action": "verify",
  "token": "verification_token_here"
}
```

### Invite Registration
```
POST /api/index.php?route=user_registration
{
  "action": "invite",
  "csrf_token": "...",
  "invite_code": "INVITE123",
  "name": "John Doe",
  "email": "john@example.com",
  "password": "securepassword"
}
```

## Response Format

All endpoints return JSON:

**Success:**
```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... }
}
```

**Error:**
```json
{
  "success": false,
  "error": "Error message"
}
```

## Error Codes

- `400` - Bad Request (invalid input)
- `401` - Unauthorized (not logged in)
- `403` - Forbidden (insufficient permissions)
- `404` - Not Found
- `500` - Internal Server Error
