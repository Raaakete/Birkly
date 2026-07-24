# Next Steps - Redesigned Based on User Feedback

## Key Insights

### 1. Permission Functions - Keep Minimal
- ✅ **Keep `{user_can}`** for admin-configured role/tier permissions (optional)
- ✅ **Frontend handles** relationship-based access using existing functions
- ❌ **Don't over-engineer** - let frontend be flexible

### 2. Entry Visibility - Frontend Only
- ✅ **No backend visibility system needed**
- ✅ **Entry has simple `visibility` field**: "public", "private", "friends-only"
- ✅ **Frontend checks** using `{user_logged_in}`, `{user_in_field}`, etc.
- ❌ **No complex permission resolution needed**

### 3. Profile as Entry - Optional
- ✅ **Default: Simple mode** (user account fields only)
- ✅ **Optional: Profile entry mode** (if "profiles" collection exists)
- ✅ **Auto-detection** (no configuration needed)
- ❌ **Don't force complexity** on simple projects

## Priority 1: Email Verification & 2FA ✅ HIGH PRIORITY

### Email Verification
- Email sending system
- Verification tokens
- Email templates
- Admin approval workflow (optional)

### 2FA (Two-Factor Authentication)
- TOTP (Time-based One-Time Password)
- SMS verification (optional)
- Backup codes
- Admin can require 2FA for specific roles

### Implementation Plan
1. Email service integration
2. Verification token system
3. Email templates
4. 2FA setup UI
5. 2FA verification flow

## Priority 2: Frontend Relationship Management

### APIs for Managing Relationships
Since frontend handles relationships via User fieldtype, we need APIs to:
- Add user to field (e.g., "Add Friend" → add to friends field)
- Remove user from field (e.g., "Unfriend")
- Check relationship status

### Template Functions
- `{user_is_friend user_id="123"}` - Check if user is friend
- `{user_is_following user_id="123"}` - Check if user is following
- Helper functions for common relationship checks

## Priority 3: Profile as Entry (Optional)

### Auto-Detection System
- Check if "profiles" collection exists
- If yes → link user account to profile entry
- Template functions work in both modes

### Template Functions
- `{user_profile}` - Get profile entry (if exists)
- `{user_profile_field field="bio"}` - Get profile field
- Works in simple mode (returns null) and profile mode (returns entry)

## What We DON'T Need

### ❌ Complex Permission System
- Frontend handles relationship-based access
- Backend only for admin-configured role/tier permissions

### ❌ Backend Visibility System
- Frontend checks visibility using template functions
- Simple `visibility` field on entries

### ❌ Mandatory Profile System
- Optional, auto-detected
- Simple projects don't need it

## Summary

**Keep it simple:**
1. Frontend handles most access control
2. Backend only for admin-configured permissions
3. Profile system is optional
4. Focus on email verification and 2FA

**Next implementation:**
1. Email verification system
2. 2FA support
3. Relationship management APIs
4. Optional profile as entry (auto-detection)
