# Permission System - When and Why

## The Question
**Why do we need permission template functions when the frontend can check relationships directly?**

## Answer: Two Different Use Cases

### Use Case 1: Relationship-Based Access (Frontend Can Handle)
**Example:** "Only friends can see this post"

**Frontend approach (simpler):**
```html
{if {user_logged_in}}
    {if {entry.visibility} == "public"}
        <!-- Show content -->
    {elseif {entry.visibility} == "friends-only"}
        {if {user_in_field {entry.author.friends}}}
            <!-- Show content -->
        {/if}
    {elseif {entry.visibility} == "private"}
        {if {entry.author_id} == {user_current.id}}
            <!-- Show content -->
        {/if}
    {/if}
{/if}
```

**This works!** No backend permission system needed. Frontend checks:
- Is user logged in?
- Is user in the right user fieldtype?
- Is user the author?

### Use Case 2: Admin-Configured Role-Based Access (Backend Needed)
**Example:** "Only premium members can create posts in this collection"

**Why backend is needed:**
- Admin configures: "Collection 'posts' → Role 'premium' can create"
- This is stored in database/settings, not in entry data
- Frontend can't know this without checking backend permissions

**Backend permission check:**
```php
// Admin configured: premium role can create in 'posts' collection
{user_can action="create" collection="posts"}
// Returns true if user has 'premium' role AND admin configured this permission
```

## When to Use What?

### Use Frontend Checks (Simple, Flexible)
- ✅ Relationship-based access (friends, followers)
- ✅ Entry ownership checks
- ✅ Public/private/friends-only visibility
- ✅ User fieldtype membership checks

**Template functions needed:**
- `{user_logged_in}` ✅ Already exists
- `{user_current}` ✅ Already exists
- `{user_in_field}` ✅ Already exists
- `{user_field}` ✅ Already exists

### Use Backend Permissions (Admin-Configured)
- ✅ Role-based collection access (admin configures which roles can create/edit)
- ✅ Tier-based access (premium tier can access premium content)
- ✅ Collection-level restrictions (only editors can create in news collection)
- ✅ Admin-managed permissions (not based on relationships)

**Template functions needed:**
- `{user_can action="create" collection="posts"}` - Check admin-configured permissions
- `{user_role}` - Get user's role
- `{user_tier}` - Get user's tier

## Conclusion

**You're right!** For most use cases, frontend relationship checks are simpler and more flexible.

**Backend permissions are only needed for:**
1. Admin-configured role/tier restrictions
2. Collection-level access control (configured in admin, not in entry data)

**Recommendation:**
- Keep `{user_can}` for admin-configured permissions (optional, only if admin uses it)
- Frontend handles relationship-based access using existing template functions
- Don't over-engineer - let frontend be flexible!
