# Security: Frontend Permission Checks

## The Question
**Are there any security issues, letting the frontend handle most of the permission requests?**

## Critical Answer: YES! ⚠️

### Frontend Checks Are NOT Security

**Frontend checks are for UX only:**
- Hide/show UI elements
- Display appropriate content
- Better user experience

**Frontend can be bypassed:**
- User can view page source
- User can modify JavaScript
- User can make direct API calls
- User can use browser dev tools

### Backend MUST Enforce Permissions

**All permission checks MUST be enforced on the backend:**
- API endpoints must verify permissions
- Database queries must filter by permissions
- File access must check permissions
- No data should be sent if user lacks permission

## The Right Approach

### Frontend: UX Layer
```html
<!-- Frontend: Hide content if user can't see it -->
{if {user_logged_in}}
    {if {user_in_field {entry.author.friends}}}
        {entry.content}  <!-- Show in UI -->
    {/if}
{/if}
```

### Backend: Security Layer
```php
// Backend API: Enforce permissions
function getEntry($entry_id) {
    $entry = loadEntry($entry_id);
    $user = getCurrentUser();
    
    // SECURITY: Check permissions on backend
    if (!canUserViewEntry($user, $entry)) {
        return ['error' => 'Access denied'];
    }
    
    return $entry;  // Only return if user has permission
}

function canUserViewEntry($user, $entry) {
    // Check visibility
    if ($entry['visibility'] === 'private') {
        return $entry['author_id'] === $user['id'];
    }
    
    if ($entry['visibility'] === 'relationship') {
        // Check if user is in author's friends list
        $author_profile = getProfile($entry['author_id']);
        $friends = $author_profile['friends'] ?? [];
        return in_array($user['id'], $friends);
    }
    
    return true;  // Public
}
```

## Security Model

### 1. Frontend: Display Logic (UX)
- Show/hide content based on permissions
- Better user experience
- Can be bypassed (not security)

### 2. Backend: Access Control (Security)
- Verify permissions on every request
- Filter data before sending
- Enforce access rules
- Cannot be bypassed

## Implementation Pattern

### Template (Frontend)
```html
<!-- UX: Hide if user can't see -->
{if {user_can_view entry_id={entry.id}}}
    {entry.content}
{else}
    <p>You don't have permission to view this.</p>
{/if}
```

### API Endpoint (Backend)
```php
// SECURITY: Enforce on backend
case 'get_entry':
    $entry_id = $_GET['entry_id'];
    $user = getCurrentUser();
    
    // Load entry
    $entry = loadEntry($entry_id);
    
    // SECURITY CHECK: Verify permission
    if (!canUserViewEntry($user, $entry)) {
        http_response_code(403);
        return ['error' => 'Access denied'];
    }
    
    // Only return if user has permission
    return $entry;
```

### Permission Check Function (Backend)
```php
function canUserViewEntry($user, $entry) {
    // Public: Everyone can see
    if ($entry['visibility'] === 'public') {
        return true;
    }
    
    // Private: Only author
    if ($entry['visibility'] === 'private') {
        return $entry['author_id'] === $user['id'];
    }
    
    // Relationship: Check user fieldtype
    if ($entry['visibility'] === 'relationship') {
        $author_profile = getProfileEntry($entry['author_id']);
        $friends = $author_profile['friends'] ?? [];
        return in_array($user['id'], $friends);
    }
    
    return false;
}
```

## Common Security Mistakes

### ❌ WRONG: Trusting Frontend
```php
// BAD: No backend check
function getEntry($entry_id) {
    return loadEntry($entry_id);  // Returns to anyone!
}
```

### ❌ WRONG: Client-Side Only
```javascript
// BAD: Only frontend check
if (userCanView(entry)) {
    fetchEntry(entryId);  // User can bypass this!
}
```

### ✅ CORRECT: Backend Enforcement
```php
// GOOD: Backend checks permission
function getEntry($entry_id) {
    $entry = loadEntry($entry_id);
    $user = getCurrentUser();
    
    if (!canUserViewEntry($user, $entry)) {
        return ['error' => 'Access denied'];
    }
    
    return $entry;
}
```

## Best Practices

### 1. Never Trust Frontend
- Frontend checks are for UX only
- Always verify on backend

### 2. Filter Data on Backend
- Don't send data user can't access
- Filter collections/queries by permissions
- Return only authorized data

### 3. Use Backend Permission Functions
- `canUserViewEntry($user, $entry)`
- `canUserEditEntry($user, $entry)`
- `canUserDeleteEntry($user, $entry)`

### 4. Test Security
- Try accessing data without permission
- Test API endpoints directly
- Verify backend blocks unauthorized access

## Summary

**Frontend permission checks:**
- ✅ Good for UX (show/hide content)
- ❌ NOT security (can be bypassed)

**Backend permission enforcement:**
- ✅ Required for security
- ✅ Must verify on every request
- ✅ Filter data before sending

**Rule of thumb:**
- Frontend: "Should I show this?" (UX)
- Backend: "Can user access this?" (Security)
